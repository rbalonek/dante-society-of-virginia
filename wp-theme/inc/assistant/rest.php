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
        "- You can only work with events right now. If asked for something else (page text, newsletters), say that's coming soon.";
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
                    'summary'  => isset( $output['summary'] ) ? $output['summary'] : '',
                    'edit_url' => get_edit_post_link( $output['event_id'], 'raw' ),
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

    return new WP_REST_Response( array(
        'reply'   => $reply ? $reply : "All set.",
        'actions' => $actions,
        'pending' => dante_assistant_pending_payload(),
    ), 200 );
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
    $id = (int) $open[0];
    return array(
        'changeset_id' => $id,
        'items'        => dante_changeset_pending_summary( $id ),
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
