<?php
/**
 * Dante Assistant — photo tool.
 *
 * Adds a picture to the Photo gallery (the dante_photo CPT in inc/photos.php).
 * The person attaches a photo in the chat (the "Add a photo" button uploads it
 * to the media library and stashes its id in $GLOBALS['dante_assistant_pending_image']);
 * this tool turns that attachment into a published Photo so it appears in the
 * Photo Collage block. It's logged as an applied change, so it's one-click
 * undoable from "Recent changes".
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tool schema for add_photo (merged into the registry by tools.php).
 */
function dante_assistant_photo_tool() {
    return array(
        'name'        => 'add_photo',
        'description' => 'Add the photo the person attached in the chat to the website\'s Photo gallery. The person must first attach a picture using the "Add a photo" button; this tool then adds that picture to the gallery so it shows in the photo collage. Optionally include a short caption. If no photo is attached, ask them to attach one first.',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'caption' => array( 'type' => 'string', 'description' => 'An optional short caption for the photo.' ),
            ),
        ),
    );
}

/**
 * Tool: create a published Photo from the attached image.
 */
function dante_tool_add_photo( $args ) {
    if ( ! current_user_can( 'upload_files' ) ) {
        return array( 'error' => 'You do not have permission to add photos.' );
    }

    $att = isset( $GLOBALS['dante_assistant_pending_image'] ) ? (int) $GLOBALS['dante_assistant_pending_image'] : 0;
    if ( $att <= 0 ) {
        return array( 'error' => 'No photo is attached yet. Ask the person to attach a picture with the "Add a photo" button, then add it to the gallery.' );
    }
    $GLOBALS['dante_assistant_pending_image'] = 0; // consume once.

    $att_post = get_post( $att );
    if ( ! $att_post || 'attachment' !== $att_post->post_type ) {
        return array( 'error' => 'That photo could not be found.' );
    }

    $caption = isset( $args['caption'] ) ? sanitize_text_field( $args['caption'] ) : '';
    if ( '' === $caption ) {
        $caption = get_the_title( $att );
    }

    $photo_id = wp_insert_post( array(
        'post_type'   => 'dante_photo',
        'post_status' => 'publish',
        'post_title'  => $caption,
    ), true );

    if ( is_wp_error( $photo_id ) ) {
        return array( 'error' => $photo_id->get_error_message() );
    }

    set_post_thumbnail( $photo_id, $att );

    // Photos appear immediately in the collage; log it so it's undoable.
    dante_changeset_log_applied( array(
        'type'    => 'create',
        'post_id' => $photo_id,
        'label'   => $caption ? sprintf( 'Added photo "%s" to the gallery', $caption ) : 'Added a photo to the gallery',
    ) );

    return array(
        'ok'       => true,
        'photo_id' => $photo_id,
        'summary'  => 'Photo added to the gallery.',
    );
}
