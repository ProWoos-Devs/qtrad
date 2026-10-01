# First implementation round — qTranslate Next 1.1.0

The initial [audit report](REPORT.md) and baseline results remain unchanged. This round implements storage, routing, compatibility and accessibility fixes in `../qtranslate-next/`, with regression tests and new evidence in `../tests/`.

| Initial finding | Result |
| --- | --- |
| 1. Recursive save subscription | Update transition state before dispatch, guard presentation, leave successful saves clean, preserve edits made while saving. |
| 2. Disabled languages lost | Preserve all tagged language keys in PHP/JS and merge enabled form values into existing post, term and site-option translations. Shared chunks/more separators survive disabled languages. |
| 3. Slashing corruption | Normalize at WordPress storage boundaries; preserve untouched whitespace/backslashes and JSON escaping. |
| 4. Metadata writes read translated values | Raw reads isolated from display filters; repeated rows, previous-value constraints, preceding providers and `0` retained. |
| 5. Broad fetch middleware | Match the current ID, registered REST namespace/base, local REST root and autosaves only; clone payloads. |
| 6. Custom post types | Dynamic REST save hooks, endpoint configuration, public editor controls and custom taxonomy fields. |
| 7. Secondary inserts inherit form values | Own nonce and matching post ID scope classic payloads/context. Programmatic complete writes remain replacements. |
| 8. Conflict check too late | Check active/network-active/renamed/loaded predecessors before exporting aliases. |
| 9. API contracts | Implement selected missing APIs, string joins, comments-only splits, available-language contracts, recursive helpers; publish the supported matrix and deliberate deviations. |
| 10. Chooser returns instead of echoes | Preserve legacy echo/string/boolean/options-array calls; internal renderer returns markup. Unsupported widget settings survive updates. |
| 11. Term-library helpers | Support strings, term objects and recursive arrays. |
| 12. Custom settings/languages lost | Preserve imported languages, edit names/locales, validate custom additions/domains, retain disabled title/tagline values. |
| 13. Backward write formats | New translated fields use comments; existing encodings remain. Explain original qTranslate limitations and bracket-only site options. |
| 14–16. URL handling | Local origin/port checks, external/scheme/fragment preservation, segment-aware subdirectory handling and query-byte preservation. |
| 17. REST detection | Recognize both early REST forms and establish/restore per-request language context. |
| 18. Host-derived redirects | Select from configured origins and canonical home rather than arbitrary incoming Host values. |
| 19. Legacy policies | Honor missing-translation messages, alternative-content, prefix and force-markers settings. |
| 20. Term renames | Capture raw old names, merge disabled entries, clean obsolete keys only when no other term uses them; term metadata distinguishes name collisions. |
| 21. Browser preferences | Explicit/cookie/browser/default precedence; quality weights and exact locale preferences; no cookie churn on REST. |
| 22. Alternate links | Canonical URLs and singular availability; core XML sitemaps remain neutral. Full multilingual sitemaps/SEO adapters are follow-up work. |
| 23. `0` serialization | Strict emptiness checks in PHP and shared cross-runtime fixtures. |
| 24. Empty translations appear in listings | Exact per-post availability index maintained on changes; legacy SQL fallback excludes empty markers. No bulk migration. |
| 25. Rename | Canonical Next filename/text domain/settings slug/widget class/readme; preserve old bootstrap, option reads and class aliases. |
| 26. Distribution provenance | Add GPL text and source credits/commits. Individual flag-image provenance remains unresolved before redistribution. |
| 27. Accessibility | Native labelled controls, selection/status semantics, focus, RTL/iframe language attributes, 44px targets, reflow/forced-colors, explicit dropdown action/no-JS fallback, labelled settings/errors and a translation template. |

Executed evidence: WordPress 6.8 with PHP 8.5.10, shared PHP/JS codec fixtures, synchronous editor-store regressions, five real predecessor guard cases and Chromium/axe-core 4.10.3 browser checks. The WordPress suite passes 80 assertions, the browser suite 40, and the editor adapter 15; the shared codec tests and five conflict-guard cases also pass. Axe reports zero violations in the tested plugin controls, with a settings contrast item requiring manual review. See `../tests/results/` and `../tests/README.md` for reproducibility.

This completes the first implementation round, not a blanket certification of every legacy integration or WordPress/PHP version. Remaining work includes assistive-technology/manual contrast review, the runtime minimum-version matrix, full multilingual SEO/sitemaps, site-specific integration adapters and file-level flag provenance. The declared minimums have not been exercised in the integration matrix. No production database or external deployment was changed.
