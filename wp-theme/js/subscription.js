/* Dante subscription — Stripe Embedded Checkout with a Monthly/Yearly selector.
 *
 * Mounts embedded checkout for the selected plan; switching plans destroys the
 * current instance and mounts a fresh one (a new Checkout Session per plan).
 * The checkout pulls its branding/product image/price from Stripe automatically.
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

	var stripe  = Stripe( window.danteSub.pk );
	var current = null;   // active EmbeddedCheckout instance
	var loading = false;

	function fetchClientSecret( plan ) {
		return function () {
			return fetch( window.danteSub.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.danteSub.nonce
				},
				body: JSON.stringify( { plan: plan } )
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( ! data || ! data.client_secret ) {
						throw new Error( ( data && data.message ) || 'Could not start checkout.' );
					}
					return data.client_secret;
				} );
		};
	}

	function load( plan ) {
		if ( loading ) {
			return;
		}
		loading = true;
		mount.style.opacity = '0.4';

		var finish = function () {
			loading = false;
			mount.style.opacity = '';
		};

		var mountNew = function () {
			stripe.initEmbeddedCheckout( { fetchClientSecret: fetchClientSecret( plan ) } )
				.then( function ( checkout ) {
					current = checkout;
					checkout.mount( '#dante-embedded-checkout' );
					finish();
				} )
				.catch( function ( err ) {
					mount.innerHTML = '<p style="color:#b3261e">' +
						( err && err.message ? err.message : 'Checkout failed to load.' ) + '</p>';
					finish();
				} );
		};

		if ( current ) {
			try { current.destroy(); } catch ( e ) {}
			current = null;
		}
		mountNew();

		// keep the "open in a new tab" fallback pointed at the selected plan
		var fb = document.getElementById( 'dante-fallback-link' );
		if ( fb && window.danteSub.links && window.danteSub.links[ plan ] ) {
			fb.setAttribute( 'href', window.danteSub.links[ plan ] );
		}
	}

	// Plan selector
	var buttons = Array.prototype.slice.call( document.querySelectorAll( '.dante-plan' ) );
	buttons.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( btn.classList.contains( 'is-active' ) || loading ) {
				return;
			}
			buttons.forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
			btn.classList.add( 'is-active' );
			load( btn.getAttribute( 'data-plan' ) );
		} );
	} );

	// Initial mount (default to the active button, else monthly)
	var active = document.querySelector( '.dante-plan.is-active' );
	load( active ? active.getAttribute( 'data-plan' ) : 'monthly' );
} )();
