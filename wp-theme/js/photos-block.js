/**
 * Dante Society — "Photo Collage" block (editor).
 * Dynamic block: PHP renders the collage on the front end. In the editor we show
 * a simple placeholder plus a size control (no ServerSideRender). No build step.
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

	var SIZE_LABEL = { small: 'small', medium: 'medium', large: 'large' };

	wp.blocks.registerBlockType( 'dante/photos', {
		apiVersion: 2,
		title: 'Photo Collage',
		description: 'A collage of all your Photos (add them under the Photos menu).',
		icon: 'format-gallery',
		category: 'widgets',

		attributes: {
			size: { type: 'string', default: 'medium' },
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			var controls = el( SelectControl, {
				label: 'Photo size',
				value: a.size || 'medium',
				options: [
					{ label: 'Small (more per row)', value: 'small' },
					{ label: 'Medium', value: 'medium' },
					{ label: 'Large (fewer per row)', value: 'large' },
				],
				onChange: function ( v ) { set( { size: v } ); },
			} );

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
				el( 'div', { style: { fontSize: '28px', marginBottom: '6px' } }, '🖼' ),
				el( 'strong', { style: { display: 'block', fontSize: '16px' } }, 'Photo Collage' ),
				el( 'p', { style: { margin: '6px 0 0' } }, 'Shows every picture from the Photos menu (' + ( SIZE_LABEL[ a.size ] || 'medium' ) + ' size).' ),
				el( 'p', { style: { margin: '6px 0 0', opacity: 0.7, fontSize: '13px' } },
					'Add pictures under Photos → Add Photo. They appear here on the live page.' )
			);

			return el(
				Fragment,
				{},
				el( InspectorControls, {}, el( PanelBody, { title: 'Photo Collage', initialOpen: true }, controls ) ),
				el( 'div', blockProps, placeholder )
			);
		},

		// Dynamic block — rendered by PHP on the front end.
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
