<?php
/**
 * One-time seeder: creates the starter Events that match the current Events page
 * (with their photos pulled from /images/ into the Media Library) so they don't
 * have to be re-entered by hand.
 *
 * Runs once per site (guarded by the 'dante_events_seeded' option). Safe to
 * delete this file and its require in functions.php once it has run.
 *
 * Month-only dates from the old page were given a representative day — adjust
 * them under Events → (edit) if needed.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seed the starter events.
 */
function dante_seed_events() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( get_option( 'dante_events_seeded' ) ) {
        return;
    }

    // Don't seed if events already exist (avoid duplicates).
    $existing = get_posts( array(
        'post_type'      => 'event',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'post_status'    => 'any',
    ) );
    if ( ! empty( $existing ) ) {
        update_option( 'dante_events_seeded', 1 );
        return;
    }

    $events = array(
        array(
            'title'    => 'Film: Valentino: The Last Emperor',
            'date'     => '2026-05-19',
            'time'     => '4:00 PM',
            'location' => 'Westminster Canterbury, 3rd Floor Commons',
            'image'    => 'valentino-the-last-emperor.jpg',
            'content'  => "Join the Dante Society for a screening of Valentino: The Last Emperor at Westminster Canterbury, 3rd Floor Commons. This look into the lavish times of the iconic designer is made with humor and an eye for detail, documenting the dramatic closing of the last true couturier's career and work.\n\nDinner: Following the film at Isabella's, 6:00 PM. Reservations: Pat Bradbury, (910) 695-5644.",
        ),
        array(
            'title'    => "Milano's Italian Dinner",
            'date'     => '2026-01-15',
            'time'     => '',
            'location' => "Milano's Restaurant",
            'image'    => 'dantemilanosdinner012026.jpg',
            'content'  => "Members and guests gathered for a wonderful evening of Italian cuisine at Milano's. A delightful start to our 2026 programming season with good food, great company, and lively conversation.",
        ),
        array(
            'title'    => 'Carnevale Celebration 2026',
            'date'     => '2026-02-15',
            'time'     => '',
            'location' => '',
            'image'    => 'dantecarnevale2026.jpg',
            'content'  => 'An evening of masks, music, and merriment celebrating the Italian Carnevale tradition. Members enjoyed traditional Carnevale treats and festive entertainment.',
        ),
        array(
            'title'    => 'Dante in Art: A Lecture by Jerry Carney',
            'date'     => '2026-04-15',
            'time'     => '',
            'location' => '',
            'image'    => 'dantevandv-jerryhits-danteinart-apr-2026.jpg',
            'content'  => "A fascinating exploration of Dante Alighieri's influence on visual art through the centuries — from Renaissance illustrations of The Divine Comedy to modern interpretations.",
        ),
        array(
            'title'    => 'Youth & Arts Program',
            'date'     => '2026-09-01',
            'time'     => '',
            'location' => '',
            'image'    => 'tyap-flyer.png',
            'content'  => 'Our Youth & Arts Program (TYAP) continues to promote Italian cultural appreciation among younger members of our community. Details of upcoming youth-oriented events will be announced soon. (Placeholder date — please update.)',
        ),
    );

    foreach ( $events as $e ) {
        $post_id = wp_insert_post( array(
            'post_type'    => 'event',
            'post_status'  => 'publish',
            'post_title'   => $e['title'],
            'post_content' => $e['content'],
        ) );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            continue;
        }

        update_post_meta( $post_id, '_event_date', $e['date'] );
        update_post_meta( $post_id, '_event_time', $e['time'] );
        update_post_meta( $post_id, '_event_location', $e['location'] );

        dante_seed_attach_image( $post_id, $e['image'] );
    }

    update_option( 'dante_events_seeded', 1 );
}
add_action( 'admin_init', 'dante_seed_events' );

/**
 * Copy an image from the site's /images/ folder into the Media Library and set
 * it as the event's featured image. Uses the local file (no network needed).
 */
function dante_seed_attach_image( $post_id, $filename ) {
    $path = trailingslashit( ABSPATH ) . 'images/' . $filename;
    if ( ! file_exists( $path ) ) {
        return;
    }

    $data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
    if ( false === $data ) {
        return;
    }

    $upload = wp_upload_bits( $filename, null, $data );
    if ( ! empty( $upload['error'] ) ) {
        return;
    }

    $filetype = wp_check_filetype( $upload['file'] );

    $attach_id = wp_insert_attachment( array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ), $upload['file'], $post_id );

    if ( is_wp_error( $attach_id ) || ! $attach_id ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $meta );

    set_post_thumbnail( $post_id, $attach_id );
}
