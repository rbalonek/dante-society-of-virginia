/**
 * Dante Society — Events calendar.
 * Drives both the inline calendar (Events block, #dante-calendar) and the
 * site-wide "Calendar" popup opened from the nav (#dante-calendar-popup).
 * Data comes from window.danteEvents (localized in functions.php).
 */
( function () {
	'use strict';

	function fmtDate( iso ) {
		try {
			var d = new Date( iso + 'T00:00:00' );
			return d.toLocaleDateString( undefined, {
				weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
			} );
		} catch ( e ) {
			return iso;
		}
	}

	// --- Event-detail modal (shared) --------------------------------------
	var detailOverlay = null;

	function buildDetailModal() {
		var overlay = document.createElement( 'div' );
		overlay.className = 'dante-modal-overlay';
		overlay.setAttribute( 'hidden', '' );
		overlay.innerHTML =
			'<div class="dante-modal" role="dialog" aria-modal="true">' +
			'<button class="dante-modal-close" aria-label="Close">&times;</button>' +
			'<img class="dante-modal-img" alt="" hidden />' +
			'<h3 id="dante-modal-title"></h3>' +
			'<p class="dante-modal-meta"></p>' +
			'<p class="dante-modal-desc"></p>' +
			'</div>';
		document.body.appendChild( overlay );

		function close() { overlay.setAttribute( 'hidden', '' ); }
		overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) close(); } );
		overlay.querySelector( '.dante-modal-close' ).addEventListener( 'click', close );
		return overlay;
	}

	function showDetail( info ) {
		var p = info.event.extendedProps || {};
		var img = detailOverlay.querySelector( '.dante-modal-img' );
		if ( p.image ) { img.src = p.image; img.removeAttribute( 'hidden' ); }
		else { img.setAttribute( 'hidden', '' ); }

		detailOverlay.querySelector( '#dante-modal-title' ).textContent = info.event.title;

		var bits = [];
		if ( info.event.startStr ) { bits.push( fmtDate( info.event.startStr ) ); }
		if ( p.time ) { bits.push( p.time ); }
		if ( p.location ) { bits.push( p.location ); }
		detailOverlay.querySelector( '.dante-modal-meta' ).textContent = bits.join( '  ·  ' );
		detailOverlay.querySelector( '.dante-modal-desc' ).textContent = p.description || '';
		detailOverlay.removeAttribute( 'hidden' );
	}

	// --- Calendar factory (used by inline + popup) ------------------------
	function makeCalendar( el, clickMode ) {
		var cfg = window.danteCalendarConfig || {};
		var dates = ( window.danteEvents || [] ).map( function ( e ) { return e.start; } ).filter( Boolean ).sort();
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
			headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listYear,listAll' },
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

				if ( clickMode === 'scroll' ) {
					var target = info.event.id ? document.getElementById( 'event-' + info.event.id ) : null;
					if ( target ) {
						target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
						target.classList.add( 'event-highlight' );
						setTimeout( function () { target.classList.remove( 'event-highlight' ); }, 2000 );
						return;
					}
				}
				showDetail( info );
			},
		};
		if ( cfg.initialDate ) { options.initialDate = cfg.initialDate; }

		return new FullCalendar.Calendar( el, options );
	}

	// --- Init -------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof FullCalendar === 'undefined' ) { return; }
		detailOverlay = buildDetailModal();

		// Inline calendar (Events block on a page).
		var inlineEl = document.getElementById( 'dante-calendar' );
		if ( inlineEl ) {
			makeCalendar( inlineEl, inlineEl.getAttribute( 'data-click' ) || 'scroll' ).render();
		}

		// Site-wide popup calendar, opened from a nav "Calendar" link.
		var overlay = document.getElementById( 'dante-cal-overlay' );
		var popupEl = document.getElementById( 'dante-calendar-popup' );
		var popupCal = null;

		function openPopup( e ) {
			if ( e ) { e.preventDefault(); }
			if ( ! overlay ) { return; }
			overlay.removeAttribute( 'hidden' );
			if ( ! popupCal && popupEl ) {
				popupCal = makeCalendar( popupEl, 'popup' );
				popupCal.render();
			} else if ( popupCal ) {
				popupCal.updateSize();
			}
		}
		function closePopup() { if ( overlay ) { overlay.setAttribute( 'hidden', '' ); } }

		// Any link pointing at #calendar (or with the toggle class) opens it.
		document.querySelectorAll( 'a[href$="#calendar"], .dante-calendar-toggle' ).forEach( function ( a ) {
			a.addEventListener( 'click', openPopup );
		} );

		if ( overlay ) {
			overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { closePopup(); } } );
			var btn = overlay.querySelector( '.dante-cal-close' );
			if ( btn ) { btn.addEventListener( 'click', closePopup ); }
		}
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { closePopup(); } } );
	} );
} )();
