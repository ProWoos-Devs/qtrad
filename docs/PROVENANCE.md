# License and provenance

qTrad is distributed under GPL-2.0-or-later. The complete GNU GPL version 2 text is included in LICENSE.

Copyright 2008 Qian Qin (mail@qianqin.de). Copyright 2014 qTranslate Team (qTranslateTeam@gmail.com). Their applicable copyright and license notices remain attached to reused/derived material. qTrad is an independent continuation with subsequent code and corrections; the name does not claim endorsement by the original authors.

The audit compared these source snapshots:

- [qianqin/qTranslate](https://github.com/qianqin/qTranslate/tree/a655eca1129af1d966a4a0308f134c768da4e513): marker handling, shared options, public `qtrans_*` functions and legacy widget behavior.
- [qTranslate-Team/qtranslate-x](https://github.com/qTranslate-Team/qtranslate-x/tree/e0e0c378308a5c1c7746357f04c41326feb05d64): bracket/swirly parsing, option policies, `qtranxf_*` functions, URL modes and compatibility behavior.

The supplied implementation was previously called qTranslate Unified and qTranslate Next. Audit/fix rounds rewrote persistence, routing and editor/control behavior. The source snapshots identify compatibility references; they do not establish a line-by-line origin for every file in the supplied implementation.

The original [qTranslate core header](https://github.com/qianqin/qTranslate/blob/a655eca1129af1d966a4a0308f134c768da4e513/qtranslate_core.php) permits GNU GPL version 2 or, at the recipient's option, any later version. The exact [qTranslate-X entry-point header](https://raw.githubusercontent.com/qTranslate-Team/qtranslate-x/e0e0c378308a5c1c7746357f04c41326feb05d64/qtranslate.php) makes the same grant and explicitly applies it to every file in that folder and its subfolders. The notice disclaims warranty, including merchantability and fitness for a particular purpose. qTrad relies on these explicit GPL-2.0-or-later source grants.

Upstream GPLv3 README/license-file labels are inconsistent with those GPLv2-or-later source notices. This discrepancy is recorded rather than treated as a reason to remove or replace the explicit grants. qTrad ships the complete GPLv2 text, uses GPL-2.0-or-later consistently for its code, and preserves separate third-party asset notices. See [WordPress's licensing guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#1-plugins-must-be-compatible-with-the-gnu-general-public-license).

## Flag assets since 1.2.0

All 38 inherited PNG flags have been removed from the distributed plugin. Original qTranslate credits many flags to Luc Balemans through Flags of the World, but this does not establish the individual origin/terms of the supplied files. Their original hashes and replacement mappings are retained in `../audit/results/replaced-flags.json`; no claim is made that these old images were covered by the PHP code's GPL grant.

The distribution now contains 41 unchanged SVGs from [lipis/flag-icons 7.3.2](https://github.com/lipis/flag-icons/tree/fe15c16e7463d0c66d6c5730e9d0e832438d98e1), commit `fe15c16e7463d0c66d6c5730e9d0e832438d98e1`. They come from `flags/4x3/` and carry that project's MIT license, copyright 2013 Panayiotis Lipiridis. Its complete, unchanged notice is included as `flags/LICENSE.flag-icons`. GPL-2.0-or-later applies to qTrad's code; the bundled flag artwork retains its MIT notice.

`flags/manifest.json` records each image's exact upstream URL, source path, SHA-256, license and absence of modifications, plus the source archive/license hashes. `python3 tests/assets.py` verifies the inventory, hashes, notices and absence of active/external SVG content without network access. The package builder runs this gate before producing a ZIP.

Saved predecessor country PNG names resolve to their SVG equivalents without rewriting shared options. `arle.png` maps to the Arab League artwork and `galego.png` to Galicia. Optional regional aliases `catalonia.png`, `basque.png` and `wales.png` resolve to licensed regional artwork. `ca.png` remains the Canadian flag; it is never inferred to mean Catalan. Languages whose saved flag is empty keep visible text. Flags are optional, configurable decorations; full language names remain available to assistive technology.

No third-party SEO plugin, checker or browser library is bundled with qTrad. Matrix fixtures download SEO packages solely into disposable sites; development dependencies stay outside the release ZIP.
