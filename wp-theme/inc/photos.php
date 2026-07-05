<?php
/**
 * Photos: a simple custom post type so the board can add pictures from wp-admin
 * (or, later, via the assistant) and show them as a responsive collage.
 *
 * Mirrors the Events pattern: a CPT + a server-rendered block (no build step).
 * Each "Photo" is one post whose Featured Image is the picture and whose title
 * is an optional caption.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the "Photo" post type.
 */
function dante_register_photo_cpt() {
    $labels = array(
        'name'               => __( 'Photos', 'dante-society' ),
        'singular_name'      => __( 'Photo', 'dante-society' ),
        'add_new'            => __( 'Add Photo', 'dante-society' ),
        'add_new_item'       => __( 'Add New Photo', 'dante-society' ),
        'edit_item'          => __( 'Edit Photo', 'dante-society' ),
        'new_item'           => __( 'New Photo', 'dante-society' ),
        'view_item'          => __( 'View Photo', 'dante-society' ),
        'search_items'       => __( 'Search Photos', 'dante-society' ),
        'not_found'          => __( 'No photos yet', 'dante-society' ),
        'not_found_in_trash' => __( 'No photos in Trash', 'dante-society' ),
        'menu_name'          => __( 'Photos', 'dante-society' ),
    );

    register_post_type( 'dante_photo', array(
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'show_in_rest'  => false, // simple classic screen: title (caption) + featured image
        'has_archive'   => false,
        'rewrite'       => false,
        'menu_icon'     => 'dashicons-format-gallery',
        'menu_position' => 7,
        'supports'      => array( 'title', 'thumbnail' ),
    ) );
}
add_action( 'init', 'dante_register_photo_cpt' );

/**
 * Add a hint BELOW the Featured Image control on the Photo screen. (Append to
 * $content — it already holds the "Set featured image" link, so don't replace
 * it.) Clicking that link opens the media library, where you can drag-and-drop
 * upload or pick an existing image.
 */
function dante_photo_featured_image_text( $content, $post_id ) {
    if ( 'dante_photo' === get_post_type( $post_id ) ) {
        $content .= '<p class="description" style="margin-top:8px;">'
            . esc_html__( 'Click "Set featured image" to upload a picture (drag-and-drop) or choose one from the Media Library. The title above is an optional caption.', 'dante-society' )
            . '</p>';
    }
    return $content;
}
add_filter( 'admin_post_thumbnail_html', 'dante_photo_featured_image_text', 10, 2 );

/**
 * Show the picture as a thumbnail column in the Photos list.
 */
function dante_photo_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        if ( 'title' === $key ) {
            $new['photo_thumb'] = __( 'Picture', 'dante-society' );
        }
        $new[ $key ] = $label;
    }
    return $new;
}
add_filter( 'manage_dante_photo_posts_columns', 'dante_photo_columns' );

function dante_photo_columns_content( $column, $post_id ) {
    if ( 'photo_thumb' === $column ) {
        if ( has_post_thumbnail( $post_id ) ) {
            echo get_the_post_thumbnail( $post_id, array( 80, 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
        } else {
            echo '&mdash;';
        }
    }
}
add_action( 'manage_dante_photo_posts_custom_column', 'dante_photo_columns_content', 10, 2 );

/* ===========================================================================
 * Bulk add — pick many images at once and make a Photo from each.
 * ======================================================================== */

/**
 * "Bulk Add" submenu under Photos.
 */
function dante_photo_bulk_menu() {
    $GLOBALS['dante_bulk_hook'] = add_submenu_page(
        'edit.php?post_type=dante_photo',
        __( 'Bulk Add Photos', 'dante-society' ),
        __( 'Bulk Add', 'dante-society' ),
        'upload_files',
        'dante-bulk-photos',
        'dante_photo_bulk_page'
    );
}
add_action( 'admin_menu', 'dante_photo_bulk_menu' );

/**
 * The Bulk Add screen.
 */
function dante_photo_bulk_page() {
    if ( ! current_user_can( 'upload_files' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bulk Add Photos', 'dante-society' ); ?></h1>
        <p><?php esc_html_e( 'Add many pictures at once. Click the button, then drag-and-drop new images or pick existing ones from the Media Library — a Photo is created for each.', 'dante-society' ); ?></p>
        <p>
            <button type="button" class="button button-primary button-hero" id="dante-bulk-select">
                <?php esc_html_e( 'Select pictures', 'dante-society' ); ?>
            </button>
        </p>
        <div id="dante-bulk-result" style="margin-top:16px;max-width:640px;"></div>
        <p>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dante_photo' ) ); ?>"><?php esc_html_e( '&larr; Back to all photos', 'dante-society' ); ?></a>
        </p>
    </div>
    <?php
}

/**
 * Load the media library + our picker script on the Bulk Add screen only.
 */
function dante_photo_bulk_assets( $hook ) {
    if ( empty( $GLOBALS['dante_bulk_hook'] ) || $hook !== $GLOBALS['dante_bulk_hook'] ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script( 'jquery' );

    $nonce = wp_create_nonce( 'dante_bulk_photos' );
    $js    = "jQuery(function($){
        var frame;
        var addingMsg = " . wp_json_encode( __( 'Adding photos…', 'dante-society' ) ) . ";
        var errMsg = " . wp_json_encode( __( 'Sorry, something went wrong.', 'dante-society' ) ) . ";
        $('#dante-bulk-select').on('click', function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title: " . wp_json_encode( __( 'Select pictures', 'dante-society' ) ) . ", button: { text: " . wp_json_encode( __( 'Add these photos', 'dante-society' ) ) . " }, multiple: true, library: { type: 'image' } });
            frame.on('select', function(){
                var ids = frame.state().get('selection').map(function(a){ return a.id; });
                if (!ids.length) { return; }
                $('#dante-bulk-select').prop('disabled', true);
                $('#dante-bulk-result').html('<p>' + addingMsg + '</p>');
                $.post(ajaxurl, { action: 'dante_bulk_add_photos', ids: ids, _nonce: '" . esc_js( $nonce ) . "' }, function(res){
                    $('#dante-bulk-select').prop('disabled', false);
                    if (res && res.success) {
                        $('#dante-bulk-result').html('<div class=\"notice notice-success\"><p>' + res.data.message + '</p></div>');
                    } else {
                        $('#dante-bulk-result').html('<div class=\"notice notice-error\"><p>' + ((res && res.data && res.data.message) || errMsg) + '</p></div>');
                    }
                }).fail(function(){
                    $('#dante-bulk-select').prop('disabled', false);
                    $('#dante-bulk-result').html('<div class=\"notice notice-error\"><p>' + errMsg + '</p></div>');
                });
            });
            frame.open();
        });
    });";

    wp_add_inline_script( 'jquery', $js );
}
add_action( 'admin_enqueue_scripts', 'dante_photo_bulk_assets' );

/**
 * AJAX: create a Photo from each selected attachment.
 */
function dante_photo_bulk_add_ajax() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to add photos.', 'dante-society' ) ) );
    }
    check_ajax_referer( 'dante_bulk_photos', '_nonce' );

    $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'message' => __( 'No pictures were selected.', 'dante-society' ) ) );
    }

    $created = 0;
    $skipped = 0;

    foreach ( $ids as $att_id ) {
        if ( 'attachment' !== get_post_type( $att_id ) || 0 !== strpos( (string) get_post_mime_type( $att_id ), 'image/' ) ) {
            $skipped++;
            continue;
        }

        // Skip if a Photo already uses this image.
        $existing = get_posts( array(
            'post_type'      => 'dante_photo',
            'posts_per_page' => 1,
            'meta_key'       => '_thumbnail_id',
            'meta_value'     => $att_id,
            'fields'         => 'ids',
        ) );
        if ( ! empty( $existing ) ) {
            $skipped++;
            continue;
        }

        $title = get_the_title( $att_id );
        $pid   = wp_insert_post( array(
            'post_type'   => 'dante_photo',
            'post_status' => 'publish',
            'post_title'  => $title ? $title : '',
        ), true );
        if ( is_wp_error( $pid ) ) {
            $skipped++;
            continue;
        }
        set_post_thumbnail( $pid, $att_id );
        $created++;
    }

    $message = sprintf(
        /* translators: %d: number of photos added. */
        _n( 'Added %d photo.', 'Added %d photos.', $created, 'dante-society' ),
        $created
    );
    if ( $skipped ) {
        $message .= ' ' . sprintf(
            /* translators: %d: number skipped. */
            _n( 'Skipped %d (already added or not an image).', 'Skipped %d (already added or not images).', $skipped, 'dante-society' ),
            $skipped
        );
    }

    wp_send_json_success( array( 'message' => $message, 'created' => $created, 'skipped' => $skipped ) );
}
add_action( 'wp_ajax_dante_bulk_add_photos', 'dante_photo_bulk_add_ajax' );

/**
 * Front-end markup for the photo collage (masonry that flows into columns).
 *
 * @param array $attributes Block attributes (size).
 * @return string
 */
function dante_render_photos_block( $attributes = array() ) {
    $size = isset( $attributes['size'] ) ? $attributes['size'] : 'medium';
    if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
        $size = 'medium';
    }

    $q = new WP_Query( array(
        'post_type'      => 'dante_photo',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( ! $q->have_posts() ) {
        return '<div class="content-card"><p>' .
            esc_html__( 'No photos yet. Add some under Photos → Add Photo in the dashboard.', 'dante-society' ) .
            '</p></div>';
    }

    ob_start();
    echo '<div class="dante-photos dante-photos--' . esc_attr( $size ) . '">';

    foreach ( $q->posts as $photo ) {
        $thumb_id = get_post_thumbnail_id( $photo->ID );
        if ( ! $thumb_id ) {
            continue;
        }
        $caption = get_the_title( $photo );
        $img     = wp_get_attachment_image( $thumb_id, 'large', false, array(
            'loading' => 'lazy',
            'alt'     => $caption ? $caption : '',
        ) );
        $full = wp_get_attachment_image_url( $thumb_id, 'full' );

        echo '<figure class="dante-photo">';
        if ( $full ) {
            echo '<a href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
        } else {
            echo $img; // phpcs:ignore WordPress.Security.EscapeOutput
        }
        if ( $caption && '' !== trim( $caption ) ) {
            echo '<figcaption>' . esc_html( $caption ) . '</figcaption>';
        }
        echo '</figure>';
    }

    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
}

/**
 * Register the "Photo Collage" block (server-rendered, no build step).
 */
function dante_register_photos_block() {
    wp_register_script(
        'dante-photos-block',
        get_template_directory_uri() . '/js/photos-block.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
        dante_ver( 'js/photos-block.js' ),
        true
    );

    register_block_type( 'dante/photos', array(
        'api_version'     => 2,
        'title'           => __( 'Photo Collage', 'dante-society' ),
        'description'     => __( 'A collage of all your Photos (add them under the Photos menu).', 'dante-society' ),
        'category'        => 'widgets',
        'icon'            => 'format-gallery',
        'render_callback' => 'dante_render_photos_block',
        'editor_script'   => 'dante-photos-block',
        'supports'        => array( 'html' => false ),
        'attributes'      => array(
            'size' => array( 'type' => 'string', 'default' => 'medium' ),
        ),
    ) );
}
add_action( 'init', 'dante_register_photos_block' );
