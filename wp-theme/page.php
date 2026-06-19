<?php
/**
 * Default Page Template
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
