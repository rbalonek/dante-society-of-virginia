/**
 * Dante Society — Events calendar.
 * Drives the inline calendar (Events block, #dante-calendar) and the site-wide
 * "Calendar" popup (#dante-calendar-popup) opened from any #calendar link.
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

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
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

	// --- Helpers for the popup --------------------------------------------
	function sortedEventDates() {
		return ( window.danteEvents || [] ).map( function ( e ) { return e.start; } ).filter( Boolean ).sort();
	}

	function nextEventDate() {
		var today = new Date();
		today.setHours( 0, 0, 0, 0 );
		var dates = sortedEventDates();
		for ( var i = 0; i < dates.length; i++ ) {
			if ( new Date( dates[ i ] + 'T00:00:00' ) >= today ) { return dates[ i ]; }
		}
		return dates.length ? dates[ dates.length - 1 ] : null; // else most recent
	}

	// Fill the "This month" sidebar with the visible month's events.
	function renderMonthList( info ) {
		var listEl = document.getElementById( 'dante-cal-monthlist' );
		if ( ! listEl ) { return; }
		var start = info.view.currentStart, end = info.view.currentEnd;
		var events = ( window.danteEvents || [] ).filter( function ( e ) {
			if ( ! e.start ) { return false; }
			var d = new Date( e.start + 'T00:00:00' );
			return d >= start && d < end;
		} ).sort( function ( a, b ) { return a.start < b.start ? -1 : 1; } );

		if ( ! events.length ) {
			var monthName = start.toLocaleDateString( undefined, { month: 'long', year: 'numeric' } );
			listEl.innerHTML = '<p class="dante-cal-empty">No events scheduled for ' + escapeHtml( monthName ) + '.</p>';
			return;
		}
		listEl.innerHTML = events.map( function ( e ) {
			var d = new Date( e.start + 'T00:00:00' );
			var mon = d.toLocaleDateString( undefined, { month: 'short' } ).toUpperCase();
			var day = d.getDate();
			var p = e.extendedProps || {};
			var meta = [ p.time, p.location ].filter( Boolean ).join( '  ·  ' );
			return '<div class="dante-cal-item">' +
				'<div class="dante-cal-badge"><span class="dante-cal-badge-mon">' + escapeHtml( mon ) + '</span>' +
				'<span class="dante-cal-badge-day">' + day + '</span></div>' +
				'<div class="dante-cal-item-body">' +
				'<div class="dante-cal-item-title">' + escapeHtml( e.title ) + '</div>' +
				( meta ? '<div class="dante-cal-item-meta">' + escapeHtml( meta ) + '</div>' : '' ) +
				'</div></div>';
		} ).join( '' );
	}

	// --- Calendar factory (inline + popup) --------------------------------
	function makeCalendar( el, clickMode, isPopup ) {
		var cfg = window.danteCalendarConfig || {};
		var calInstance;

		var options = {
			initialView: 'dayGridMonth',
			height: 'auto',
			buttonText: { today: 'Today' },
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

		if ( isPopup ) {
			// Month grid + a "This month" sidebar + a "Next Event" jump button.
			options.headerToolbar = { left: 'prev,next today nextEvent', center: '', right: 'title' };
			options.customButtons = {
				nextEvent: {
					text: 'Next Event',
					click: function () {
						var target = nextEventDate();
						if ( target && calInstance ) { calInstance.gotoDate( target ); }
					},
				},
			};
			options.datesSet = function ( info ) { renderMonthList( info ); };
		} else {
			// Inline (Events block): month + list views with a switcher.
			var dates = sortedEventDates();
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
			options.headerToolbar = { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listYear,listAll' };
			options.views = {
				dayGridMonth: { buttonText: 'Calendar' },
				listYear: { buttonText: "This Year's Events" },
				listAll: listAllView,
			};
		}

		if ( cfg.initialDate ) { options.initialDate = cfg.initialDate; }

		calInstance = new FullCalendar.Calendar( el, options );
		return calInstance;
	}

	// --- Init -------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof FullCalendar === 'undefined' ) { return; }
		detailOverlay = buildDetailModal();

		// Inline calendar (Events block on a page).
		var inlineEl = document.getElementById( 'dante-calendar' );
		if ( inlineEl ) {
			makeCalendar( inlineEl, inlineEl.getAttribute( 'data-click' ) || 'scroll', false ).render();
		}

		// Site-wide popup calendar.
		var overlay = document.getElementById( 'dante-cal-overlay' );
		var popupEl = document.getElementById( 'dante-calendar-popup' );
		var popupCal = null;

		function openPopup( e ) {
			if ( e ) { e.preventDefault(); }
			if ( ! overlay ) { return; }
			overlay.removeAttribute( 'hidden' );
			if ( ! popupCal && popupEl ) {
				popupCal = makeCalendar( popupEl, 'popup', true );
				popupCal.render();
			} else if ( popupCal ) {
				popupCal.updateSize();
			}
		}
		function closePopup() { if ( overlay ) { overlay.setAttribute( 'hidden', '' ); } }

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
}() );
