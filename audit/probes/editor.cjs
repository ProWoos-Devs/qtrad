/* Behavior probes using a minimal WordPress store and DOM adapter.
 * Run: node audit/probes/editor.cjs
 * These exercise the actual editor.js; they are not browser integration tests.
 */
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const code = fs.readFileSync(path.join(__dirname, '../../qtranslate-next/assets/js/editor.js'), 'utf8');
const results = [];
function check(name, expected, actual) {
  results.push({ case: name, pass: JSON.stringify(expected) === JSON.stringify(actual), expected, actual });
}
function editor(config = {}) {
  const listeners = {};
  let subscribers = [];
  let middleware;
  let attrs = { title: '[:en]Hello[:de]Hallo[:fr]Bonjour[:]', content: '[:en]<p>Hello</p>[:de]<p>Hallo</p>[:fr]<p>Bonjour</p>[:]', excerpt: '', ...Object.fromEntries(Object.entries(config).filter(([key]) => ['title', 'content', 'excerpt'].includes(key))) };
  let saving = false;
  const select = {
    getEditedPostAttribute: key => attrs[key],
    getCurrentPostId: () => 42,
    getBlocks: () => attrs.content,
    isSavingPost: () => saving,
  };
  const dispatch = {
    editPost: patch => { Object.assign(attrs, patch); if (config.notifyDispatch) subscribers.slice().forEach(listener => listener()); },
    resetBlocks: content => { attrs.content = content; if (config.notifyDispatch) subscribers.slice().forEach(listener => listener()); },
  };
  const document = {
    readyState: 'complete', getElementById: () => null, querySelector: () => null,
    querySelectorAll: () => [], addEventListener: (event, listener) => { listeners[event] = listener; },
  };
  const wp = {
    data: { select: () => select, dispatch: () => dispatch, subscribe: listener => {
      subscribers.push(listener);
      return () => { subscribers = subscribers.filter(item => item !== listener); };
    } },
    blocks: { serialize: value => value, parse: value => value },
    apiFetch: { use: handler => { middleware = handler; } },
  };
  const context = { window: { qtuEditorConfig: { enabled: ['en', 'de'], editLang: 'en', writeFormat: 'keep', title: attrs.title, content: attrs.content, excerpt: '', ...config }, wp }, document, wp };
  vm.runInNewContext(code, context);
  return {
    tick: () => subscribers.slice().forEach(listener => listener()),
    click: lang => listeners.click({ target: { closest: () => ({ getAttribute: () => lang }) }, preventDefault() {} }),
    attrs: () => ({ ...attrs }),
    set: patch => Object.assign(attrs, patch),
    request: (url, data) => middleware({ method: 'POST', path: url, data }, options => options).data,
    saving: value => { saving = value; subscribers.slice().forEach(listener => listener()); },
  };
}
let e = editor();
e.tick();
check('block editor initially displays selected language', 'Hello', e.attrs().title);
e.click('de');
e.set({ title: 'Neu', content: '<p>Neu</p>' });
let payload = e.request('/wp/v2/posts/42', { title: 'Neu', content: '<p>Neu</p>', excerpt: '' });
check('block save preserves enabled English/German', '[:en]Hello[:de]Neu[:]', payload.title);
check('block save retains disabled French', true, payload.title.includes('[:fr]Bonjour'));
payload = e.request('/wp/v2/posts/99', { title: 'Unrelated title' });
check('middleware does not rewrite other post IDs', 'Unrelated title', payload.title);
payload = e.request('/wp/v2/templates/theme/header', { content: '<p>Header</p>' });
check('middleware does not rewrite templates with post content', '<p>Header</p>', payload.content);
payload = e.request('/wp/v2/books/42', { title: 'Neu', content: '<p>Neu</p>' });
check('custom post type save contains both languages', '[:en]Hello[:de]Neu[:]', payload.title);
e = editor();
e.tick();
e.click('de');
e.set({ title: 'Updated German title', content: '<p>literal [:en] tag</p>' });
payload = e.request('/wp/v2/posts/42', { title: 'Updated German title', content: '<p>literal [:en] tag</p>' });
check('literal tag in content does not discard title edits', true, payload.title.includes('Updated German title'));
e = editor({ title: '0', content: '0' });
e.tick();
payload = e.request('/wp/v2/posts/42', { title: '0', content: '0' });
check('JS join preserves zero string', '0', payload.title);
e = editor({ notifyDispatch: true });
e.tick();
let savingError = null;
try { e.saving(true); e.saving(false); } catch (error) { savingError = error.message; }
check('save completion does not recursively dispatch', null, savingError);
console.log(JSON.stringify({ passed: results.filter(r => r.pass).length, total: results.length, cases: results }, null, 2));
