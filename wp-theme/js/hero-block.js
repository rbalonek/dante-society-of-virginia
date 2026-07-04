/**
 * Dante Society — "Full Screen Hero" block (editor).
 * Dynamic block: PHP renders the front end. In the editor we show a live preview
 * plus sidebar controls (no ServerSideRender). No build step.
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
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ColorPalette = wp.components.ColorPalette;
	var BaseControl = wp.components.BaseControl;

	var COLORS = [
		{ name: 'Gold', color: '#C8963E' },
		{ name: 'Green', color: '#1B4332' },
		{ name: 'Dark green', color: '#0D2B1F' },
		{ name: 'Cream', color: '#FAF3E0' },
		{ name: 'White', color: '#FFFFFF' },
	];

	wp.blocks.registerBlockType( 'dante/hero', {
		apiVersion: 2,
		title: 'Full Screen Hero',
		description: 'A full-screen banner with a title, line, text, and a button — all optional and configurable.',
		icon: 'cover-image',
		category: 'widgets',

		attributes: {
			title: { type: 'string', default: 'Dante Society of Virginia' },
			showTitle: { type: 'boolean', default: true },
			subtitle: { type: 'string', default: 'Celebrating Italian language, art, and culture in Central Virginia since 1998.' },
			showSubtitle: { type: 'boolean', default: true },
			showLine: { type: 'boolean', default: true },
			lineColor: { type: 'string', default: '#C8963E' },
			showButton: { type: 'boolean', default: true },
			buttonText: { type: 'string', default: 'See Upcoming Events' },
			buttonUrl: { type: 'string', default: '/events' },
			buttonColor: { type: 'string', default: '#C8963E' },
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'dante-hero-editor' } );

			// --- Sidebar controls ---------------------------------------
			var contentPanel = el( PanelBody, { title: 'Title & Text', initialOpen: true },
				el( ToggleControl, { key: 't1', label: 'Show title', checked: !! a.showTitle, onChange: function ( v ) { set( { showTitle: v } ); } } ),
				el( TextControl, { key: 't2', label: 'Title', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
				el( ToggleControl, { key: 't3', label: 'Show line under the title', checked: !! a.showLine, onChange: function ( v ) { set( { showLine: v } ); } } ),
				el( ToggleControl, { key: 't4', label: 'Show text', checked: !! a.showSubtitle, onChange: function ( v ) { set( { showSubtitle: v } ); } } ),
				el( TextareaControl, { key: 't5', label: 'Text', value: a.subtitle, onChange: function ( v ) { set( { subtitle: v } ); } } )
			);

			var buttonPanel = el( PanelBody, { title: 'Button', initialOpen: false },
				el( ToggleControl, { key: 'b1', label: 'Show button', checked: !! a.showButton, onChange: function ( v ) { set( { showButton: v } ); } } ),
				el( TextControl, { key: 'b2', label: 'Button text', value: a.buttonText, onChange: function ( v ) { set( { buttonText: v } ); } } ),
				el( TextControl, { key: 'b3', label: 'Button link', help: 'e.g. /events, or a full web address', value: a.buttonUrl, onChange: function ( v ) { set( { buttonUrl: v } ); } } )
			);

			var colorPanel = el( PanelBody, { title: 'Colors', initialOpen: false },
				el( BaseControl, { key: 'c1', label: 'Button color' },
					el( ColorPalette, { colors: COLORS, value: a.buttonColor, onChange: function ( v ) { set( { buttonColor: v || '#C8963E' } ); }, disableCustomColors: false } )
				),
				el( BaseControl, { key: 'c2', label: 'Line color' },
					el( ColorPalette, { colors: COLORS, value: a.lineColor, onChange: function ( v ) { set( { lineColor: v || '#C8963E' } ); }, disableCustomColors: false } )
				)
			);

			// --- Live preview -------------------------------------------
			var preview = el( 'div', {
				style: {
					minHeight: '360px',
					display: 'flex',
					flexDirection: 'column',
					alignItems: 'center',
					justifyContent: 'center',
					textAlign: 'center',
					padding: '48px 20px',
					color: '#FAF3E0',
					background: 'linear-gradient(rgba(13,43,31,0.55),rgba(13,43,31,0.8)), #2a3d31',
					borderRadius: '6px',
				},
			},
				a.showTitle ? el( 'h1', { key: 'h', style: { fontFamily: 'Playfair Display, serif', fontSize: '2.4rem', margin: 0, textShadow: '2px 2px 6px rgba(0,0,0,0.55)' } }, a.title || '' ) : null,
				a.showLine ? el( 'div', { key: 'l', style: { width: '80px', height: '3px', margin: '18px auto', background: a.lineColor } } ) : null,
				a.showSubtitle ? el( 'p', { key: 's', style: { fontSize: '1.15rem', maxWidth: '620px', lineHeight: 1.7, margin: 0, textShadow: '1px 1px 3px rgba(0,0,0,0.5)' } }, a.subtitle || '' ) : null,
				a.showButton ? el( 'span', { key: 'btn', style: { display: 'inline-block', marginTop: '30px', padding: '15px 32px', background: a.buttonColor, color: '#0D2B1F', fontWeight: 700, letterSpacing: '1.5px', textTransform: 'uppercase', borderRadius: '4px' } }, ( a.buttonText || '' ) + '  →' ) : null,
				el( 'p', { key: 'hint', style: { marginTop: '26px', fontSize: '12px', opacity: 0.7 } }, 'Background image: Customize → Background Images → “Homepage hero background”' )
			);

			return el( Fragment, {},
				el( InspectorControls, {}, contentPanel, buttonPanel, colorPanel ),
				el( 'div', blockProps, preview )
			);
		},

		// Dynamic block — rendered by PHP on the front end.
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
