<?php
/**
 * Template Name: Events Page
 * Description: Displays events in a calendar-style layout with event cards.
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <h2><?php the_title(); ?></h2>
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>

    <?php
    // Query events (posts in 'events' category or all posts)
    $events_query = new WP_Query( array(
        'post_type'      => 'post',
        'posts_per_page' => 20,
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ) );

    if ( $events_query->have_posts() ) : ?>
        <div class="content-card">
            <h2>Upcoming Events</h2>
            <?php while ( $events_query->have_posts() ) : $events_query->the_post(); ?>
                <div class="event-card">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="event-card-image">
                            <?php the_post_thumbnail( 'medium' ); ?>
                        </div>
                    <?php endif; ?>
                    <div class="event-card-content">
                        <div class="event-date"><?php echo get_the_date( 'F j, Y' ); ?></div>
                        <h3><?php the_title(); ?></h3>
                        <?php the_excerpt(); ?>
                        <a href="<?php the_permalink(); ?>" style="color: var(--gold); font-weight: 600;">Read More &raquo;</a>
                    </div>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
