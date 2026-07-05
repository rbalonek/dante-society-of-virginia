<?php
/**
 * Template Name: Home Page
 *
 * A full-bleed canvas for the front page: the header is overlaid (transparent)
 * and the page content runs edge-to-edge, so a "Full Screen Hero" block fills
 * the viewport with the logo + nav floating on top.
 *
 * Add a Full Screen Hero block to the page to configure the title, line, text,
 * button, colours, and button link. If the page has no hero block yet, the
 * default hero is shown so the homepage is never empty. The background image is
 * set in Customize → Background Images → "Homepage hero background".
 *
 * NOTE: this file is deliberately NOT named page-home.php — WordPress would
 * auto-apply a page-{slug}.php file to the "home"-slug page regardless of the
 * selected template. As template-home.php it only applies when chosen.
 *
 * @package Dante_Society
 */

get_header();
?>

<main class="main-content main-content--flush">
    <?php
    while ( have_posts() ) :
        the_post();

        if ( has_block( 'dante/hero', get_post() ) ) {
            the_content();
        } else {
            // No hero block yet — show the default splash so it's never blank.
            echo dante_render_hero_block(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped within.
        }
    endwhile;
    ?>
</main>

<?php get_footer(); ?>
