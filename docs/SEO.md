# Multilingual SEO in 1.3.0

qTrad uses the same URL and translation-availability rules for canonical tags, HTML `hreflang` links and XML sitemap entries. Language-tag generation maps configured WordPress locales to SEO tags (for example legacy `pb` → `pt-BR`, `tw` → `zh-TW`, `ua` → `uk`) and removes locale suffixes such as `_formal`. Unsupported language identifiers are omitted; imported storage codes do not have to equal the emitted language tag.

## Editing

The **SEO translations** panel supplies optional titles and descriptions for every enabled language in classic and block editors. Native labelled controls require no JavaScript and include each field's language/direction. Saving is post/nonce/capability scoped and preserves disabled translations. Blank values fall back to existing SEO settings or generated metadata. Overrides are stored separately in `_qtrad_seo_title` and `_qtrad_seo_description`; activation does not rewrite content or predecessor metadata.

With no supported SEO plugin active, qTrad adds translated descriptions, OpenGraph/Twitter metadata and a basic WebPage JSON-LD graph. WordPress continues to own singular canonical tags; qTrad adjusts their URLs and adds archive canonicals. The SEO panel's title override participates in WordPress document-title generation.

## Yoast and Rank Math

When Yoast or Rank Math is active, it owns its tags and XML sitemaps. qTrad filters their existing output instead of adding a competing description/social/schema generator. Adapters select marked metadata, apply qTrad's optional overrides, localize canonical/social URLs and schema text/identifiers, and expand vendor-rendered sitemap entries while retaining lastmod/image extensions. Yoast's older schema-piece hooks and newer graph hook are both supported. Only one SEO plugin should be active.

Core, Yoast and Rank Math sitemap endpoints remain language-neutral, including plain-permalink sitemap queries. Entries cover available published post/page/custom-post translations and the provider's existing homepage, taxonomy and author URLs. Core provider filters and vendor exclusions remain in force. Expansion reduces each provider's batch size to leave room for translated URLs; sitemap generation bypasses a visitor's selected-language archive filter. Sitemaps use neither language cookies nor browser negotiation.

## Missing translations and exclusions

A tagged body determines post availability; a tagged title is used when the body is untagged. Nonempty plain fields are deliberately shared content under qTrad's existing availability policy. Empty marked translations are excluded; the string `0` is content. Disabled languages are retained in storage but omitted from alternates/sitemaps.

Missing-language pages retain the site's existing visitor fallback behavior and receive `noindex`. They do not claim reciprocal alternates or appear as translated sitemap entries. Draft/private/password-protected posts are excluded. Vendor noindex metadata and canonical overrides pointing away from the current post are respected. `qtrad_seo_post_indexable` allows a site to exclude additional post/language pairs.

Taxonomy/author archives are treated as shared archive views; individual translated slugs, archive-level translation availability and per-language robots settings are not introduced. Existing SEO plugin templates, images, breadcrumbs and schema types remain the vendor's responsibility. Yoast breadcrumb links are localized; custom integrations can filter `qtrad_seo_language_tag` or `qtrad_seo_schema`. Other SEO plugins and premium extensions need their own adapters/validation.

## Validation

The matrix in `../tests/matrix/cells.json` runs persistence, SEO-model and actual HTTP HTML/XML checks against core and compatible pinned Yoast/Rank Math packages. Requests alternate English/German/Spanish repeatedly to exercise cached output. Fixtures include missing/disabled/empty translations, `0`, private/draft/password-protected content, a custom post type and tiny core sitemap batches. Browser cells additionally exercise accessible editing/saving of the SEO panel. See `../tests/results/matrix/` for actual results and runtime/package fingerprints; a configured CI cell alone is not a passing result.

Technical references: [Google localized-page guidance](https://developers.google.com/search/docs/specialty/international/localized-versions), [WordPress sitemap providers](https://developer.wordpress.org/reference/classes/wp_sitemaps_provider/), [Yoast metadata API](https://developer.yoast.com/customization/apis/metadata-api/) and [sitemap API](https://developer.yoast.com/features/xml-sitemaps/api/), [Rank Math hooks](https://rankmath.com/docs/filters-and-hooks/frontend/).
