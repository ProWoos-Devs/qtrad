/* Run only against the disposable localhost fixture prepared by tests/wordpress.php.
 * node tests/browser.cjs tests/results/wordpress-6.8.json /path/to/axe.min.js
 * Requires Playwright. Browser credentials belong exclusively to that fixture.
 */
const fs = require('node:fs');
const assert = require('node:assert/strict');
let chromium; try { ({chromium} = require('playwright')); } catch { ({chromium} = require('/usr/lib/node_modules/playwright')); }
(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const base = 'http://127.0.0.1:8931', url = base + '/' + fixture.browser_post_slug + '/';
  const results = { checks: [], accessibility: [], errors: [], errorDetails: [] };
  function check(name, actual, expected = true) { results.checks.push({name, actual, expected, pass: JSON.stringify(actual) === JSON.stringify(expected)}); }
  const browser = await chromium.launch({headless: true});
  const page = await browser.newPage();
  try {
  page.on('pageerror', err => { results.errors.push(err.message); const detail = {url:page.url(),afterCheck:results.checks.at(-1)?.name,stack:err.stack}; results.errorDetails.push(detail); page.evaluate(() => ({editors: window.tinymce?.editors?.map(e => ({id:e.id,initialized:e.initialized,removed:e.removed,body:!!e.getBody()})),blocks:window.wp?.data?.select('core/block-editor')?.getBlocks()?.map(b => ({name:b.name,id:b.clientId})),content:window.wp?.data?.select('core/editor')?.getEditedPostAttribute('content')})).then(value => {detail.context=value;}).catch(() => {}); });
  async function axe(scope, name) {
    await page.addScriptTag({path: process.argv[3]});
    const report = await page.evaluate(async selector => {
      const result = await axe.run({include: [[selector]]}, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']}});
      return {violations: result.violations.map(v => ({id:v.id, impact:v.impact, nodes:v.nodes.map(n => n.target)})), incomplete: result.incomplete.map(v => v.id), passes:result.passes.length};
    }, scope);
    results.accessibility.push({name, ...report});
  }
  for (const [name, path, headers, status] of [
    ['German page', '/de/' + fixture.browser_post_slug + '/', {}, 200],
    ['Canonical default', '/en/' + fixture.browser_post_slug + '/', {}, 302],
    ['Browser negotiation', '/', {'Accept-Language':'de'}, 302],
    ['REST German', '/wp-json/wp/v2/posts/' + fixture.browser_post_id + '?lang=de', {}, 200],
    ['Query REST German', '/?rest_route=/wp/v2/posts/' + fixture.browser_post_id + '&lang=de', {}, 200],
    ['Sitemap', '/wp-sitemap.xml', {}, 200],
    ['Unknown host redirects to configured home', '/?lang=de', {Host:'untrusted.example:8931'}, 302]
  ]) {
    const response = await fetch(base + path, {headers, redirect:'manual'});
    check(name + ' status', response.status, status);
    const body = await response.text();
    if (name.includes('REST')) { check(name + ' rendered value', JSON.parse(body).title.rendered, 'Hallo'); check(name + ' no language cookie', response.headers.get('set-cookie'), null); }
    if (name.includes('host')) check(name + ' destination', response.headers.get('location'), base + '/de/');
    if (name === 'German page') check('English alternate is canonical', body.includes('hreflang="en-US" href="' + url + '"'));
  }
  const homepage = await fetch(base + '/', {headers:{'Accept-Language':'en'}});
  check('Default homepage remains uncacheable', homepage.headers.get('cache-control').includes('no-cache'));
  check('Default homepage varies with language preferences', homepage.headers.get('vary').includes('Accept-Language') && homepage.headers.get('vary').includes('Cookie'));
  await page.goto(url);
  await axe('.qtrad-switcher', 'Public switchers');
  const form = page.locator('.qtrad-language-form');
  await form.locator('select').selectOption(base + '/de/' + fixture.browser_post_slug + '/');
  check('Dropdown selection does not navigate', page.url(), url);
  await form.getByRole('button', {name:'Go', exact:true}).press('Enter');
  await page.waitForURL('**/de/**');
  check('Dropdown Go navigates', page.url(), base + '/de/' + fixture.browser_post_slug + '/');
  check('Current language is marked', await page.locator('.qtrad-style-both [aria-current="true"]').getAttribute('lang'), 'de-DE');
  check('Missing Catalan flag has visible name', await page.locator('.qtrad-style-image .lang-ca bdi').isVisible());
  await page.setViewportSize({width:320,height:900});
  check('Switchers fit 320 CSS pixels', await page.locator('.qtrad-switcher').evaluateAll(nodes => nodes.every(n => n.scrollWidth <= n.clientWidth + 1)));
  await page.emulateMedia({forcedColors:'active'});
  check('Flag names visible in forced colors', await page.locator('.qtrad-style-image .lang-de bdi').isVisible());
  await page.emulateMedia({forcedColors:'none'});
  await page.setViewportSize({width:1280,height:900});
  const noJS = await browser.newContext({javaScriptEnabled:false});
  const plain = await noJS.newPage(); await plain.goto(url);
  check('No-JS dropdown fallback has ordinary links', await plain.locator('.qtrad-style-dropdown a').count(), 4);
  check('No-JS dropdown form remains hidden', await plain.locator('.qtrad-language-form').isVisible(), false);
  await noJS.close();
  await page.goto(base + '/wp-login.php');
  await page.locator('#user_login').fill('qtrad_auditor'); await page.locator('#user_pass').fill('qtrad-local-audit-only');
  await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
  await page.goto(base + '/wp-admin/post.php?post=' + fixture.browser_post_id + '&action=edit');
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
  // WordPress 5.8's inline TinyMCE attaches document mouse handlers before init.
  await page.waitForFunction(() => !window.tinymce || window.tinymce.editors.every(e => e.initialized && e.getBody()));
  const dialog = page.locator('.components-modal__header button').first(); if (await dialog.isVisible()) await dialog.press('Enter');
  await page.locator('.qtrad-lsb__btn[data-qtrad-lang="en"]:enabled').waitFor();
  await axe('#qtrad-languages', 'Editor language controls');
  check('Initial editor value', await page.evaluate(() => wp.data.select('core/editor').getEditedPostAttribute('title')), 'Hello');
  const german = page.getByRole('button', {name:'Deutsch', exact:true});
  await german.focus(); await page.keyboard.press('Space');
  check('Keyboard selects German', await german.getAttribute('aria-pressed'), 'true');
  check('Keyboard focus stays on selected button', await german.evaluate(n => n === document.activeElement));
  check('Live region announces language', await page.locator('#qtrad-language-status').textContent(), 'Editing Deutsch. Other translations are preserved when you save.');
  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({title:'Updated German title'}));
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  check('Successful save leaves editor clean', await page.evaluate(() => wp.data.select('core/editor').isEditedPostDirty()), false);
  let raw = await page.evaluate(async id => (await wp.apiFetch({path:'/wp/v2/posts/' + id + '?context=edit'})).title.raw, fixture.browser_post_id);
  check('Saved title retains disabled French', raw, '[:en]Hello[:de]Updated German title[:fr]Bonjour[:]');
  await page.getByRole('button', {name:'English', exact:true}).click();
  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({title:'Updated English title'}));
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  raw = await page.evaluate(async id => (await wp.apiFetch({path:'/wp/v2/posts/' + id + '?context=edit'})).title.raw, fixture.browser_post_id);
  check('Second language save retains first edit', raw, '[:en]Updated English title[:de]Updated German title[:fr]Bonjour[:]');
  await page.getByRole('button', {name:'العربية', exact:true}).click();
  const canvasTitle = await page.locator('iframe[name="editor-canvas"]').count()
    ? page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-post-title__input')
    : page.locator('.editor-post-title__input');
  check('Arabic editor direction', await canvasTitle.getAttribute('dir'), 'rtl');
  check('Arabic editor language', await canvasTitle.getAttribute('lang'), 'ar');
  await page.goto(base + '/wp-admin/post.php?post=' + fixture.browser_post_id + '&action=edit&qtrad_classic=1');
  await page.locator('.qtrad-lsb__btn[data-qtrad-lang="de"]:enabled').waitFor();
  await page.getByRole('button', {name:'Deutsch', exact:true}).press('Enter');
  check('Classic keyboard switching loads German', await page.locator('#title').inputValue(), 'Updated German title');
  await page.locator('#title').fill('Classic German title');
  await axe('#qtrad-seo', 'SEO translation fields');
  await page.locator('#qtrad-seo-title-de').fill('Browser German SEO title');
  await page.locator('#qtrad-seo-description-de').fill('Browser German SEO description');
  await Promise.all([page.waitForURL('**/post.php?post=**&action=edit**'), page.locator('#publish').click()]);
  await page.locator('.qtrad-lsb__btn[data-qtrad-lang="de"]:enabled').waitFor();
  check('Classic save shows selected language', await page.locator('#title').inputValue(), 'Classic German title');
  check('SEO title save retains language value', await page.locator('#qtrad-seo-title-de').inputValue(), 'Browser German SEO title');
  check('SEO description save retains language value', await page.locator('#qtrad-seo-description-de').inputValue(), 'Browser German SEO description');
  await page.goto(base + '/wp-admin/post.php?post=' + fixture.browser_post_id + '&action=edit');
  await page.locator('.qtrad-lsb__btn[data-qtrad-lang="en"]:enabled').waitFor();
  raw = await page.evaluate(async id => (await wp.apiFetch({path:'/wp/v2/posts/' + id + '?context=edit'})).title.raw, fixture.browser_post_id);
  check('Classic save retains other and disabled translations', raw, '[:en]Updated English title[:de]Classic German title[:fr]Bonjour[:]');
  await page.goto(base + '/wp-admin/options-general.php?page=qtrad');
  await axe('.qtrad-settings', 'Language settings');
  check('Settings stylesheet loads', await page.locator('.qtrad-lang-choice').first().evaluate(n => getComputedStyle(n).display), 'inline-flex');
  await page.locator('#qtrad-new-code').fill('invalid');
  await page.locator('#qtrad-new-name').fill('Invalid language');
  await page.locator('#qtrad-new-locale').fill('invalid!');
  await page.getByRole('button', {name:'Save languages', exact:true}).click();
  await page.locator('#qtrad-errors').waitFor();
  await page.waitForFunction(() => document.activeElement.id === 'qtrad-errors');
  check('Settings error receives focus', await page.evaluate(() => document.activeElement.id), 'qtrad-errors');
  check('Invalid language value is retained', await page.locator('#qtrad-new-code').inputValue(), 'invalid');
  await axe('#qtrad-errors', 'Settings error notice');
  await page.locator('#qtrad-new-code').fill('zz');
  await page.locator('#qtrad-new-name').fill('Test language');
  await page.locator('#qtrad-new-locale').fill('en_GB');
  await page.getByRole('button', {name:'Save languages', exact:true}).click();
  await page.locator('input[name="enabled[]"][value="zz"]').waitFor();
  check('New language becomes enabled', await page.locator('input[name="enabled[]"][value="zz"]').isChecked());
  await page.locator('input[name="enabled[]"][value="fr"]').check();
  await page.getByRole('button', {name:'Save languages', exact:true}).click();
  await page.locator('input[name="blogname[fr]"]').waitFor();
  check('Settings preserve disabled French site title', await page.locator('input[name="blogname[fr]"]').inputValue(), 'Titre français');
  await page.screenshot({path: 'tests/results/settings.png', fullPage:true});
  await page.goto(base + '/wp-admin/edit-tags.php?taxonomy=category');
  await axe('.qtrad-term-field', 'Taxonomy translation fields');
  } finally { await browser.close(); console.log(JSON.stringify(results,null,2)); }
  assert.equal(results.checks.filter(c => !c.pass).length,0, 'Browser assertions failed');
  assert.equal(results.accessibility.filter(c => c.violations.length).length,0, 'Accessibility violations found');
  assert.deepEqual(results.errors,[], 'Browser JavaScript errors');
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
