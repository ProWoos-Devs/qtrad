/* ACF field types with one input per language: the base field's behavior, applied to the language that was clicked. */
(function ($) {
	'use strict';
	if (typeof acf === 'undefined' || !acf.models || !acf.registerFieldType) { return; }
	var config = window.qtradAcf || {}, fallback = config['default'] || '';

	function languageOf($el) {
		var $wrap = $el && $el.closest ? $el.closest('.qtrad-acf-lang') : $();
		return $wrap.length ? $wrap.data('lang') : fallback;
	}

	function extend(baseName, type, control, input, extra) {
		var Base = acf.models[baseName];
		if (!Base) { return; }
		var proto = Base.prototype, events = proto.events || {}, overrides = {
			type: type,
			$control: function () { return this.$('.qtrad-acf-lang[data-lang="' + (this.lang || fallback) + '"] ' + control); },
			$input: function () { return this.$('.qtrad-acf-lang[data-lang="' + (this.lang || fallback) + '"] ' + input); }
		};
		Object.keys(events).forEach(function (event) {
			var handler = events[event];
			if (typeof proto[handler] !== 'function' || overrides[handler]) { return; }
			overrides[handler] = function (e, $el) {
				this.lang = languageOf($el);
				return proto[handler].apply(this, arguments);
			};
		});
		acf.registerFieldType(Base.extend($.extend(overrides, extra || {})));
	}

	extend('UrlField', 'qtranslate_url', '.acf-input-wrap', 'input[type="url"]');
	extend('ImageField', 'qtranslate_image', '.acf-image-uploader', 'input[type="hidden"]');
	extend('FileField', 'qtranslate_file', '.acf-file-uploader', 'input[type="hidden"]');
	extend('WysiwygField', 'qtranslate_wysiwyg', '.acf-editor-wrap', 'textarea', {
		initializeEditor: function () {
			var self = this;
			this.$('.acf-editor-wrap').each(function () {
				var $wrap = $(this), $textarea = $wrap.find('textarea'), id = $textarea.attr('id'), newId = acf.uniqueId('acf-editor-');
				acf.rename({ target: $wrap, search: id, replace: newId, destructive: true });
				acf.tinymce.initialize(newId, { tinymce: true, quicktags: true, toolbar: self.get('toolbar'), mode: self.getMode(), field: self });
			});
		}
	});
	extend('PostObjectField', 'qtranslate_post_object', 'select', 'select', {
		initialize: function () {
			// ACF answers the query for the base type; one select2 per language.
			var self = this, action = this.get('ajax_action') || 'acf/fields/post_object/query';
			this.inherit(this.$input());
			this.select2 = [];
			if (this.get('ui')) {
				this.$('select').each(function () {
					self.select2.push(acf.newSelect2($(this), { field: self, ajax: self.get('ajax'), multiple: false, placeholder: self.get('placeholder'), allowNull: self.get('allow_null'), ajaxAction: action }));
				});
			}
		},
		onRemove: function () {
			(this.select2 || []).forEach(function (instance) { instance.destroy(); });
		}
	});
})(jQuery);
