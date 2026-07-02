<?php
/**
 * Dante Assistant — change log / version control.
 *
 * Every change the assistant makes is grouped into a "change set" (one per edit
 * session) and records an *inverse* operation so it can be undone. Change sets
 * are stored as a private `dante_change` custom post type — no custom table, so
 * this travels with the site and needs no migration.
 *
 * Lifecycle of a change set:
 *   pending_review  → the assistant made drafts; the human hasn't approved yet
 *   applied         → the human clicked "Approve & publish all"
 *   reverted        → the human clicked "Undo"
 *
 * The last DANTE_ASSISTANT_UNDO_KEEP *applied* sets stay undoable; older ones
 * are pruned.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DANTE_ASSISTANT_UNDO_KEEP', 5 );

/**
 * Register the private change-set post type.
 */
function dante_change_register_cpt() {
    register_post_type( 'dante_change', array(
        'labels'          => array( 'name' => __( 'Assistant Changes', 'dante-society' ) ),
        'public'          => false,
        'show_ui'         => false,
        'show_in_rest'    => false,
        'has_archive'     => false,
        'rewrite'         => false,
        'query_var'       => false,
        'supports'        => array( 'title' ),
        'capability_type' => 'post',
    ) );
}
add_action( 'init', 'dante_change_register_cpt' );

/**
 * Get the current open (pending_review) change set for this user, creating one
 * if none is open. Everything the assistant does accumulates here until the user
 * approves or discards — that's what makes "approve all at once" possible.
 *
 * @return int Change-set post ID.
 */
function dante_changeset_current() {
    $existing = get_posts( array(
        'post_type'      => 'dante_change',
        'post_status'    => 'draft',
        'posts_per_page' => 1,
        'author'         => get_current_user_id(),
        'meta_key'       => '_status',
        'meta_value'     => 'pending_review',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ) );

    if ( ! empty( $existing ) ) {
        return (int) $existing[0];
    }

    $id = wp_insert_post( array(
        'post_type'   => 'dante_change',
        'post_status' => 'draft',
        'post_title'  => 'Change set ' . current_time( 'Y-m-d H:i' ),
        'post_author' => get_current_user_id(),
    ) );

    update_post_meta( $id, '_status', 'pending_review' );
    update_post_meta( $id, '_ops', wp_json_encode( array() ) );

    return (int) $id;
}

/**
 * Log a change that has ALREADY been applied to the live site (e.g. editing a
 * published event or page). It goes straight into a one-op, "applied" change set
 * so it shows in "Recent changes" and is immediately undoable — separate from
 * the pending-approval flow used for freshly-created drafts.
 *
 * @param array $op Same shape as dante_changeset_record()'s $op.
 * @return int Change-set post ID.
 */
function dante_changeset_log_applied( $op ) {
    $id = wp_insert_post( array(
        'post_type'   => 'dante_change',
        'post_status' => 'draft',
        'post_title'  => 'Change ' . current_time( 'Y-m-d H:i:s' ),
        'post_author' => get_current_user_id(),
    ) );

    update_post_meta( $id, '_status', 'applied' );
    update_post_meta( $id, '_applied_at', current_time( 'mysql' ) );
    update_post_meta( $id, '_ops', wp_slash( wp_json_encode( array( $op ), JSON_UNESCAPED_UNICODE ) ) );

    dante_changeset_prune();
    return (int) $id;
}

/**
 * Append an operation (with its inverse) to a change set.
 *
 * @param int   $changeset_id Change-set post ID.
 * @param array $op {
 *     @type string $type    'create' | 'update'
 *     @type int    $post_id Affected event/post ID.
 *     @type string $label   Human summary, e.g. 'Created event "Vino e Viaggio"'.
 *     @type array  $before  For 'update': prior field/meta values to restore.
 * }
 */
function dante_changeset_record( $changeset_id, $op ) {
    $ops   = json_decode( get_post_meta( $changeset_id, '_ops', true ), true );
    $ops   = is_array( $ops ) ? $ops : array();
    $ops[] = $op;
    // wp_slash so update_post_meta's internal wp_unslash leaves the JSON intact
    // (otherwise backslash escapes for dashes/quotes/accents get stripped).
    update_post_meta( $changeset_id, '_ops', wp_slash( wp_json_encode( $ops, JSON_UNESCAPED_UNICODE ) ) );
}

/**
 * The draft posts waiting for approval in a change set (the ones the assistant
 * created and set to draft, tagged with this session).
 *
 * @return int[] Post IDs.
 */
function dante_changeset_pending_posts( $changeset_id ) {
    return get_posts( array(
        'post_type'      => 'any',
        'post_status'    => array( 'draft', 'pending' ),
        'posts_per_page' => -1,
        'meta_key'       => '_dante_changeset',
        'meta_value'     => $changeset_id,
        'fields'         => 'ids',
    ) );
}

/**
 * Approve & publish everything in a change set, in one go.
 *
 * @return int Number of items published.
 */
function dante_changeset_approve( $changeset_id ) {
    $count = 0;
    foreach ( dante_changeset_pending_posts( $changeset_id ) as $post_id ) {
        wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
        $count++;
    }
    update_post_meta( $changeset_id, '_status', 'applied' );
    update_post_meta( $changeset_id, '_applied_at', current_time( 'mysql' ) );
    dante_changeset_prune();
    return $count;
}

/**
 * Publish a single pending item. If it was the last one waiting, the change set
 * is marked applied (so it enters the undo history). Lets the user approve
 * events one at a time from the dashboard instead of all at once.
 *
 * @return array ['ok'=>bool, 'applied'=>bool] or ['error'=>string]
 */
function dante_changeset_publish_one( $changeset_id, $post_id ) {
    if ( (int) get_post_meta( $post_id, '_dante_changeset', true ) !== (int) $changeset_id ) {
        return array( 'error' => 'That item is not part of this change set.' );
    }
    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

    // If nothing else is waiting, close the change set into history.
    if ( empty( dante_changeset_pending_posts( $changeset_id ) ) ) {
        update_post_meta( $changeset_id, '_status', 'applied' );
        update_post_meta( $changeset_id, '_applied_at', current_time( 'mysql' ) );
        dante_changeset_prune();
        return array( 'ok' => true, 'applied' => true );
    }
    return array( 'ok' => true, 'applied' => false );
}

/**
 * Discard everything in a pending change set (trash the drafts, drop the set).
 *
 * @return int Number of items discarded.
 */
function dante_changeset_discard( $changeset_id ) {
    $count = 0;
    foreach ( dante_changeset_pending_posts( $changeset_id ) as $post_id ) {
        wp_trash_post( $post_id );
        $count++;
    }
    wp_delete_post( $changeset_id, true );
    return $count;
}

/**
 * Undo an applied change set by replaying its inverse operations in reverse.
 *
 * @return true|WP_Error
 */
function dante_changeset_undo( $changeset_id ) {
    $status = get_post_meta( $changeset_id, '_status', true );
    if ( 'applied' !== $status ) {
        return new WP_Error( 'not_undoable', 'That change can no longer be undone.' );
    }

    $ops = json_decode( get_post_meta( $changeset_id, '_ops', true ), true );
    $ops = is_array( $ops ) ? $ops : array();

    foreach ( array_reverse( $ops ) as $op ) {
        if ( 'create' === $op['type'] ) {
            // Inverse of "created" = trash it.
            if ( ! empty( $op['post_id'] ) ) {
                wp_trash_post( $op['post_id'] );
            }
        } elseif ( 'update' === $op['type'] && ! empty( $op['before'] ) ) {
            // Inverse of "updated" = restore prior fields + meta.
            $before = $op['before'];
            $fields = array( 'ID' => $op['post_id'] );
            if ( isset( $before['post_title'] ) )   { $fields['post_title'] = $before['post_title']; }
            if ( isset( $before['post_content'] ) ) { $fields['post_content'] = $before['post_content']; }
            wp_update_post( $fields );

            if ( isset( $before['meta'] ) && is_array( $before['meta'] ) ) {
                foreach ( $before['meta'] as $key => $value ) {
                    if ( '' === $value || null === $value ) {
                        delete_post_meta( $op['post_id'], $key );
                    } else {
                        update_post_meta( $op['post_id'], $key, $value );
                    }
                }
            }
        }
    }

    update_post_meta( $changeset_id, '_status', 'reverted' );
    return true;
}

/**
 * List recent applied change sets (most recent first), for the "Recent changes"
 * undo panel.
 *
 * @return array[] Each: ['id','label','when','items'].
 */
function dante_changeset_history( $limit = DANTE_ASSISTANT_UNDO_KEEP ) {
    $ids = get_posts( array(
        'post_type'      => 'dante_change',
        'post_status'    => 'draft',
        'posts_per_page' => $limit,
        'orderby'        => 'meta_value',
        'meta_key'       => '_applied_at',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'meta_query'     => array(
            array( 'key' => '_status', 'value' => 'applied' ),
        ),
    ) );

    $out = array();
    foreach ( $ids as $id ) {
        $ops = json_decode( get_post_meta( $id, '_ops', true ), true );
        $ops = is_array( $ops ) ? $ops : array();
        $out[] = array(
            'id'    => $id,
            'label' => dante_changeset_summary( $ops ),
            'when'  => get_post_meta( $id, '_applied_at', true ),
            'items' => count( $ops ),
        );
    }
    return $out;
}

/**
 * Build the list of pending items (labels + edit links) for the review panel.
 *
 * @return array[] Each: ['post_id','label','edit_url'].
 */
function dante_changeset_pending_summary( $changeset_id ) {
    $ops  = json_decode( get_post_meta( $changeset_id, '_ops', true ), true );
    $ops  = is_array( $ops ) ? $ops : array();
    $out  = array();
    foreach ( $ops as $op ) {
        $out[] = array(
            'post_id'  => isset( $op['post_id'] ) ? (int) $op['post_id'] : 0,
            'label'    => isset( $op['label'] ) ? $op['label'] : '(change)',
            'edit_url' => ! empty( $op['post_id'] ) ? get_edit_post_link( $op['post_id'], 'raw' ) : '',
        );
    }
    return $out;
}

/**
 * One-line summary of a set of ops for the history list.
 */
function dante_changeset_summary( $ops ) {
    if ( empty( $ops ) ) {
        return 'No changes';
    }
    $labels = wp_list_pluck( $ops, 'label' );
    $labels = array_filter( $labels );
    if ( count( $labels ) <= 2 ) {
        return implode( '; ', $labels );
    }
    return sprintf( '%s (+%d more)', $labels[0], count( $labels ) - 1 );
}

/**
 * Keep only the most recent applied change sets undoable; hard-delete older ones.
 */
function dante_changeset_prune() {
    $old = get_posts( array(
        'post_type'      => 'dante_change',
        'post_status'    => 'draft',
        'posts_per_page' => -1,
        'offset'         => DANTE_ASSISTANT_UNDO_KEEP,
        'meta_key'       => '_applied_at',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
        'meta_query'     => array(
            array( 'key' => '_status', 'value' => 'applied' ),
        ),
        'fields'         => 'ids',
    ) );
    foreach ( $old as $id ) {
        wp_delete_post( $id, true );
    }
}
