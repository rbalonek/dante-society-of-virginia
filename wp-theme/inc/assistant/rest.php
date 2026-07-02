<?php
/**
 * Dante Assistant — REST endpoints + the agent loop.
 *
 * Routes (all under /wp-json/dante/v1/, nonce + capability protected):
 *   POST assistant/chat     Run one user message through the agent loop.
 *   GET  assistant/pending  Items waiting for approval in the open change set.
 *   POST assistant/approve  Publish all pending items at once.
 *   POST assistant/discard  Trash all pending items.
 *   GET  assistant/history  Recent applied change sets (for undo).
 *   POST assistant/undo     Undo an applied change set by id.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared permission check for every route.
 */
function dante_assistant_rest_permission() {
    return current_user_can( 'edit_posts' );
}

/**
 * Register routes.
 */
function dante_assistant_register_routes() {
    $perm = 'dante_assistant_rest_permission';

    register_rest_route( 'dante/v1', '/assistant/chat', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_chat',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/pending', array(
        'methods'             => 'GET',
        'callback'            => 'dante_assistant_rest_pending',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/approve', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_approve',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/publish-item', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_publish_item',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/upload', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_upload',
        'permission_callback' => function () { return current_user_can( 'upload_files' ); },
    ) );
    register_rest_route( 'dante/v1', '/assistant/discard', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_discard',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/history', array(
        'methods'             => 'GET',
        'callback'            => 'dante_assistant_rest_history',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/undo', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_undo',
        'permission_callback' => $perm,
    ) );

    // Newsletter actions (human-clicked; the model never sends).
    register_rest_route( 'dante/v1', '/assistant/newsletter/test', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_nl_test',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/newsletter/schedule', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_nl_schedule',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/newsletter/send', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_nl_send',
        'permission_callback' => $perm,
    ) );
    register_rest_route( 'dante/v1', '/assistant/newsletter/cancel', array(
        'methods'             => 'POST',
        'callback'            => 'dante_assistant_rest_nl_cancel',
        'permission_callback' => $perm,
    ) );
}
add_action( 'rest_api_init', 'dante_assistant_register_routes' );

/**
 * The system prompt — the assistant's "house rules".
 */
function dante_assistant_system_prompt() {
    $today = current_time( 'l, F j, Y' );
    return
        "You are the friendly assistant for the website of the Dante Alighieri Society of Virginia, " .
        "a small non-profit. You help board members (often not technical, sometimes older) update the " .
        "website by chatting in plain English.\n\n" .
        "Today is {$today}.\n\n" .
        "Rules:\n" .
        "- Keep replies short, warm, and jargon-free. Never say 'WordPress', 'draft status', 'API', or 'database'. Say 'the website'.\n" .
        "- Everything you create or change is saved as a draft for the person to review and publish — never say it is live.\n" .
        "- Before adding an event, make sure you have at least a title and a specific date. If the date or time is unclear, ask a short follow-up question instead of guessing.\n" .
        "- To change an existing event, first use find_events to locate it, then update_event with only the fields that change.\n" .
        "- After you make a change, tell the person plainly what you did in one sentence and remind them they can review and publish it.\n" .
        "- You can help with two things: events and email newsletters.\n" .
        "- For a newsletter, use compose_newsletter to prepare it. Never offer to send it yourself — after composing, tell the person they can preview it, send themselves a test, schedule it, or send it to everyone using the buttons that appear. For a 'single_event' newsletter, find the event with find_events first.\n" .
        "- If asked for something else (like editing page text), say that's coming soon.";
}

/**
 * POST assistant/chat — run the agent loop for one user message.
 *
 * Request body:
 *   message : string          The new user message.
 *   history : array           Prior visible turns: [ {role:'user'|'assistant', text:'...'}, ... ].
 */
function dante_assistant_rest_chat( WP_REST_Request $request ) {
    $provider = dante_assistant_provider();
    if ( is_wp_error( $provider ) ) {
        return new WP_REST_Response( array( 'error' => $provider->get_error_message() ), 200 );
    }

    $message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
    if ( '' === $message ) {
        return new WP_REST_Response( array( 'error' => 'Please type a message.' ), 200 );
    }

    // A photo the user attached in the chat; applied to the event a tool creates
    // or edits this turn (consumed once, inside the tool handler).
    $attachment_id = (int) $request->get_param( 'attachment_id' );
    $GLOBALS['dante_assistant_pending_image'] = $attachment_id > 0 ? $attachment_id : 0;

    // Reset the "a newsletter was composed this turn" signal.
    $GLOBALS['dante_assistant_last_newsletter'] = 0;

    // Rebuild the conversation as content-block messages.
    $messages = dante_assistant_build_messages( $request->get_param( 'history' ), $message );
    $tools    = dante_assistant_tools();
    $system   = dante_assistant_system_prompt();

    $actions   = array();
    $reply     = '';
    $max_turns = 6; // safety bound on tool round-trips.

    for ( $i = 0; $i < $max_turns; $i++ ) {
        $result = $provider->chat( $system, $messages, $tools );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 200 );
        }

        // Record the assistant turn (text and/or tool_use blocks) in history.
        $messages[] = array( 'role' => 'assistant', 'content' => $result['assistant_content'] );

        if ( 'tool' !== $result['stop'] || empty( $result['tool_calls'] ) ) {
            $reply = $result['text'];
            break;
        }

        // Execute each requested tool and feed results back as a user turn.
        $tool_result_blocks = array();
        foreach ( $result['tool_calls'] as $call ) {
            $output = dante_assistant_run_tool( $call['name'], $call['input'] );
            if ( isset( $output['event_id'] ) ) {
                $actions[] = array(
                    'summary'     => isset( $output['summary'] ) ? $output['summary'] : '',
                    'preview_url' => get_preview_post_link( $output['event_id'] ),
                );
            }
            $tool_result_blocks[] = array(
                'type'        => 'tool_result',
                'tool_use_id' => $call['id'],
                'content'     => wp_json_encode( $output ),
            );
        }
        $messages[] = array( 'role' => 'user', 'content' => $tool_result_blocks );
    }

    $payload = array(
        'reply'   => $reply ? $reply : "All set.",
        'actions' => $actions,
        'pending' => dante_assistant_pending_payload(),
    );

    // If the assistant composed a newsletter this turn, include its card data.
    if ( ! empty( $GLOBALS['dante_assistant_last_newsletter'] ) ) {
        $payload['newsletter'] = dante_assistant_newsletter_payload( $GLOBALS['dante_assistant_last_newsletter'] );
    }

    return new WP_REST_Response( $payload, 200 );
}

/**
 * Convert the visible chat history + new message into content-block messages.
 */
function dante_assistant_build_messages( $history, $new_message ) {
    $messages = array();
    if ( is_array( $history ) ) {
        foreach ( $history as $turn ) {
            $role = ( isset( $turn['role'] ) && 'assistant' === $turn['role'] ) ? 'assistant' : 'user';
            $text = isset( $turn['text'] ) ? sanitize_textarea_field( $turn['text'] ) : '';
            if ( '' === $text ) {
                continue;
            }
            $messages[] = array(
                'role'    => $role,
                'content' => array( array( 'type' => 'text', 'text' => $text ) ),
            );
        }
    }
    $messages[] = array(
        'role'    => 'user',
        'content' => array( array( 'type' => 'text', 'text' => $new_message ) ),
    );
    return $messages;
}

/**
 * Build the "pending review" payload (shared by chat + pending routes).
 */
function dante_assistant_pending_payload() {
    $open = get_posts( array(
        'post_type'      => 'dante_change',
        'post_status'    => 'draft',
        'posts_per_page' => 1,
        'author'         => get_current_user_id(),
        'meta_key'       => '_status',
        'meta_value'     => 'pending_review',
        'fields'         => 'ids',
    ) );

    if ( empty( $open ) ) {
        return array( 'changeset_id' => 0, 'items' => array() );
    }
    $id    = (int) $open[0];
    $items = array_map( 'dante_assistant_event_preview', dante_changeset_pending_posts( $id ) );
    return array(
        'changeset_id' => $id,
        'items'        => $items,
    );
}

/**
 * A rich, renderable preview of one pending event — everything the dashboard
 * needs to show it and offer Publish / See preview without opening the editor.
 */
function dante_assistant_event_preview( $post_id ) {
    $date = get_post_meta( $post_id, '_event_date', true );
    $desc = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
    return array(
        'post_id'     => (int) $post_id,
        'title'       => get_the_title( $post_id ),
        'date'        => $date,
        'date_label'  => $date ? date_i18n( 'l, F j, Y', strtotime( $date ) ) : '',
        'time'        => get_post_meta( $post_id, '_event_time', true ),
        'location'    => get_post_meta( $post_id, '_event_location', true ),
        'excerpt'     => wp_trim_words( $desc, 40 ),
        'thumb'       => get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '',
        'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
        'preview_url' => get_preview_post_link( $post_id ),
    );
}

function dante_assistant_rest_pending( WP_REST_Request $request ) {
    return new WP_REST_Response( dante_assistant_pending_payload(), 200 );
}

function dante_assistant_rest_approve( WP_REST_Request $request ) {
    $id = (int) $request->get_param( 'changeset_id' );
    if ( ! $id || 'dante_change' !== get_post_type( $id ) ) {
        return new WP_REST_Response( array( 'error' => 'Nothing to approve.' ), 200 );
    }
    $count = dante_changeset_approve( $id );
    return new WP_REST_Response( array(
        'ok'      => true,
        'message' => sprintf( _n( 'Published %d change.', 'Published %d changes.', $count, 'dante-society' ), $count ),
        'pending' => dante_assistant_pending_payload(),
        'history' => dante_changeset_history(),
    ), 200 );
}

function dante_assistant_rest_publish_item( WP_REST_Request $request ) {
    $changeset_id = (int) $request->get_param( 'changeset_id' );
    $post_id      = (int) $request->get_param( 'post_id' );
    if ( ! $changeset_id || ! $post_id || 'event' !== get_post_type( $post_id ) ) {
        return new WP_REST_Response( array( 'error' => 'Nothing to publish.' ), 200 );
    }
    $result = dante_changeset_publish_one( $changeset_id, $post_id );
    if ( isset( $result['error'] ) ) {
        return new WP_REST_Response( $result, 200 );
    }
    return new WP_REST_Response( array(
        'ok'      => true,
        'message' => sprintf( '"%s" is now live on the website.', get_the_title( $post_id ) ),
        'pending' => dante_assistant_pending_payload(),
        'history' => dante_changeset_history(),
    ), 200 );
}

/**
 * POST assistant/upload — receive a photo from the chat and add it to the media
 * library. Returns the attachment id, which the next chat message attaches to
 * the event it creates/edits.
 */
function dante_assistant_rest_upload( WP_REST_Request $request ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $files = $request->get_file_params();
    if ( empty( $files['file'] ) ) {
        return new WP_REST_Response( array( 'error' => 'No photo was received.' ), 200 );
    }

    $attachment_id = media_handle_upload( 'file', 0 );
    if ( is_wp_error( $attachment_id ) ) {
        return new WP_REST_Response( array( 'error' => $attachment_id->get_error_message() ), 200 );
    }

    return new WP_REST_Response( array(
        'ok'            => true,
        'attachment_id' => $attachment_id,
        'thumb'         => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
    ), 200 );
}

function dante_assistant_rest_discard( WP_REST_Request $request ) {
    $id = (int) $request->get_param( 'changeset_id' );
    if ( ! $id || 'dante_change' !== get_post_type( $id ) ) {
        return new WP_REST_Response( array( 'error' => 'Nothing to discard.' ), 200 );
    }
    $count = dante_changeset_discard( $id );
    return new WP_REST_Response( array(
        'ok'      => true,
        'message' => sprintf( _n( 'Discarded %d draft.', 'Discarded %d drafts.', $count, 'dante-society' ), $count ),
        'pending' => dante_assistant_pending_payload(),
    ), 200 );
}

function dante_assistant_rest_history( WP_REST_Request $request ) {
    return new WP_REST_Response( array( 'history' => dante_changeset_history() ), 200 );
}

function dante_assistant_rest_undo( WP_REST_Request $request ) {
    $id     = (int) $request->get_param( 'changeset_id' );
    $result = dante_changeset_undo( $id );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 200 );
    }
    return new WP_REST_Response( array(
        'ok'      => true,
        'message' => 'That change was undone.',
        'history' => dante_changeset_history(),
    ), 200 );
}

/**
 * Validate that a request targets a real newsletter draft.
 *
 * @return int|WP_REST_Response The id, or an error response.
 */
function dante_assistant_nl_require_id( WP_REST_Request $request ) {
    $id = (int) $request->get_param( 'id' );
    if ( ! $id || 'dante_newsletter' !== get_post_type( $id ) ) {
        return new WP_REST_Response( array( 'error' => 'That newsletter could not be found.' ), 200 );
    }
    return $id;
}

function dante_assistant_rest_nl_test( WP_REST_Request $request ) {
    $id = dante_assistant_nl_require_id( $request );
    if ( $id instanceof WP_REST_Response ) {
        return $id;
    }

    $email = sanitize_email( (string) $request->get_param( 'email' ) );
    if ( ! is_email( $email ) ) {
        $email = wp_get_current_user()->user_email;
    }

    $data = dante_assistant_newsletter_data( $id );
    $html = dante_assistant_newsletter_html( $id, home_url( '/' ) );
    $ok   = dante_nl_send( $email, $data['subject'] ? $data['subject'] : 'Test', $html );

    return new WP_REST_Response( array(
        'ok'      => (bool) $ok,
        'message' => $ok
            ? sprintf( 'Test sent to %s. (On Local, real delivery needs SMTP set up.)', $email )
            : 'The test email could not be sent.',
    ), 200 );
}

function dante_assistant_rest_nl_schedule( WP_REST_Request $request ) {
    $id = dante_assistant_nl_require_id( $request );
    if ( $id instanceof WP_REST_Response ) {
        return $id;
    }

    // Interpret the datetime-local value in the site's timezone.
    $raw = sanitize_text_field( (string) $request->get_param( 'datetime' ) );
    $dt  = $raw ? date_create( $raw, wp_timezone() ) : false;
    if ( ! $dt ) {
        return new WP_REST_Response( array( 'error' => 'Please choose a valid date and time.' ), 200 );
    }
    $timestamp = $dt->getTimestamp();
    if ( $timestamp <= time() + 30 ) {
        return new WP_REST_Response( array( 'error' => 'Please choose a time in the future.' ), 200 );
    }

    // Replace any prior schedule for this newsletter, then schedule.
    wp_clear_scheduled_hook( 'dante_assistant_send_newsletter', array( $id ) );
    wp_schedule_single_event( $timestamp, 'dante_assistant_send_newsletter', array( $id ) );

    update_post_meta( $id, '_nl_state', 'scheduled' );
    update_post_meta( $id, '_nl_send_time', $timestamp );

    $count = count( dante_get_subscribers() );
    return new WP_REST_Response( array(
        'ok'         => true,
        'message'    => sprintf( 'Scheduled to send to %d subscriber(s) on %s.', $count, date_i18n( 'F j, Y \a\t g:i a', $timestamp ) ),
        'newsletter' => dante_assistant_newsletter_payload( $id ),
    ), 200 );
}

function dante_assistant_rest_nl_send( WP_REST_Request $request ) {
    $id = dante_assistant_nl_require_id( $request );
    if ( $id instanceof WP_REST_Response ) {
        return $id;
    }

    // Cancel any pending schedule so it doesn't send twice.
    wp_clear_scheduled_hook( 'dante_assistant_send_newsletter', array( $id ) );
    $count = dante_assistant_newsletter_send_all( $id );

    return new WP_REST_Response( array(
        'ok'         => true,
        'message'    => sprintf( 'Newsletter sent to %d subscriber(s). (On Local, real delivery needs SMTP set up.)', $count ),
        'newsletter' => dante_assistant_newsletter_payload( $id ),
    ), 200 );
}

function dante_assistant_rest_nl_cancel( WP_REST_Request $request ) {
    $id = dante_assistant_nl_require_id( $request );
    if ( $id instanceof WP_REST_Response ) {
        return $id;
    }

    wp_clear_scheduled_hook( 'dante_assistant_send_newsletter', array( $id ) );
    update_post_meta( $id, '_nl_state', 'draft' );
    delete_post_meta( $id, '_nl_send_time' );

    return new WP_REST_Response( array(
        'ok'         => true,
        'message'    => 'The scheduled send was canceled.',
        'newsletter' => dante_assistant_newsletter_payload( $id ),
    ), 200 );
}
