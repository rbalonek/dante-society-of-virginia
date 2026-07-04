<?php
/**
 * Template Name: Home Page
 *
 * A deliberately minimal, image-forward front page, per Gail's notes:
 *   - the hero banner stays, but with smaller, less "in your face" type
 *     (scoped in style.css under .page-template-template-home);
 *   - the heavier body text lives on the interior pages via the menu, so this
 *     template intentionally does NOT render the page's blocks;
 *   - the Domenico di Michelino painting is featured on its own, with just a
 *     short opening message beneath it.
 *
 * NOTE: this file is deliberately NOT named page-home.php — WordPress would
 * auto-apply a page-{slug}.php file to the "home"-slug page regardless of the
 * selected template. As template-home.php it only applies when chosen.
 *
 * The opening message is editable in Appearance → Customize → Hero Section
 * (the "Opening Message" field). Until it's filled in, a clearly-marked dummy
 * is shown so it's obvious where to add — or remove — it.
 *
 * @package Dante_Society
 */

get_header();

$home_message   = trim( (string) get_theme_mod( 'dante_hero_message', '' ) );
$is_placeholder = ( '' === $home_message );
if ( $is_placeholder ) {
    $home_message = 'Opening Message — please replace or delete.';
}
?>

<main class="main-content">
    <div class="content-card home-card">

        <figure class="home-painting">
            <img src="<?php echo esc_url( get_template_directory_uri() . '/images/background2.jpg' ); ?>"
                 alt="<?php esc_attr_e( 'Dante and the Divine Comedy — Domenico di Michelino, 1465', 'dante-society' ); ?>" />
            <figcaption><?php esc_html_e( 'Dante and the Divine Comedy — Domenico di Michelino, 1465', 'dante-society' ); ?></figcaption>
        </figure>

        <div class="home-message<?php echo $is_placeholder ? ' is-placeholder' : ''; ?>">
            <?php echo wp_kses_post( wpautop( $home_message ) ); ?>
        </div>

    </div>
</main>

<?php get_footer(); ?>
