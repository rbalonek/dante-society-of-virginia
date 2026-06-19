<?php
/**
 * Template Name: Membership Page
 * Description: Displays membership tiers, benefits, and payment instructions.
 */
get_header(); ?>

<main class="main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="content-card">
            <h2><?php the_title(); ?></h2>
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>

    <div class="content-card">
        <h2>Membership Dues</h2>
        <div class="membership-tiers">
            <div class="tier-card">
                <div class="tier-name">Individual</div>
                <div class="tier-price">$35</div>
                <div class="tier-desc">Annual membership for one person</div>
            </div>
            <div class="tier-card">
                <div class="tier-name">Family</div>
                <div class="tier-price">$60</div>
                <div class="tier-desc">Annual membership for the whole family</div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h2>Member Benefits</h2>
        <ul>
            <li>Invitations to all monthly programs, lectures, and events</li>
            <li>Film &amp; dinner series participation</li>
            <li>Carnevale celebration access</li>
            <li>Advance notice of special events and cultural collaborations</li>
            <li>Travel opportunities with fellow members</li>
            <li>Monthly email updates and program reminders</li>
        </ul>
    </div>

    <div class="content-card">
        <h2>How to Pay Dues</h2>
        <p><strong>We do not accept Zelle.</strong> Please use one of the following methods:</p>

        <div class="info-box">
            <strong>Option 1: Mail a Check</strong><br>
            Make checks payable to <strong>Dante Society of Virginia</strong> and mail to:<br>
            <strong>P.O. Box 131, Forest, VA 24551</strong>
        </div>

        <div class="info-box">
            <strong>Option 2: Bank Bill Pay</strong><br>
            Use your bank's online bill pay feature to send a payment to:<br>
            <strong>Dante Society of Virginia, P.O. Box 131, Forest, VA 24551</strong>
        </div>
    </div>
</main>

<?php get_footer(); ?>
