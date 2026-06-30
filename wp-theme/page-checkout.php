<?php
/**
 * Template Name: Membership Checkout (Demo)
 * Description: A Stripe-style checkout MOCKUP for membership dues.
 *              This is a demonstration only — NO payment is processed and
 *              no card data is sent anywhere. It exists to preview the
 *              online-dues flow before a real Stripe account is connected.
 */
get_header();

// Plan presets. A real Stripe integration would map these to Price IDs.
$plans = array(
	'individual' => array( 'label' => 'Individual Membership', 'amount' => 35, 'desc' => 'Annual membership for one person' ),
	'family'     => array( 'label' => 'Family Membership',     'amount' => 60, 'desc' => 'Annual membership for the whole family' ),
);
$selected = isset( $_GET['plan'] ) && isset( $plans[ $_GET['plan'] ] ) ? sanitize_key( $_GET['plan'] ) : 'individual';
?>

<main class="main-content checkout-main">

	<div class="checkout-demo-banner">
		<strong>Demo / test mode.</strong> This is a preview of the online dues flow — no real payment is processed and no card information is stored or sent. Use any numbers in the fields below to try it out.
	</div>

	<div class="checkout-grid" id="checkout">

		<!-- Summary (brand) panel -->
		<aside class="checkout-summary">
			<div class="checkout-merchant">
				<span class="checkout-merchant-mark">D</span>
				<span class="checkout-merchant-name"><?php bloginfo( 'name' ); ?></span>
			</div>

			<p class="checkout-summary-label">Membership dues</p>
			<div class="checkout-amount" id="summary-amount">$<?php echo esc_html( $plans[ $selected ]['amount'] ); ?>.00</div>
			<p class="checkout-summary-plan" id="summary-plan"><?php echo esc_html( $plans[ $selected ]['label'] ); ?></p>

			<div class="checkout-line-items">
				<div class="checkout-line">
					<span id="line-desc"><?php echo esc_html( $plans[ $selected ]['label'] ); ?></span>
					<span id="line-amount">$<?php echo esc_html( $plans[ $selected ]['amount'] ); ?>.00</span>
				</div>
				<div class="checkout-line checkout-line-total">
					<span>Total due today</span>
					<span id="total-amount">$<?php echo esc_html( $plans[ $selected ]['amount'] ); ?>.00</span>
				</div>
			</div>

			<p class="checkout-summary-note">Annual membership · billed once · September&ndash;September fiscal year</p>
		</aside>

		<!-- Payment form panel -->
		<section class="checkout-form-panel">
			<form class="checkout-form" id="checkout-form" novalidate>

				<div class="checkout-field">
					<label>Membership</label>
					<div class="checkout-plan-toggle" role="radiogroup" aria-label="Membership plan">
						<?php foreach ( $plans as $key => $plan ) : ?>
							<label class="checkout-plan-option<?php echo $key === $selected ? ' is-selected' : ''; ?>" data-amount="<?php echo esc_attr( $plan['amount'] ); ?>" data-label="<?php echo esc_attr( $plan['label'] ); ?>">
								<input type="radio" name="plan" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, $selected ); ?>>
								<span class="checkout-plan-name"><?php echo esc_html( ucfirst( $key ) ); ?></span>
								<span class="checkout-plan-price">$<?php echo esc_html( $plan['amount'] ); ?>/yr</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="checkout-field">
					<label for="co-email">Email</label>
					<input type="email" id="co-email" name="email" autocomplete="email" placeholder="you@example.com" required>
				</div>

				<div class="checkout-field">
					<label for="co-name">Name on card</label>
					<input type="text" id="co-name" name="name" autocomplete="cc-name" placeholder="Full name" required>
				</div>

				<div class="checkout-field">
					<label for="co-card">Card information</label>
					<div class="checkout-card-group">
						<input type="text" id="co-card" name="card" inputmode="numeric" placeholder="1234 1234 1234 1234" autocomplete="cc-number" maxlength="19" required>
						<div class="checkout-card-row">
							<input type="text" id="co-exp" name="exp" placeholder="MM / YY" autocomplete="cc-exp" maxlength="7" required>
							<input type="text" id="co-cvc" name="cvc" inputmode="numeric" placeholder="CVC" autocomplete="cc-csc" maxlength="4" required>
						</div>
					</div>
				</div>

				<div class="checkout-field">
					<label for="co-zip">Billing ZIP</label>
					<input type="text" id="co-zip" name="zip" inputmode="numeric" placeholder="24551" maxlength="10" required>
				</div>

				<button type="submit" class="btn btn-primary checkout-pay" id="checkout-pay">
					<span class="checkout-pay-label">Pay <span id="pay-amount">$<?php echo esc_html( $plans[ $selected ]['amount'] ); ?>.00</span></span>
					<span class="checkout-pay-spinner" hidden>Processing&hellip;</span>
				</button>

				<p class="checkout-secure">
					&#128274; Demo checkout &mdash; powered by <strong>Stripe</strong> (not connected). No charge will be made.
				</p>
			</form>

			<!-- Success state (shown after fake submit) -->
			<div class="checkout-success" id="checkout-success" hidden>
				<div class="checkout-success-check">&#10003;</div>
				<h2>Payment received</h2>
				<p>Thank you for joining the <?php bloginfo( 'name' ); ?>! A receipt would be emailed to <strong id="success-email"></strong>.</p>
				<p class="checkout-success-amount" id="success-amount"></p>
				<p class="checkout-demo-reminder">(Demo only &mdash; no real payment was processed.)</p>
				<a class="btn btn-outline" href="<?php echo esc_url( home_url( '/membership' ) ); ?>">Back to Membership</a>
			</div>
		</section>

	</div>
</main>

<?php get_footer(); ?>
