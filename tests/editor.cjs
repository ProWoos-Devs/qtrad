/* Behavior probes using a minimal WordPress store and DOM adapter.
 * Run: node audit/probes/editor.cjs
 * These exercise the actual editor.js; they are not browser integration tests.
 */
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const code = fs.readFileSync(path.join(__dirname, '../qtrad/assets/js/editor.js'), 'utf8');
const codec = require('../qtrad/assets/js/codec.js');
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
  let parsedId = 0;
  let blocks = [{name:'core/freeform', clientId:'initial-block', attributes:{content:attrs.content}, innerBlocks:[]}];
  const select = {
    getEditedPostAttribute: key => attrs[key],
    getCurrentPostId: () => 42,
    getBlocks: () => blocks,
    isSavingPost: () => saving,
  };
  const dispatch = {
    editPost: patch => { Object.assign(attrs, patch); if (config.notifyDispatch) subscribers.slice().forEach(listener => listener()); },
    resetBlocks: value => { blocks = value; attrs.content = value.map(block => block.attributes.content).join(''); if (config.notifyDispatch) subscribers.slice().forEach(listener => listener()); },
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
    blocks: { serialize: value => value.map(block => block.attributes.content).join(''), parse: value => [{name:'core/freeform',clientId:'parsed-'+(++parsedId),attributes:{content:value},innerBlocks:[]}] },
    apiFetch: { use: handler => { middleware = handler; } },
  };
  const context = { window: { URL, location: {href:'http://example.test/wp-admin/post.php'}, qtuCodec: codec, qtuEditorConfig: { enabled: ['en', 'de'], editLang: 'en', writeFormat: 'keep', restRoot: 'http://example.test/wp-json/', title: attrs.title, content: attrs.content, excerpt: '', ...config }, wp }, document, wp };
  if (config.preloaded) { attrs.title = 'Hello'; attrs.content = '<p>Hello</p>'; blocks[0].attributes.content = attrs.content; }
  vm.runInNewContext(code, context);
  return {
    tick: () => subscribers.slice().forEach(listener => listener()),
    click: lang => listeners.click({ target: { closest: () => ({ getAttribute: () => lang }) }, preventDefault() {} }),
    attrs: () => ({ ...attrs }),
    blocks: () => blocks,
    set: patch => Object.assign(attrs, patch),
    request: (url, data) => middleware({ method: 'POST', path: url, data }, options => options).data,
    requestFull: (url, data) => middleware({method:'POST',url,data}, options=>options).data,
    saving: value => { saving = value; subscribers.slice().forEach(listener => listener()); },
  };
}
let e = editor();
e.tick();
check('block editor initially displays selected language', 'Hello', e.attrs().title);
e.click('de');
check('language switch preserves existing classic block identity', 'initial-block', e.blocks()[0].clientId);
e.set({ title: 'Neu', content: '<p>Neu</p>' });
let payload = e.request('/wp/v2/posts/42', { title: 'Neu', content: '<p>Neu</p>', excerpt: '' });
check('block save preserves enabled English/German', '[:en]Hello[:de]Neu[:fr]Bonjour[:]', payload.title);
check('block save retains disabled French', true, payload.title.includes('[:fr]Bonjour'));
payload = e.request('/wp/v2/posts/99', { title: 'Unrelated title' });
check('middleware does not rewrite other post IDs', 'Unrelated title', payload.title);
const preloaded = editor({preloaded:true});
preloaded.set({title:'Changed English'});
check('plain editor preload retains other and disabled translations', '[:en]Changed English[:de]Hallo[:fr]Bonjour[:]', preloaded.request('/wp/v2/posts/42',{title:'Changed English'}).title);
payload = e.request('/wp/v2/templates/theme/header', { content: '<p>Header</p>' });
check('middleware does not rewrite templates with post content', '<p>Header</p>', payload.content);
e = editor({ restBase: 'books' });
e.click('de'); e.set({ title: 'Neu' });
payload = e.request('/wp/v2/books/42', { title: 'Neu', content: '<p>Neu</p>' });
check('custom post type save contains both languages', '[:en]Hello[:de]Neu[:fr]Bonjour[:]', payload.title);
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
e = editor({ notifyDispatch: true });
e.saving(true); e.set({title:'Typed during save'}); e.saving(false);
check('save completion preserves edits made during request', 'Typed during save', e.attrs().title);
e = editor();
check('full external REST URL is left untouched', 'External title', e.requestFull('https://other.example/wp-json/wp/v2/posts/42', {title:'External title'}).title);
e.click('de'); e.set({title:'Local title'});
check('full local REST URL packs only its current post', true, e.requestFull('http://example.test/wp-json/wp/v2/posts/42', {title:'Local title'}).title.includes('[:en]Hello'));
// A classic custom post type can support a title without a content editor.
const classicEvents = {}, nodes = {
  title:{value:'[:en]Hello[:de]Hallo[:fr]Bonjour[:]'},
  post:{addEventListener:(name,fn)=>{classicEvents[name]=fn;}},
  qtu_js:{value:''}, qtu_edit_lang:{value:'en'}
};
const classicDocument = {readyState:'complete', getElementById:key=>nodes[key] || null, querySelectorAll:()=>[], addEventListener:(name,fn)=>{classicEvents[name]=fn;}};
vm.runInNewContext(code, {window:{qtuCodec:codec, qtuEditorConfig:{enabled:['en','de'], editLang:'en', writeFormat:'keep', title:nodes.title.value, content:'', excerpt:''}}, document:classicDocument});
check('title-only classic editor initializes', 'Hello', nodes.title.value);
classicEvents.click({target:{closest:()=>({getAttribute:()=> 'de'})}});
check('title-only classic editor switches language', 'Hallo', nodes.title.value);
classicEvents.submit();
check('title-only classic editor submits scoped stores', '1', nodes.qtu_js.value);
console.log(JSON.stringify({ passed: results.filter(r => r.pass).length, total: results.length, cases: results }, null, 2));

process.exitCode = results.some(r => !r.pass) ? 1 : 0;
