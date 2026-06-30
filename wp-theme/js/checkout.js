/**
 * Dante Society — Membership checkout DEMO behavior.
 *
 * This drives the mockup only: plan switching, light input formatting, and a
 * simulated "payment" that shows a success state. NOTHING is sent anywhere and
 * no real charge is made. When a real Stripe account is connected, this file
 * would be replaced by Stripe.js / a Checkout Session redirect.
 */
( function () {
	'use strict';

	var form = document.getElementById( 'checkout-form' );
	if ( ! form ) {
		return;
	}

	var money = function ( n ) {
		return '$' + Number( n ).toFixed( 2 );
	};

	var setText = function ( id, value ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.textContent = value;
		}
	};

	// --- Plan selection -> update all amounts -------------------------------
	var options = document.querySelectorAll( '.checkout-plan-option' );

	function selectPlan( option ) {
		var amount = option.getAttribute( 'data-amount' );
		var label = option.getAttribute( 'data-label' );

		options.forEach( function ( o ) {
			o.classList.toggle( 'is-selected', o === option );
		} );

		setText( 'summary-amount', money( amount ) );
		setText( 'summary-plan', label );
		setText( 'line-desc', label );
		setText( 'line-amount', money( amount ) );
		setText( 'total-amount', money( amount ) );
		setText( 'pay-amount', money( amount ) );
	}

	options.forEach( function ( option ) {
		option.addEventListener( 'click', function () {
			var radio = option.querySelector( 'input[type="radio"]' );
			if ( radio ) {
				radio.checked = true;
			}
			selectPlan( option );
		} );
	} );

	// --- Light input formatting (cosmetic only) -----------------------------
	var card = document.getElementById( 'co-card' );
	if ( card ) {
		card.addEventListener( 'input', function () {
			var digits = card.value.replace( /\D/g, '' ).slice( 0, 16 );
			card.value = digits.replace( /(.{4})/g, '$1 ' ).trim();
		} );
	}

	var exp = document.getElementById( 'co-exp' );
	if ( exp ) {
		exp.addEventListener( 'input', function () {
			var digits = exp.value.replace( /\D/g, '' ).slice( 0, 4 );
			exp.value = digits.length > 2 ? digits.slice( 0, 2 ) + ' / ' + digits.slice( 2 ) : digits;
		} );
	}

	// --- Simulated payment --------------------------------------------------
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( ! form.checkValidity() ) {
			form.reportValidity();
			return;
		}

		var btn = document.getElementById( 'checkout-pay' );
		var label = btn.querySelector( '.checkout-pay-label' );
		var spinner = btn.querySelector( '.checkout-pay-spinner' );

		btn.disabled = true;
		if ( label ) { label.hidden = true; }
		if ( spinner ) { spinner.hidden = false; }

		// Fake network latency, then show the success panel.
		window.setTimeout( function () {
			var email = ( document.getElementById( 'co-email' ) || {} ).value || '';
			var total = document.getElementById( 'total-amount' );

			setText( 'success-email', email );
			setText(
				'success-amount',
				( total ? total.textContent : '' ) + ' paid'
			);

			form.hidden = true;
			var success = document.getElementById( 'checkout-success' );
			if ( success ) {
				success.hidden = false;
				success.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		}, 1400 );
	} );
}() );
