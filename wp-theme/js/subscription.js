/* Dante subscription — Stripe Embedded Checkout mount (wp-admin Billing page).
 *
 * Fetches a Checkout Session client_secret from our REST endpoint (which mints
 * it server-side with the secret key) and mounts Stripe's embedded checkout in
 * #dante-embedded-checkout. The checkout pulls its branding/product image/price
 * from the Stripe account + product automatically — no styling needed here.
 */
( function () {
	var mount = document.getElementById( 'dante-embedded-checkout' );
	if ( ! mount || typeof window.danteSub === 'undefined' || ! window.danteSub.pk ) {
		return;
	}
	if ( typeof Stripe === 'undefined' ) {
		mount.innerHTML = '<p style="color:#b3261e">Could not load Stripe.js.</p>';
		return;
	}

	var stripe = Stripe( window.danteSub.pk );

	function fetchClientSecret() {
		return fetch( window.danteSub.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.danteSub.nonce
			}
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.client_secret ) {
					throw new Error( ( data && data.message ) || 'Could not start checkout.' );
				}
				return data.client_secret;
			} );
	}

	stripe.initEmbeddedCheckout( { fetchClientSecret: fetchClientSecret } )
		.then( function ( checkout ) {
			checkout.mount( '#dante-embedded-checkout' );
		} )
		.catch( function ( err ) {
			mount.innerHTML = '<p style="color:#b3261e">' +
				( err && err.message ? err.message : 'Checkout failed to load.' ) + '</p>';
		} );
} )();
