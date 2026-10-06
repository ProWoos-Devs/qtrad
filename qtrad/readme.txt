=== qTrad ===
Contributors: rafaelminuesa
Tags: multilingual, bilingual, language, translation, i18n
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual content that keeps your qTranslate and qTranslate-X translations, with accessible editing and multilingual SEO.

== Description ==

qTrad is an independent continuation of qTranslate and qTranslate-X. Existing translations keep working as they are, and you gain modern editing, accessible language controls and multilingual SEO.

qTrad keeps all translations of a post title, content and excerpt together in the same field. It reads the qTranslate comment markers, the qTranslate-X bracket markers and the swirly markers.

* <!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->
* [:en]Hello[:de]Hallo[:]
* {:en}Hello{:de}Hallo{:}

The classic and block editors show one language at a time. Saving preserves the other translations, including those of disabled languages. Public custom post types with a REST endpoint are supported. Raw mode shows the complete multilingual field for direct editing.

Language controls use full names, keyboard-operable buttons, announced selection changes, visible focus, generous targets and right-to-left editing. Public switchers offer links, flags with accessible names, short codes, or a labelled dropdown with an explicit Go button. Dropdowns fall back to ordinary links without JavaScript.

The qtranslate_* language, locale, flag, URL, term and fallback settings are reused as they are. Existing qTranslate and qTranslate-X widgets keep their place in the sidebar. Imported custom languages remain available in Settings → Languages, where you can edit their names and locales and add new two- or three-letter language codes.

qTrad offers content compatibility and a documented subset of the qtrans_* and qtranxf_* public functions. It does not include every feature of its predecessors or their third-party integration modules.

= Read and write compatibility =

The "Keep" format preserves whichever marker style each field already uses, and new translated post fields use comment markers. Choose comments if a site may need to return to the original qTranslate, which cannot read qTranslate-X closing brackets or swirly markers. The site title and tagline always use brackets because WordPress strips comments from those settings, so returning those two to the original qTranslate needs a deliberate conversion. Activation never rewrites posts.

= Custom fields, user profiles and options =

List custom fields under "Custom fields" in Settings → Languages to show them in the visitor's language and to merge edits into the language you are editing. A checkbox below that list also shows every other custom field and user profile field that holds language markers in the visitor's language. Under "Options" you can do the same for options, such as theme and widget settings, either all of them or a list of names. "Text filters" takes filter hooks of your theme or other plugins whose text should be shown in the current language. These three work on the public site only: wp-admin, the REST API and WP-CLI keep reading what is stored. If code on the public site writes a value back after reading it translated, qTrad keeps the stored translations of everything that did not change.

Translations of disabled languages are kept until you replace a complete field in raw mode or through a full programmatic update. Ordinary wp_update_post() calls replace the fields they supply. Pass qtrad_language to request an update of one language only. REST saves in language-button mode merge plain values into the language given by qtrad_language or lang, otherwise into the request language. Complete marker strings replace complete fields.

= URLs =

The URL modes are the same as in qTranslate-X:

1. Query parameter
2. Language path
3. Subdomain
4. One domain per language

Sites with plain permalinks use query URLs. Subdomains and separate domains need the matching DNS, certificates and server configuration.

An explicit language URL always wins. Cookies and browser negotiation apply only to the bare homepage, and other bare URLs serve the default language when its prefix is hidden. REST endpoints, assets, admin URLs, feeds and XML sitemaps never receive language prefixes.

Each language can have its own slug for a post, page, custom post type entry, category, tag or other term. Edit them in the "Translated slugs" box of the editor and on the term screens; an empty field uses the WordPress slug. Post types and taxonomies can also have a translated base, such as "kategorie" instead of "category", under Settings → Languages. Links, the language switcher, canonical and hreflang tags and sitemaps use the translated address, and the WordPress address under another language redirects to it. The WordPress admin and the REST API keep working with the WordPress slug.

Slugs and bases are stored the way qTranslate-XT stores them, so a site that used its Slugs module keeps its addresses, and slugs left by the older qTranslate Slug plugin are read as well.

= SEO =

Alternate language links and multilingual sitemap entries use canonical URLs and only list languages a post is actually translated into. Adapters for WordPress core, Yoast SEO and Rank Math cover metadata, social sharing and schema output. Pages missing a translation keep the configured visitor fallback and receive noindex. The SEO translations panel takes optional per-language titles and descriptions.

Full-page caches should bypass the personalized bare homepage or vary it by the language cookie and Accept-Language. Browser negotiation can be turned off for a stable homepage.

= Switchers =

Use the shortcode [qtrad_switcher style="both"] or the qTrad Language Chooser widget. Styles are text, image, both, short and dropdown. Prefer text or both so visitors can identify languages without interpreting flags. The qtrans_* and qtranxf_* chooser functions used in older themes keep working.

Language menu items created with qTranslate-X or qTranslate-XT (custom links to #qtransLangSw) keep working in classic navigation menus, with their type, title, flags, names, colon and current options. To add one, create a custom link with the URL #qtransLangSw for a "Language" item with all languages below it, or #qtransLangSw?type=AL for a direct link to the other language. Custom links that point into the site follow the visitor's language; add setlang=no to a link's query string to keep it as written.

== Installation ==

1. Back up the site and try the switch on a staging copy first.
2. Deactivate qTranslate, qTranslate-X or qTranslate-XT. qTrad will not start while one of them is active.
3. Install and activate qTrad, then open Settings → Languages.
4. Check representative content, editor saves, language URLs and any site-specific integrations.

Your posts and qtranslate_* settings need no conversion. Uninstalling qTrad keeps translations and settings in place.

== Frequently Asked Questions ==

= Is this a drop-in replacement for every extension? =

No. qTrad covers stored content, the shared settings and the commonly used public functions of qTranslate and qTranslate-X. Other integration modules, menu-language management and third-party custom editor controls need to be checked on your site.

= What if JavaScript is unavailable? =

Public switchers remain plain links. In the classic editor the full marker strings stay visible and the language buttons stay disabled, so edit complete fields carefully. The WordPress block editor itself requires JavaScript.

= I am moving from qTranslate-XT. What should I check? =

Posts, pages, titles, excerpts, term names, the site title and tagline, widgets with markers, language menu items and the shared qtranslate_* settings work as they are, including two- and three-letter language codes. Translated slugs and translated URL bases keep their addresses and can be edited in qTrad. Custom fields, user profile fields and options with language markers are shown in the visitor's language: on the first visit to wp-admin qTrad looks for such data, turns the matching settings on and lists what it found. qTrad does not include XT's integration modules (ACF, WooCommerce, Yoast, Gravity Forms and others) or per-language date formats, and lists those as well. No content is changed or deleted, so reactivating qTranslate-XT shows that data again.

= How do I include a literal marker in content? =

Language markers are reserved syntax. To show an example such as [:en] in a post, encode it with HTML entities, for example &#91;:en&#93;.

== Credits ==

qTrad continues the work of qTranslate by Qian Qin and qTranslate-X by the qTranslate Team, both licensed GPLv2 or later. It is an independent project and is not endorsed by the original authors.

The bundled flag images are unchanged SVGs from flag-icons 7.3.2 by Panayiotis Lipiridis, licensed MIT. The full notice is in flags/LICENSE.flag-icons and the source of each file is recorded in flags/manifest.json.

== Screenshots ==

1. The block editor shows one language at a time. Language buttons switch between translations, and saving keeps every other translation.
2. Settings → Languages, with the qTranslate URL modes and language list.
3. The accessible language chooser on the front end, with full language names.
4. After a switch from qTranslate-XT, administrators see which data qTrad keeps but does not display.

== Changelog ==

= 1.7.0 =

* Custom fields and user profile fields that contain language markers can be shown in the visitor's language without listing each one.
* Options that contain language markers, such as theme and widget settings, can be translated on the public site, all of them or a list of names.
* Text filters: filter hooks of a theme or another plugin whose text is shown in the current language.
* Code on the public site that writes back a value it read translated keeps the other languages.
* After a switch from qTranslate-X or qTranslate-XT, these settings are turned on where such data is found.

= 1.6.0 =

* Translated slugs can be edited: a "Translated slugs" box in the editor and fields on the term screens, one slug per language.
* Post types and taxonomies can have a translated URL base per language, under Settings → Languages. Bases stored by qTranslate-XT are used as they are.
* Addresses from before a base was translated redirect to the new address.

= 1.5.0 =

* Translated slugs stored by qTranslate-XT or the qTranslate Slug plugin are served: posts, pages, custom post types and terms open under the slug of each language.
* Links, the language switcher, canonical and hreflang tags and sitemaps use the translated slug, and the stored slug under another language redirects to it.
* The migration notice reports translated URL bases, which are not used yet.

= 1.4.0 =

* Language menu items created with qTranslate-X or qTranslate-XT (custom links to #qtransLangSw) work in classic navigation menus.
* Custom menu links that point into the site follow the visitor's language.
* The Plugins screen links to the settings page as "Settings".

= 1.3.0 =

* First public release on WordPress.org.
