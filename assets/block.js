( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var c = wp.components;

	wp.blocks.registerBlockType( 'wp-holidays/hero', {
		apiVersion: 2,
		title: 'Hero ngày lễ',
		description: 'Tự hiển thị hero theo ngày lễ đang diễn ra.',
		icon: 'calendar-alt',
		category: 'design',
		attributes: {
			holiday: { type: 'string', default: '' },
			align: { type: 'string' },
		},
		supports: { align: [ 'wide', 'full' ], html: false },

		edit: function ( props ) {
			var options = [ { value: '', label: 'Tự động theo ngày' } ].concat( window.wphHolidays || [] );

			return el(
				Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: 'Ngày lễ' },
						el( c.SelectControl, {
							label: 'Hiển thị',
							help: 'Ở chế độ tự động, trình soạn thảo xem trước ngày lễ sắp tới. Ngoài site, hero chỉ hiện đúng dịp lễ.',
							value: props.attributes.holiday,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { holiday: value } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el( wp.serverSideRender, {
						block: 'wp-holidays/hero',
						attributes: props.attributes,
					} )
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )( window.wp );
