# qTrad branding release — 1.2.1

The selected name is **qTrad — Multilingual Content**, with package slug/text domain `qtrad` and canonical entry point `qtrad/qtrad.php`. Its description identifies it as an independent continuation of qTranslate and qTranslate-X. No WordPress.org submission or name reservation has been made.

The package directory, settings page, interface strings, widget label, development tooling, documentation and translation template now use qTrad. The source version and readme stable tag are 1.2.1. The readme has five tags. Earlier product names remain only where they explain history or preserve compatibility.

Existing multilingual markers, qtranslate_* options, qtranslate_next_settings/qtranslate_unified_settings, _qtn_* metadata, legacy SEO filter names, qtrans_*/qtranxf_* APIs, widget ID and former widget classes retain their contracts. The new [qtrad_switcher] shortcode uses the same renderer as the retained [qtranslate_switcher]/[qtu_switcher] shortcodes. Former qtranslate-next.php and qtranslate-unified.php filenames remain header-free bootstraps inside the new folder, leaving one activatable WordPress entry point. Conflict checks also recognize the former Next/Unified distributions.

## Upgrading a former package

Deactivate qTranslate Next or Unified, upload the qtrad folder and activate qTrad. The folder/entry-point change requires manual activation; content and settings require no conversion. Integrations hardcoding an absolute former folder path must update that path. Do not activate both distributions together.

## Licensing

GPL-2.0-or-later is retained. PROVENANCE.md now records the exact qTranslate-X entry-point grant at the audited commit: it permits GPLv2 or any later version and applies throughout the source tree. The upstream GPLv3 README/license-file discrepancy is documented. Applicable Qian Qin/qTranslate Team copyright, warranty and source notices remain documented; the 41 SVG flags retain their unchanged MIT notice and pinned provenance.

## Executed verification

All 12 matrix cells pass for the renamed 1.2.1 distribution, including actual core/Yoast/Rank Math HTTP output, both Chromium/axe runs and both real multisite runs. Each cell passes 85 storage/routing/REST/rename integration cases and 76 SEO-model cases. Both browser reports pass 42 assertions, with no JavaScript errors or axe violations; both multisite reports pass ten cases. Added checks verify former bootstraps, one activatable entry, preserved widget identity/class and equivalent native/legacy shortcode output. Host checks pass 17 editor cases, 11 JavaScript codec cases and seven conflict-guard cases. PHP/JavaScript syntax and translation-template checks pass, with expected untranslated POT-header placeholders.

Captured logs contain no qTrad PHP warnings, notices, deprecations or fatal errors. Third-party fixture deprecations remain recorded. Current runtime/test fingerprints are verified by the release gate. The builder includes only the qtrad distribution directory, plus licenses and flag manifest, and emits a local ZIP/checksum.

See [current matrix evidence](../tests/results/matrix/summary.json), [compatibility and upgrade details](../qtrad/COMPATIBILITY.md), and [provenance](../qtrad/PROVENANCE.md). The previous 1.2.0 matrix is preserved in tests/results/qtranslate-next-1.2.0-matrix/ and its original ZIP remains in dist/. Manual screen-reader/contrast checks and site-specific production pilots remain as documented in ACCESSIBILITY.md; automated results do not certify an entire site.
