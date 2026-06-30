<?php
/**
 * Template Name: Membership Page
 * Description: Renders the editable Membership page content (dues, benefits,
 *              and payment options). All copy lives in the page itself so the
 *              Society can edit it in the WordPress editor — this template no
 *              longer hardcodes (and duplicates) those sections.
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <h2><?php the_title(); ?></h2>
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
