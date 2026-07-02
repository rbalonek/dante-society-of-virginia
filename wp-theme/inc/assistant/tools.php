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
            'description' => 'Create a NEW event as a draft. Use when the user wants to add an event to the calendar. If the date or time is unclear, ask the user before calling this. Never invent details.',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'title'       => array( 'type' => 'string', 'description' => 'Event name.' ),
                    'date'        => array( 'type' => 'string', 'description' => 'Event date as YYYY-MM-DD.' ),
                    'time'        => array( 'type' => 'string', 'description' => 'Human-readable time, e.g. "5:30 - 7:00 PM". Optional.' ),
                    'location'    => array( 'type' => 'string', 'description' => 'Where it happens. Optional.' ),
                    'description' => array( 'type' => 'string', 'description' => 'A short paragraph describing the event. Optional.' ),
                ),
                'required'   => array( 'title', 'date' ),
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
                    'date'        => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
                    'time'        => array( 'type' => 'string' ),
                    'location'    => array( 'type' => 'string' ),
                    'description' => array( 'type' => 'string' ),
                ),
                'required'   => array( 'event_id' ),
            ),
        ),
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
    if ( empty( $args['title'] ) || empty( $args['date'] ) ) {
        return array( 'error' => 'A title and a date are required.' );
    }

    $date = dante_assistant_normalize_date( $args['date'] );
    if ( ! $date ) {
        return array( 'error' => 'The date was not understood. Ask the user for a specific date.' );
    }

    $post_id = wp_insert_post( array(
        'post_type'    => 'event',
        'post_status'  => 'draft',
        'post_title'   => sanitize_text_field( $args['title'] ),
        'post_content' => isset( $args['description'] ) ? wp_kses_post( $args['description'] ) : '',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return array( 'error' => $post_id->get_error_message() );
    }

    update_post_meta( $post_id, '_event_date', $date );
    if ( ! empty( $args['time'] ) ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( $args['time'] ) );
    }
    if ( ! empty( $args['location'] ) ) {
        update_post_meta( $post_id, '_event_location', sanitize_text_field( $args['location'] ) );
    }

    $changeset = dante_changeset_current();
    update_post_meta( $post_id, '_dante_changeset', $changeset );
    dante_changeset_record( $changeset, array(
        'type'    => 'create',
        'post_id' => $post_id,
        'label'   => sprintf( 'Added event "%s" (%s)', $args['title'], $date ),
    ) );

    return array(
        'ok'       => true,
        'event_id' => $post_id,
        'status'   => 'draft',
        'summary'  => sprintf( 'Draft event "%s" created for %s.', $args['title'], $date ),
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
            '_event_time'     => get_post_meta( $post_id, '_event_time', true ),
            '_event_location' => get_post_meta( $post_id, '_event_location', true ),
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
        $changed[] = 'date';
    }
    if ( isset( $args['time'] ) && '' !== $args['time'] ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( $args['time'] ) );
        $changed[] = 'time';
    }
    if ( isset( $args['location'] ) && '' !== $args['location'] ) {
        update_post_meta( $post_id, '_event_location', sanitize_text_field( $args['location'] ) );
        $changed[] = 'location';
    }

    if ( empty( $changed ) ) {
        return array( 'error' => 'Nothing to change — no fields were provided.' );
    }

    $changeset = dante_changeset_current();
    update_post_meta( $post_id, '_dante_changeset', $changeset );
    dante_changeset_record( $changeset, array(
        'type'    => 'update',
        'post_id' => $post_id,
        'label'   => sprintf( 'Edited event "%s" (%s)', get_the_title( $post_id ), implode( ', ', $changed ) ),
        'before'  => $before,
    ) );

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
