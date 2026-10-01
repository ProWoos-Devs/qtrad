=== qTrad — Multilingual Content ===
Tags: multilingual, bilingual, language, accessibility, i18n
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An independent continuation of qTranslate and qTranslate-X with accessible multilingual editing and SEO.

== Description ==

Keep your existing qTranslate translations and gain modern editing, accessible language controls and multilingual SEO. qTrad is an independent continuation of qTranslate and qTranslate-X, previously developed here as qTranslate Next and qTranslate Unified.

qTrad keeps translations together in post titles, content and excerpts. It reads qTranslate comment markers, qTranslate-X bracket markers and swirly markers:

* <!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->
* [:en]Hello[:de]Hallo[:]
* {:en}Hello{:de}Hallo{:}

Classic and block editors show one language at a time. Saving preserves the other translations, including disabled languages. Public custom post types with a REST endpoint are supported. Raw mode leaves complete multilingual fields visible for direct editing.

Language controls use full names, keyboard-operable buttons, announced selection changes, visible focus, generous targets and right-to-left editing. Public switchers offer links, flags with accessible names, short codes, or a labelled dropdown with an explicit Go button. Dropdowns fall back to ordinary links without JavaScript. See ACCESSIBILITY.md for verified coverage and remaining manual checks.

Legacy qtranslate_* language, locale, flag, URL, term and fallback settings are retained. Existing qtranslate widgets keep their sidebar identity. Imported custom languages remain available in Settings → Languages; you can edit their names/locales and add two-letter language codes.

This is content compatibility and a documented subset of the legacy public APIs. It does not include every predecessor feature or third-party integration module. See COMPATIBILITY.md for the supported functions and limits.

= Read and write compatibility =

Keep preserves the format already in each field and uses comments for newly translated post fields. Select comments if a site needs to return to original qTranslate. Original qTranslate cannot correctly read qTranslate-X closing brackets or swirly syntax. Site title and tagline use brackets because WordPress sanitizes comments out of those options; returning those settings to original qTranslate requires deliberate conversion. Activation never rewrites posts.

Disabled translations are retained until you intentionally replace a complete field in raw mode or through a full programmatic update. Ordinary wp_update_post() calls replace supplied fields; pass qtu_language to request a partial language update. REST saves in language-button mode merge plain values into the requested qtu_language or lang, otherwise the request language. Complete marker strings replace complete fields.

= URLs =

Legacy modes: 1 query, 2 language path, 3 subdomain, 4 mapped domains. Plain permalinks use query URLs. Subdomains/domains need appropriate DNS, certificates and server configuration.

Explicit language URLs take precedence. Cookies/browser negotiation apply on the bare homepage; bare interior URLs consistently serve the default language when its prefix is hidden. Public switching to the default uses an explicit URL to update the language cookie, then redirects to its canonical address. REST endpoints, assets, administrative URLs, feeds and XML sitemaps do not receive language prefixes.

Alternate language links and multilingual sitemap entries use canonical URLs and available translations. WordPress core, Yoast and Rank Math adapters cover metadata, social sharing and schema output. Missing translations receive noindex while retaining the configured visitor fallback. The SEO translations panel supplies optional language-specific titles/descriptions. See SEO.md for scope and tested integration versions. Configure full-page caches to bypass personalized bare-homepage responses or vary by the language cookie/Accept-Language. Browser negotiation can be disabled for a stable homepage.

= Switchers =

[qtrad_switcher style="both"]

Styles: text, image, both, short, dropdown. Prefer text or both so visitors can identify languages without interpreting flags. The legacy qtranslate widget ID and qtrans_*/qtranxf_* chooser functions are supported. Legacy custom widget templates/CSS are retained in saved options for rollback but are not executed by qTrad.

== Installation ==

1. Back up the site and test the switch on a staging copy.
2. Deactivate qTranslate, qTranslate-X and qTranslate-XT.
3. Upload the qtrad folder to wp-content/plugins/.
4. Activate qTrad and open Settings → Languages.
5. Verify representative content, editor saves, language URLs and site-specific integrations.

The canonical entry point is qtrad/qtrad.php. When upgrading qTranslate Next or Unified, deactivate the former plugin, upload the qtrad folder and activate qTrad. The folder/entry-point change requires manual activation; existing content and settings need no conversion. Do not activate both distributions together.

qtranslate-next.php and qtranslate-unified.php remain bootstrap shims inside the qtrad folder for direct integrations; neither has a plugin header. Integrations using an absolute path to a former folder must update that path. Existing qtranslate_unified_settings and qtranslate_next_settings, qtranslate_* shared settings, legacy widget identity and [qtranslate_switcher]/[qtu_switcher] shortcodes remain supported. New settings saves keep the existing qtranslate_next_settings key. Uninstall deliberately retains translations/settings.

== Frequently Asked Questions ==

= Is this a drop-in replacement for every extension? =

No. The supported API matrix is in COMPATIBILITY.md. Other integration modules, menu-language management and third-party custom editor controls need separate validation. Bundled flags have pinned MIT-licensed sources and an offline provenance check; see PROVENANCE.md.

= What if JavaScript is unavailable? =

Public switchers remain links. In the classic editor, full marker strings remain visible and language buttons stay disabled. Edit complete fields carefully. The WordPress block editor itself requires JavaScript.

= How do I include a literal marker in content? =

Language markers are reserved syntax. Encode a literal example such as [:en] using HTML entities (for example &#91;:en&#93;) in HTML/code content rather than inserting it as an actual marker.

== Changelog ==

= 1.2.1 =

* Rename the plugin to qTrad — Multilingual Content, using qtrad/qtrad.php and the qtrad text domain.
* Keep earlier bootstrap filenames, settings, widget classes and shortcodes for compatibility; add [qtrad_switcher].
* Document the exact upstream GPLv2-or-later source-tree grant and retain MIT flag notices.

= 1.2.0 =

* Add a reproducible WordPress/PHP/MySQL/MariaDB test matrix and CI workflow.
* Add multilingual core, Yoast and Rank Math metadata/sitemap integration.
* Add accessible language-specific SEO title/description fields and missing-translation noindex behavior.
* Replace inherited flag PNGs with pinned MIT-licensed SVGs and preserve saved filename aliases.
* Preserve per-site language context through multisite switches/restores.
* Preload the selected editor language and preserve matching block identities when switching.

= 1.1.0 =

* Fix recursive editor save handling and scope packing to the current post's REST endpoint.
* Preserve disabled languages, whitespace, backslashes, metadata rows and conditional updates.
* Support custom post REST saves/autosaves, raw replacements and term translation renames.
* Correct URL origin checks, query preservation, REST handling, browser preferences and canonical alternates.
* Retain imported/custom languages and legacy fallback/marker policies.
* Add accessible language controls, progressive dropdown enhancement, RTL canvas support and regression tests.
* Complete qTrad branding and document licensing, compatibility and verified accessibility coverage.

= 1.0.0 =

* Initial implementation, previously called qTranslate Unified.
