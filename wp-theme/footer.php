    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-col">
                <h4>Contact</h4>
                <p>
                    P.O. Box 131<br>
                    Forest, VA 24551<br>
                    <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Send us a message</a>
                </p>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <?php
                // Editable in Appearance → Menus → "Footer Links" location; the
                // fallback shows sensible defaults until a menu is assigned.
                wp_nav_menu( array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'footer-menu',
                    'fallback_cb'    => 'dante_footer_menu_fallback',
                    'depth'          => 1,
                ) );
                ?>
            </div>

            <div class="footer-col">
                <h4><?php bloginfo( 'name' ); ?></h4>
                <p><?php echo esc_html( get_theme_mod( 'dante_footer_about', 'Promoting Italian culture, arts, literature, and community in Virginia since 1998.' ) ); ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>
</html>
