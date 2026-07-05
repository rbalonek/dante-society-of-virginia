<?php
/**
 * Template Name: Photos Page
 *
 * A gallery page on a solid dark-green background: a centered "GALLERY / <title>
 * / rule / intro" header, then the photo collage. Photos are managed under the
 * Photos menu (or via the assistant); this template shows them all — the page's
 * own content is used as the short intro line.
 *
 * NOTE: this file is deliberately NOT named page-photos.php — WordPress would
 * auto-apply a page-{slug}.php file to the "photos"-slug page regardless of the
 * selected template. As template-photos.php it only applies when chosen.
 *
 * @package Dante_Society
 */

get_header();

// The page's content becomes the short intro line under the title.
$intro = '';
while ( have_posts() ) {
    the_post();
    $intro = trim( wp_strip_all_tags( get_the_content() ) );
}
?>

<header class="photos-header">
    <p class="photos-eyebrow"><?php esc_html_e( 'Gallery', 'dante-society' ); ?></p>
    <h1 class="photos-title"><?php echo esc_html( get_the_title() ); ?></h1>
    <div class="photos-sep"></div>
    <?php if ( '' !== $intro ) : ?>
        <p class="photos-intro"><?php echo esc_html( $intro ); ?></p>
    <?php endif; ?>
</header>

<main class="photos-main">
    <?php echo dante_render_photos_block( array( 'size' => 'large' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped within. ?>
</main>

<?php get_footer(); ?>
