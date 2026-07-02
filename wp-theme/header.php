<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="site-header">
    <div class="header-inner">
        <?php if ( has_custom_logo() ) : ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo logo--custom">
                <?php echo wp_get_attachment_image(
                    get_theme_mod( 'custom_logo' ),
                    'full',
                    false,
                    array(
                        'class' => 'custom-logo',
                        'alt'   => get_bloginfo( 'name' ),
                    )
                ); ?>
                <div class="logo-text">
                    <span class="logo-name"><?php bloginfo( 'name' ); ?></span>
                    <span class="logo-subtitle">Since 1998</span>
                </div>
            </a>
        <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
                <div class="logo-icon">D</div>
                <div class="logo-text">
                    <span class="logo-name"><?php bloginfo( 'name' ); ?></span>
                    <span class="logo-subtitle">Dante Society</span>
                </div>
            </a>
        <?php endif; ?>
        <button class="nav-toggle" id="nav-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'dante-society' ); ?>">&#9776;</button>
        <nav class="main-nav" id="main-nav">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'main-nav',
                'fallback_cb'    => 'dante_primary_menu_fallback',
                'depth'          => 2,
            ) );
            ?>
        </nav>
    </div>
</header>

<?php if ( is_front_page() ) : ?>
<section class="hero">
    <h1><?php echo esc_html( get_theme_mod( 'dante_hero_title', 'Dante Society of Virginia' ) ); ?></h1>
    <div class="hero-separator"></div>
    <p class="tagline"><?php echo esc_html( get_theme_mod( 'dante_hero_tagline', 'Celebrating Italian language, art, and culture in Central Virginia since 1998.' ) ); ?></p>
    <?php
    // Optional short "opening message" box. Editable in Appearance → Customize →
    // Hero Section. Hidden entirely until it's filled in.
    $dante_hero_message = trim( (string) get_theme_mod( 'dante_hero_message', '' ) );
    if ( '' !== $dante_hero_message ) : ?>
    <div class="hero-message"><?php echo wp_kses_post( wpautop( $dante_hero_message ) ); ?></div>
    <?php endif; ?>
</section>
<?php elseif ( ! is_front_page() ) : ?>
<section class="page-header">
    <h1><?php wp_title( '' ); ?></h1>
</section>
<?php endif; ?>
