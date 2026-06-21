/**
 * Dante Society — "Events (calendar + list)" block.
 * Dynamic block: PHP (render_callback) produces the front-end markup. In the
 * editor we show a simple, dependency-free placeholder (no ServerSideRender,
 * which can crash the editor's positioning logic). No build step.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;

	var DISPLAY_LABEL = { both: 'Calendar + event list', list: 'Event list', calendar: 'Calendar' };
	var SCOPE_LABEL = { all: 'all events', year: "this year's events", upcoming: 'upcoming events' };
	var STYLE_LABEL = { cards: 'card style (with images)', simple: 'simple list style' };

	wp.blocks.registerBlockType( 'dante/events', {
		apiVersion: 2,
		title: 'Events (calendar + list)',
		description: 'Shows the events calendar and the auto-generated list of events. Manage events under the "Events" menu.',
		icon: 'calendar-alt',
		category: 'widgets',

		attributes: {
			clickBehavior: { type: 'string', default: 'scroll' },
			display: { type: 'string', default: 'both' },
			scope: { type: 'string', default: 'all' },
			listStyle: { type: 'string', default: 'cards' },
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			var summary = ( DISPLAY_LABEL[ a.display ] || DISPLAY_LABEL.both ) +
				' — ' + ( SCOPE_LABEL[ a.scope ] || SCOPE_LABEL.all ) +
				( a.display !== 'calendar' ? ', ' + ( STYLE_LABEL[ a.listStyle ] || STYLE_LABEL.cards ) : '' );

			var controls = [
				el( SelectControl, {
					key: 'display',
					label: 'Show',
					value: a.display || 'both',
					options: [
						{ label: 'Calendar + list', value: 'both' },
						{ label: 'List only', value: 'list' },
						{ label: 'Calendar only', value: 'calendar' },
					],
					onChange: function ( v ) { set( { display: v } ); },
				} ),
				el( SelectControl, {
					key: 'scope',
					label: 'Which events',
					value: a.scope || 'all',
					options: [
						{ label: 'All events', value: 'all' },
						{ label: "This year's events", value: 'year' },
						{ label: 'Upcoming events only', value: 'upcoming' },
					],
					onChange: function ( v ) { set( { scope: v } ); },
				} ),
				el( SelectControl, {
					key: 'listStyle',
					label: 'List style',
					value: a.listStyle || 'cards',
					options: [
						{ label: 'Cards (image beside text)', value: 'cards' },
						{ label: 'Simple (date + title, like Programs)', value: 'simple' },
					],
					onChange: function ( v ) { set( { listStyle: v } ); },
				} ),
				el( SelectControl, {
					key: 'click',
					label: 'When a visitor clicks a calendar event',
					value: a.clickBehavior || 'scroll',
					options: [
						{ label: 'Scroll to the event on the page', value: 'scroll' },
						{ label: 'Show a popup with details', value: 'popup' },
					],
					onChange: function ( v ) { set( { clickBehavior: v } ); },
				} ),
			];

			var placeholder = el(
				'div',
				{
					style: {
						border: '2px dashed #C8963E',
						borderRadius: '8px',
						padding: '24px',
						textAlign: 'center',
						background: '#FAF3E0',
						color: '#1B4332',
						fontFamily: 'Lato, sans-serif',
					},
				},
				el( 'div', { style: { fontSize: '28px', marginBottom: '6px' } }, '📅' ),
				el( 'strong', { style: { display: 'block', fontSize: '16px' } }, 'Events block' ),
				el( 'p', { style: { margin: '6px 0 0' } }, summary ),
				el( 'p', { style: { margin: '6px 0 0', opacity: 0.7, fontSize: '13px' } },
					'The calendar and event list appear here on the live page.' )
			);

			return el(
				Fragment,
				{},
				el( InspectorControls, {}, el( PanelBody, { title: 'Events Display', initialOpen: true }, controls ) ),
				el( 'div', blockProps, placeholder )
			);
		},

		// Dynamic block — rendered by PHP on the front end.
		save: function () {
			return null;
		},
	} );
} )( window.wp );
