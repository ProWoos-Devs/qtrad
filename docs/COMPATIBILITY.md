# Compatibility in 1.12.1

qTrad reads comment, bracket and swirly markers and retains shared `qtranslate_*` options. It does not declare `QTX_VERSION`: it is not a particular qTranslate-X release. URL enum constants keep their historical numeric values. Only one qTranslate implementation may be active; the guard runs before exporting predecessor function names, including network-active and renamed packages.

| API | Contract |
| --- | --- |
| `qtrans_getLanguage`, `qtranxf_getLanguage`, `qtranxf_getLanguageDefault` | Current/default language code. |
| `qtrans_getLanguageName`, `qtranxf_getLanguageName`, `qtranxf_getLanguageNameNative` | Saved native name, defaulting to the current language. |
| `qtrans_getSortedLanguages`, `qtranxf_getSortedLanguages` | Enabled language order; optionally reversed. |
| `qtrans_isEnabled`, `qtranxf_isEnabled` | Whether the code is enabled. |
| `qtranxf_isMultilingual` | Whether a string contains supported language markers. |
| `qtrans_use`, `qtranxf_use` | Language selection, recursive arrays/objects and fallback/empty policies. A disabled requested language leaves the source intact. |
| `qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage`, `qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage` | Current translation, otherwise first available enabled translation, respecting prefix policy. |
| Both prefixes: `useCurrentLanguageIfNotFoundShowAvailable`, `useCurrentLanguageIfNotFoundShowEmpty`, `useDefaultLanguage` | Missing-translation message/empty/default selection. Legacy alternative-content and message preferences apply. |
| `qtrans_split`, `qtranxf_split` | Language map, including disabled tagged languages. `qtrans_split($text, false)` recognizes comments only. Shared chunks are preserved in disabled translations as well. |
| `qtrans_join` | Array input uses brackets, matching X's historical alias. String input preserves its detected encoding, matching legacy string behavior. |
| `qtranxf_join`, `qtranxf_join_b`, `qtranxf_join_c`, `qtranxf_join_s` | Selected/bracket/comment/swirly serialization; disabled keys and force-markers policy retained. |
| `qtrans_getAvailableLanguages` | Plain strings: enabled languages. Tagged strings: nonempty tagged languages. |
| `qtranxf_getAvailableLanguages` | Plain strings: `false`. Tagged strings: nonempty tagged languages, including disabled ones; an entirely empty marked value returns an empty array. The string `0` is content. These last two rules intentionally fix legacy truthiness behavior. |
| `qtrans_isAvailableIn`, `qtranxf_isAvailableIn` | Post-content availability. Empty body: false. Plain body: available in default language. Default argument selects the default language. |
| `qtrans_convertURL`, `qtranxf_convertURL`, `qtranxf_convertURLs` | Local URLs only; arrays supported by `convertURLs`. External/non-HTTP URLs, fragments, other ports and neutral paths remain unchanged. Unrelated query bytes and repeated keys retained. |
| `qtrans_useTermLib`, `qtranxf_useTermLib` | Strings, term objects and recursive arrays. |
| `qtrans_generateLanguageSelectCode`, `qtranxf_generateLanguageSelectCode` | Echo markup. Accept style strings, legacy boolean flag selection and an options array with `type`/`style` and `id`. |
| `qtranxf_use_language`, `qtranxf_translate_deep` | Language selection with the language first, as in qTranslate-XT; strings, arrays and objects. |
| `qtranxf_get_language_blocks`, `qtranxf_split_languages` | Marker tokens of a string, and the language map built from them. |
| `qtranxf_translate_post` | Translates `post_title`, `post_content` and `post_excerpt` of a post object in place. |
| `qtranxf_get_url_for_language` | URL in the given language, optionally with the default language shown. |
| `qtranxf_term_use` | Term names, term objects and arrays, from markers or the shared term library. |
| `QTX_Translator`, filters `translate_text`, `translate_term`, `translate_url`, `get_language`, `set_language` | qTranslate-XT's translator object and the filters it answers; `QTX_TRANSLATOR_SHOW_*` flags are defined. |

Internal `qtrad_language_chooser()` returns markup for shortcodes/widgets. Chooser HTML and widget CSS differ from the legacy implementations to support accessible controls. The widget ID remains `qtranslate`; old class names remain aliases. Existing unsupported widget options survive updates, but custom format/CSS execution is not supported.

## Persistence

`wp_insert_post()` and `wp_update_post()` retain WordPress's slashed-input contract. Ordinary programmatic writes replace supplied fields, including revision restores. For a partial update, use:

```php
wp_update_post( wp_slash( array(
    'ID'           => $post_id,
    'post_title'   => 'Hallo',
    'qtrad_language' => 'de',
) ) );
```

REST plain writes in language-button mode merge into `qtrad_language`, `lang`, or the request's selected language. Full marker strings and raw-editor writes replace complete supplied fields. Classic form translations require an own nonce and matching post ID; unrelated inserts cannot inherit them. Content sanitization remains subject to WordPress capabilities.

Configured scalar custom metadata translates on public reads and merges plain updates in the selected edit/request language. Raw arrays/objects are not translated as scalar fields. Repeated rows and previous-value conditions are respected. Use a complete marker string for a complete multilingual metadata replacement. `_qtrad_*` keys are reserved for internal metadata.

Term translations update the shared name library and `_qtrad_translations` metadata. Metadata distinguishes terms with identical names in different taxonomies; the legacy name-only library cannot express that distinction. Old-name entries are removed only when no other term uses the name.

## Boundaries

Comments are the backward-write choice for original qTranslate post fields. Original qTranslate does not understand qTrad/X closing brackets or swirly syntax. Blog title/tagline use brackets to survive WordPress option sanitization. Their conversion on rollback is a separate step.

No automatic activation-time rewrite occurs. Availability metadata is maintained as posts change; untouched legacy posts use SQL matching. Availability follows tagged body content, then tagged title if the body is untagged. `0` counts as content; an empty marker does not. There is no bulk index migration.

XML sitemap endpoints remain neutral and are served without language cookies. Core/Yoast/Rank Math entries include available translated post/page/custom-post URLs. Alternate links and metadata share the routing canonical policy. Missing singular translations receive noindex. SEO.md describes overrides, archive policy and adapter limits. Language-specific menus, legacy date-formatting APIs and predecessor extension modules remain outside this release.

The declared minimums remain WordPress 5.8/PHP 7.4. All 12 cells in ../tests/matrix/cells.json passed for 1.12.1 on GitHub Actions (run https://github.com/ProWoos-Devs/qtrad/actions/runs/37738126145): representative compatible pairings through WordPress 7.1.2/PHP 8.5, with MySQL 8.0 and MariaDB 10.11. Actual reports and runtime/test fingerprints live in ../tests/results/matrix/. This samples version boundaries rather than every possible combination. Legacy PHP cells verify migration compatibility, not a production hosting recommendation. The older first-round WordPress 6.8/PHP 8.5 result is retained as historical evidence and is outside core’s official pairing matrix.

Multisite language/options caches follow each site and restore the caller’s selection after nested switch_to_blog()/restore_current_blog() calls. Site-specific custom blocks, plugins, themes, domain routing and caches still require staging validation.

## Plugin identity

The plugin entry point is `qtrad/qtrad.php`, with text domain and settings page slug `qtrad`. qTrad's own names use the `qtrad_` prefix: settings in the `qtrad_settings` option, the `qtrad_edit_language` user preference, `_qtrad_*` post and term metadata, the `qtrad_seo_*` filters and the `[qtrad_switcher]` shortcode. Earlier development builds (called qTranslate Unified and qTranslate Next) were never released, so their names are not carried over.

`Qtrad_Widget` is the registered widget class. The `qTranslateWidget` and `qTranslateXWidget` class names from qTranslate and qTranslate-X remain available, and the widget ID base remains `qtranslate`, so sidebars that held either predecessor's widget keep it.
