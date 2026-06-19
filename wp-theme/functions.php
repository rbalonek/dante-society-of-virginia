<?php
/**
 * Dante Society of Virginia Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DANTE_THEME_VERSION', '1.0.0' );

/**
 * Theme Setup
 */
function dante_setup() {
    // Add theme support
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
    add_theme_support( 'custom-logo', array(
        'height'      => 80,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ) );
    add_theme_support( 'customize-selective-refresh-widgets' );

    // Register navigation menus
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'dante-society' ),
    ) );

    // Set content width
    if ( ! isset( $content_width ) ) {
        $content_width = 1100;
    }
}
add_action( 'after_setup_theme', 'dante_setup' );

/**
 * Enqueue Styles and Scripts
 */
function dante_enqueue_assets() {
    // Google Fonts
    wp_enqueue_style(
        'dante-google-fonts',
        'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@400;600;700&display=swap',
        array(),
        DANTE_THEME_VERSION
    );

    // Main stylesheet
    wp_enqueue_style(
        'dante-style',
        get_stylesheet_uri(),
        array( 'dante-google-fonts' ),
        DANTE_THEME_VERSION
    );

    // Custom CSS file
    wp_enqueue_style(
        'dante-custom',
        get_template_directory_uri() . '/css/style.css',
        array( 'dante-style' ),
        DANTE_THEME_VERSION
    );

    // Navigation toggle script
    wp_enqueue_script(
        'dante-navigation',
        get_template_directory_uri() . '/js/navigation.js',
        array(),
        DANTE_THEME_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'dante_enqueue_assets' );

/**
 * Register Widget Areas
 */
function dante_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Footer Column 1', 'dante-society' ),
        'id'            => 'footer-1',
        'description'   => __( 'First footer column', 'dante-society' ),
        'before_widget' => '<div id="%1$s" class="footer-col widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 2', 'dante-society' ),
        'id'            => 'footer-2',
        'description'   => __( 'Second footer column', 'dante-society' ),
        'before_widget' => '<div id="%1$s" class="footer-col widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 3', 'dante-society' ),
        'id'            => 'footer-3',
        'description'   => __( 'Third footer column', 'dante-society' ),
        'before_widget' => '<div id="%1$s" class="footer-col widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );
}
add_action( 'widgets_init', 'dante_widgets_init' );

/**
 * Customizer Settings
 */
function dante_customize_register( $wp_customize ) {
    // Hero Section
    $wp_customize->add_section( 'dante_hero', array(
        'title'    => __( 'Hero Section', 'dante-society' ),
        'priority' => 30,
    ) );

    $wp_customize->add_setting( 'dante_hero_title', array(
        'default'           => 'Dante Alighieri Society of Virginia',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'dante_hero_title', array(
        'label'   => __( 'Hero Title', 'dante-society' ),
        'section' => 'dante_hero',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'dante_hero_tagline', array(
        'default'           => 'Celebrating Italian culture in Virginia since 1998.',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'dante_hero_tagline', array(
        'label'   => __( 'Hero Tagline', 'dante-society' ),
        'section' => 'dante_hero',
        'type'    => 'textarea',
    ) );
}
add_action( 'customize_register', 'dante_customize_register' );

/**
 * Primary menu fallback.
 *
 * Used by header.php when no menu has been assigned to the "Primary" location
 * (e.g. on a fresh install). This lets the site navigation work out of the box.
 * Once an editor builds a menu under Appearance → Menus and assigns it to
 * "Primary Menu", that menu takes over automatically.
 */
function dante_primary_menu_fallback() {
    $links = array(
        home_url( '/' )                => 'Events',
        home_url( '/about' )           => 'About',
        home_url( '/board' )           => 'Board',
        home_url( '/programs' )        => 'Programs',
        home_url( '/membership' )      => 'Membership',
        home_url( '/italian-culture' ) => 'Italian Culture',
        home_url( '/contact' )         => 'Contact',
    );

    echo '<ul id="menu-primary" class="menu">';
    foreach ( $links as $url => $label ) {
        printf(
            '<li class="menu-item"><a href="%s">%s</a></li>',
            esc_url( $url ),
            esc_html( $label )
        );
    }
    echo '</ul>';
}

/**
 * Get page ID by template
 */
function dante_get_page_id_by_template( $template_name ) {
    $pages = get_pages( array(
        'meta_key'   => '_wp_page_template',
        'meta_value' => $template_name,
    ) );
    if ( ! empty( $pages ) ) {
        return $pages[0]->ID;
    }
    return false;
}
