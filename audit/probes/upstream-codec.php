<?php
/** Run against the downloaded, pinned upstream source directory. */
define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG', false);
function add_action() {}
function add_filter() {}
$q_config = array('enabled_languages' => array('en', 'de'), 'default_language' => 'en', 'language' => 'en', 'force_markers' => false);
require $argv[1] . '/qtranslate_core.php';
require $argv[1] . '/qtranslate_utils.php';
$prefix = function_exists('qtrans_split') ? 'qtrans_' : 'qtranxf_';
$split = $prefix . 'split';
$available = $prefix . 'getAvailableLanguages';
echo json_encode(array(
    'plugin' => basename($argv[1]),
    'Next bracket string' => $split('[:en]Hello[:de]Hallo[:]'),
    'Next swirly string' => $split('{:en}Hello{:de}Hallo{:}'),
    'comment string' => $split('<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->'),
    'plain availability' => $available('Plain'),
    'legacy join string input' => $prefix === 'qtrans_' ? qtrans_join('<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->') : null,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
