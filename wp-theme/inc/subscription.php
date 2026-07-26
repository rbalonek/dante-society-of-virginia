<?php
/**
 * Subscription / billing portal (single-client) — EMBEDDED CHECKOUT + LIVE STATUS.
 *
 * A client-facing "Subscription" screen in wp-admin (top-level menu + Dashboard
 * widget). Checkout is in-app via Stripe **Embedded Checkout**; subscription
 * status is read **live from Stripe** (no manual toggle) — the screen shows the
 * checkout until an active subscription exists for the plan's price, then flips
 * to a branded "Current Subscription" summary automatically. Status is cached in
 * a 5-minute transient (with a last-known-good option as a network fallback).
 *
 * Config (hardcoded / server-side):
 *   - DANTE_SUB_PRICE_ID          recurring Price id (price_…)  [not secret]
 *   - DANTE_SUB_PUBLISHABLE_KEY   publishable key (pk_…)        [not secret]
 *   - DANTE_STRIPE_SECRET         secret/restricted key         [secrets file, SSH]
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Hardcoded plan (matches the Stripe product) ---------------------------
define( 'DANTE_SUB_PLAN_NAME', 'Dante Society of Virginia Wordpress Site' );
define( 'DANTE_SUB_PLAN_DESC', 'Monthly subscription to WordPress access to http://www.dantesocietyofva.org/' );
define( 'DANTE_SUB_PAYMENT_LINK', 'https://buy.stripe.com/eVq3cudL22vO8KBaZj3AY00' ); // fallback (new tab)
define( 'DANTE_SUB_PRICE_ID', 'price_1TxDuWCFukojpZoaOhKIfxqL' );
define( 'DANTE_SUB_PUBLISHABLE_KEY', 'pk_live_51QkBqDCFukojpZoa4dFU68wzFGrDxwqjj2zX8ZgJl77dzGxZaYdrZTl9ieOph6zeuxQP3BAUzrLwD58qLuvCVStO00GmKtLQ8s' );

/**
 * Secret key — from the server-side secrets file (constant or env), never the DB.
 */
function dante_sub_stripe_secret() {
    if ( defined( 'DANTE_STRIPE_SECRET' ) && DANTE_STRIPE_SECRET ) {
        return trim( (string) DANTE_STRIPE_SECRET );
    }
    $env = getenv( 'DANTE_STRIPE_SECRET' );
    return $env ? trim( $env ) : '';
}

/**
 * True when checkout can run (all three pieces present).
 */
function dante_sub_is_configured() {
    return '' !== DANTE_SUB_PRICE_ID && '' !== DANTE_SUB_PUBLISHABLE_KEY && '' !== dante_sub_stripe_secret();
}

/* ===========================================================================
 * Live subscription status (Stripe)
 * ======================================================================== */

/**
 * The active subscription object for our price, or null. Cached 5 min in a
 * transient; on a Stripe/network error, falls back to the last known value.
 * Memoised per request so a page never calls Stripe twice.
 *
 * @return array|null
 */
function dante_sub_fetch_subscription() {
    static $memo = false;
    if ( false !== $memo ) {
        return $memo;
    }

    $cached = get_transient( 'dante_sub_cache' );
    if ( false !== $cached ) {
        $memo = ( 'none' === $cached ) ? null : $cached;
        return $memo;
    }

    if ( ! dante_sub_is_configured() ) {
        $memo = null;
        return null;
    }

    $resp = wp_remote_get(
        'https://api.stripe.com/v1/subscriptions?status=active&limit=1&price=' . rawurlencode( DANTE_SUB_PRICE_ID ),
        array(
            'timeout' => 15,
            'headers' => array( 'Authorization' => 'Bearer ' . dante_sub_stripe_secret() ),
        )
    );

    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) >= 400 ) {
        $last = get_option( 'dante_sub_last' );
        $memo = is_array( $last ) ? $last : null;
        return $memo; // don't cache a failure
    }

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $sub  = ( ! empty( $body['data'][0] ) ) ? $body['data'][0] : null;

    set_transient( 'dante_sub_cache', $sub ? $sub : 'none', 5 * MINUTE_IN_SECONDS );
    update_option( 'dante_sub_last', $sub ? $sub : '', false );
    $memo = $sub;
    return $sub;
}

/**
 * Whether to show the Subscribed view. Live Stripe check, with an admin-only
 * ?preview_status=subscribed|unsubscribed override for previewing either design.
 */
function dante_subscription_is_subscribed() {
    if ( current_user_can( 'manage_options' ) && isset( $_GET['preview_status'] ) ) {
        return 'subscribed' === $_GET['preview_status'];
    }
    return null !== dante_sub_fetch_subscription();
}

/* ===========================================================================
 * REST: create an embedded Checkout Session
 * ======================================================================== */

function dante_subscription_rest() {
    register_rest_route(
        'dante/v1',
        '/checkout-session',
        array(
            'methods'             => 'POST',
            'callback'            => 'dante_subscription_create_session',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        )
    );
}
add_action( 'rest_api_init', 'dante_subscription_rest' );

function dante_subscription_create_session() {
    $secret = dante_sub_stripe_secret();
    if ( '' === $secret || '' === DANTE_SUB_PRICE_ID ) {
        return new WP_Error( 'not_configured', 'Checkout is not configured yet.', array( 'status' => 400 ) );
    }

    $return_url = admin_url( 'admin.php?page=dante-subscription' ) . '&session_id={CHECKOUT_SESSION_ID}';

    $resp = wp_remote_post(
        'https://api.stripe.com/v1/checkout/sessions',
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => array(
                'mode'                    => 'subscription',
                'ui_mode'                 => 'embedded',
                'line_items[0][price]'    => DANTE_SUB_PRICE_ID,
                'line_items[0][quantity]' => 1,
                'return_url'              => $return_url,
            ),
        )
    );

    if ( is_wp_error( $resp ) ) {
        return new WP_Error( 'stripe_unreachable', $resp->get_error_message(), array( 'status' => 502 ) );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $code >= 400 || empty( $body['client_secret'] ) ) {
        $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Could not start checkout.';
        return new WP_Error( 'stripe_error', $msg, array( 'status' => 400 ) );
    }

    return rest_ensure_response( array( 'client_secret' => $body['client_secret'] ) );
}

/* ===========================================================================
 * Admin menu
 * ======================================================================== */

function dante_subscription_menu() {
    add_menu_page(
        __( 'Subscription', 'dante-society' ),
        __( 'Subscription', 'dante-society' ),
        'manage_options',
        'dante-subscription',
        'dante_subscription_page',
        'dashicons-money-alt',
        7
    );

    add_submenu_page(
        'dante-subscription',
        __( 'Billing', 'dante-society' ),
        __( 'Billing', 'dante-society' ),
        'manage_options',
        'dante-subscription',
        'dante_subscription_page'
    );

    add_submenu_page(
        'dante-subscription',
        __( 'Subscription Settings', 'dante-society' ),
        __( 'Settings', 'dante-society' ),
        'manage_options',
        'dante-subscription-settings',
        'dante_subscription_settings_page'
    );
}
add_action( 'admin_menu', 'dante_subscription_menu' );

/* ===========================================================================
 * Enqueue Stripe.js + our mount script on the Billing page only
 * ======================================================================== */

function dante_subscription_assets( $hook ) {
    if ( 'toplevel_page_dante-subscription' !== $hook ) {
        return;
    }
    if ( dante_subscription_is_subscribed() || ! dante_sub_is_configured() || isset( $_GET['session_id'] ) ) {
        return; // no checkout form to render
    }

    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
    wp_enqueue_script(
        'dante-subscription',
        get_template_directory_uri() . '/js/subscription.js',
        array( 'stripe-js' ),
        dante_ver( 'js/subscription.js' ),
        true
    );
    wp_localize_script(
        'dante-subscription',
        'danteSub',
        array(
            'endpoint' => esc_url_raw( rest_url( 'dante/v1/checkout-session' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'pk'       => DANTE_SUB_PUBLISHABLE_KEY,
        )
    );
}
add_action( 'admin_enqueue_scripts', 'dante_subscription_assets' );

/* ===========================================================================
 * Shared styles
 * ======================================================================== */

function dante_subscription_styles() {
    ?>
    <style>
        .dante-sub-card{max-width:640px;background:#fff;border:1px solid #e2e2d5;border-top:4px solid #1f4d2e;border-radius:10px;padding:24px 28px;margin:16px 0;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .dante-sub-card h2{margin:0 0 4px;font-size:20px;color:#1f4d2e}
        .dante-sub-desc{color:#6b6b5e;margin:2px 0 0;line-height:1.5;font-size:13px}
        .dante-sub-card--active{border-top-color:#1f7a3d}
        .dante-sub-badge{display:inline-block;background:#e6f4ea;color:#1f7a3d;font-weight:700;font-size:13px;padding:4px 12px;border-radius:999px;margin-bottom:16px}
        /* Stripe-style product summary */
        .dante-sub-summary{display:flex;align-items:center;gap:16px;margin:4px 0 8px}
        .dante-sub-logo-wrap{flex:0 0 auto;width:56px;height:56px;border-radius:50%;overflow:hidden;background:#f3f2ea;display:flex;align-items:center;justify-content:center}
        .dante-sub-logo-wrap img{width:56px;height:56px;object-fit:cover}
        .dante-sub-summary-main{flex:1 1 auto;min-width:0}
        .dante-sub-plan{font-size:16px;font-weight:600;color:#1f4d2e;line-height:1.3}
        .dante-sub-amount{flex:0 0 auto;font-size:16px;font-weight:700;color:#1f4d2e;white-space:nowrap}
        .dante-sub-rows{border-top:1px solid #edece3;margin-top:16px;padding-top:12px}
        .dante-sub-row{display:flex;justify-content:space-between;padding:5px 0;font-size:14px;color:#444}
        .dante-sub-row span:first-child{color:#6b6b5e}
        .dante-sub-row strong{color:#1f4d2e;font-weight:600}
        /* Embedded checkout */
        #dante-embedded-checkout{max-width:700px;margin:16px 0;min-height:360px}
        .dante-sub-fallback{font-size:13px;color:#6b6b5e;margin-top:8px;max-width:700px}
        .dante-sub-notice{max-width:700px;background:#fff8e5;border:1px solid #e8d9a0;border-radius:8px;padding:16px 20px;margin:16px 0;color:#6b5a1e}
        .dante-sub-status{max-width:640px;background:#f6f7f4;border:1px solid #e2e2d5;border-radius:8px;padding:14px 18px;margin:8px 0 16px;font-size:14px;color:#444}
        .dante-sub-status b{color:#1f7a3d}
    </style>
    <?php
}

/* ===========================================================================
 * Helpers to pull display bits from the live subscription
 * ======================================================================== */

function dante_sub_amount_label( $sub ) {
    if ( $sub && ! empty( $sub['items']['data'][0]['price']['unit_amount'] ) ) {
        $price    = $sub['items']['data'][0]['price'];
        $amount   = number_format( $price['unit_amount'] / 100, 2 );
        $interval = isset( $price['recurring']['interval'] ) ? $price['recurring']['interval'] : 'month';
        $symbol   = ( isset( $price['currency'] ) && 'usd' === $price['currency'] ) ? '$' : strtoupper( $price['currency'] ?? '' ) . ' ';
        return $symbol . $amount . ' / ' . $interval;
    }
    return '$15.00 / month';
}

function dante_sub_next_payment( $sub ) {
    if ( ! $sub ) {
        return '';
    }
    $cpe = 0;
    if ( ! empty( $sub['current_period_end'] ) ) {
        $cpe = (int) $sub['current_period_end'];
    } elseif ( ! empty( $sub['items']['data'][0]['current_period_end'] ) ) {
        $cpe = (int) $sub['items']['data'][0]['current_period_end'];
    }
    return $cpe ? date_i18n( get_option( 'date_format' ), $cpe ) : '';
}

/* ===========================================================================
 * The billing card (reused by page + dashboard widget)
 * ======================================================================== */

function dante_subscription_card( $compact = false ) {

    /* -------- Subscribed: branded "Current Subscription" summary -------- */
    if ( dante_subscription_is_subscribed() ) {
        $sub    = dante_sub_fetch_subscription();
        $amount = dante_sub_amount_label( $sub );
        $next   = dante_sub_next_payment( $sub );

        $logo = '';
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo = wp_get_attachment_image( $logo_id, array( 56, 56 ) );
        }

        echo '<div class="dante-sub-card dante-sub-card--active">';
        echo '<div class="dante-sub-badge">' . esc_html__( '✓ Subscription active', 'dante-society' ) . '</div>';
        echo '<div class="dante-sub-summary">';
        if ( $logo ) {
            echo '<div class="dante-sub-logo-wrap">' . wp_kses_post( $logo ) . '</div>';
        }
        echo '<div class="dante-sub-summary-main">';
        echo '<div class="dante-sub-plan">' . esc_html( DANTE_SUB_PLAN_NAME ) . '</div>';
        if ( ! $compact ) {
            echo '<div class="dante-sub-desc">' . esc_html( DANTE_SUB_PLAN_DESC ) . '</div>';
        }
        echo '</div>';
        echo '<div class="dante-sub-amount">' . esc_html( $amount ) . '</div>';
        echo '</div>'; // summary

        if ( ! $compact ) {
            echo '<div class="dante-sub-rows">';
            echo '<div class="dante-sub-row"><span>' . esc_html__( 'Status', 'dante-society' ) . '</span><strong>' . esc_html__( 'Active', 'dante-society' ) . '</strong></div>';
            if ( $next ) {
                echo '<div class="dante-sub-row"><span>' . esc_html__( 'Next payment', 'dante-society' ) . '</span><strong>' . esc_html( $next ) . '</strong></div>';
            }
            echo '<div class="dante-sub-row"><span>' . esc_html__( 'Billed', 'dante-society' ) . '</span><strong>' . esc_html__( 'Monthly', 'dante-society' ) . '</strong></div>';
            echo '</div>';
        }
        echo '</div>'; // card
        return;
    }

    /* -------- Unsubscribed (dashboard widget): prompt + link to Billing -------- */
    if ( $compact ) {
        echo '<div class="dante-sub-card">';
        echo '<h2>' . esc_html( DANTE_SUB_PLAN_NAME ) . '</h2>';
        echo '<p class="dante-sub-desc">' . esc_html__( 'Subscribe to keep your website active.', 'dante-society' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=dante-subscription' ) ) . '">' . esc_html__( 'Subscribe', 'dante-society' ) . '</a></p>';
        echo '</div>';
        return;
    }

    /* -------- Returning from checkout -------- */
    if ( isset( $_GET['session_id'] ) ) {
        delete_transient( 'dante_sub_cache' ); // re-check Stripe on next load
        echo '<div class="dante-sub-card dante-sub-card--active">';
        echo '<div class="dante-sub-badge">' . esc_html__( '✓ Payment received', 'dante-society' ) . '</div>';
        echo '<h2>' . esc_html__( 'Thank you!', 'dante-society' ) . '</h2>';
        echo '<p class="dante-sub-desc">' . esc_html__( 'Your subscription is being activated — this page will show it as active in a moment.', 'dante-society' ) . '</p>';
        echo '</div>';
        return;
    }

    /* -------- Not configured yet (admin-only screen) -------- */
    if ( ! dante_sub_is_configured() ) {
        echo '<div class="dante-sub-notice"><strong>' . esc_html__( 'Embedded checkout isn\'t configured yet.', 'dante-society' ) . '</strong><br>';
        echo esc_html__( 'Needs the Price ID, publishable key, and the DANTE_STRIPE_SECRET in the server secrets file.', 'dante-society' ) . '</div>';
        return;
    }

    /* -------- Unsubscribed (Billing screen): embedded checkout -------- */
    echo '<div id="dante-embedded-checkout"></div>';
    printf(
        '<p class="dante-sub-fallback">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
        esc_html__( 'Checkout not loading?', 'dante-society' ),
        esc_url( DANTE_SUB_PAYMENT_LINK ),
        esc_html__( 'Open secure checkout in a new tab', 'dante-society' )
    );
}

/* ===========================================================================
 * The "Billing" screen (client-facing)
 * ======================================================================== */

function dante_subscription_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    dante_subscription_styles();
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Subscription', 'dante-society' ) . '</h1>';
    if ( ! dante_subscription_is_subscribed() && ! isset( $_GET['session_id'] ) && dante_sub_is_configured() ) {
        echo '<p style="max-width:680px;color:#555;">' . esc_html__( 'Complete the secure checkout below to activate your website subscription.', 'dante-society' ) . '</p>';
    }
    dante_subscription_card( false );
    echo '</div>';
}

/* ===========================================================================
 * Dashboard widget
 * ======================================================================== */

function dante_subscription_dashboard_widget() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_add_dashboard_widget(
        'dante_subscription_widget',
        __( 'Website Subscription', 'dante-society' ),
        'dante_subscription_widget_render'
    );
}
add_action( 'wp_dashboard_setup', 'dante_subscription_dashboard_widget' );

function dante_subscription_widget_render() {
    dante_subscription_styles();
    dante_subscription_card( true );
}

/* ===========================================================================
 * Settings screen — live status (no manual toggle) + refresh + preview links
 * ======================================================================== */

function dante_subscription_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['dante_refresh'] ) && check_admin_referer( 'dante_sub_refresh' ) ) {
        delete_transient( 'dante_sub_cache' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Status refreshed from Stripe.', 'dante-society' ) . '</p></div>';
    }

    dante_subscription_styles();
    $sub        = dante_sub_fetch_subscription();
    $configured = dante_sub_is_configured();
    $billing    = admin_url( 'admin.php?page=dante-subscription' );
    $refresh    = wp_nonce_url( admin_url( 'admin.php?page=dante-subscription-settings&dante_refresh=1' ), 'dante_sub_refresh' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Subscription Settings', 'dante-society' ); ?></h1>
        <p style="max-width:700px;color:#555;">
            <?php esc_html_e( 'Status is read live from Stripe — no manual switch. The Billing screen shows checkout until an active subscription exists, then the confirmation.', 'dante-society' ); ?>
        </p>

        <div class="dante-sub-status">
            <?php
            if ( ! $configured ) {
                echo esc_html__( 'Not configured — missing Price ID, publishable key, or DANTE_STRIPE_SECRET.', 'dante-society' );
            } elseif ( $sub ) {
                printf(
                    /* translators: %s: next payment date */
                    esc_html__( 'Live status: %1$s. Next payment: %2$s.', 'dante-society' ),
                    '<b>' . esc_html__( 'Active', 'dante-society' ) . '</b>',
                    esc_html( dante_sub_next_payment( $sub ) ?: '—' )
                );
            } else {
                esc_html_e( 'Live status: no active subscription yet.', 'dante-society' );
            }
            ?>
            &nbsp; <a href="<?php echo esc_url( $refresh ); ?>"><?php esc_html_e( 'Refresh now', 'dante-society' ); ?></a>
        </div>

        <p>
            <strong><?php esc_html_e( 'Preview:', 'dante-society' ); ?></strong>
            <a href="<?php echo esc_url( $billing . '&preview_status=unsubscribed' ); ?>"><?php esc_html_e( 'checkout view', 'dante-society' ); ?></a> &middot;
            <a href="<?php echo esc_url( $billing . '&preview_status=subscribed' ); ?>"><?php esc_html_e( 'subscribed view', 'dante-society' ); ?></a>
            <br><span class="description"><?php esc_html_e( 'Preview links only change what you see; they don\'t change the real status.', 'dante-society' ); ?></span>
        </p>
    </div>
    <?php
}
