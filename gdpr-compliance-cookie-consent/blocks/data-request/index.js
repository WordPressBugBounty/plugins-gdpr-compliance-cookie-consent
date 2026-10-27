/* Editor side of a dynamic block: the markup is rendered by PHP (the same callback as the [stm-gpdr-data-access]
   shortcode), the editor shows it through ServerSideRender and edits the attributes in the sidebar. */
( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'gdpr-compliance-cookie-consent/data-request', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Settings', 'gdpr-compliance-cookie-consent' ) },
						el( components.TextControl, {
							label: __( 'Title', 'gdpr-compliance-cookie-consent' ),
							value: a.title,
							onChange: function ( v ) {
								props.setAttributes( { title: v } );
							},
						} ),
						el( components.TextareaControl, {
							label: __( 'Description', 'gdpr-compliance-cookie-consent' ),
							value: a.description,
							onChange: function ( v ) {
								props.setAttributes( { description: v } );
							},
						} )
					)
				),
				el( serverSideRender, { block: 'gdpr-compliance-cookie-consent/data-request', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
