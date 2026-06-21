<?php
/**
 * Events: a simple custom post type so the board can add events from a form
 * (Title, Date, Time, Location, Description, Image) and have them render
 * automatically on the Events page and in the calendar.
 *
 * No third-party plugins — uses a native meta box + the featured image.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the "Event" post type.
 */
function dante_register_event_cpt() {
    $labels = array(
        'name'               => __( 'Events', 'dante-society' ),
        'singular_name'      => __( 'Event', 'dante-society' ),
        'add_new'            => __( 'Add Event', 'dante-society' ),
        'add_new_item'       => __( 'Add New Event', 'dante-society' ),
        'edit_item'          => __( 'Edit Event', 'dante-society' ),
        'new_item'           => __( 'New Event', 'dante-society' ),
        'view_item'          => __( 'View Event', 'dante-society' ),
        'search_items'       => __( 'Search Events', 'dante-society' ),
        'not_found'          => __( 'No events found', 'dante-society' ),
        'not_found_in_trash' => __( 'No events found in Trash', 'dante-society' ),
        'menu_name'          => __( 'Events', 'dante-society' ),
    );

    register_post_type( 'event', array(
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => false,
        'show_in_rest'  => true, // block editor for the description
        'menu_icon'     => 'dashicons-calendar-alt',
        'menu_position' => 5,
        'supports'      => array( 'title', 'editor', 'thumbnail' ),
        'rewrite'       => array( 'slug' => 'event' ),
    ) );
}
add_action( 'init', 'dante_register_event_cpt' );

/**
 * Event details meta box (Date / Time / Location).
 */
function dante_event_meta_box() {
    add_meta_box(
        'dante_event_details',
        __( 'Event Details', 'dante-society' ),
        'dante_event_meta_box_html',
        'event',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'dante_event_meta_box' );

/**
 * Render the meta box.
 */
function dante_event_meta_box_html( $post ) {
    wp_nonce_field( 'dante_save_event', 'dante_event_nonce' );

    $date     = get_post_meta( $post->ID, '_event_date', true );
    $time     = get_post_meta( $post->ID, '_event_time', true );
    $location = get_post_meta( $post->ID, '_event_location', true );
    ?>
    <p>
        <label for="dante_event_date" style="display:block;font-weight:600;margin-bottom:4px;">
            <?php esc_html_e( 'Date', 'dante-society' ); ?>
        </label>
        <input type="date" id="dante_event_date" name="dante_event_date"
               value="<?php echo esc_attr( $date ); ?>" style="width:100%;" />
    </p>
    <p>
        <label for="dante_event_time" style="display:block;font-weight:600;margin-bottom:4px;">
            <?php esc_html_e( 'Time', 'dante-society' ); ?>
        </label>
        <input type="text" id="dante_event_time" name="dante_event_time"
               value="<?php echo esc_attr( $time ); ?>"
               placeholder="<?php esc_attr_e( 'e.g. 4:00 PM', 'dante-society' ); ?>"
               style="width:100%;" />
    </p>
    <p>
        <label for="dante_event_location" style="display:block;font-weight:600;margin-bottom:4px;">
            <?php esc_html_e( 'Location', 'dante-society' ); ?>
        </label>
        <input type="text" id="dante_event_location" name="dante_event_location"
               value="<?php echo esc_attr( $location ); ?>"
               placeholder="<?php esc_attr_e( 'e.g. Westminster Canterbury', 'dante-society' ); ?>"
               style="width:100%;" />
    </p>
    <p style="color:#666;font-size:12px;">
        <?php esc_html_e( 'Add the description in the main editor and the photo with "Set featured image".', 'dante-society' ); ?>
    </p>
    <?php
}

/**
 * Save the meta box fields.
 */
function dante_save_event_meta( $post_id ) {
    if ( ! isset( $_POST['dante_event_nonce'] ) ||
         ! wp_verify_nonce( $_POST['dante_event_nonce'], 'dante_save_event' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['dante_event_date'] ) ) {
        update_post_meta( $post_id, '_event_date', sanitize_text_field( wp_unslash( $_POST['dante_event_date'] ) ) );
    }
    if ( isset( $_POST['dante_event_time'] ) ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( wp_unslash( $_POST['dante_event_time'] ) ) );
    }
    if ( isset( $_POST['dante_event_location'] ) ) {
        update_post_meta( $post_id, '_event_location', sanitize_text_field( wp_unslash( $_POST['dante_event_location'] ) ) );
    }
}
add_action( 'save_post_event', 'dante_save_event_meta' );

/**
 * Show the date as a sortable column in the admin Events list.
 */
function dante_event_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['event_date'] = __( 'Event Date', 'dante-society' );
        }
    }
    return $new;
}
add_filter( 'manage_event_posts_columns', 'dante_event_columns' );

function dante_event_columns_content( $column, $post_id ) {
    if ( 'event_date' === $column ) {
        $date = get_post_meta( $post_id, '_event_date', true );
        echo $date ? esc_html( date_i18n( 'M j, Y', strtotime( $date ) ) ) : '&mdash;';
    }
}
add_action( 'manage_event_posts_custom_column', 'dante_event_columns_content', 10, 2 );

/**
 * Query helper: upcoming events (today onward), soonest first.
 * Falls back to all events if none are upcoming.
 *
 * @return WP_Query
 */
function dante_get_upcoming_events( $limit = -1 ) {
    $args = array(
        'post_type'      => 'event',
        'posts_per_page' => $limit,
        'meta_key'       => '_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => '_event_date',
                'value'   => current_time( 'Y-m-d' ),
                'compare' => '>=',
                'type'    => 'DATE',
            ),
        ),
    );

    $query = new WP_Query( $args );

    // If nothing upcoming, show all events (most recent first) so the page
    // is never empty.
    if ( ! $query->have_posts() ) {
        $query = new WP_Query( array(
            'post_type'      => 'event',
            'posts_per_page' => $limit,
            'meta_key'       => '_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ) );
    }

    return $query;
}

/**
 * Build the events array consumed by FullCalendar (and the popup).
 *
 * @return array
 */
function dante_get_calendar_events() {
    $events = array();

    $query = new WP_Query( array(
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => '_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ) );

    foreach ( $query->posts as $post ) {
        $date = get_post_meta( $post->ID, '_event_date', true );
        if ( ! $date ) {
            continue;
        }

        $thumb = get_the_post_thumbnail_url( $post->ID, 'medium' );

        // The calendar renders these as plain text, so decode HTML entities
        // (e.g. "&#038;" → "&") that WordPress stores in titles/content.
        $events[] = array(
            'id'            => (string) $post->ID,
            'title'         => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
            'start'         => $date,
            'allDay'        => true,
            'extendedProps' => array(
                'time'        => get_post_meta( $post->ID, '_event_time', true ),
                'location'    => get_post_meta( $post->ID, '_event_location', true ),
                'image'       => $thumb ? $thumb : '',
                'description' => html_entity_decode( wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 ), ENT_QUOTES, 'UTF-8' ),
            ),
        );
    }

    wp_reset_postdata();

    return $events;
}

/**
 * Enqueue the calendar library, init script, and event data.
 * Shared by the Events block and the (optional) Events page template.
 */
function dante_enqueue_calendar_assets() {
    wp_enqueue_script(
        'fullcalendar',
        get_template_directory_uri() . '/js/lib/fullcalendar.min.js',
        array(),
        '6.1.15',
        true
    );
    wp_enqueue_script(
        'dante-calendar',
        get_template_directory_uri() . '/js/calendar.js',
        array( 'fullcalendar' ),
        DANTE_THEME_VERSION,
        true
    );

    $events = dante_get_calendar_events();
    wp_localize_script( 'dante-calendar', 'danteEvents', $events );

    // Open the calendar on the next upcoming event (or the most recent one if
    // none are upcoming), since the current month often has no events.
    $initial_date = '';
    $next         = dante_get_upcoming_events( 1 );
    if ( $next->have_posts() ) {
        $initial_date = get_post_meta( $next->posts[0]->ID, '_event_date', true );
    }
    wp_reset_postdata();

    wp_localize_script( 'dante-calendar', 'danteCalendarConfig', array(
        'initialDate' => $initial_date,
    ) );
}

/**
 * The Events markup: calendar + the styled event list. Shared by the block and
 * the page template. In the editor preview ($preview = true) the interactive
 * calendar is replaced by a placeholder, since its JavaScript can't run there.
 *
 * @param bool $preview Whether this is the editor (server-side render) preview.
 * @return string
 */
function dante_events_markup( $preview = false, $click_behavior = 'scroll', $display = 'both', $scope = 'all', $list_style = 'cards' ) {
    $show_calendar = ( 'list' !== $display );
    $show_list     = ( 'calendar' !== $display );

    $hint = ( 'popup' === $click_behavior )
        ? 'Click any event in the calendar to see its details.'
        : 'Click any event in the calendar to jump to it below.';

    ob_start();

    if ( $show_calendar ) :
        ?>
        <div class="content-card">
            <h2>Events Calendar</h2>
            <?php if ( $preview ) : ?>
                <p class="events-calendar-hint">📅 The interactive calendar appears here on the live page.</p>
            <?php else : ?>
                <p class="events-calendar-hint"><?php echo esc_html( $hint ); ?></p>
                <div id="dante-calendar" data-click="<?php echo esc_attr( $click_behavior ); ?>"></div>
            <?php endif; ?>
        </div>
        <?php
    endif;

    if ( $show_list ) :
        $events = dante_event_list_query( $scope );
        ?>
        <div class="content-card">
            <h2><?php echo esc_html( dante_event_list_heading( $scope ) ); ?></h2>
            <?php
            if ( $events->have_posts() ) :
                while ( $events->have_posts() ) :
                    $events->the_post();

                    $date     = get_post_meta( get_the_ID(), '_event_date', true );
                    $time     = get_post_meta( get_the_ID(), '_event_time', true );
                    $location = get_post_meta( get_the_ID(), '_event_location', true );

                    $meta_bits = array();
                    if ( $date ) {
                        $meta_bits[] = date_i18n( 'l, F j, Y', strtotime( $date ) );
                    }
                    if ( $time ) {
                        $meta_bits[] = $time;
                    }
                    ?>
                    <?php if ( 'simple' === $list_style ) : ?>
                    <div class="program-item" id="event-<?php echo esc_attr( get_the_ID() ); ?>">
                        <?php if ( $meta_bits ) : ?>
                            <div class="program-date"><?php echo esc_html( implode( '  ·  ', $meta_bits ) ); ?></div>
                        <?php endif; ?>
                        <h3><?php the_title(); ?></h3>
                        <?php if ( $location ) : ?>
                            <p class="event-listing-location"><strong>Location:</strong> <?php echo esc_html( $location ); ?></p>
                        <?php endif; ?>
                        <div class="event-listing-desc"><?php the_content(); ?></div>
                    </div>
                    <?php else : ?>
                    <article class="event-listing" id="event-<?php echo esc_attr( get_the_ID() ); ?>">
                        <div class="event-listing-text">
                            <h3 class="event-listing-title"><?php the_title(); ?></h3>
                            <?php if ( $meta_bits ) : ?>
                                <p class="event-listing-date"><?php echo esc_html( implode( '  ·  ', $meta_bits ) ); ?></p>
                            <?php endif; ?>
                            <?php if ( $location ) : ?>
                                <p class="event-listing-location"><strong>Location:</strong> <?php echo esc_html( $location ); ?></p>
                            <?php endif; ?>
                            <div class="event-listing-desc"><?php the_content(); ?></div>
                        </div>
                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="event-listing-image"><?php the_post_thumbnail( 'medium' ); ?></div>
                        <?php endif; ?>
                    </article>
                    <?php endif; ?>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                ?>
                <p>No events to show yet. Add one under <strong>Events &rarr; Add Event</strong> in the dashboard.</p>
                <?php
            endif;
            ?>
        </div>
        <?php
    endif;

    return ob_get_clean();
}

/**
 * Build the event-list query for a given scope: all | year | upcoming.
 */
function dante_event_list_query( $scope = 'all' ) {
    $args = array(
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => '_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    );

    if ( 'year' === $scope ) {
        $year          = current_time( 'Y' );
        $args['order'] = 'ASC';
        $args['meta_query'] = array(
            array(
                'key'     => '_event_date',
                'value'   => array( $year . '-01-01', $year . '-12-31' ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        );
    } elseif ( 'upcoming' === $scope ) {
        $args['order'] = 'ASC';
        $args['meta_query'] = array(
            array(
                'key'     => '_event_date',
                'value'   => current_time( 'Y-m-d' ),
                'compare' => '>=',
                'type'    => 'DATE',
            ),
        );
    }

    return new WP_Query( $args );
}

/**
 * Heading for the event list, based on scope.
 */
function dante_event_list_heading( $scope = 'all' ) {
    if ( 'year' === $scope ) {
        return sprintf( "This Year's Events (%s)", current_time( 'Y' ) );
    }
    if ( 'upcoming' === $scope ) {
        return 'Upcoming Events';
    }
    return 'Events';
}

/**
 * Render callback for the "Events" block.
 */
function dante_render_events_block( $attributes, $content ) {
    $preview = defined( 'REST_REQUEST' ) && REST_REQUEST;
    $click   = isset( $attributes['clickBehavior'] ) ? $attributes['clickBehavior'] : 'scroll';
    $display = isset( $attributes['display'] ) ? $attributes['display'] : 'both';
    $scope   = isset( $attributes['scope'] ) ? $attributes['scope'] : 'all';
    $style   = isset( $attributes['listStyle'] ) ? $attributes['listStyle'] : 'cards';

    // Only load the calendar library when a calendar is actually shown.
    if ( ! $preview && 'list' !== $display ) {
        dante_enqueue_calendar_assets();
    }

    return dante_events_markup( $preview, $click, $display, $scope, $style );
}

/**
 * Register the "Events" block (server-side rendered, no build step).
 */
function dante_register_events_block() {
    wp_register_script(
        'dante-events-block',
        get_template_directory_uri() . '/js/events-block.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
        DANTE_THEME_VERSION,
        true
    );

    register_block_type( 'dante/events', array(
        'api_version'     => 2,
        'title'           => __( 'Events (calendar + list)', 'dante-society' ),
        'category'        => 'widgets',
        'icon'            => 'calendar-alt',
        'render_callback' => 'dante_render_events_block',
        'editor_script'   => 'dante-events-block',
        'supports'        => array( 'html' => false ),
        'attributes'      => array(
            'clickBehavior' => array(
                'type'    => 'string',
                'default' => 'scroll',
            ),
            'display'       => array(
                'type'    => 'string',
                'default' => 'both',
            ),
            'scope'         => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'listStyle'     => array(
                'type'    => 'string',
                'default' => 'cards',
            ),
        ),
    ) );
}
add_action( 'init', 'dante_register_events_block' );
