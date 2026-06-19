<?php
/**
 * Index Template
 */
get_header(); ?>

<main class="main-content">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <div class="content-card">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="event-card-image">
                        <?php the_post_thumbnail( 'large' ); ?>
                    </div>
                <?php endif; ?>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div class="entry-meta">
                    <span class="event-date"><?php echo get_the_date( 'F j, Y' ); ?></span>
                </div>
                <div class="entry-content">
                    <?php the_excerpt(); ?>
                </div>
                <a href="<?php the_permalink(); ?>" class="btn btn-outline">Read More</a>
            </div>
        <?php endwhile; ?>
        <?php the_posts_navigation(); ?>
    <?php else : ?>
        <div class="content-card">
            <h2><?php _e( 'No posts found', 'dante-society' ); ?></h2>
            <p><?php _e( 'Check back soon for upcoming events and programs.', 'dante-society' ); ?></p>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
