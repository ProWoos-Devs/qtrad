/* Disposable site only. Credentials below belong to the local audit fixture.
 * node audit/probes/browser.cjs /path/to/wordpress-probe-results.json
 */
const fs = require('node:fs');
const { chromium } = require('/usr/lib/node_modules/playwright');
(async () => {
  const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const base = 'http://127.0.0.1:8931';
  const slug = fixture.browser_post_slug || 'qtn-audit-browser';
  const results = { browser: [], http: [], errors: [] };
  for (const [name, url, headers] of [
    ['German page', '/de/' + slug + '/', {}],
    ['default-language explicit URL', '/en/' + slug + '/', {}],
    ['browser detection with hidden default', '/', { 'Accept-Language': 'de' }],
    ['REST rendered language parameter', '/wp-json/wp/v2/posts/' + fixture.browser_post_id + '?lang=de', {}],
    ['query REST route', '/?rest_route=/wp/v2/posts/' + fixture.browser_post_id + '&lang=de', {}],
    ['XML sitemap', '/wp-sitemap.xml', {}],
    ['host header supplied by client', '/?lang=de', { Host: 'untrusted.example:8931' }],
  ]) {
    const response = await fetch(base + url, { redirect: 'manual', headers });
    const body = await response.text();
    results.http.push({ name, status: response.status, location: response.headers.get('location'),
      contentType: response.headers.get('content-type'), cookieSet: !!response.headers.get('set-cookie'),
      title: (() => { try { return JSON.parse(body).title; } catch { return null; } })(),
      alternates: [...body.matchAll(/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)"/g)].map(m => ({ lang: m[1], url: m[2] })),
    });
  }
  results.http.push(await new Promise((resolve, reject) => {
    const request = require('node:http').get(base + '/?lang=de', { headers: { Host: 'untrusted.example:8931' } }, response => {
      response.resume();
      response.on('end', () => resolve({ name: 'raw HTTP host header', status: response.statusCode, location: response.headers.location }));
    });
    request.on('error', reject);
  }));
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('pageerror', err => results.errors.push(err.message));
  await page.goto(base + '/wp-login.php');
  await page.locator('#user_login').fill('qtn_auditor');
  await page.locator('#user_pass').fill('qtn-local-audit-only');
  await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
  await page.goto(base + '/wp-admin/post.php?post=' + fixture.browser_post_id + '&action=edit');
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), { timeout: 30000 });
  await page.waitForTimeout(2000);
  const closeDialog = page.locator('.components-modal__header button').first();
  if (await closeDialog.isVisible()) await closeDialog.click();
  results.browser.push({ name: 'initial editor state', actual: await page.evaluate(() => ({
    title: wp.data.select('core/editor').getEditedPostAttribute('title'),
    buttonCount: document.querySelectorAll('.qtu-lsb__btn').length,
    configEnabled: window.qtuEditorConfig?.enabled,
  })) });
  await page.locator('[data-qtu-lang="de"].qtu-lsb__btn').click();
  results.browser.push({ name: 'German editor state', actual: await page.evaluate(() => ({
    title: wp.data.select('core/editor').getEditedPostAttribute('title'),
    content: wp.data.select('core/editor').getEditedPostAttribute('content'),
  })) });
  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ title: 'Updated German title' }));
  let saveError = null;
  try {
    await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  } catch (error) {
    saveError = error.message;
  }
  results.browser.push({ name: 'save completes without JavaScript exception', actual: saveError });
  await page.waitForTimeout(1500);
  results.browser.push({ name: 'stored fields after German save', actual: await page.evaluate(async id => {
    const post = await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' });
    return { title: post.title.raw, content: post.content.raw, excerpt: post.excerpt.raw };
  }, fixture.browser_post_id) });
  await page.screenshot({ path: '/tmp/qtranslate-next-audit/results/editor-after-save.png', fullPage: false });
  await browser.close();
  console.log(JSON.stringify(results, null, 2));
})().catch(error => { console.error(error.stack); process.exit(1); });
