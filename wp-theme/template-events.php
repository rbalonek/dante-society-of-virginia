<?php
/**
 * Template Name: Events Page
 * Description: Editable intro + a calendar + the auto-generated list of events
 *              (managed under the "Events" menu in wp-admin).
 *
 * NOTE: this file is deliberately NOT named page-events.php — WordPress would
 * auto-apply a page-{slug}.php file to the "events"-slug page regardless of the
 * selected template, forcing the calendar/list on with no block to edit. As
 * template-events.php it only applies when explicitly chosen. Prefer the
 * "Events (calendar + list)" block on a Default-template page instead.
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
