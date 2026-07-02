<?php
/**
 * Newsletter tool: a subscriber list managed in WordPress plus a composer with
 * three templates (all upcoming events / a single event / a free message), a
 * live preview, "send test", and send-to-all via wp_mail.
 *
 * NOTE on deliverability/compliance: wp_mail on a typical host has poor inbox
 * placement. For real sending, install an SMTP plugin (e.g. WP Mail SMTP) and
 * connect a real sending service. Every email includes the society's mailing
 * address and a working unsubscribe link (CAN-SPAM basics).
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ===========================================================================
 * Subscribers (custom post type)
 * ======================================================================== */

function dante_register_subscriber_cpt() {
    register_post_type( 'dante_subscriber', array(
        'labels'       => array(
            'name'          => __( 'Subscribers', 'dante-society' ),
            'singular_name' => __( 'Subscriber', 'dante-society' ),
            'add_new'       => __( 'Add Subscriber', 'dante-society' ),
            'add_new_item'  => __( 'Add Subscriber', 'dante-society' ),
            'edit_item'     => __( 'Edit Subscriber', 'dante-society' ),
            'menu_name'     => __( 'Subscribers', 'dante-society' ),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => false, // added manually under the Newsletter menu, in order
        'supports'      => array(), // no title field; we use labeled Email/Name fields
        'menu_icon'     => 'dashicons-groups',
    ) );
}
add_action( 'init', 'dante_register_subscriber_cpt' );

/**
 * Meta box: subscriber name + status.
 */
function dante_subscriber_meta_box() {
    add_meta_box( 'dante_subscriber_details', __( 'Subscriber Details', 'dante-society' ), 'dante_subscriber_meta_html', 'dante_subscriber', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'dante_subscriber_meta_box' );

function dante_subscriber_meta_html( $post ) {
    wp_nonce_field( 'dante_save_subscriber', 'dante_subscriber_nonce' );
    $email  = $post->post_title; // email is stored as the post title
    $name   = get_post_meta( $post->ID, '_nl_name', true );
    $status = get_post_meta( $post->ID, '_nl_status', true );
    if ( ! $status ) {
        $status = 'subscribed';
    }
    ?>
    <p>
        <label for="dante_nl_email" style="display:block;font-weight:600;"><?php esc_html_e( 'Email address', 'dante-society' ); ?></label>
        <input type="email" id="dante_nl_email" name="dante_nl_email" value="<?php echo esc_attr( $email ); ?>" style="width:100%;max-width:400px;" required />
    </p>
    <p>
        <label for="dante_nl_name" style="display:block;font-weight:600;"><?php esc_html_e( 'Name', 'dante-society' ); ?></label>
        <input type="text" id="dante_nl_name" name="dante_nl_name" value="<?php echo esc_attr( $name ); ?>" style="width:100%;max-width:400px;" />
    </p>
    <p>
        <label for="dante_nl_status" style="display:block;font-weight:600;"><?php esc_html_e( 'Status', 'dante-society' ); ?></label>
        <select id="dante_nl_status" name="dante_nl_status">
            <option value="subscribed" <?php selected( $status, 'subscribed' ); ?>><?php esc_html_e( 'Subscribed', 'dante-society' ); ?></option>
            <option value="unsubscribed" <?php selected( $status, 'unsubscribed' ); ?>><?php esc_html_e( 'Unsubscribed', 'dante-society' ); ?></option>
        </select>
    </p>
    <?php
}

function dante_save_subscriber( $post_id ) {
    if ( ! isset( $_POST['dante_subscriber_nonce'] ) || ! wp_verify_nonce( $_POST['dante_subscriber_nonce'], 'dante_save_subscriber' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['dante_nl_name'] ) ) {
        update_post_meta( $post_id, '_nl_name', sanitize_text_field( wp_unslash( $_POST['dante_nl_name'] ) ) );
    }
    if ( isset( $_POST['dante_nl_status'] ) ) {
        $status = ( 'unsubscribed' === $_POST['dante_nl_status'] ) ? 'unsubscribed' : 'subscribed';
        update_post_meta( $post_id, '_nl_status', $status );
    }
    if ( ! get_post_meta( $post_id, '_nl_token', true ) ) {
        update_post_meta( $post_id, '_nl_token', wp_generate_password( 24, false ) );
    }

    // Store the email as the post title (so it shows in the list, stays unique).
    if ( isset( $_POST['dante_nl_email'] ) ) {
        $email = sanitize_email( wp_unslash( $_POST['dante_nl_email'] ) );
        if ( is_email( $email ) && $email !== get_post_field( 'post_title', $post_id ) ) {
            remove_action( 'save_post_dante_subscriber', 'dante_save_subscriber' );
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $email ) );
            add_action( 'save_post_dante_subscriber', 'dante_save_subscriber' );
        }
    }
}
add_action( 'save_post_dante_subscriber', 'dante_save_subscriber' );

/**
 * Admin columns: email status.
 */
function dante_subscriber_columns( $columns ) {
    return array(
        'cb'        => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
        'nl_email'  => __( 'Email', 'dante-society' ),
        'nl_name'   => __( 'Name', 'dante-society' ),
        'nl_status' => __( 'Status', 'dante-society' ),
        'date'      => __( 'Date', 'dante-society' ),
    );
}
add_filter( 'manage_dante_subscriber_posts_columns', 'dante_subscriber_columns' );

function dante_subscriber_column_content( $column, $post_id ) {
    if ( 'nl_email' === $column ) {
        $email = get_the_title( $post_id );
        printf(
            '<a href="%s"><strong>%s</strong></a>',
            esc_url( get_edit_post_link( $post_id ) ),
            esc_html( $email ? $email : '(no email)' )
        );
    } elseif ( 'nl_name' === $column ) {
        echo esc_html( get_post_meta( $post_id, '_nl_name', true ) );
    } elseif ( 'nl_status' === $column ) {
        $status = get_post_meta( $post_id, '_nl_status', true );
        echo esc_html( $status ? ucfirst( $status ) : 'Subscribed' );
    }
}
add_action( 'manage_dante_subscriber_posts_custom_column', 'dante_subscriber_column_content', 10, 2 );

/**
 * Get all currently-subscribed subscribers.
 *
 * @return array of WP_Post
 */
function dante_get_subscribers() {
    $q = new WP_Query( array(
        'post_type'      => 'dante_subscriber',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_nl_status', 'value' => 'subscribed' ),
            array( 'key' => '_nl_status', 'compare' => 'NOT EXISTS' ),
        ),
    ) );
    return $q->posts;
}

/* ===========================================================================
 * Front-end subscribe + unsubscribe
 * ======================================================================== */

/**
 * [dante_subscribe] — a simple front-end signup form.
 */
function dante_subscribe_shortcode() {
    $msg = '';
    if ( isset( $_GET['subscribed'] ) ) {
        $msg = '<p class="newsletter-msg">Thank you! You have been added to our mailing list.</p>';
    }
    ob_start();
    echo $msg; // phpcs:ignore WordPress.Security.EscapeOutput
    ?>
    <form class="newsletter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="dante_subscribe" />
        <?php wp_nonce_field( 'dante_subscribe', 'dante_subscribe_nonce' ); ?>
        <input type="text" name="nl_name" placeholder="Your name" />
        <input type="email" name="nl_email" placeholder="Your email address" required />
        <button type="submit" class="btn btn-primary">Subscribe</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'dante_subscribe', 'dante_subscribe_shortcode' );

/**
 * Handle the subscribe form submission.
 */
function dante_handle_subscribe() {
    if ( ! isset( $_POST['dante_subscribe_nonce'] ) || ! wp_verify_nonce( $_POST['dante_subscribe_nonce'], 'dante_subscribe' ) ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    $email = isset( $_POST['nl_email'] ) ? sanitize_email( wp_unslash( $_POST['nl_email'] ) ) : '';
    $name  = isset( $_POST['nl_name'] ) ? sanitize_text_field( wp_unslash( $_POST['nl_name'] ) ) : '';
    $back  = wp_get_referer() ? wp_get_referer() : home_url( '/' );

    if ( ! is_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'subscribe_error', '1', $back ) );
        exit;
    }

    // Avoid duplicates (match on the email stored as the post title).
    $dupes = new WP_Query( array(
        'post_type'      => 'dante_subscriber',
        'title'          => $email,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ) );

    if ( ! $dupes->have_posts() ) {
        $id = wp_insert_post( array(
            'post_type'   => 'dante_subscriber',
            'post_status' => 'publish',
            'post_title'  => $email,
        ) );
        if ( $id && ! is_wp_error( $id ) ) {
            update_post_meta( $id, '_nl_name', $name );
            update_post_meta( $id, '_nl_status', 'subscribed' );
            update_post_meta( $id, '_nl_token', wp_generate_password( 24, false ) );
        }
    } else {
        update_post_meta( $dupes->posts[0], '_nl_status', 'subscribed' );
    }

    wp_safe_redirect( add_query_arg( 'subscribed', '1', $back ) );
    exit;
}
add_action( 'admin_post_dante_subscribe', 'dante_handle_subscribe' );
add_action( 'admin_post_nopriv_dante_subscribe', 'dante_handle_subscribe' );

/**
 * Handle one-click unsubscribe links: /?dante_unsub=TOKEN
 */
function dante_handle_unsubscribe() {
    if ( empty( $_GET['dante_unsub'] ) ) {
        return;
    }
    $token = sanitize_text_field( wp_unslash( $_GET['dante_unsub'] ) );

    $q = new WP_Query( array(
        'post_type'      => 'dante_subscriber',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_key'       => '_nl_token',
        'meta_value'     => $token,
    ) );

    if ( $q->have_posts() ) {
        update_post_meta( $q->posts[0]->ID, '_nl_status', 'unsubscribed' );
        wp_die(
            '<h1>You have been unsubscribed</h1><p>You will no longer receive emails from the Dante Society of Virginia.</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Return to the website</a></p>',
            'Unsubscribed',
            array( 'response' => 200 )
        );
    }
}
add_action( 'template_redirect', 'dante_handle_unsubscribe' );

/* ===========================================================================
 * Email rendering (3 templates)
 * ======================================================================== */

/**
 * Wrap inner content in the branded, email-safe shell.
 */
function dante_nl_email_shell( $inner_html, $unsubscribe_url = '#', $footer_text = '' ) {
    ob_start();
    ?>
<div style="background:#FAF3E0;padding:24px 0;font-family:Arial,Helvetica,sans-serif;color:#2D2D2D;">
  <table width="600" align="center" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
    <tr><td style="background:#1B4332;padding:20px 28px;">
      <span style="color:#C8963E;font-size:22px;font-weight:bold;">Dante Society of Virginia</span>
    </td></tr>
    <tr><td style="padding:28px;line-height:1.6;font-size:16px;"><?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
    <tr><td style="background:#1B4332;padding:22px 28px;color:#cbbfa6;font-size:12px;line-height:1.6;text-align:center;">
      <?php if ( $footer_text ) : ?>
        <p style="margin:0 0 14px;color:#e5ddca;"><?php echo nl2br( esc_html( $footer_text ) ); ?></p>
      <?php endif; ?>
      <p style="margin:0 0 14px;">
        <a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="background:#C8963E;color:#1B4332;text-decoration:none;font-weight:bold;padding:9px 18px;border-radius:5px;display:inline-block;">Unsubscribe</a>
      </p>
      Dante Society of Virginia &middot; P.O. Box 131, Forest, VA 24551
    </td></tr>
  </table>
</div>
    <?php
    return ob_get_clean();
}

/**
 * Render a single event as an email block.
 */
function dante_nl_event_block( $post_id ) {
    $date = get_post_meta( $post_id, '_event_date', true );
    $time = get_post_meta( $post_id, '_event_time', true );
    $loc  = get_post_meta( $post_id, '_event_location', true );
    $img  = get_the_post_thumbnail_url( $post_id, 'medium' );
    $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 55 );

    $meta = array();
    if ( $date ) {
        $meta[] = date_i18n( 'l, F j, Y', strtotime( $date ) );
    }
    if ( $time ) {
        $meta[] = $time;
    }

    ob_start();
    ?>
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;border-bottom:1px solid #eee;padding-bottom:20px;">
      <tr><td>
        <?php if ( $img ) : ?>
          <img src="<?php echo esc_url( $img ); ?>" alt="" style="max-width:100%;border-radius:6px;margin-bottom:12px;" />
        <?php endif; ?>
        <h2 style="color:#1B4332;font-size:20px;margin:0 0 6px;"><?php echo esc_html( get_the_title( $post_id ) ); ?></h2>
        <?php if ( $meta ) : ?>
          <p style="color:#C8963E;font-weight:bold;margin:0 0 4px;"><?php echo esc_html( implode( '  &middot;  ', $meta ) ); ?></p>
        <?php endif; ?>
        <?php if ( $loc ) : ?>
          <p style="margin:0 0 8px;"><strong>Location:</strong> <?php echo esc_html( $loc ); ?></p>
        <?php endif; ?>
        <p style="margin:0;"><?php echo esc_html( $desc ); ?></p>
      </td></tr>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Build the inner content for a given template + data.
 */
function dante_nl_build_inner( $data ) {
    $headline = ! empty( $data['headline'] ) ? $data['headline'] : '';
    $intro    = ! empty( $data['intro'] ) ? $data['intro'] : '';

    $image_html = '';
    if ( ! empty( $data['image_url'] ) ) {
        $image_html = '<img src="' . esc_url( $data['image_url'] ) . '" alt="" style="max-width:100%;border-radius:6px;margin:0 0 20px;display:block;" />';
    }
    $image_pos = ! empty( $data['image_pos'] ) ? $data['image_pos'] : 'top';

    // Build the main body for the chosen template.
    $body = '';
    if ( 'message' === $data['template'] ) {
        $body .= wp_kses_post( $data['body'] );
    } elseif ( 'single_event' === $data['template'] && ! empty( $data['event_id'] ) ) {
        $body .= dante_nl_event_block( (int) $data['event_id'] );
    } elseif ( 'all_events' === $data['template'] ) {
        $q = dante_get_upcoming_events();
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $p ) {
                $body .= dante_nl_event_block( $p->ID );
            }
            wp_reset_postdata();
            $body .= '<p style="text-align:center;margin:8px 0 0;"><a href="' . esc_url( home_url( '/' ) ) . '" style="background:#C8963E;color:#1B4332;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:6px;display:inline-block;">See all events</a></p>';
        } else {
            $body .= '<p>No upcoming events at this time.</p>';
        }
    }

    // "Middle" places the image between paragraphs of the body text.
    if ( $image_html && 'middle' === $image_pos ) {
        $body = dante_nl_place_image_middle( $body, $image_html );
    }

    // Assemble: headline, [top image], intro, body, [bottom image].
    $inner = '';
    if ( $headline ) {
        $inner .= '<h1 style="color:#1B4332;font-size:26px;margin:0 0 16px;">' . esc_html( $headline ) . '</h1>';
    }
    if ( $image_html && 'top' === $image_pos ) {
        $inner .= $image_html;
    }
    if ( $intro ) {
        $inner .= '<p style="margin:0 0 20px;">' . nl2br( esc_html( $intro ) ) . '</p>';
    }
    $inner .= $body;
    if ( $image_html && 'bottom' === $image_pos ) {
        $inner .= $image_html;
    }

    return $inner;
}

/**
 * Insert an image after the middle paragraph of some HTML body. Falls back to
 * appending it if the body has fewer than two paragraphs to split between.
 */
function dante_nl_place_image_middle( $body, $image_html ) {
    $count = substr_count( strtolower( $body ), '</p>' );
    if ( $count < 2 ) {
        return $body . $image_html;
    }
    $target = max( 1, (int) round( $count / 2 ) );
    $n      = 0;
    return preg_replace_callback(
        '/<\/p>/i',
        function ( $m ) use ( &$n, $target, $image_html ) {
            $n++;
            return ( $n === $target ) ? '</p>' . $image_html : $m[0];
        },
        $body
    );
}

/* ===========================================================================
 * Admin: Newsletter composer
 * ======================================================================== */

function dante_newsletter_menu() {
    add_menu_page(
        __( 'Newsletter', 'dante-society' ),
        __( 'Newsletter', 'dante-society' ),
        'manage_options',
        'dante-newsletter',
        'dante_newsletter_page',
        'dashicons-email',
        6
    );

    // First submenu = the composer (rename the auto-created duplicate).
    add_submenu_page(
        'dante-newsletter',
        __( 'Compose Newsletter', 'dante-society' ),
        __( 'Compose', 'dante-society' ),
        'manage_options',
        'dante-newsletter',
        'dante_newsletter_page'
    );

    // Then the subscriber list.
    add_submenu_page(
        'dante-newsletter',
        __( 'Subscribers', 'dante-society' ),
        __( 'Subscribers', 'dante-society' ),
        'manage_options',
        'edit.php?post_type=dante_subscriber'
    );
}
add_action( 'admin_menu', 'dante_newsletter_menu' );

function dante_newsletter_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $notice  = '';
    $preview = '';
    $data    = array(
        'template' => 'all_events',
        'subject'  => '',
        'headline' => '',
        'intro'    => '',
        'event_id' => 0,
        'body'     => '',
        'footer'   => "You're receiving this email because you subscribed to updates from the Dante Society of Virginia.",
    );

    if ( isset( $_POST['dante_nl_nonce'] ) && wp_verify_nonce( $_POST['dante_nl_nonce'], 'dante_nl_compose' ) ) {
        $data['template'] = isset( $_POST['template'] ) && in_array( $_POST['template'], array( 'all_events', 'single_event', 'message' ), true ) ? $_POST['template'] : 'all_events';
        $data['subject']  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $data['headline'] = isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '';
        $data['intro']    = isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '';
        $data['event_id'] = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        $data['body']     = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
        $data['footer']   = isset( $_POST['footer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['footer'] ) ) : '';

        $action = isset( $_POST['dante_nl_action'] ) ? $_POST['dante_nl_action'] : 'preview';
        $inner  = dante_nl_build_inner( $data );

        if ( 'send_test' === $action ) {
            $to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
            if ( ! is_email( $to ) ) {
                $to = wp_get_current_user()->user_email;
            }
            $html = dante_nl_email_shell( $inner, home_url( '/' ), $data['footer'] );
            $ok   = dante_nl_send( $to, $data['subject'] ? $data['subject'] : 'Test', $html );
            $notice = $ok
                ? '<div class="notice notice-success"><p>Test sent to ' . esc_html( $to ) . '. (On Local, real email may not be delivered without SMTP setup.)</p></div>'
                : '<div class="notice notice-error"><p>Could not send the test email.</p></div>';
        } elseif ( 'send_all' === $action ) {
            $subs  = dante_get_subscribers();
            $count = 0;
            foreach ( $subs as $sub ) {
                $token = get_post_meta( $sub->ID, '_nl_token', true );
                $unsub = $token ? home_url( '/?dante_unsub=' . rawurlencode( $token ) ) : home_url( '/' );
                $html  = dante_nl_email_shell( $inner, $unsub, $data['footer'] );
                if ( dante_nl_send( $sub->post_title, $data['subject'] ? $data['subject'] : 'Newsletter', $html ) ) {
                    $count++;
                }
            }
            $notice = '<div class="notice notice-success"><p>Newsletter sent to ' . (int) $count . ' subscriber(s). (On Local, real email may not be delivered without SMTP setup.)</p></div>';
        }

        $preview = dante_nl_email_shell( dante_nl_build_inner( $data ), home_url( '/' ), $data['footer'] );
    }

    // Events for the picker.
    $events = get_posts( array(
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => '_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ) );

    $sub_count = count( dante_get_subscribers() );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Newsletter', 'dante-society' ); ?></h1>
        <p><?php echo esc_html( sprintf( _n( '%d active subscriber.', '%d active subscribers.', $sub_count, 'dante-society' ), $sub_count ) ); ?>
           <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dante_subscriber' ) ); ?>"><?php esc_html_e( 'Manage subscribers', 'dante-society' ); ?></a></p>
        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
            <form method="post" style="flex:1 1 420px;max-width:560px;background:#fff;padding:20px;border:1px solid #ddd;border-radius:6px;">
                <?php wp_nonce_field( 'dante_nl_compose', 'dante_nl_nonce' ); ?>

                <p>
                    <label for="template"><strong><?php esc_html_e( 'Newsletter type', 'dante-society' ); ?></strong></label><br>
                    <select name="template" id="template" onchange="danteNlToggle()">
                        <option value="all_events" <?php selected( $data['template'], 'all_events' ); ?>>All upcoming events</option>
                        <option value="single_event" <?php selected( $data['template'], 'single_event' ); ?>>A specific event</option>
                        <option value="message" <?php selected( $data['template'], 'message' ); ?>>Just a message</option>
                    </select>
                </p>

                <p>
                    <label for="subject"><strong><?php esc_html_e( 'Email subject', 'dante-society' ); ?></strong></label><br>
                    <input type="text" name="subject" id="subject" value="<?php echo esc_attr( $data['subject'] ); ?>" style="width:100%;" />
                </p>

                <p>
                    <label for="headline"><strong><?php esc_html_e( 'Headline (shown at the top)', 'dante-society' ); ?></strong></label><br>
                    <input type="text" name="headline" id="headline" value="<?php echo esc_attr( $data['headline'] ); ?>" style="width:100%;" />
                </p>

                <div class="dante-nl-field" data-for="all_events single_event">
                    <p>
                        <label for="intro"><strong><?php esc_html_e( 'Intro text', 'dante-society' ); ?></strong></label><br>
                        <textarea name="intro" id="intro" rows="4" style="width:100%;"><?php echo esc_textarea( $data['intro'] ); ?></textarea>
                    </p>
                </div>

                <div class="dante-nl-field" data-for="single_event">
                    <p>
                        <label for="event_id"><strong><?php esc_html_e( 'Choose the event', 'dante-society' ); ?></strong></label><br>
                        <select name="event_id" id="event_id" style="width:100%;">
                            <option value="0"><?php esc_html_e( '— Select an event —', 'dante-society' ); ?></option>
                            <?php foreach ( $events as $ev ) :
                                $d = get_post_meta( $ev->ID, '_event_date', true ); ?>
                                <option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $data['event_id'], $ev->ID ); ?>>
                                    <?php echo esc_html( get_the_title( $ev ) . ( $d ? ' (' . date_i18n( 'M j, Y', strtotime( $d ) ) . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <div class="dante-nl-field" data-for="message">
                    <p><strong><?php esc_html_e( 'Message', 'dante-society' ); ?></strong></p>
                    <?php
                    wp_editor( $data['body'], 'body', array(
                        'textarea_name' => 'body',
                        'textarea_rows' => 10,
                        'media_buttons' => true,
                    ) );
                    ?>
                </div>

                <p>
                    <label for="footer"><strong><?php esc_html_e( 'Footer message', 'dante-society' ); ?></strong></label><br>
                    <textarea name="footer" id="footer" rows="2" style="width:100%;"><?php echo esc_textarea( $data['footer'] ); ?></textarea>
                    <span style="color:#666;font-size:12px;"><?php esc_html_e( 'Shown above the mailing address and the Unsubscribe button (both always included).', 'dante-society' ); ?></span>
                </p>

                <hr>

                <p>
                    <label for="test_email"><strong><?php esc_html_e( 'Send test to', 'dante-society' ); ?></strong></label><br>
                    <input type="email" name="test_email" id="test_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" style="width:100%;" />
                </p>

                <p style="margin-top:16px;">
                    <button type="submit" name="dante_nl_action" value="preview" class="button"><?php esc_html_e( 'Update preview', 'dante-society' ); ?></button>
                    <button type="submit" name="dante_nl_action" value="send_test" class="button"><?php esc_html_e( 'Send test', 'dante-society' ); ?></button>
                    <button type="submit" name="dante_nl_action" value="send_all" class="button button-primary"
                        onclick="return confirm('Send this newsletter to all <?php echo (int) $sub_count; ?> subscribers?');">
                        <?php esc_html_e( 'Send to all subscribers', 'dante-society' ); ?></button>
                </p>
            </form>

            <div style="flex:1 1 420px;">
                <h2><?php esc_html_e( 'Preview', 'dante-society' ); ?></h2>
                <?php if ( $preview ) : ?>
                    <iframe style="width:100%;height:640px;border:1px solid #ddd;border-radius:6px;background:#fff;" srcdoc="<?php echo esc_attr( $preview ); ?>"></iframe>
                <?php else : ?>
                    <p><em><?php esc_html_e( 'Fill in the form and click "Update preview".', 'dante-society' ); ?></em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function danteNlToggle() {
        var t = document.getElementById( 'template' ).value;
        document.querySelectorAll( '.dante-nl-field' ).forEach( function ( el ) {
            var forList = ( el.getAttribute( 'data-for' ) || '' ).split( ' ' );
            el.style.display = forList.indexOf( t ) !== -1 ? '' : 'none';
        } );
    }
    document.addEventListener( 'DOMContentLoaded', danteNlToggle );
    </script>
    <?php
}

/**
 * Send one HTML email.
 */
function dante_nl_send( $to, $subject, $html ) {
    $from_name = get_bloginfo( 'name' );
    $admin     = get_option( 'admin_email' );
    $headers   = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $admin . '>',
    );
    return wp_mail( $to, $subject, $html, $headers );
}
