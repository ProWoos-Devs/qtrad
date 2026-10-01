# qTrad regression tests

These are behavior tests, not replicas of implementation internals. The original audit and its baseline results remain in `../audit/`. The former Next 1.2.0 matrix evidence is preserved in `results/qtranslate-next-1.2.0-matrix/`; current qTrad runs use `results/matrix/`.

Run the parser/editor tests without WordPress:

```sh
php tests/codec.php
node tests/codec.cjs
node tests/editor.cjs
```

The PHP and JavaScript codecs consume `fixtures/codec.json`: comments/brackets/swirly syntax, disabled languages, whitespace/backslashes, shared text, `0`, empty fields, force markers and more separators. Editor tests exercise the actual script against a synchronous store adapter, including recursive dispatch and edits made during an in-flight save.

## WordPress integration

**Use an isolated, disposable installation and database.** `wordpress.php` replaces site options, creates users/posts/terms and deliberately sets known fixture passwords. Never point it at a real site. It loads the workspace source manually so an installed copy cannot duplicate its hooks.

The recorded environment uses official WordPress 6.8, PHP 8.5.10 and MariaDB 12.3.3. Configure the disposable site's home/site URL as `http://127.0.0.1:8931`, install the Twenty Twenty-Five theme, copy `qtrad/` into its plugin directory, and copy `tests/fixtures/wordpress-mu.php` into its `wp-content/mu-plugins/` directory. That helper forces the classic editor only for the test URL and preserves the test query parameter after form saves. It is not part of the distributed plugin.

```sh
php tests/wordpress.php /path/to/disposable/wordpress --prepare-browser > tests/results/wordpress-6.8.json
```

The suite returns nonzero if an assertion fails. `--prepare-browser` activates the installed qTrad copy and creates a published browser fixture and the local-only `qtn_auditor` account. Copy changed workspace source to that disposable plugin directory before browser runs. Re-running fixture preparation resets language settings; browser tests intentionally change/save them.

Serve the site on localhost port 8931 with a WordPress-aware router. Install Playwright/Chromium and axe-core 4.10.3 separately (these are development tools, not plugin dependencies). Run:

```sh
node tests/browser.cjs tests/results/wordpress-6.8.json /path/to/axe-core/axe.min.js > tests/results/browser-wordpress-6.8.json
```

The browser test covers HTTP/REST routing, the block and classic editors, repeated saves, keyboard focus/status, RTL inside the block-editor iframe, clean save state, dropdown behavior, no-JavaScript fallback, forced colors, 320-pixel layouts, invalid settings/error focus, custom language creation and preservation of a disabled site title. Axe checks plugin controls using WCAG A/AA tags through WCAG 2.2. Incomplete axe results require manual review; zero automated violations is not a site-wide conformance claim.

## Conflict guard

Point these tests at the pinned predecessor core files used by the audit:

```sh
php tests/load-order.php original /path/to/qtranslate/qtranslate_core.php
php tests/load-order.php x /path/to/qtranslate-x/qtranslate_core.php
php tests/load-order.php renamed /path/to/qtranslate-x/qtranslate_core.php
php tests/load-order.php network /path/to/qtranslate-x/qtranslate_core.php
php tests/load-order.php loaded /path/to/qtranslate-x/qtranslate_core.php
php tests/load-order.php next /path/to/qtranslate-x/qtranslate_core.php
php tests/load-order.php unified /path/to/qtranslate-x/qtranslate_core.php
```

The test asserts that qTrad exports no predecessor function names when a conflicting plugin is present, then loads the real upstream core to detect redeclarations. The `renamed` case creates a small fixture plugin header under the system temporary directory.

Also lint all distributed PHP, check JavaScript syntax with `node --check`, and validate the translation template with `msgfmt --check`. Version/database results are recorded by the matrix below. Additional browsers and actual assistive technology remain follow-up validation.

## Repeatable compatibility and SEO matrix

Install Docker with access to its daemon, Python 3 and Node.js 22+. The runner creates its own temporary WordPress tree, private Docker network and database container. It exposes only the test PHP server on localhost:8931 and removes its containers/network/site after each cell. It never accepts an existing WordPress installation/database. Official WordPress and pinned SEO packages are downloaded into a system-temporary cache; runtime/package hashes and actual PHP versions are recorded in each report.

```sh
npm ci --prefix tests
npx --prefix tests playwright install --with-deps chromium
python3 tests/assets.py
    python3 tests/matrix/run.py --browser
```

For a single cell:

```sh
python3 tests/matrix/run.py --cell wp58-php74-maria --browser
```

Run cells sequentially on one host because their HTTP checks use localhost:8931. The GitHub Actions workflow runs cells on separate runners. `cells.json` is the single matrix source: PHP 7.4 and 8.0–8.5 across representative compatible WordPress 5.8, 6.0, 6.4, 6.8, 6.9 and 7.1.2 pairings, using MariaDB 10.11 or MySQL 8.0. This samples version boundaries; it does not assert that every possible version combination works. Old releases are deliberate compatibility/migration fixtures, not a recommended production stack. Pairings follow [WordPress's core PHP matrix](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/).

Every cell runs the shared PHP codec, 85 storage/routing/REST assertions, SEO model/pagination/security fixtures, then actual HTTP pages/sitemaps against core, Yoast and Rank Math. Pinned adapter fixtures are Yoast 17.9/Rank Math 1.0.76.1 below WordPress 6.7, Yoast 25.9/Rank Math 1.0.279 for 6.8, and Yoast 28.6/Rank Math 1.0.279 from 6.9. Each vendor is activated alone through WordPress's normal activation hooks, with a complete bootstrap/rewrite refresh before HTTP checks. Rank Math uses its supported registration-skip setting; no external account is connected. Existing vendor caches remain enabled.

The minimum/current cells additionally convert their disposable installation to a real multisite network and check site-specific settings, nested switch/restore context and network conflict detection. With `--browser`, these same cells run Chromium/axe checks, classic/block editing, SEO fields and accessibility regressions. A cell run without its browser suite cannot satisfy the package gate.

Results go to `tests/results/matrix/<cell>/`. Reports include source/test SHA-256 maps and execution times; HTML/XML URL inventories, bootstrap diagnostics and logs support investigation. Third-party legacy core/plugin deprecations may appear in debug logs; no such libraries are shipped with qTrad. `.github/workflows/compatibility.yml` also runs the JavaScript codec/editor tests and flag-provenance gate, collects results, then verifies the full matrix before allowing a release check to pass.

## Local release packaging

```sh
python3 tools/package.py --verify-only
python3 tools/package.py
```

The builder requires passing results for all configured cells, browser/axe/multisite coverage on the boundary cells, current runtime/test fingerprints and a verified complete flag inventory. It produces only a local ZIP/checksum in `dist/`, containing the `qtrad` distribution directory; tests, downloaded SEO plugins and Node dependencies are excluded. It does not publish or deploy anything. Markdown/readme prose does not invalidate runtime tests; executable code, assets, notices and the translation template do.
