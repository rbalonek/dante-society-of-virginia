<?php
/**
 * Dante Society of Virginia Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DANTE_THEME_VERSION', '1.0.0' );

/**
 * Asset version based on a theme file's modification time, so updated CSS/JS
 * always busts browser + server caches (instead of a static version that never
 * changes). Falls back to the theme version if the file is missing.
 *
 * @param string $relative Path relative to the theme root, e.g. 'css/style.css'.
 * @return string
 */
function dante_ver( $relative ) {
    $path = get_template_directory() . '/' . ltrim( $relative, '/' );
    return file_exists( $path ) ? (string) filemtime( $path ) : DANTE_THEME_VERSION;
}

// Events: custom post type, fields, and helpers.
require_once get_template_directory() . '/inc/events.php';

// One-time seeder for the starter events (safe to remove after it runs once).
require_once get_template_directory() . '/inc/seed-events.php';

// Newsletter: subscribers + composer (send via wp_mail).
require_once get_template_directory() . '/inc/newsletter.php';

// Full Screen Hero block (configurable splash hero).
require_once get_template_directory() . '/inc/hero-block.php';

// Photos: custom post type + collage block.
require_once get_template_directory() . '/inc/photos.php';

// Dante Assistant: chat-based site editing (dashboard widget + agent loop).
require_once get_template_directory() . '/inc/assistant/assistant.php';

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
        dante_ver( 'style.css' )
    );

    // Custom CSS file
    wp_enqueue_style(
        'dante-custom',
        get_template_directory_uri() . '/css/style.css',
        array( 'dante-style' ),
        dante_ver( 'css/style.css' )
    );

    // Navigation toggle script
    wp_enqueue_script(
        'dante-navigation',
        get_template_directory_uri() . '/js/navigation.js',
        array(),
        dante_ver( 'js/navigation.js' ),
        true
    );

    // Membership checkout (demo) — only on the checkout page template.
    if ( is_page_template( 'page-checkout.php' ) ) {
        wp_enqueue_script(
            'dante-checkout',
            get_template_directory_uri() . '/js/checkout.js',
            array(),
            DANTE_THEME_VERSION,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'dante_enqueue_assets' );

/**
 * The site logo (emblem) displays at 64px in the header, but WordPress emits a
 * "100vw" sizes attribute that makes browsers fetch a much larger source than
 * needed. Constrain it so a small, appropriately sized variant is served.
 */
function dante_custom_logo_image_attributes( $attr ) {
    if ( isset( $attr['class'] ) && false !== strpos( $attr['class'], 'custom-logo' ) ) {
        $attr['sizes'] = '64px';
    }
    return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'dante_custom_logo_image_attributes' );

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
        'default'           => 'Dante Society of Virginia',
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

    $wp_customize->add_setting( 'dante_hero_message', array(
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
    ) );

    $wp_customize->add_control( 'dante_hero_message', array(
        'label'       => __( 'Opening Message', 'dante-society' ),
        'description' => __( 'A short welcome shown in a box beneath the tagline on the homepage. Leave empty to show a small "goes here" placeholder.', 'dante-society' ),
        'section'     => 'dante_hero',
        'type'        => 'textarea',
    ) );

    // Layout & Mobile
    $wp_customize->add_section( 'dante_layout', array(
        'title'    => __( 'Layout & Mobile', 'dante-society' ),
        'priority' => 35,
    ) );

    $wp_customize->add_setting( 'dante_mobile_breakpoint', array(
        'default'           => 900,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ) );

    $wp_customize->add_control( 'dante_mobile_breakpoint', array(
        'label'       => __( 'Mobile layout breakpoint (px)', 'dante-society' ),
        'description' => __( 'Screens at or below this width show the stacked mobile layout (hamburger menu, single column). 900 makes tablets use the mobile layout. Range 480–1200.', 'dante-society' ),
        'section'     => 'dante_layout',
        'type'        => 'number',
        'input_attrs' => array(
            'min'  => 480,
            'max'  => 1200,
            'step' => 10,
        ),
    ) );

    // Background Images
    $wp_customize->add_section( 'dante_backgrounds', array(
        'title'    => __( 'Background Images', 'dante-society' ),
        'priority' => 36,
    ) );

    $wp_customize->add_setting( 'dante_bg_image', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'dante_bg_image', array(
        'label'       => __( 'Page background image', 'dante-society' ),
        'description' => __( 'Shown behind the whole site. Leave empty to use the default Dante painting.', 'dante-society' ),
        'section'     => 'dante_backgrounds',
    ) ) );

    $wp_customize->add_setting( 'dante_hero_image', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'dante_hero_image', array(
        'label'       => __( 'Homepage hero background', 'dante-society' ),
        'description' => __( 'Background behind the homepage title area. Leave empty for the default.', 'dante-society' ),
        'section'     => 'dante_backgrounds',
    ) ) );
}
add_action( 'customize_register', 'dante_customize_register' );

/**
 * Apply Customizer background image choices (override the CSS defaults).
 */
function dante_background_css() {
    $css = '';

    $bg = get_theme_mod( 'dante_bg_image' );
    if ( $bg ) {
        $css .= 'body{background-image:url(' . esc_url( $bg ) . ');}';
    }

    $hero = get_theme_mod( 'dante_hero_image' );
    if ( $hero ) {
        $css .= '.hero{background:linear-gradient(rgba(27,67,50,0.5),rgba(13,43,31,0.62)),url(' . esc_url( $hero ) . ');background-size:cover;background-position:center;}';
    }
    // The Full Screen Hero block sets its own background inline (see
    // dante_render_hero_block), so no template-specific rule is needed here.

    if ( $css ) {
        wp_add_inline_style( 'dante-custom', $css );
    }
}

/**
 * Default splash-hero background image for the Home Page template. Uses the
 * bundled portrait (images/hero-portrait.jpg) once it is added to the theme;
 * falls back to the existing hero painting until then. Overridable via
 * Customize → Background Images → "Homepage hero background".
 */
function dante_home_hero_default_url() {
    $dir = get_template_directory();
    $rel = file_exists( $dir . '/images/hero-portrait.jpg' ) ? 'hero-portrait.jpg' : 'background1.jpg';
    return get_template_directory_uri() . '/images/' . $rel;
}
add_action( 'wp_enqueue_scripts', 'dante_background_css', 25 );

/**
 * ---------------------------------------------------------------------------
 * Simplified block editor for non-technical editors
 * ---------------------------------------------------------------------------
 */

/**
 * Load the front-end fonts/colors into the editor canvas (WYSIWYG) and the
 * editor-clarity styles (large, obvious resize handles, visible spacers).
 */
function dante_editor_setup() {
    add_theme_support( 'editor-styles' );
    add_editor_style( array(
        // Load brand fonts inside the editor canvas (iframe).
        'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@400;600;700&display=swap',
        'css/editor.css',
    ) );
}
add_action( 'after_setup_theme', 'dante_editor_setup' );

/**
 * Styles for the editor chrome (block toolbar) — outside the canvas iframe.
 */
function dante_editor_chrome_assets() {
    wp_enqueue_style(
        'dante-editor-chrome',
        get_template_directory_uri() . '/css/editor-chrome.css',
        array(),
        DANTE_THEME_VERSION
    );

    wp_enqueue_script(
        'dante-editor-js',
        get_template_directory_uri() . '/js/editor.js',
        array( 'wp-dom-ready', 'wp-data', 'wp-notices', 'wp-preferences' ),
        DANTE_THEME_VERSION,
        true
    );

    // Make the admin-set mobile breakpoint available to the editor script.
    wp_add_inline_script(
        'dante-editor-js',
        'window.danteEditor = { breakpoint: ' . dante_get_mobile_breakpoint() . ' };',
        'before'
    );
}
add_action( 'enqueue_block_editor_assets', 'dante_editor_chrome_assets' );

/**
 * Restrict the block inserter to a small, friendly set of blocks so editors
 * aren't faced with 90+ options. Extend this list as custom blocks/widgets
 * are added later.
 */
function dante_allowed_blocks( $allowed_blocks, $context ) {
    return array(
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/media-text', // image beside text, draggable width, left/right toggle
        'core/gallery',
        'core/list',
        'core/list-item',
        'core/buttons',
        'core/button',
        'core/quote',
        'core/separator',
        'core/spacer',
        'core/shortcode', // allows [dante_subscribe] signup form
        'dante/events', // calendar + auto event list
        'dante/hero', // full screen hero banner
        'dante/photos', // photo collage
    );
}
add_filter( 'allowed_block_types_all', 'dante_allowed_blocks', 10, 2 );

/**
 * Labeled one-click image alignment. These appear in the image block's "Styles"
 * panel as clearly named choices — friendlier than the unlabeled alignment icon
 * in the toolbar for non-technical editors.
 */
function dante_register_block_styles() {
    register_block_style( 'core/image', array(
        'name'  => 'align-center',
        'label' => __( 'Centered', 'dante-society' ),
    ) );
    register_block_style( 'core/image', array(
        'name'  => 'align-left',
        'label' => __( 'Float left (text wraps right)', 'dante-society' ),
    ) );
    register_block_style( 'core/image', array(
        'name'  => 'align-right',
        'label' => __( 'Float right (text wraps left)', 'dante-society' ),
    ) );
}
add_action( 'init', 'dante_register_block_styles' );

/**
 * Mobile breakpoint (px): screen widths at or below this switch the site to its
 * stacked mobile layout (hamburger menu, single column). Admin-set via the
 * Customizer (Appearance → Customize → Layout & Mobile); also surfaced in the
 * block editor. Default 900 so tablets use the mobile layout.
 */
function dante_get_mobile_breakpoint() {
    $bp = absint( get_theme_mod( 'dante_mobile_breakpoint', 900 ) );
    return max( 480, min( 1200, $bp ) );
}

/**
 * Output the responsive (mobile) CSS at the admin-selected breakpoint.
 * Attached to the main theme stylesheet so it loads right after it.
 */
function dante_responsive_css() {
    $bp = dante_get_mobile_breakpoint();

    $css = "@media (max-width:{$bp}px){"
        // iOS renders fixed backgrounds blurry/pixelated; use scroll on mobile.
        . 'body{background-attachment:scroll}'
        . '.header-inner{height:64px}'
        . '.logo-name{font-size:1rem}'
        . '.logo-subtitle{display:none}'
        . '.hero{padding:60px 20px}'
        . '.hero h1{font-size:1.8rem}'
        . '.hero .tagline{font-size:1rem}'
        . '.page-header{padding:40px 20px}'
        . '.page-header h1{font-size:1.6rem}'
        . '.main-content{padding:40px 20px}'
        . '.content-card{padding:20px}'
        . '.footer-inner{grid-template-columns:1fr;gap:24px}'
        . '.membership-tiers{grid-template-columns:1fr}'
        . '.newsletter-form{flex-direction:column}'
        . '.photo-gallery{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}'
        . '.nav-toggle{display:block}'
        . '.main-nav{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark-green);flex-direction:column;padding:16px;gap:2px;border-bottom:3px solid var(--gold)}'
        . '.main-nav.open{display:flex}'
        // Two centered columns of larger links so the menu fits cleanly.
        . '.main-nav ul{display:grid;grid-template-columns:1fr 1fr;width:100%;gap:8px;list-style:none;margin:0;padding:0}'
        . '.main-nav li{width:100%;margin:0}'
        . '.main-nav a{display:block;padding:16px 12px;width:100%;border-radius:6px;text-align:center;font-size:1.15rem}'
        . '.event-card{flex-direction:column}'
        . '.event-card-image{width:100%;min-width:100%;height:200px}'
        // Stack "image beside text" (Media & Text) into one column on small screens.
        . '.wp-block-media-text{grid-template-columns:1fr!important;gap:16px}'
        . '.wp-block-media-text .wp-block-media-text__media{grid-column:1;grid-row:1}'
        . '.wp-block-media-text .wp-block-media-text__content{grid-column:1;grid-row:2}'
        // Stack the events list (image below text) on small screens.
        . '.event-listing{flex-direction:column}'
        . '.event-listing-image{max-width:100%;flex-basis:auto}'
        . '}';

    wp_add_inline_style( 'dante-custom', $css );
}
add_action( 'wp_enqueue_scripts', 'dante_responsive_css', 20 );

/**
 * Load the calendar assets site-wide so the nav "Calendar" popup works on every
 * page (and the inline Events-block calendar too).
 */
function dante_events_assets() {
    if ( ! is_admin() ) {
        dante_enqueue_calendar_assets();
    }
}
add_action( 'wp_enqueue_scripts', 'dante_events_assets' );

/**
 * Output the site-wide calendar popup (opened by a nav link to #calendar).
 */
function dante_calendar_popup_markup() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <div class="dante-cal-overlay" id="dante-cal-overlay" hidden>
        <div class="dante-cal-modal">
            <div class="dante-cal-head">
                <div>
                    <h2 class="dante-cal-title"><?php esc_html_e( 'Events Calendar', 'dante-society' ); ?></h2>
                    <p class="dante-cal-subtitle"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
                </div>
                <button class="dante-cal-close" aria-label="<?php esc_attr_e( 'Close', 'dante-society' ); ?>">&times;</button>
            </div>
            <div class="dante-cal-body">
                <aside class="dante-cal-side">
                    <h3 class="dante-cal-side-title" id="dante-cal-monthtitle"><?php esc_html_e( 'This month', 'dante-society' ); ?></h3>
                    <div id="dante-cal-monthlist" class="dante-cal-monthlist"></div>
                </aside>
                <div id="dante-calendar-popup"></div>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'dante_calendar_popup_markup' );

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
        home_url( '/' )                => 'Home',
        '#calendar'                    => 'Calendar',
        home_url( '/about' )           => 'About',
        home_url( '/board' )           => 'Board',
        home_url( '/programs' )        => 'Events',
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
 * Auto-add a "Calendar" item to the primary menu (opens the calendar popup).
 * Saves having to add a Custom Link by hand. Skipped if one already exists.
 */
function dante_add_calendar_menu_item( $items, $args ) {
    if ( isset( $args->theme_location ) && 'primary' === $args->theme_location
        && false === strpos( $items, '#calendar' ) ) {
        $items .= '<li class="menu-item"><a href="#calendar" class="dante-calendar-toggle">Calendar</a></li>';
    }
    return $items;
}
add_filter( 'wp_nav_menu_items', 'dante_add_calendar_menu_item', 10, 2 );

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
