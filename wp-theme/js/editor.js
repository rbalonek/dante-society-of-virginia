/**
 * Dante Society — block editor behavior (no build step; uses global wp.*).
 *
 *  1. Defaults the fixed top toolbar ON, so editing controls are always in the
 *     same place (predictable for non-technical editors). Set once per browser
 *     so an editor can still turn it off later.
 *  2. Clearly states the site's mobile breakpoint at the top of the editor.
 */
( function ( wp ) {
	if ( ! wp || ! wp.domReady ) {
		return;
	}

	wp.domReady( function () {
		var cfg = window.danteEditor || {};
		var bp = parseInt( cfg.breakpoint, 10 ) || 900;

		// 1. Default the fixed top toolbar on (once per browser).
		try {
			if ( ! window.localStorage.getItem( 'danteFixedToolbarInit' ) ) {
				wp.data.dispatch( 'core/preferences' ).set( 'core', 'fixedToolbar', true );
				window.localStorage.setItem( 'danteFixedToolbarInit', '1' );
			}
		} catch ( e ) {}

		// 2. State the mobile breakpoint clearly at the top of the editor.
		try {
			wp.data.dispatch( 'core/notices' ).createInfoNotice(
				'This site switches to its mobile layout at ' + bp + 'px wide. ' +
					'Use the Tablet or Mobile preview (the screen icon near the top-right) ' +
					'to see how a page looks on smaller screens.',
				{
					id: 'dante-breakpoint-note',
					isDismissible: false,
				}
			);
		} catch ( e ) {}
	} );
} )( window.wp );
