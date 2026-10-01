(function () {
  'use strict';
  var cfg = window.qtuEditorConfig, codec = window.qtuCodec;
  if (!cfg || !codec || cfg.editorMode === 'raw') return;
  var enabled = cfg.enabled || [], fields = ['title', 'content', 'excerpt'];
  var state = { active: enabled.indexOf(cfg.editLang) >= 0 ? cfg.editLang : enabled[0], formats: {}, ready: false, presenting: false };
  fields.forEach(function (key) {
    state[key] = codec.split(cfg[key] || '', enabled, false);
    state.formats[key] = cfg.writeFormat !== 'keep' ? cfg.writeFormat : codec.detect(cfg[key]) || 'comment';
  });
  function mark(lang, announce) {
    state.active = lang;
    var hidden = document.getElementById('qtu_edit_lang');
    if (hidden) hidden.value = lang;
    document.querySelectorAll('.qtu-lsb__btn').forEach(function (button) {
      var selected = button.getAttribute('data-qtu-lang') === lang;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.disabled = false;
    });
    var locale = (cfg.locales && cfg.locales[lang] || lang).replace(/_/g, '-');
    var dir = cfg.directions && cfg.directions[lang] || 'ltr';
    function apply(doc, canvas) {
      if (canvas && doc.body) { doc.body.setAttribute('lang', locale); doc.body.setAttribute('dir', dir); }
      doc.querySelectorAll('#title, #content, #excerpt, .editor-post-title__input, .block-editor-writing-flow, .editor-post-excerpt textarea').forEach(function (node) {
        node.setAttribute('lang', locale); node.setAttribute('dir', dir);
      });
    }
    apply(document, false);
    document.querySelectorAll('iframe[name="editor-canvas"], .editor-visual-editor iframe').forEach(function (frame) {
      try {
        if (frame.contentDocument) {
          apply(frame.contentDocument, true);
          observeDocument(frame.contentDocument);
        }
        if (!frame.qtnLanguageBound) { frame.qtnLanguageBound = true; frame.addEventListener('load', function () { mark(state.active); }); }
      } catch (error) { /* A third-party cross-origin canvas cannot be accessed. */ }
    });
    var ed = window.tinymce && window.tinymce.get('content');
    if (ed && ed.getBody()) { ed.getBody().setAttribute('lang', locale); ed.getBody().setAttribute('dir', dir); }
    var status = document.getElementById('qtu-language-status');
    if (status && announce) status.textContent = (cfg.languageMessage || 'Editing %s.').replace('%s', cfg.names[lang] || lang);
  }
  var observedDocuments = [];
  function observeDocument(doc) {
    if (!window.MutationObserver || !doc.body || observedDocuments.indexOf(doc) >= 0) return;
    observedDocuments.push(doc);
    new window.MutationObserver(function () { if (state.ready) mark(state.active); }).observe(doc.body, {childList:true, subtree:true});
  }
  function writeStores() {
    document.querySelectorAll('textarea.qtu-store').forEach(function (node) {
      var field = node.getAttribute('data-qtu-store'), lang = node.getAttribute('data-qtu-lang');
      node.value = state[field][lang] || '';
    });
  }
  function editor() {
    if (!window.wp || !wp.data) return null;
    var select = wp.data.select('core/editor');
    return select && select.getEditedPostAttribute ? select : null;
  }
  function classicContent() {
    var ed = window.tinymce && window.tinymce.get('content'), node = document.getElementById('content');
    return ed && ed.initialized && ed.getBody() && !ed.isHidden() ? ed.getContent() : node ? node.value : '';
  }
  function preserveBlockIds(next, previous) {
    return next.map(function (block, index) {
      var old = previous[index];
      if (old && old.name === block.name && old.clientId) {
        block.clientId = old.clientId;
        block.innerBlocks = preserveBlockIds(block.innerBlocks || [], old.innerBlocks || []);
      }
      return block;
    });
  }
  function capture() {
    if (state.classic) {
      fields.forEach(function (key) {
        var node = document.getElementById(key);
        if (node) state[key][state.active] = key === 'content' ? classicContent() : node.value;
      });
    } else {
      var select = editor(); if (!select) return;
      fields.forEach(function (key) {
        var value = select.getEditedPostAttribute(key);
        if (typeof value === 'string') state[key][state.active] = value;
      });
    }
    writeStores();
  }
  function present(lang, announce) {
    if (state.presenting) return;
    state.presenting = true;
    try {
      mark(lang, announce);
      if (state.classic) {
        fields.forEach(function (key) { var node = document.getElementById(key); if (node) node.value = state[key][lang] || ''; });
        var ed = window.tinymce && window.tinymce.get('content');
        if (ed && ed.initialized && ed.getBody() && !ed.isHidden()) ed.setContent(state.content[lang] || '');
      } else {
        var select = editor(); if (!select) return;
        var attrs = {};
        fields.forEach(function (key) {
          var value = state[key][lang] || '';
          if (select.getEditedPostAttribute(key) !== value) attrs[key] = value;
        });
        if (Object.keys(attrs).length) {
          if ('content' in attrs && wp.blocks) {
            var blockDispatch = wp.data.dispatch('core/block-editor');
            if (blockDispatch && blockDispatch.resetBlocks) {
              var blockSelect = wp.data.select('core/block-editor');
              var previous = blockSelect && blockSelect.getBlocks ? blockSelect.getBlocks() : [];
              blockDispatch.resetBlocks(preserveBlockIds(wp.blocks.parse(attrs.content), previous));
            }
          }
          wp.data.dispatch('core/editor').editPost(attrs);
        }
      }
    } finally { state.presenting = false; }
  }
  function packed() {
    capture();
    return {
      title: codec.join(state.title, state.formats.title, enabled, cfg.forceMarkers),
      content: codec.joinContent(state.content, state.formats.content, enabled, cfg.forceMarkers),
      excerpt: codec.join(state.excerpt, state.formats.excerpt, enabled, cfg.forceMarkers)
    };
  }
  function installFetch() {
    if (!wp.apiFetch || !wp.apiFetch.use) return;
    wp.apiFetch.use(function (options, next) {
      var select = editor(), id = select && select.getCurrentPostId && select.getCurrentPostId();
      var method = (options.method || 'GET').toUpperCase();
      var target = String(options.path || options.url || '').split('?')[0];
      var base = '/' + (cfg.restNamespace || 'wp/v2') + '/' + (cfg.restBase || 'posts') + '/' + id;
      if (options.url) {
        try {
          var parsed = new window.URL(target, window.location.href), root = new window.URL(cfg.restRoot, window.location.href);
          target = parsed.origin === root.origin && parsed.pathname.indexOf(root.pathname) === 0 ? '/' + parsed.pathname.slice(root.pathname.length) : '';
        } catch (error) { target = ''; }
      }
      var own = id && (target === base || target === base + '/autosaves');
      if (state.ready && own && /^(POST|PUT|PATCH)$/.test(method) && options.data) {
        var payload = packed(), data = Object.assign({}, options.data);
        fields.forEach(function (key) {
          if (!(key in data)) return;
          // Explicit multilingual requests already contain a complete value.
          var value = typeof data[key] === 'object' && data[key] !== null ? data[key].raw : data[key];
          if (!codec.hasTags(value)) data[key] = payload[key];
        });
        data.qtu_language = state.active;
        if (cfg.fieldNonce) data.qtu_editor_nonce = cfg.fieldNonce;
        options = Object.assign({}, options, { data: data });
      }
      var result = next(options);
      if (state.ready && own && target.indexOf('/autosaves') < 0 && /^(POST|PUT|PATCH)$/.test(method) && result && result.then) {
        return result.then(function (post) {
          if (!post || !post.id) return post;
          var displayed = Object.assign({}, post);
          fields.forEach(function (key) {
            if (!post[key] || typeof post[key].raw !== 'string') return;
            // Keep the store's saved value consistent with the visible language,
            // so presenting translated fields does not make a successful save dirty.
            displayed[key] = Object.assign({}, post[key], { raw: codec.split(post[key].raw, enabled, false)[state.active] || '' });
          });
          return displayed;
        });
      }
      return result;
    });
  }
  function bindClassic() {
    var form = document.getElementById('post');
    if (!form || !fields.some(function (key) { return document.getElementById(key); })) return false;
    state.classic = state.ready = true;
    present(state.active, true);
    function bindRichEditor(ed) {
      if (!ed || ed.id !== 'content' || ed.qtnLanguageBound) return;
      ed.qtnLanguageBound = true;
      ed.on('init', function () { ed.setContent(state.content[state.active] || ''); mark(state.active); });
    }
    if (window.tinymce && window.tinymce.on) {
      bindRichEditor(window.tinymce.get('content'));
      window.tinymce.on('AddEditor', function (event) { bindRichEditor(event.editor); });
    }
    form.addEventListener('submit', function () {
      capture();
      var flag = document.getElementById('qtu_js');
      if (flag) flag.value = '1';
    });
    if (window.wp && wp.autosave && wp.autosave.getPostData) {
      var original = wp.autosave.getPostData;
      wp.autosave.getPostData = function () {
        var data = original.apply(this, arguments), values = packed();
        data.post_title = values.title; data.content = values.content; data.excerpt = values.excerpt;
        data.qtu_edit_lang = state.active;
        return data;
      };
    }
    return true;
  }
  function bindBlock() {
    if (!editor()) return false;
    installFetch();
    var wasSaving = false;
    function observe() {
      var select = editor(); if (!select || state.presenting) return;
      if (!state.ready) {
        if (!select.getCurrentPostId || !select.getCurrentPostId()) return;
        fields.forEach(function (key) {
          var value = select.getEditedPostAttribute(key);
          if (codec.hasTags(value)) { state[key] = codec.split(value, enabled, false); state.formats[key] = cfg.writeFormat !== 'keep' ? cfg.writeFormat : codec.detect(value); }
          else if (typeof value === 'string') state[key][state.active] = value;
        });
        state.ready = true;
        present(state.active, true);
      }
      var saving = !!(select.isSavingPost && select.isSavingPost());
      var finished = wasSaving && !saving;
      // Update before dispatching: subscriptions can fire synchronously.
      wasSaving = saving;
      if (finished) {
        fields.forEach(function (key) {
          var value = select.getEditedPostAttribute(key);
          if (codec.hasTags(value)) state[key] = codec.split(value, enabled, false);
          else if (typeof value === 'string') state[key][state.active] = value;
        });
        present(state.active);
      }
    }
    wp.data.subscribe(observe);
    observe();
    return true;
  }
  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('.qtu-lsb__btn');
    if (!button || !state.ready) return;
    var lang = button.getAttribute('data-qtu-lang');
    if (enabled.indexOf(lang) < 0 || lang === state.active) return;
    capture(); present(lang, true);
    // Keep keyboard focus on the activated language button.
  });
  var attempts = 0;
  function boot() {
    if (bindClassic() || bindBlock()) { observeDocument(document); return; }
    if (++attempts < 100) window.setTimeout(boot, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
