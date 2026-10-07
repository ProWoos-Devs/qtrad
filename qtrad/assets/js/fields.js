/* One input per language for single text settings that are stored with language markers. */
(function () {
	'use strict';
	var cfg = window.qtradFieldsConfig, codec = window.qtradCodec;
	if (!cfg || !codec || !cfg.selector) { return; }
	var langs = cfg.enabled || [], pattern = cfg.namePattern ? new RegExp(cfg.namePattern) : null, count = 0;

	function wanted(field) {
		if (field.hidden || field.readOnly || field.disabled || field.closest('.qtrad-fields')) { return false; }
		if (field.tagName === 'INPUT' && field.type !== 'text') { return false; }
		return cfg.always || codec.hasTags(field.value) || (pattern && pattern.test(field.name || ''));
	}

	function enhance(field) {
		var area = field.tagName === 'TEXTAREA', texts = codec.split(field.value, langs, false), base = field.id || ('qtrad-field-' + (++count));
		var label = field.id ? document.querySelector('label[for="' + field.id.replace(/"/g, '\\"') + '"]') : null;
		var name = label ? label.textContent.trim() : (field.getAttribute('aria-label') || field.name || '');
		var group = document.createElement('div'), inputs = {};
		group.className = 'qtrad-fields';
		group.setAttribute('role', 'group');
		group.setAttribute('aria-label', name);
		langs.forEach(function (lang) {
			var row = document.createElement('div'), tag = document.createElement('label'), input = document.createElement(area ? 'textarea' : 'input');
			row.className = 'qtrad-fields__lang';
			input.id = base + '-qtrad-' + lang;
			tag.htmlFor = input.id;
			tag.textContent = cfg.names[lang] || lang;
			if (!area) { input.type = 'text'; }
			else { input.rows = field.rows || 3; input.cols = field.cols || 20; }
			input.className = field.className;
			input.value = texts[lang] || '';
			input.lang = cfg.locales[lang] || lang;
			input.dir = cfg.directions[lang] || 'ltr';
			if (field.placeholder && lang === cfg.defaultLanguage) { input.placeholder = field.placeholder; }
			input.addEventListener('input', sync);
			inputs[lang] = input;
			row.appendChild(tag);
			row.appendChild(input);
			group.appendChild(row);
		});
		function sync() {
			langs.forEach(function (lang) { texts[lang] = inputs[lang].value; });
			// Languages without an input keep their stored text, since split() returned them too.
			field.value = codec.join(texts, 'bracket', langs, !!cfg.forceMarkers);
			field.dispatchEvent(new Event('change', { bubbles: true }));
		}
		field.hidden = true;
		field.setAttribute('aria-hidden', 'true');
		field.tabIndex = -1;
		field.parentNode.insertBefore(group, field.nextSibling);
		if (label && langs.length) { label.htmlFor = inputs[langs[0]].id; }
	}

	function run() {
		document.querySelectorAll(cfg.selector).forEach(function (field) {
			if (wanted(field)) { enhance(field); }
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); }
	else { run(); }
})();
