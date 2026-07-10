<?php
/**
 * Dante Assistant — tools the model can call.
 *
 * Each tool = a JSON schema advertised to the model + a PHP handler that runs
 * against WordPress. Every *mutating* tool:
 *   - checks capability,
 *   - creates/edits as a DRAFT tagged to the current change set,
 *   - records an inverse op so the change can be undone.
 *
 * Slice 1 ships three event tools. Add page/newsletter tools in later slices by
 * appending to dante_assistant_tools() and dante_assistant_run_tool().
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The tool schemas sent to the model.
 *
 * @return array[]
 */
function dante_assistant_tools() {
    return array(
        array(
            'name'        => 'create_event',
            'description' => 'Create a NEW event as a draft. Use when the user wants to add an event to the calendar. Provide EITHER a specific date, OR a month (when only the month is known, e.g. "sometime in May 2027"). Never invent details.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'title'       => array( 'type' => 'string', 'description' => 'Event name.' ),
                    'date'        => array( 'type' => 'string', 'description' => 'Event date as YYYY-MM-DD, when a specific day is known.' ),
                    'month'       => array( 'type' => 'string', 'description' => 'Month only, as YYYY-MM, when there is no specific day yet (e.g. "January 2027" → "2027-01"). Use INSTEAD of date; the event is listed in that month with a "Watch for more details…" note until a day is set. Do not send both date and month.' ),
                    'time'        => array( 'type' => 'string', 'description' => 'Human-readable time, e.g. "5:30 - 7:00 PM". Optional.' ),
                    'location'    => array( 'type' => 'string', 'description' => 'Where it happens. Optional.' ),
                    'description' => array( 'type' => 'string', 'description' => 'A short paragraph describing the event. Optional.' ),
                ),
                'required'   => array( 'title' ),
            ),
        ),
        array(
            'name'        => 'find_events',
            'description' => 'Search existing events by keyword and/or upcoming-only, to find an event before editing it or to answer "what is coming up?". Returns id, title, date, time, location for each match.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'query'         => array( 'type' => 'string', 'description' => 'Text to match in the event title. Optional.' ),
                    'upcoming_only' => array( 'type' => 'boolean', 'description' => 'If true, only events today or later.' ),
                ),
            ),
        ),
        array(
            'name'        => 'update_event',
            'description' => 'Update an existing event (found via find_events). Only include the fields you want to change. Runs as a draft edit that the user approves.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'event_id'    => array( 'type' => 'integer', 'description' => 'The id from find_events.' ),
                    'title'       => array( 'type' => 'string' ),
                    'date'        => array( 'type' => 'string', 'description' => 'YYYY-MM-DD. Setting this on a month-only event pins it to a real day (and clears the "date TBA" note).' ),
                    'month'       => array( 'type' => 'string', 'description' => 'YYYY-MM. Sets the event to a month with no specific day yet ("date TBA"). Do not send both date and month.' ),
                    'time'        => array( 'type' => 'string' ),
                    'location'    => array( 'type' => 'string' ),
                    'description' => array( 'type' => 'string' ),
                ),
                'required'   => array( 'event_id' ),
            ),
        ),
        array(
            'name'        => 'list_pages',
            'description' => 'List the website pages (id, title, slug, and which one is the front/cover page). Use this first when the person wants to change page wording, to find the right page. "Cover page", "home page", and "front page" mean the page where is_front is true.',
            'input_schema' => array( 'type' => 'object', 'properties' => new stdClass() ),
        ),
        array(
            'name'        => 'read_page',
            'description' => 'Read a page\'s editable sections. Returns a numbered list of blocks (paragraphs, headings, lists) with their current text. Always call this before edit_page_block so you know each section\'s index and exact current wording.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'page_id' => array( 'type' => 'integer', 'description' => 'The page id from list_pages.' ),
                ),
                'required'   => array( 'page_id' ),
            ),
        ),
        array(
            'name'        => 'edit_page_block',
            'description' => 'Replace the wording of ONE section of a page. Provide the section\'s index (from read_page) and new_text = the COMPLETE new wording for that whole section (not just the part that changed). Only paragraphs, headings, and lists can be edited this way; for a list, put each item on its own line. This takes effect on the live website right away and can be undone.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'page_id'     => array( 'type' => 'integer', 'description' => 'The page id from list_pages.' ),
                    'block_index' => array( 'type' => 'integer', 'description' => 'The section number from read_page.' ),
                    'new_text'    => array( 'type' => 'string', 'description' => 'The full replacement wording for that section. For lists, one item per line.' ),
                ),
                'required'   => array( 'page_id', 'block_index', 'new_text' ),
            ),
        ),
        array(
            'name'        => 'compose_newsletter',
            'description' => 'Compose an email newsletter as a DRAFT for the person to preview, test, schedule, or send. This does NOT send anything by itself — the person sends it with a button. Pick a type: "all_events" (all upcoming events), "single_event" (one event — first find it with find_events and pass its id), or "message" (a free-form written note — put the wording in "body"). If the person attached a photo, it is added to the email automatically, so never say you cannot include an image. After composing, they can choose whether the photo appears at the top, in the middle of the text, or at the bottom using a "Photo position" control on the newsletter card — mention this if they ask where the image goes.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'type'     => array( 'type' => 'string', 'enum' => array( 'all_events', 'single_event', 'message' ), 'description' => 'Which kind of newsletter.' ),
                    'subject'  => array( 'type' => 'string', 'description' => 'The email subject line.' ),
                    'headline' => array( 'type' => 'string', 'description' => 'A large heading at the top of the email. Optional.' ),
                    'intro'    => array( 'type' => 'string', 'description' => 'A short intro paragraph (for the event types). Optional.' ),
                    'event_id' => array( 'type' => 'integer', 'description' => 'For "single_event": the event id from find_events.' ),
                    'body'     => array( 'type' => 'string', 'description' => 'For "message": the written content. Simple HTML (paragraphs, links, bold) is allowed.' ),
                ),
                'required'   => array( 'type', 'subject' ),
            ),
        ),
        dante_assistant_photo_tool(),
    );
}

/**
 * Dispatch a tool call. Returns an array that is JSON-encoded back to the model.
 *
 * @param string $name  Tool name.
 * @param array  $input Tool input.
 * @return array
 */
function dante_assistant_run_tool( $name, $input ) {
    switch ( $name ) {
        case 'create_event':
            return dante_tool_create_event( $input );
        case 'find_events':
            return dante_tool_find_events( $input );
        case 'update_event':
            return dante_tool_update_event( $input );
        case 'compose_newsletter':
            return dante_tool_compose_newsletter( $input );
        case 'list_pages':
            return dante_tool_list_pages( $input );
        case 'read_page':
            return dante_tool_read_page( $input );
        case 'edit_page_block':
            return dante_tool_edit_page_block( $input );
        case 'add_photo':
            return dante_tool_add_photo( $input );
        default:
            return array( 'error' => 'Unknown tool: ' . $name );
    }
}

/**
 * Tool: create an event as a draft tagged to the current change set.
 */
function dante_tool_create_event( $args ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return array( 'error' => 'You do not have permission to add events.' );
    }
    if ( empty( $args['title'] ) ) {
        return array( 'error' => 'A title is required.' );
    }

    $date  = ! empty( $args['date'] )  ? dante_assistant_normalize_date( $args['date'] )   : '';
    $month = ! empty( $args['month'] ) ? dante_assistant_normalize_month( $args['month'] ) : '';

    if ( '' === $date && '' === $month ) {
        return array( 'error' => 'Provide a specific date (YYYY-MM-DD), or a month (YYYY-MM) if only the month is known.' );
    }
    // A real date always wins; a month-only event carries no date.
    if ( '' !== $date ) {
        $month = '';
    }

    // A friendly "when" for the summary/label.
    $when = $date
        ? $date
        : date_i18n( 'F Y', strtotime( $month . '-01' ) ) . ' (date TBA)';

    $post_id = wp_insert_post( array(
        'post_type'    => 'event',
        'post_status'  => 'draft',
        'post_title'   => sanitize_text_field( $args['title'] ),
        'post_content' => isset( $args['description'] ) ? wp_kses_post( $args['description'] ) : '',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return array( 'error' => $post_id->get_error_message() );
    }

    update_post_meta( $post_id, '_event_date', $date );  // '' for a month-only event
    if ( '' !== $month ) {
        update_post_meta( $post_id, '_event_month', $month );
    }
    if ( ! empty( $args['time'] ) ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( $args['time'] ) );
    }
    if ( ! empty( $args['location'] ) ) {
        update_post_meta( $post_id, '_event_location', sanitize_text_field( $args['location'] ) );
    }

    // Attach a photo the user added in the chat, so the draft is fully ready.
    dante_assistant_apply_pending_image( $post_id );

    $changeset = dante_changeset_current();
    update_post_meta( $post_id, '_dante_changeset', $changeset );
    dante_changeset_record( $changeset, array(
        'type'    => 'create',
        'post_id' => $post_id,
        'label'   => sprintf( 'Added event "%s" (%s)', $args['title'], $when ),
    ) );

    return array(
        'ok'       => true,
        'event_id' => $post_id,
        'status'   => 'draft',
        'summary'  => sprintf( 'Draft event "%s" created for %s.', $args['title'], $when ),
    );
}

/**
 * Tool: find events by title keyword / upcoming.
 */
function dante_tool_find_events( $args ) {
    $query_args = array(
        'post_type'      => 'event',
        'post_status'    => array( 'publish', 'draft' ),
        'posts_per_page' => 20,
        'meta_key'       => '_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    );

    if ( ! empty( $args['query'] ) ) {
        $query_args['s'] = sanitize_text_field( $args['query'] );
    }
    if ( ! empty( $args['upcoming_only'] ) ) {
        $query_args['meta_query'] = array(
            array(
                'key'     => '_event_date',
                'value'   => current_time( 'Y-m-d' ),
                'compare' => '>=',
                'type'    => 'DATE',
            ),
        );
    }

    $q       = new WP_Query( $query_args );
    $results = array();
    foreach ( $q->posts as $post ) {
        $results[] = array(
            'id'       => $post->ID,
            'title'    => get_the_title( $post ),
            'date'     => get_post_meta( $post->ID, '_event_date', true ),
            'time'     => get_post_meta( $post->ID, '_event_time', true ),
            'location' => get_post_meta( $post->ID, '_event_location', true ),
            'status'   => $post->post_status,
        );
    }
    wp_reset_postdata();

    return array( 'ok' => true, 'count' => count( $results ), 'events' => $results );
}

/**
 * Tool: update an existing event (records the prior state for undo).
 */
function dante_tool_update_event( $args ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return array( 'error' => 'You do not have permission to edit events.' );
    }
    $post_id = isset( $args['event_id'] ) ? (int) $args['event_id'] : 0;
    $post    = $post_id ? get_post( $post_id ) : null;
    if ( ! $post || 'event' !== $post->post_type ) {
        return array( 'error' => 'That event was not found. Use find_events first.' );
    }

    // Snapshot the "before" state so this edit can be undone.
    $before = array(
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'meta'         => array(
            '_event_date'     => get_post_meta( $post_id, '_event_date', true ),
            '_event_month'    => get_post_meta( $post_id, '_event_month', true ),
            '_event_time'     => get_post_meta( $post_id, '_event_time', true ),
            '_event_location' => get_post_meta( $post_id, '_event_location', true ),
            '_thumbnail_id'   => get_post_meta( $post_id, '_thumbnail_id', true ),
        ),
    );

    $fields  = array( 'ID' => $post_id );
    $changed = array();

    if ( isset( $args['title'] ) && '' !== $args['title'] ) {
        $fields['post_title'] = sanitize_text_field( $args['title'] );
        $changed[] = 'title';
    }
    if ( isset( $args['description'] ) && '' !== $args['description'] ) {
        $fields['post_content'] = wp_kses_post( $args['description'] );
        $changed[] = 'description';
    }
    if ( count( $fields ) > 1 ) {
        wp_update_post( $fields );
    }

    if ( isset( $args['date'] ) && '' !== $args['date'] ) {
        $date = dante_assistant_normalize_date( $args['date'] );
        if ( ! $date ) {
            return array( 'error' => 'The date was not understood.' );
        }
        update_post_meta( $post_id, '_event_date', $date );
        delete_post_meta( $post_id, '_event_month' ); // a real day supersedes month-only.
        $changed[] = 'date';
    } elseif ( isset( $args['month'] ) && '' !== $args['month'] ) {
        $month = dante_assistant_normalize_month( $args['month'] );
        if ( ! $month ) {
            return array( 'error' => 'The month was not understood.' );
        }
        update_post_meta( $post_id, '_event_month', $month );
        update_post_meta( $post_id, '_event_date', '' ); // month-only: no specific day.
        $changed[] = 'month';
    }
    if ( isset( $args['time'] ) && '' !== $args['time'] ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( $args['time'] ) );
        $changed[] = 'time';
    }
    if ( isset( $args['location'] ) && '' !== $args['location'] ) {
        update_post_meta( $post_id, '_event_location', sanitize_text_field( $args['location'] ) );
        $changed[] = 'location';
    }

    // Attach a photo the user added in the chat, if any.
    if ( dante_assistant_apply_pending_image( $post_id ) ) {
        $changed[] = 'photo';
    }

    if ( empty( $changed ) ) {
        return array( 'error' => 'Nothing to change — no fields were provided.' );
    }

    // A published event goes live immediately and is individually undoable. A
    // still-draft event's edits ride along with that draft's pending approval
    // (undoing its creation removes it), so no separate log entry is needed.
    if ( 'publish' === get_post_status( $post_id ) ) {
        dante_changeset_log_applied( array(
            'type'    => 'update',
            'post_id' => $post_id,
            'label'   => sprintf( 'Edited event "%s" (%s)', get_the_title( $post_id ), implode( ', ', $changed ) ),
            'before'  => $before,
        ) );
    }

    return array(
        'ok'       => true,
        'event_id' => $post_id,
        'changed'  => $changed,
        'summary'  => sprintf( 'Updated %s on "%s".', implode( ', ', $changed ), get_the_title( $post_id ) ),
    );
}

/**
 * Best-effort date normalization to YYYY-MM-DD. Accepts things like
 * "October 22, 2026" or "2026-10-22". Returns '' if it can't parse.
 */
function dante_assistant_normalize_date( $raw ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }
    $ts = strtotime( $raw );
    return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

/**
 * Normalize a month to YYYY-MM. Accepts "2027-05" or "May 2027" (anchored to the
 * first of the month to avoid day-overflow, e.g. "February" on the 30th).
 * Returns '' if it can't parse.
 */
function dante_assistant_normalize_month( $raw ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }
    if ( preg_match( '/^(\d{4})-(\d{2})$/', $raw, $m ) ) {
        return $m[1] . '-' . $m[2];
    }
    $ts = strtotime( 'first day of ' . $raw );
    if ( ! $ts ) {
        $ts = strtotime( $raw );
    }
    return $ts ? gmdate( 'Y-m', $ts ) : '';
}

/**
 * If the current chat turn carried an uploaded photo (stashed by the chat
 * endpoint), set it as the event's featured image and consume it so it's only
 * applied once. Returns true if a photo was applied.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function dante_assistant_apply_pending_image( $post_id ) {
    $att = isset( $GLOBALS['dante_assistant_pending_image'] ) ? (int) $GLOBALS['dante_assistant_pending_image'] : 0;
    if ( $att <= 0 ) {
        return false;
    }
    $GLOBALS['dante_assistant_pending_image'] = 0; // consume once, whatever happens.

    $att_post = get_post( $att );
    if ( ! $att_post || 'attachment' !== $att_post->post_type ) {
        return false;
    }
    set_post_thumbnail( $post_id, $att );
    return true;
}
