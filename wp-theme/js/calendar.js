/**
 * Dante Society — Events calendar (FullCalendar) + click-to-popup modal.
 * Data comes from window.danteEvents (localized in functions.php).
 */
( function () {
	'use strict';

	function fmtDate( iso ) {
		try {
			var d = new Date( iso + 'T00:00:00' );
			return d.toLocaleDateString( undefined, {
				weekday: 'long',
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			} );
		} catch ( e ) {
			return iso;
		}
	}

	// --- Modal -------------------------------------------------------------
	function buildModal() {
		var overlay = document.createElement( 'div' );
		overlay.className = 'dante-modal-overlay';
		overlay.setAttribute( 'hidden', '' );
		overlay.innerHTML =
			'<div class="dante-modal" role="dialog" aria-modal="true" aria-labelledby="dante-modal-title">' +
			'<button class="dante-modal-close" aria-label="Close">&times;</button>' +
			'<img class="dante-modal-img" alt="" hidden />' +
			'<h3 id="dante-modal-title"></h3>' +
			'<p class="dante-modal-meta"></p>' +
			'<p class="dante-modal-desc"></p>' +
			'</div>';
		document.body.appendChild( overlay );

		function close() {
			overlay.setAttribute( 'hidden', '' );
		}
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				close();
			}
		} );
		overlay.querySelector( '.dante-modal-close' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				close();
			}
		} );

		return overlay;
	}

	function showModal( overlay, info ) {
		var p = info.event.extendedProps || {};
		var img = overlay.querySelector( '.dante-modal-img' );

		if ( p.image ) {
			img.src = p.image;
			img.removeAttribute( 'hidden' );
		} else {
			img.setAttribute( 'hidden', '' );
		}

		overlay.querySelector( '#dante-modal-title' ).textContent = info.event.title;

		var metaBits = [];
		if ( info.event.startStr ) {
			metaBits.push( fmtDate( info.event.startStr ) );
		}
		if ( p.time ) {
			metaBits.push( p.time );
		}
		if ( p.location ) {
			metaBits.push( p.location );
		}
		overlay.querySelector( '.dante-modal-meta' ).textContent = metaBits.join( '  ·  ' );
		overlay.querySelector( '.dante-modal-desc' ).textContent = p.description || '';

		overlay.removeAttribute( 'hidden' );
	}

	// --- Init --------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.getElementById( 'dante-calendar' );
		if ( ! el || typeof FullCalendar === 'undefined' ) {
			return;
		}

		var overlay = buildModal();
		var cfg = window.danteCalendarConfig || {};

		// Date range that covers every event (for the "All Events" list view).
		var dates = ( window.danteEvents || [] )
			.map( function ( e ) { return e.start; } )
			.filter( Boolean )
			.sort();
		function plusOneDay( iso ) {
			var d = new Date( iso + 'T00:00:00' );
			d.setDate( d.getDate() + 1 );
			return d.toISOString().slice( 0, 10 );
		}
		var listAllView = { type: 'list', buttonText: 'All Events' };
		if ( dates.length ) {
			listAllView.visibleRange = { start: dates[ 0 ], end: plusOneDay( dates[ dates.length - 1 ] ) };
		} else {
			listAllView.duration = { years: 1 };
		}

		var options = {
			initialView: 'dayGridMonth',
			height: 'auto',
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,listYear,listAll',
			},
			buttonText: { today: 'Today' },
			views: {
				dayGridMonth: { buttonText: 'Calendar' },
				listYear: { buttonText: "This Year's Events" },
				listAll: listAllView,
			},
			events: window.danteEvents || [],
			eventDisplay: 'block',
			eventClick: function ( info ) {
				info.jsEvent.preventDefault();

				// Behavior is set per-block via the data-click attribute.
				var behavior = el.getAttribute( 'data-click' ) || 'scroll';

				if ( behavior === 'popup' ) {
					showModal( overlay, info );
					return;
				}

				// Default: scroll to the matching event on the page.
				var target = info.event.id
					? document.getElementById( 'event-' + info.event.id )
					: null;

				if ( target ) {
					target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					target.classList.add( 'event-highlight' );
					setTimeout( function () {
						target.classList.remove( 'event-highlight' );
					}, 2000 );
				} else {
					// Fall back to the popup if the listing isn't on this page.
					showModal( overlay, info );
				}
			},
		};

		// Open on the month of the first event (set in functions.php).
		if ( cfg.initialDate ) {
			options.initialDate = cfg.initialDate;
		}

		var calendar = new FullCalendar.Calendar( el, options );
		calendar.render();
	} );
} )();
