<?php
// Bootstrap-only guard regression. No database/WordPress required.
// php tests/load-order.php original|x|renamed|network|loaded /path/to/upstream/qtranslate_core.php
$kind = $argv[1];
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('WP_PLUGIN_DIR', sys_get_temp_dir() . '/qtrad-guard-plugins');
class WP_Widget {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function add_action() {}
function register_activation_hook() {}
function get_option($key,$default=array()) {
    global $kind;
    if ($key !== 'active_plugins') return $default;
    return $kind === 'original' ? array('qtranslate/qtranslate.php') : ($kind === 'x' ? array('qtranslate-x/qtranslate.php') : ($kind === 'renamed' ? array('renamed/main.php') : array()));
}
function get_transient() { return false; }
function set_transient() { return true; }
function get_file_data($file, $headers) {
    $data = (string) file_get_contents($file, false, null, 0, 8192);
    $result = array();
    foreach ($headers as $key => $name) $result[$key] = preg_match('/^[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $data, $m) ? trim($m[1]) : '';
    return $result;
}
function is_multisite() { return $GLOBALS['kind'] === 'network'; }
function get_site_option() { return array('qtranslate-x/qtranslate.php'=>1); }
if ($kind === 'renamed') {
    @mkdir(WP_PLUGIN_DIR . '/renamed',0777,true);
    file_put_contents(WP_PLUGIN_DIR . '/renamed/main.php', "<?php\n/**\n * Plugin Name: qTranslate-X\n */\n");
}
if ($kind === 'loaded') define('QTX_VERSION','3.4.8');
require dirname(__DIR__) . '/qtrad/qtrad.php';
if (function_exists('qtranxf_use') || function_exists('qtrans_use')) { fwrite(STDERR,"qTrad exported predecessor names despite a conflict.\n"); exit(1); }
require $argv[2];
echo "Guard passed: $kind.\n";
