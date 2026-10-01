<?php
/** Bootstrap-only reproduction. No WordPress/database dependency.
 * php audit/probes/load-order.php /path/to/upstream/qtranslate_core.php
 * Expected current result: PHP fatal redeclaration (exit 255).
 */
define('ABSPATH', __DIR__ . '/');
class WP_Widget {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function add_action() {}
function register_activation_hook() {}
require dirname(__DIR__, 2) . '/qtranslate-next/qtranslate-unified.php';
require $argv[1];
echo "Both plugins loaded without a redeclaration.\n";
