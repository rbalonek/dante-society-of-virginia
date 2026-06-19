<?php
/**
 * Single Post Template
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <div class="event-date"><?php echo get_the_date( 'F j, Y' ); ?></div>
            <h2><?php the_title(); ?></h2>
            <?php if ( has_post_thumbnail() ) : ?>
                <div style="margin: 20px 0;">
                    <?php the_post_thumbnail( 'large', array( 'style' => 'width:100%;max-width:600px;border-radius:8px;' ) ); ?>
                </div>
            <?php endif; ?>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
            <?php
            // Previous/next post navigation
            the_post_navigation( array(
                'prev_text' => '&laquo; %title',
                'next_text' => '%title &raquo;',
            ) );
            ?>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
