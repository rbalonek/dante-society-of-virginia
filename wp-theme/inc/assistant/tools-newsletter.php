<?php
/**
 * Dante Assistant — newsletter tool.
 *
 * The model can COMPOSE a newsletter draft; it can never send. Preview, test,
 * schedule, and send-to-all are human-clicked actions (REST routes wired to the
 * dashboard card), so the AI can't email subscribers by itself.
 *
 * Reuses the existing composer engine in inc/newsletter.php:
 *   dante_nl_build_inner(), dante_nl_email_shell(), dante_nl_send(),
 *   dante_get_subscribers().
 *
 * Newsletters are stored as a private `dante_newsletter` post; the composed
 * fields live in the `_nl_data` meta as JSON. They do NOT flow through the event
 * change-set / undo system (you can't un-send an email).
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The private post type that stores composed newsletters.
 */
function dante_assistant_newsletter_cpt() {
    register_post_type( 'dante_newsletter', array(
        'labels'       => array( 'name' => __( 'Newsletters', 'dante-society' ) ),
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => array( 'title' ),
    ) );
}
add_action( 'init', 'dante_assistant_newsletter_cpt' );

/**
 * The default footer line (matches the manual composer).
 */
function dante_assistant_newsletter_default_footer() {
    return "You're receiving this email because you subscribed to updates from the Dante Society of Virginia.";
}

/**
 * Tool: compose a newsletter draft. Returns the new newsletter id.
 */
function dante_tool_compose_newsletter( $args ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return array( 'error' => 'You do not have permission to create newsletters.' );
    }
    if ( empty( $args['subject'] ) ) {
        return array( 'error' => 'A subject line is required.' );
    }

    $type = isset( $args['type'] ) && in_array( $args['type'], array( 'all_events', 'single_event', 'message' ), true )
        ? $args['type'] : 'all_events';

    if ( 'single_event' === $type && empty( $args['event_id'] ) ) {
        return array( 'error' => 'For a single-event newsletter, find the event with find_events first and pass its id.' );
    }
    if ( 'message' === $type && empty( $args['body'] ) ) {
        return array( 'error' => 'For a message newsletter, include the written content in "body".' );
    }

    $data = array(
        'template' => $type,
        'subject'  => sanitize_text_field( $args['subject'] ),
        'headline' => isset( $args['headline'] ) ? sanitize_text_field( $args['headline'] ) : '',
        'intro'    => isset( $args['intro'] ) ? sanitize_textarea_field( $args['intro'] ) : '',
        'event_id' => isset( $args['event_id'] ) ? absint( $args['event_id'] ) : 0,
        'body'     => isset( $args['body'] ) ? wp_kses_post( $args['body'] ) : '',
        'footer'   => dante_assistant_newsletter_default_footer(),
    );

    // If the user attached a photo in the chat, place it near the top of the email.
    $att = isset( $GLOBALS['dante_assistant_pending_image'] ) ? (int) $GLOBALS['dante_assistant_pending_image'] : 0;
    if ( $att > 0 ) {
        $GLOBALS['dante_assistant_pending_image'] = 0; // consume once.
        $url = wp_get_attachment_image_url( $att, 'large' );
        if ( $url ) {
            $data['image_id']  = $att;
            $data['image_url'] = $url;
        }
    }

    $id = wp_insert_post( array(
        'post_type'   => 'dante_newsletter',
        'post_status' => 'draft',
        'post_title'  => $data['subject'],
    ), true );

    if ( is_wp_error( $id ) ) {
        return array( 'error' => $id->get_error_message() );
    }

    // wp_slash so update_post_meta's internal wp_unslash doesn't strip the
    // backslashes in the JSON (which would corrupt dashes, quotes, accents).
    update_post_meta( $id, '_nl_data', wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
    update_post_meta( $id, '_nl_state', 'draft' );

    // Signal to the chat endpoint that a newsletter card should be shown.
    $GLOBALS['dante_assistant_last_newsletter'] = (int) $id;

    return array(
        'ok'             => true,
        'newsletter_id'  => (int) $id,
        'summary'        => sprintf( 'Newsletter "%s" composed as a draft.', $data['subject'] ),
    );
}

/**
 * Load a newsletter's composed fields (with defaults).
 */
function dante_assistant_newsletter_data( $id ) {
    $raw = json_decode( get_post_meta( $id, '_nl_data', true ), true );
    $raw = is_array( $raw ) ? $raw : array();
    return wp_parse_args( $raw, array(
        'template' => 'all_events',
        'subject'  => '',
        'headline' => '',
        'intro'    => '',
        'event_id' => 0,
        'body'     => '',
        'footer'   => dante_assistant_newsletter_default_footer(),
    ) );
}

/**
 * Render the full branded email HTML for a newsletter.
 */
function dante_assistant_newsletter_html( $id, $unsubscribe_url = '#' ) {
    $data  = dante_assistant_newsletter_data( $id );
    $inner = dante_nl_build_inner( $data );
    return dante_nl_email_shell( $inner, $unsubscribe_url, $data['footer'] );
}

/**
 * Send a newsletter to every current subscriber. Marks it sent.
 *
 * @return int Number of emails sent.
 */
function dante_assistant_newsletter_send_all( $id ) {
    $data  = dante_assistant_newsletter_data( $id );
    $inner = dante_nl_build_inner( $data );
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

    update_post_meta( $id, '_nl_state', 'sent' );
    update_post_meta( $id, '_nl_sent_count', $count );
    update_post_meta( $id, '_nl_sent_at', current_time( 'mysql' ) );

    return $count;
}

/**
 * A short human description of what the newsletter contains.
 */
function dante_assistant_newsletter_summary( $data ) {
    if ( 'single_event' === $data['template'] ) {
        $title = $data['event_id'] ? get_the_title( $data['event_id'] ) : '';
        return $title ? sprintf( 'About the event "%s"', $title ) : 'About a single event';
    }
    if ( 'message' === $data['template'] ) {
        return 'A written message';
    }
    return 'All upcoming events';
}

/**
 * Build the payload the dashboard card renders (details + rendered preview).
 */
function dante_assistant_newsletter_payload( $id ) {
    $data  = dante_assistant_newsletter_data( $id );
    $state = get_post_meta( $id, '_nl_state', true );
    $state = $state ? $state : 'draft';
    $time  = get_post_meta( $id, '_nl_send_time', true );

    return array(
        'id'                 => (int) $id,
        'subject'            => $data['subject'],
        'summary'            => dante_assistant_newsletter_summary( $data ),
        'state'              => $state,
        'send_time'          => $time ? date_i18n( 'F j, Y \a\t g:i a', (int) $time ) : '',
        'sent_count'         => (int) get_post_meta( $id, '_nl_sent_count', true ),
        'subscriber_count'   => count( dante_get_subscribers() ),
        'has_image'          => ! empty( $data['image_url'] ),
        'image_pos'          => ! empty( $data['image_pos'] ) ? $data['image_pos'] : 'top',
        'preview_html'       => dante_assistant_newsletter_html( $id, home_url( '/' ) ),
        'default_test_email' => wp_get_current_user()->user_email,
    );
}

/**
 * WP-Cron target for a scheduled send.
 *
 * NOTE: WP-Cron only fires on site traffic. For reliable timed sends on the live
 * server, a real system cron hitting wp-cron.php is sturdier (see CLAUDE.md).
 */
function dante_assistant_cron_send_newsletter( $id ) {
    if ( 'sent' === get_post_meta( $id, '_nl_state', true ) ) {
        return;
    }
    dante_assistant_newsletter_send_all( $id );
}
add_action( 'dante_assistant_send_newsletter', 'dante_assistant_cron_send_newsletter' );
