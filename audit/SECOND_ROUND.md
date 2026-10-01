# Second implementation round — qTranslate Next 1.2.0

Completed the authorized sequence: establish the compatibility matrix, resolve flag provenance, validate the SEO implementation against the matrix, then build a local release. The original audit and first-round evidence remain unchanged.

## Version coverage

All 12 configured cells passed on 1 October 2026. Every cell uses a fresh disposable WordPress installation and private MySQL/MariaDB container. Actual versions, official package hashes, Docker image IDs, execution times, and plugin/test SHA-256 inventories are recorded with each result. No production installation or database was used.

| WordPress | PHP (executed) | Database image | Result |
| --- | --- | --- | --- |
| 5.8 | 7.4.33 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp58-php74-maria/report.json) |
| 6.0 | 8.0.30 | mysql:8.0 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp60-php80-mysql/report.json) |
| 6.4 | 8.1.34 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp64-php81-maria/report.json) |
| 6.8 | 8.2.34 | mysql:8.0 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp68-php82-mysql/report.json) |
| 6.8 | 8.3.35 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp68-php83-maria/report.json) |
| 6.8 | 8.4.26 | mysql:8.0 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp68-php84-mysql/report.json) |
| 6.9 | 8.5.11 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp69-php85-maria/report.json) |
| 7.1.2 | 7.4.33 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp71-php74-maria/report.json) |
| 7.1.2 | 8.2.34 | mysql:8.0 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp71-php82-mysql/report.json) |
| 7.1.2 | 8.3.35 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp71-php83-maria/report.json) |
| 7.1.2 | 8.4.26 | mysql:8.0 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp71-php84-mysql/report.json) |
| 7.1.2 | 8.5.11 | mariadb:10.11 | [Pass](../tests/results/qtranslate-next-1.2.0-matrix/wp71-php85-maria/report.json) |

This is representative boundary coverage, not every possible combination. Minimum requirements stay WordPress 5.8/PHP 7.4; the readme's tested declaration advances to WordPress 7.1 after executing 7.1.2. Historical versions are migration fixtures, not hosting recommendations. The GitHub Actions workflow is configured to run the same matrix; the evidence above comes from completed local Docker runs, not a hosted CI run.

Recorded totals across the matrix:

| Suite | Passing recorded cases |
| --- | ---: |
| PHP codec | 120 |
| Existing storage/routing/REST integration | 960 |
| SEO model, URL, availability, metadata and save security | 912 |
| Core SEO over HTTP | 720 |
| Real Yoast SEO over HTTP | 780 |
| Real Rank Math SEO over HTTP | 768 |
| Chromium browser behavior/accessibility | 84 |
| Real multisite context/conflicts | 20 |

The browser/multisite suites execute on WordPress 5.8/PHP 7.4 and WordPress 7.1.2/PHP 8.5. Both pass 42 browser cases and ten multisite cases. Browser reports contain no JavaScript errors or axe violations. Additional host checks pass 11 JavaScript codec cases, 17 editor-store cases, five real predecessor conflict-guard cases, PHP/JavaScript syntax checks and translation-template validation. POT template header placeholders remain intentionally untranslated.

Captured logs contain no Next PHP warnings, notices, deprecations or fatal errors. The 222 captured PHP diagnostics are third-party deprecations from the pinned compatibility fixtures; they remain visible in the logs and those libraries are not distributed with Next. See [aggregate evidence](../tests/results/qtranslate-next-1.2.0-matrix/summary.json) and [diagnostics summary](../tests/results/qtranslate-next-1.2.0-matrix/diagnostics-summary.json).

## Flag provenance resolved

Removed all 38 inherited PNGs, retaining their hashes/replacement mappings in [the replacement record](results/replaced-flags.json). The distribution now contains 41 unchanged SVGs from flag-icons 7.3.2, pinned to commit `fe15c16e7463d0c66d6c5730e9d0e832438d98e1`. Each has an exact upstream source URL, SHA-256, MIT license entry and modification statement in [the manifest](../qtranslate-next/flags/manifest.json). The complete upstream MIT notice is bundled. The offline checker validates inventory/hashes/notices and excludes active/external SVG content.

Country PNG settings resolve to matching SVGs; Arab League, Galician and optional regional aliases have explicit mappings. Shared predecessor options are not rewritten. `ca.png` retains its Canadian meaning, and empty/missing flag selections retain readable language names. See [PROVENANCE.md](../qtranslate-next/PROVENANCE.md).

## SEO implemented and validated

Core, Yoast and Rank Math use shared translation-availability and canonical URL rules. HTML alternate links are reciprocal and use valid locale-derived SEO language tags. Missing singular translations keep visitor fallback behavior but receive noindex and no alternate claims. Sitemaps expand available published post/page/custom-post translations, remain language-neutral, omit missing/private/draft/password/disabled/empty variants, and preserve vendor lastmod/image extensions. Core batch-boundary fixtures check small pagination limits and duplicate prevention.

Native descriptions/social tags/basic JSON-LD are supplied when a supported SEO plugin is absent. When present, the SEO plugin owns its output and Next filters translated titles/descriptions, canonical/social URLs, schema and sitemap entries. Repeated English/German/Spanish HTTP requests exercise vendor caches. Actual pinned integration packages are Yoast 17.9/25.9/28.6 and Rank Math 1.0.76.1/1.0.279, selected according to WordPress requirements and activated separately through WordPress hooks.

An accessible SEO-translations panel provides labelled language-specific titles/descriptions, native inputs, language/direction attributes and optional overrides. Nonce/capability/post-ID validation scopes saves; disabled translations remain stored. The new fields require no JavaScript. See [SEO.md](../qtranslate-next/SEO.md) for behavior, hooks and integration limits.

## Compatibility fixes found during the matrix

Gutenberg now receives the selected language in its initial admin preload while the complete raw translation map remains available to Next. Ordinary REST edit requests still return complete raw fields. Matching blocks retain their identities across language changes, avoiding unnecessary TinyMCE teardown. Classic rich-editor initialization is guarded. Store regressions verify disabled-language retention with the selected-language preload.

The WordPress 5.8 browser harness waits for inline TinyMCE to initialize before dismissing the welcome dialog by keyboard; its old document mouse handler otherwise runs before an editor body exists. No page errors are filtered out.

Multisite switches reload each site's own language/options context; nested restores preserve the caller's chosen language. Real network fixtures also verify predecessor conflicts when plugins are network active.

## Packaging and remaining limits

The local package builder requires all 12 passing cells, complete browser/axe/multisite evidence at both boundaries, matching current runtime/test inventories and successful flag verification. Five non-mutating rejection checks confirm it rejects untested runtime inventory, stale tests, failed cells, missing browser evidence and changed flag artwork. Packaging includes only the distribution directory, with licenses and asset manifest; tests, SEO plugins, Node dependencies and Docker fixtures are excluded.

No release was published or deployed. Manual NVDA/Firefox and VoiceOver/Safari testing, speech input, 200–400% zoom, open disclosures and axe's incomplete color-contrast checks remain. Zero automated violations is not site-wide WCAG certification. Taxonomy/author archives use shared archive views; translated slugs, per-language archive availability/robots, unsupported SEO plugins, premium extensions, theme/custom-block integrations, domain routing and production caches need separate staging validation.
