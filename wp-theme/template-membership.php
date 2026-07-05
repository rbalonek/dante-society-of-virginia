<?php
/**
 * Template Name: Membership Page
 * Description: Renders the editable Membership page content (dues, benefits,
 *              and payment options). All copy lives in the page itself so the
 *              Society can edit it in the WordPress editor — this template no
 *              longer hardcodes (and duplicates) those sections.
 *
 * NOTE: this file is deliberately NOT named page-membership.php — WordPress
 * would auto-apply a page-{slug}.php file to the "membership"-slug page
 * regardless of the selected template. As template-membership.php it only
 * applies when explicitly chosen; the Membership page renders fine on the
 * Default template since all its copy is in blocks.
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
