<?php
/**
 * Dante Society — "Full Screen Hero" block.
 *
 * A configurable full-viewport hero: title, accent line, subtitle text, and a
 * call-to-action button — each of which can be shown/hidden, with editable text,
 * the button link, and the button/line colors. Server-rendered (no build step),
 * same pattern as the events block.
 *
 * The background image comes from Customize → Background Images → "Homepage hero
 * background" (falling back to the bundled portrait), so the picture is changed
 * in one obvious place while everything else is edited on the block.
 *
 * Best used on a page set to the "Home Page" template, which provides the
 * full-bleed canvas and the overlaid (transparent) header.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default attributes — shared by the block and the template fallback.
 */
function dante_hero_defaults() {
    return array(
        'title'        => 'Dante Society of Virginia',
        'showTitle'    => true,
        'subtitle'     => 'Celebrating Italian language, art, and culture in Central Virginia since 1998.',
        'showSubtitle' => true,
        'showLine'     => true,
        'lineColor'    => '#C8963E',
        'showButton'   => true,
        'buttonText'   => 'See Upcoming Events',
        'buttonUrl'    => '/events',
        'buttonColor'  => '#C8963E',
    );
}

/**
 * Render the hero. Also used by template-home.php as the default when a page has
 * no hero block yet, so the splash is never empty.
 */
function dante_render_hero_block( $attributes = array() ) {
    $a = wp_parse_args( $attributes, dante_hero_defaults() );

    // Background: the Customizer hero image, else the bundled portrait default.
    $bg = get_theme_mod( 'dante_hero_image' );
    if ( ! $bg ) {
        $bg = dante_home_hero_default_url();
    }
    $style = 'background:linear-gradient(rgba(13,43,31,0.45),rgba(13,43,31,0.7)),url(' . esc_url( $bg ) . ');'
        . 'background-size:cover;background-position:center;';

    // Allow a relative link like "/events".
    $url = trim( (string) $a['buttonUrl'] );
    if ( '' !== $url && '/' === $url[0] ) {
        $url = home_url( $url );
    }

    ob_start();
    ?>
    <section class="dante-hero" style="<?php echo esc_attr( $style ); ?>">
        <?php if ( ! empty( $a['showTitle'] ) && '' !== trim( (string) $a['title'] ) ) : ?>
            <h1 class="dante-hero__title"><?php echo esc_html( $a['title'] ); ?></h1>
        <?php endif; ?>

        <?php if ( ! empty( $a['showLine'] ) ) : ?>
            <div class="dante-hero__line" style="background-color:<?php echo esc_attr( $a['lineColor'] ); ?>;"></div>
        <?php endif; ?>

        <?php if ( ! empty( $a['showSubtitle'] ) && '' !== trim( (string) $a['subtitle'] ) ) : ?>
            <p class="dante-hero__subtitle"><?php echo esc_html( $a['subtitle'] ); ?></p>
        <?php endif; ?>

        <?php if ( ! empty( $a['showButton'] ) && '' !== trim( (string) $a['buttonText'] ) && '' !== $url ) : ?>
            <a class="dante-hero__button" href="<?php echo esc_url( $url ); ?>"
               style="background-color:<?php echo esc_attr( $a['buttonColor'] ); ?>;">
                <?php echo esc_html( $a['buttonText'] ); ?> &rarr;
            </a>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Register the block + its editor script.
 */
function dante_register_hero_block() {
    wp_register_script(
        'dante-hero-block',
        get_template_directory_uri() . '/js/hero-block.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
        dante_ver( 'js/hero-block.js' ),
        true
    );

    register_block_type( 'dante/hero', array(
        'api_version'     => 2,
        'title'           => __( 'Full Screen Hero', 'dante-society' ),
        'description'     => __( 'A full-screen banner with a title, line, text, and a button — all optional and configurable.', 'dante-society' ),
        'category'        => 'widgets',
        'icon'            => 'cover-image',
        'render_callback' => 'dante_render_hero_block',
        'editor_script'   => 'dante-hero-block',
        'supports'        => array( 'html' => false, 'multiple' => false ),
        'attributes'      => array(
            'title'        => array( 'type' => 'string',  'default' => 'Dante Society of Virginia' ),
            'showTitle'    => array( 'type' => 'boolean', 'default' => true ),
            'subtitle'     => array( 'type' => 'string',  'default' => 'Celebrating Italian language, art, and culture in Central Virginia since 1998.' ),
            'showSubtitle' => array( 'type' => 'boolean', 'default' => true ),
            'showLine'     => array( 'type' => 'boolean', 'default' => true ),
            'lineColor'    => array( 'type' => 'string',  'default' => '#C8963E' ),
            'showButton'   => array( 'type' => 'boolean', 'default' => true ),
            'buttonText'   => array( 'type' => 'string',  'default' => 'See Upcoming Events' ),
            'buttonUrl'    => array( 'type' => 'string',  'default' => '/events' ),
            'buttonColor'  => array( 'type' => 'string',  'default' => '#C8963E' ),
        ),
    ) );
}
add_action( 'init', 'dante_register_hero_block' );
