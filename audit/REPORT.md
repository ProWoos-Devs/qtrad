# qTranslate Next audit

Audited on 1 October 2026. Local plugin version: 1.0.0. Its main file is still `qtranslate-next/qtranslate-unified.php`.

**Release assessment: this build needs fixes before production use or migration of an existing multilingual site.** It can read basic content from both predecessors, but several normal save paths lose translations or damage content, and the block editor throws a JavaScript exception after saving. Compatibility currently covers a subset of names and storage formats, rather than the full behavior of either predecessor.

The plugin source was not changed. This directory contains the report, reproduction scripts, results, and fingerprints of all 51 audited plugin files. The workspace has no usable Git repository, so the SHA-256 manifest identifies the exact snapshot.

## Scope and evidence

Reviewed all nine PHP files, the editor JavaScript, both CSS files, readme, uninstall behavior, and flag references. Compared against these pinned sources:

- [qTranslate](https://github.com/qianqin/qTranslate/tree/a655eca1129af1d966a4a0308f134c768da4e513), commit `a655eca1129af1d966a4a0308f134c768da4e513`.
- [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x/tree/e0e0c378308a5c1c7746357f04c41326feb05d64), commit `e0e0c378308a5c1c7746357f04c41326feb05d64`.

Validation used disposable WordPress databases and copied installations under `/tmp/qtranslate-next-audit`. No existing site's database was modified.

| Evidence | Result and limits |
| --- | --- |
| PHP lint and JavaScript syntax check | All plugin files pass on PHP 8.5.10 / Node 26.8.1. Syntax checks do not establish minimum-version compatibility. |
| Official WordPress 6.8 database probes | 40 targeted cases: 9 pass, 31 expose failures. These are deliberately selected edge and regression cases, not a general quality score. See [results](results/wordpress-6.8.json). |
| Editor behavior probes | 9 cases: 3 pass, 6 expose failures. Execute the actual editor script with a minimal DOM/store adapter; see [results](results/editor.json). |
| Chromium with official WordPress 6.8 and Twenty Twenty-Five | English/German switching works initially. Save produces a stack overflow; stored fields lose disabled French. See [browser and HTTP evidence](results/browser-wordpress-6.8.json). |
| Loading Next before each predecessor | Both original core files fail with a function redeclaration before the conflict guard runs. |
| Original codecs executed separately | qTranslate leaves Next's closing `[:]` visible and does not understand swirly markers; qTranslate-X handles all three styles. See [qTranslate](results/upstream-qtranslate.json) and [qTranslate-X](results/upstream-qtranslate-x.json). |
| Additional local WordPress copy | The earlier 37-case run reproduced the main failures on a local installation reporting version 7.1.2. That installation is not treated as an official release baseline. |

Severity: **P1** means a release blocker involving lost/corrupted data or a fatal error; **P2** means a material compatibility, routing, integrity, or security defect; **P3** means packaging or interface work. There are **8 P1, 16 P2, and 3 P3 findings** below. Some codec defects are inherited from qTranslate-X; they remain defects in this build, but are not all new regressions.

## Release blockers

### 1. P1 — Block-editor save completion recursively dispatches

Location: `assets/js/editor.js:328–334`, particularly the assignment after `presentBlock()`.

When saving changes from true to false, the subscription calls `presentBlock()`. Its `editPost()` and `resetBlocks()` dispatches synchronously notify subscribers while `wasSaving` is still true. The same callback calls itself again until the stack overflows.

**Reproduction:** open the prepared multilingual post, switch to German, change its title, and save. Chromium on WordPress 6.8 throws `RangeError: Maximum call stack size exceeded`. The store-adapter probe independently reproduces the recursion. The server can already have saved the post, so the exception is not evidence that nothing was written.

**Fix:** update transition state before dispatch, guard re-entry, and make presentation idempotent. Test successful saves, failed saves, autosaves, and editing while a save is pending.

### 2. P1 — Saving deletes disabled-language translations

Locations: `includes/fields.php:78–88`, `includes/admin.php:148–153`, `assets/js/editor.js:72–104`.

The codec retains unknown/disabled language blocks, but the classic form and its server save rebuild only enabled languages. The block serializer also iterates only `enabled`.

**Reproduction:** keep `[:en]Hello[:de]Hallo[:fr]Bonjour[:]`, enable only English and German, then save. French disappears in the PHP probe and real block-editor workflow. Disabling a language should be reversible; deleting its content during an unrelated edit makes it irreversible without a backup or revision.

**Fix:** merge edits into every existing language key, including disabled keys. Treat explicit deletion as a separate operation. Apply the same preservation rule to term translations and site title/tagline saves, which also rebuild only enabled keys.

### 3. P1 — Post saves mix slashed and unslashed data

Locations: `includes/fields.php:81–101`.

WordPress passes slashed data to `wp_insert_post_data` and subsequently unslashes it. The classic branch returns unslashed values. The fallback branch merges slashed incoming data with unslashed existing data. This damages untouched translations too. The [WordPress hook contract](https://developer.wordpress.org/reference/hooks/wp_insert_post_data/) explicitly specifies slashed values.

**Reproduction:** saving content containing `C:\temp` stores `C:temp`; a block attribute containing an escaped backslash loses an escape. Editing German also strips a backslash from existing English content.

**Fix:** unslash input before processing, merge with raw stored values, then slash the entire result exactly once before returning it. Test JSON, quotes, backslashes, regex examples, and untouched translations. The same double-unslash risk exists in the recursive metadata update at `fields.php:205`.

### 4. P1 — Metadata writes merge against a translated display value

Locations: `includes/fields.php:162–208`.

The update callback calls `get_post_meta()` while the display filter is active. Its own `$busy` flag does not disable the separate read callback's `$busy` flag. It therefore receives one translated string instead of the stored multilingual value. It also always selects `qtu_admin_language()`, including during front-end/REST writes.

**Reproduction:** store `[:en]Hello[:de]Hallo[:]`, select German on the front end, then update the configured field to `Neu`. The raw database value becomes plain `Neu`, displayed in every language. Both originals are lost.

**Fix:** read raw metadata with this display filter bypassed, preserve every stored language, and take the edit language from an explicit write context. Display filtering must not affect persistence.

### 5. P1 — API middleware overwrites unrelated resources

Location: `assets/js/editor.js:274–295`.

The middleware matches any POST/PUT whose URL starts with posts, pages, or templates. It does not check the current resource's ID, distinguish autosaves/revisions from other writes, or verify that the request belongs to this editor. Matching fields are replaced with the current editor's complete payload.

**Reproduction:** while editing post 42, a request to `/wp/v2/posts/99` with title `Unrelated title` receives post 42's multilingual title. A template request receives post 42's body. Both are demonstrated by executing the actual middleware.

**Fix:** use the current post's REST base and ID, explicitly handle its own autosave route, and exclude unrelated resources. Test interactions with plugins that create or update posts from the editor.

### 6. P1 — Custom post type saves lack the editor's language context

Locations: `assets/js/editor.js:281`, `includes/fields.php:20–27`, `includes/runtime.php:266–268`.

The UI is added to public custom post types, but middleware only supports the three hardcoded REST bases. A German edit of a `books` resource is sent as plain text. Normal REST requests do not populate `$_POST['qtu_edit_lang']`, and the `/wp-json/` bootstrap selects the default language.

**Evidence:** the middleware probe leaves `/wp/v2/books/42` single-language; the HTTP probe confirms `/wp-json/...?...lang=de` still renders English. The resulting wrong-language server merge follows from these verified behaviors and code inspection; a complete custom-post-type browser workflow was not run.

**Fix:** discover the active post type's REST base and carry language information through authenticated REST saves. Cover custom REST namespaces, autosaves, and custom post types with excerpts disabled.

### 7. P1 — Classic form payload applies to other posts inserted in the same request

Location: `includes/fields.php:65–88`.

Any `wp_insert_post_data` call during a request with a valid `qtu_field_nonce` and `qtu_field` receives those fields. There is no check tying the payload to the post being edited. Revisions need deliberate handling, but unrelated inserts must not inherit the main post's title/body.

**Reproduction:** simulate a valid classic form save and create a secondary page with title `Secondary page`. Its title becomes `[:en]Main title[:de]Haupttitel[:]`.

**Fix:** identify the submitted post and scope the payload to it and deliberately supported revision/autosave operations. Preserve ordinary API behavior for other records.

### 8. P1 — Conflict guard runs after conflicting declarations

Locations: `qtranslate-unified.php:39–45,51–55`, aliases in `includes/widget.php:115–120`.

Next exports predecessor function/class names immediately. The conflict guard waits until `plugins_loaded`. If Next loads first, the predecessor then declares the same names and PHP fails before the notice or activation check can execute.

**Reproduction:** load Next followed by the pinned qTranslate core: fatal `qtrans_useTermLib` redeclaration. Loading Next followed by qTranslate-X fails on `qtranxf_useTermLib`.

**Fix:** detect active conflicts before loading compatibility declarations; also handle plugins installed under different directory names and network activation. Test both load orders. Coexistence is not required, but refusing to start must be reliable.

## Compatibility, routing, and integrity

### 9. P2 — Public API coverage and behavior are incomplete

Location: `includes/compat.php`.

Missing established names include `qtrans_isEnabled`, `qtranxf_isEnabled`, both `*_isAvailableIn` functions, `qtranxf_convertURLs`, and `qtranxf_isMultilingual`. Themes calling them can fail fatally. Existing wrappers also differ: Next reports only the default language for plain `qtrans_getAvailableLanguages()` text, whereas original qTranslate reports every enabled language; qTranslate-X's corresponding function returns false. `qtrans_join()` accepts a string in both predecessors but Next returns an empty string for string input. Original qTranslate's `$quicktags=false` split behavior is ignored.

The [function inventory](results/upstream-function-inventory.csv) records declarations in the inspected upstream files, including internal functions; absence in that inventory is not automatically a requirement to expose every internal function. `QTX_VERSION=3.4.8` and an empty `url_info` also invite integrations to assume capabilities this build lacks.

**Fix:** publish a supported API matrix and implement/test actual public contracts, including argument types, return values, and side effects. Narrow the readme's blanket compatibility promise until that work is complete.

### 10. P2 — Legacy language chooser calls produce no output

Location: `includes/compat.php:166–180`.

Both original chooser functions echo markup. Next only returns it. A normal theme call such as `qtrans_generateLanguageSelectCode('text');` renders nothing. qTranslate-X additionally accepts an options array and booleans, which Next treats as an unsupported style.

**Fix:** keep a return-based internal renderer for shortcodes/widgets and preserve the legacy functions' echo behavior and argument forms. Preserve supported legacy widget options when updating an existing widget; the current `update()` discards options such as hide-title and custom formatting.

### 11. P2 — Term-library helpers do not translate strings or arrays

Location: `includes/compat.php:160–163,256–258`.

Both predecessor term-library helpers support strings, objects, and recursive arrays. Next forwards them to `qtu_filter_get_term()`, which accepts only a term object.

**Reproduction:** with `News → Nachrichten` in the legacy library, both `qtrans_useTermLib('News')` and `qtrans_useTermLib(['News'])` return the untranslated input.

**Fix:** implement recursive library translation independently of the WordPress term-object hook.

### 12. P2 — Settings remove custom legacy languages

Locations: `includes/config.php:260–277`, `includes/admin.php:275–279`.

Loading allows custom saved language codes, but the settings form and save validator only allow the built-in catalog. A save silently disables a custom code and can replace the default language. The settings form also cannot edit imported names, locales, or flag paths.

**Reproduction:** load enabled `['en','xx']` with default `xx`, save those same values, and the result is only `['en']`.

**Fix:** render and preserve imported languages and provide validated custom-language management. Preserve disabled translations in blogname/blogdescription rather than rebuilding only enabled values.

### 13. P2 — Newly written formats are not fully reversible to original qTranslate

Locations: `includes/tokens.php:207–211`, `includes/admin.php:246–247`, readme migration claims.

Next defaults new fields to bracket output and always writes site title/tagline in bracket form. Original qTranslate's codec does not recognize the closing `[:]`: `[:en]Hello[:de]Hallo[:]` reads as German `Hallo[:]`. It treats swirly text as untagged text in every language. Executing the pinned original codec confirms both results.

**Fix:** define separate read-compatibility and backward-write policies. A site that needs to return to original qTranslate needs comment encoding for suitable fields and a documented policy for options that WordPress sanitizes. Do not promise migration-free return to every predecessor for every write format.

### 14. P2 — URL conversion rewrites external and non-HTTP links

Location: `includes/runtime.php:118–211,214–236`.

There is no site-origin check or scheme restriction. External links receive language prefixes and may inherit the site's port. Mail addresses and fragment-only links are rebuilt as absolute URLs.

**Reproduction:** an external `https://other.example/path?x=1` becomes `https://other.example:8931/de/path?x=1`; `mailto:user@example.test` becomes `mailto://127.0.0.1:8931/de/user@example.test`; `#section` points at the site root instead of the current document.

**Fix:** parse and classify origins/schemes before localization; preserve external URLs, mailto/tel links, fragments, and appropriate relative references.

### 15. P2 — Subdirectory URLs and neutral paths are misidentified

Locations: `includes/runtime.php:32–45,224–225,368–369`.

Neutral-path checks use the absolute path, while their regex assumes WordPress is installed at `/`. Home-prefix stripping also lacks a path-segment boundary.

**Reproduction:** with home `https://site.example/blog`, `/blog/wp-login.php` becomes `/blog/de/wp-login.php`, and `/blogger/post` is reduced to `ger/post`. Admin/REST/assets generated from subdirectory installations can receive invalid language paths.

**Fix:** identify the home path on segment boundaries, then apply neutral-path checks to the relative site path, both before and after language-prefix removal.

### 16. P2 — Query rewriting changes unrelated parameters

Location: `includes/runtime.php:153–159,203–208`.

`parse_str()` followed by `http_build_query()` collapses repeated parameters and changes parameter spelling/encoding. This affects language conversion and request redirects, even when the input has no `lang` parameter.

**Reproduction:** `a=1&a=2&v=a%20b` becomes `a=2&v=a+b`. Signed links may fail validation, and repeated filter values are lost.

**Fix:** remove or replace only the intended language parameter while preserving all other query bytes and repeated keys. Test signed URLs, arrays, percent escapes, and encoded `lang`-like names.

### 17. P2 — REST handling depends on a constant defined too late

Location: `includes/runtime.php:239–268`.

Request normalization runs at `plugins_loaded`, but WordPress defines `REST_REQUEST` when serving the parsed REST route. See the [WordPress REST loader](https://developer.wordpress.org/reference/functions/rest_api_loaded/). The `/wp-json/` neutral branch forces the default language; query-form REST requests enter the normal redirect path.

**Reproduction:** `/wp-json/wp/v2/posts/ID?lang=de` returns English rendered title `Hello`; `/?rest_route=/wp/v2/posts/ID&lang=de` returns a 302 to a localized front-end URL instead of directly serving JSON.

**Fix:** recognize both REST URL forms early and select REST language at an appropriate request hook. Keep authentication, raw edit values, and render language distinct. Test GET/POST, plain permalinks, and cookie-authenticated saves.

### 18. P2 — Current-request redirects trust arbitrary Host headers

Locations: `includes/runtime.php:125,327,348–353`.

The current URL derives its host from client-supplied `HTTP_HOST` and sends the result through `wp_redirect()` without validating it against configured site/language domains.

**Reproduction:** a raw HTTP request with `Host: untrusted.example:8931` and `/?lang=de` returns `Location: http://untrusted.example:8931/de/`. This depends on the web server accepting unknown hosts; it is not an unconditional production exploit.

**Fix:** select a canonical host from the configured home/domain map, validate approved aliases, and validate redirect destinations. Domain mode needs an explicit allowlist rather than a blanket same-host restriction.

### 19. P2 — Legacy fallback options are not preserved

Location: `includes/config.php:134–180`, `includes/tokens.php:267–292`.

Next ignores legacy `show_alternative_content_message`, `show_alternative_content`, `not_available`, and `force_markers`. Its own alternate-message option defaults off, and enabling it always appends fallback content. Existing sites can change from a missing-translation notice to displaying content in another language. Identical translations collapse to untagged text despite a saved force-markers preference.

**Reproduction:** set `qtranslate_force_markers=true`; joining identical English/German values returns plain `Same` instead of tagged values. The remaining option differences are verified against qTranslate-X's option declarations and fallback implementation.

**Fix:** load and honor supported legacy policies, preserve configured messages, and pass force-markers consistently through PHP and JavaScript serialization.

### 20. P2 — Term renames leave obsolete translation-library entries

Location: `includes/fields.php:125–145`.

The save hook runs after the term has its new name and unsets that new name, rather than the old library key. The old entry remains. A later term reusing the old name can inherit unrelated translations. Library updates also replace disabled-language entries (finding 2).

**Reproduction:** rename `Old` to `New` with translated fields. `qtranslate_term_name['Old']` remains, and the new record drops its disabled French value.

**Fix:** capture the canonical old name before the update, merge the library entry, and handle shared names across taxonomies deliberately. Ensure library writes use raw canonical names rather than display-translated term objects.

### 21. P2 — Browser language detection does not honor its settings/preferences

Locations: `includes/runtime.php:47–81,307–320`.

With hide-default enabled, the code selects the default language before considering cookies or browser detection. These are both default-enabled settings, so the advertised first-visit detection is disabled in the default configuration. Negotiation also accepts `q=0`, and any `pt-BR` occurrence makes a higher-priority `pt-PT` preference choose Brazilian Portuguese.

**Reproduction:** a first homepage request with `Accept-Language: de` stays English; `de;q=0,en;q=0` selects German; `pt-PT,pt-BR;q=0.1` selects `pb`.

**Fix:** define explicit precedence for language URLs, cookies, browser preferences, and the bare default-language URL. Parse regional preferences and quality values per entry. Avoid persistent cache-dependent language choices on bare URLs.

### 22. P2 — Alternate language links contradict canonical routing

Location: `includes/runtime.php:422–432`.

The default-language alternate always forces `/en/`, but request normalization redirects that address to the bare canonical URL when hide-default is on. It also advertises every enabled language irrespective of available translations.

**Reproduction:** the German page advertises an English alternate at `/en/...`; requesting that URL returns a 302 to the bare URL. The HTTP evidence records both responses.

**Fix:** generate alternate URLs from the same canonical policy as routing and from actual translation availability. Add deliberate multilingual sitemap and SEO-plugin behavior; the current hooks do not establish complete SEO compatibility.

### 23. P2 — PHP serialization loses the string `0`

Location: `includes/tokens.php:96–109`.

Truthiness checks treat the valid string `0` as absent. `qtu_join(['en'=>'0','de'=>'0'])` returns an empty string. JavaScript preserves the same value, so the two codecs disagree. The relevant truthiness pattern is inherited from qTranslate-X.

**Fix:** compare strictly against empty strings/null according to the supported types. Add shared codec fixtures consumed by both runtimes.

### 24. P2 — Hide-untranslated includes empty translations

Location: `includes/runtime.php:435–462`.

The SQL condition checks only for a marker, not whether it owns nonempty translated content. An empty German marker in either title or body is sufficient to include the post.

**Reproduction:** with German selected and hide-untranslated enabled, a post containing only English text and empty German markers still appears in a real `WP_Query` result.

**Fix:** make availability and visibility follow one defined rule. Prefer a maintained per-post availability index over repeated wildcard scans of title/content, while preserving legacy-content behavior.

## Naming, distribution, and interface

### 25. P3 — The rename to qTranslate Next is incomplete

Locations: main filename/header, translation calls, settings page slug, widget class, readme installation step.

The visible plugin name is Next, but the filename/text domain are `qtranslate-unified`, installation tells users to upload the Unified folder, and settings URLs/classes still use Unified. The Plugin URI points to qTranslate-X's repository. Internal `qtu_*` names alone are not a defect and do not require a wholesale rename.

**Fix:** establish `qtranslate-next` as the distribution slug/text domain and update user-facing instructions/metadata. Retain aliases or migration reads for existing Unified options, activation basenames, and classes where necessary. Preserve the intentional legacy widget ID `qtranslate` and shared `qtranslate_*` options.

### 26. P3 — Distribution provenance is incomplete

Location: plugin header/readme and package contents.

The package declares GPL-2.0-or-later but contains no license text or attribution/provenance document. The original PHP headers credit Qian Qin; original readme material also credits flag sources. Original qTranslate itself has a GPLv2-or-later PHP header alongside a GPLv3 license file, so the file alone is not a basis for declaring Next's license invalid.

**Fix:** document which code/assets were reused and their source commits, retain applicable notices, and ship the chosen license text. Resolve any uncertainty about asset provenance before distribution. No licensing violation determination is made by this audit.

### 27. P3 — Language controls and flag styles need correction

Locations: `includes/admin.php:129–134`, `includes/compat.php:267–295`, `includes/config.php:40–43`, front CSS.

Tabs use `aria-pressed` rather than tab selection semantics and have no panel relationship or arrow-key behavior. The dropdown has no built-in accessible label. Every text/both/short link receives flag classes, so text-only is not meaningfully text-only. Catalog flags for Catalan (`catala.png`), Basque (`eu_ES.png`), and Welsh (`cy_GB.png`) are absent; the image-only style can consequently provide no visible language control for them.

**Fix:** implement either complete tabs or ordinary toggle buttons, label the dropdown, honor display styles, and provide visible fallbacks when a flag is unavailable. Verify keyboard, screen-reader, RTL, and focus behavior.

## Security and lifecycle review

Settings saves check capability and nonce. Term writes check `edit_term`; normal WordPress term handlers supply their own request protection. The absence of a separate Next term nonce is not, by itself, proof of a CSRF vulnerability in those handlers. Post field HTML from an author is passed through KSES: the database probe removed both a script tag and an event handler. SQL availability checks use `$wpdb->prepare()` and `esc_like()`. Flag paths use `basename()` and an existence check. Uninstall intentionally retains shared data, which is appropriate for switching plugins.

The confirmed security issue is Host-derived redirect handling (18). Save/filter scoping and metadata integrity defects also matter to security-sensitive integrations, but the probes did not establish an unauthenticated write or privilege-escalation path.

The admin-language GET action at `admin.php:35–54` persists user preference without a nonce. This is a lower-impact preference-CSRF hardening item because the preference influences later save destinations. Add a nonce without obstructing normal switching. A capability check alone does not establish request intent.

Front-end requests set a year-long cookie even when the same language is already selected; the HTTP probes observe this for ordinary pages and the XML sitemap. Assess its effect on caching and avoid unnecessary cookie writes. No CDN or persistent object-cache configuration was tested.

## Remediation order and release criteria

1. Fix the eight P1 findings, with tests that compare untouched translations and raw database values before/after saving. Include classic, block, REST, autosave, revision restore, secondary inserts, and custom post types.
2. Separate raw storage access from display translation. Use one codec specification with PHP/JavaScript fixtures, explicit language context, and preservation of disabled keys.
3. Establish tested public API and option matrices for both predecessors. Document read compatibility, write compatibility, fallback policies, and reversible migration limits separately.
4. Correct URL classification, canonicalization, REST detection, and host validation. Test query/path/subdomain/domain modes, ports, subdirectory installs, plain permalinks, feeds, pagination, and signed query strings.
5. Finish Next naming, provenance, accessibility, and distribution documentation. Add an appropriate test runner and CI matrix; the supplied failure probes are starting evidence, not a finished release suite.

This audit did not certify PHP 7.4 or WordPress 5.8, perform an exhaustive PHP/WordPress version matrix, exercise a real multisite migration, configure DNS/TLS for domain modes, or test WooCommerce, ACF, SEO integrations, simultaneous editors, persistent caches, or every custom block. Such checks are release criteria after the confirmed blockers are fixed, rather than grounds to ignore the reproduced failures.

## Running the evidence probes

Use a disposable WordPress installation and database. The PHP database probe creates posts/users/terms and replaces configuration options in that installation. It loads the source in this workspace manually so an active installed copy cannot duplicate the hooks.

```sh
php audit/probes/wordpress.php /path/to/disposable/wordpress
node audit/probes/editor.cjs
php audit/probes/upstream-codec.php /path/to/pinned/qtranslate
php audit/probes/upstream-codec.php /path/to/pinned/qtranslate-x
php audit/probes/load-order.php /path/to/pinned/qtranslate/qtranslate_core.php
php audit/probes/load-order.php /path/to/pinned/qtranslate-x/qtranslate_core.php
```

The load-order probes intentionally exit with a PHP fatal error against this snapshot. JSON `pass=false` values record observed defects; the current scripts emit results rather than enforce a CI exit status.

The browser script expects the prepared local fixture at `http://127.0.0.1:8931`, a copied plugin, its local audit credentials, and a PHP router that serves WordPress paths. It performs a real save. Use the `--prepare-browser` argument on the database probe to produce its fixture IDs; do not run it against a real site. Temporary server/database processes started for this audit were stopped after evidence capture.
