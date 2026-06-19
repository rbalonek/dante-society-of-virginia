<?php
/**
 * 404 Template
 */
get_header(); ?>

<main class="main-content">
    <div class="content-card" style="text-align:center;">
        <h2>Page Not Found</h2>
        <div class="hero-separator" style="margin:20px auto;"></div>
        <p>Sorry, we couldn't find the page you're looking for. It may have been moved or no longer exists.</p>
        <p style="margin-top:20px;">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">Return Home</a>
            <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="btn btn-outline">Contact Us</a>
        </p>
    </div>
</main>

<?php get_footer(); ?>
