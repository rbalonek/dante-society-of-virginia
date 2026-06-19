<?php
/**
 * Default Page Template
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <h2><?php the_title(); ?></h2>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
