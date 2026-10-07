/* Language switcher block: settings in the sidebar, the server renders the switcher. */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks || !wp.element) { return; }
	var el = wp.element.createElement, __ = wp.i18n.__;
	var blockEditor = wp.blockEditor || wp.editor, components = wp.components;
	var ServerSideRender = wp.serverSideRender || components.ServerSideRender;
	wp.blocks.registerBlockType('qtrad/language-switcher', {
		edit: function (props) {
			var blockProps = blockEditor.useBlockProps ? blockEditor.useBlockProps() : {};
			return el(wp.element.Fragment, null,
				el(blockEditor.InspectorControls, null,
					el(components.PanelBody, { title: __('Display', 'qtrad') },
						el(components.SelectControl, {
							label: __('Show languages as', 'qtrad'),
							value: props.attributes.display,
							options: [
								{ value: 'text', label: __('Text only', 'qtrad') },
								{ value: 'both', label: __('Flags and text', 'qtrad') },
								{ value: 'image', label: __('Flags', 'qtrad') },
								{ value: 'short', label: __('Language codes', 'qtrad') },
								{ value: 'dropdown', label: __('Dropdown', 'qtrad') }
							],
							onChange: function (value) { props.setAttributes({ display: value }); }
						})
					)
				),
				el('div', blockProps, el(ServerSideRender, { block: 'qtrad/language-switcher', attributes: props.attributes }))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
