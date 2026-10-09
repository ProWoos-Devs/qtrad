# qTrad

[![Version](https://img.shields.io/badge/Version-1.12.2-red.svg)](https://github.com/ProWoos-Devs/qtrad/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8+-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**qTranslate compatible multilingual sites.** qTrad is an independent continuation of qTranslate and qTranslate-X. Every translation of a post lives in that same post, so there are no duplicate posts to keep in sync, and switching languages in the editor is one click. Sites built on qTranslate, qTranslate-X or qTranslate-XT keep their translations and settings as they are, and gain modern editing, accessible language controls and multilingual SEO.

> **Current Version: 1.12.2** | **Released: October 9, 2026** | Prepared for wordpress.org, slug `qtrad`

## What it reads

qTrad reads all three marker formats and can keep whichever one a field already uses.

```text
<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->    qTranslate
[:en]Hello[:de]Hallo[:]                          qTranslate-X
{:en}Hello{:de}Hallo{:}                          swirly
```

The `qtranslate_*` settings, the `qtrans_*` and `qtranxf_*` functions themes call, the old widgets and language menu items, qTranslate-XT's translated slugs, ACF field types and WooCommerce order language all keep working. Activation never rewrites posts, and uninstalling leaves translations and settings in place.

## Features

- **Editing.** The classic and block editors show one language at a time, and saving keeps every other translation, including those of disabled languages. Raw mode shows the complete field.
- **Accessible language controls.** Full language names, keyboard-operable buttons, announced changes, visible focus and right-to-left editing.
- **URLs.** Query, path, subdomain or one domain per language, as in qTranslate-X. Translated slugs per post and term, and translated bases such as `kategorie` instead of `category`.
- **SEO.** Canonical alternate links, hreflang and multilingual sitemap entries that list only languages a page is translated into, with adapters for WordPress core, Yoast SEO and Rank Math.
- **Switchers.** A Language switcher block, the `[qtrad_switcher]` shortcode and a widget, as text, flags, short codes or a labelled dropdown that falls back to links without JavaScript.
- **Custom fields, user profiles and options** in the visitor's language, with edits merged into the language being edited.
- **Advanced Custom Fields.** One input per language, qTranslate-XT's `qtranslate_*` field types, per-language validation.
- **WooCommerce.** Product, attribute, gateway, shipping and email texts in the visitor's language, also in the cart and checkout blocks. Orders keep the customer's language, and customer emails are sent in it.
- **Date and time formats** per language.
- **Translation overview.** A "Missing in …" filter on the post lists and a Translations box on the dashboard.

The full description is in [`qtrad/readme.txt`](qtrad/readme.txt).

## Installation

1. Back up the site and try the switch on a staging copy first.
2. Deactivate qTranslate, qTranslate-X or qTranslate-XT. qTrad does not start while one of them is active.
3. Install and activate qTrad, then open Settings → Languages.
4. Check representative content, editor saves, language URLs and any site-specific integrations.

## Repository layout

| Path | Contents |
| --- | --- |
| `qtrad/` | The distributable plugin |
| `tests/` | Codec, editor, WordPress, SEO, ACF, WooCommerce, multisite and browser suites; see [`tests/README.md`](tests/README.md) |
| `tests/matrix/` | The 12-cell compatibility matrix (WordPress 5.8 to 7.1.2, PHP 7.4 to 8.5, MySQL and MariaDB) run in Docker |
| `tests/results/matrix/` | The recorded results of the last matrix run |
| `tools/package.py` | Builds `dist/qtrad-X.Y.Z.zip` |
| `docs/` | [Accessibility](docs/ACCESSIBILITY.md), [compatibility](docs/COMPATIBILITY.md), [SEO](docs/SEO.md) and [provenance](docs/PROVENANCE.md) |
| `.wordpress-org/` | Directory icons, banners and screenshots for SVN `/assets` |

## Development

CI runs the full matrix on every push. One cell runs locally with Docker.

```sh
python3 tests/matrix/run.py --cell wp71-php85-maria --browser
git checkout -- tests/results    # the local run overwrites the recorded results
```

`tools/package.py` builds only when all 12 cells in `tests/results/matrix/` passed against the current source, so after a change: push, wait for CI, download its results into `tests/results/matrix/` (`gh run download <id>`), commit them, then build.

```sh
python3 tools/package.py              # dist/qtrad-X.Y.Z.zip and its .sha256
python3 tools/package.py --verify-only
```

Prefixes are `qtrad_`, `QTRAD_` and `Qtrad_`. The qTranslate public names in `qtrad/includes/compat.php` and the `qtranslate_*` options are kept on purpose, for compatibility.

## Privacy

qTrad sends no data anywhere and loads nothing from other sites. It stores the visitor's chosen language in the cookie `qtrans_front_language` and the administrator's editing language in `qtrans_admin_language`.

## Credits and license

qTrad continues the work of qTranslate by Qian Qin and qTranslate-X by the qTranslate Team. It is an independent project and is not endorsed by the original authors. Licensed GPL-2.0-or-later; see [`docs/PROVENANCE.md`](docs/PROVENANCE.md).

The bundled flags are unchanged SVGs from [flag-icons](https://github.com/lipis/flag-icons) 7.3.2 by Panayiotis Lipiridis, licensed MIT.
