<?php
/**
 * Template Name: Events Page
 * Description: Editable intro + a calendar + the auto-generated list of events
 *              (managed under the "Events" menu in wp-admin).
 *
 * @package Dante_Society
 */

get_header(); ?>

<main class="main-content">

    <?php
    // Editable page intro (anything the board adds in the page editor).
    while ( have_posts() ) :
        the_post();
        if ( trim( get_the_content() ) ) :
            ?>
            <div class="content-card">
                <div class="entry-content"><?php the_content(); ?></div>
            </div>
            <?php
        endif;
    endwhile;
    ?>

    <?php
    // Calendar + auto event list (same markup the Events block renders).
    echo dante_events_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>

</main>

<?php get_footer(); ?>
