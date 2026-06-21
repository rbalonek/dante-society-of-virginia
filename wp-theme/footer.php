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
                <p>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Events</a><br>
                    <a href="<?php echo esc_url( home_url( '/about' ) ); ?>">About Us</a><br>
                    <a href="<?php echo esc_url( home_url( '/board' ) ); ?>">Board of Directors</a><br>
                    <a href="<?php echo esc_url( home_url( '/programs' ) ); ?>">Programs</a><br>
                    <a href="<?php echo esc_url( home_url( '/membership' ) ); ?>">Membership</a><br>
                    <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact</a>
                </p>
            </div>

            <div class="footer-col">
                <h4><?php bloginfo( 'name' ); ?></h4>
                <p>Promoting Italian culture, arts, literature, and community in Virginia since 1998.</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>
</html>
