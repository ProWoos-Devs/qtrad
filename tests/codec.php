<?php
// No WordPress/database needed. Both codecs consume the same fixture file.
define('ABSPATH', __DIR__);
function qtrad_setting($key, $default = null) { return $default; }
require dirname(__DIR__) . '/qtrad/includes/tokens.php';
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/codec.json'), true);
$results = array();
foreach ($cases as $case) {
    $parts = qtrad_split($case['text'], $case['enabled'], false);
    $joined = qtrad_join($parts, $case['format'], $case['enabled'], $case['force'] ?? false);
    $pass = $parts === $case['parts'] && (!empty($case['skipRoundTrip']) || $joined === ($case['joined'] ?? $case['text']));
    $results[] = array('name' => $case['name'], 'pass' => $pass, 'parts' => $parts, 'joined' => $joined);
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(count(array_filter($results, fn($case) => !$case['pass'])) ? 1 : 0);
