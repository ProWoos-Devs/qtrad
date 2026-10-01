<?php
/**
 * Run only against a disposable WordPress installation:
 * php audit/probes/wordpress.php /path/to/disposable/wordpress
 * This creates fixtures and replaces options in that installation.
 */
if (empty($argv[1]) || !is_file($argv[1] . '/wp-load.php')) {
    fwrite(STDERR, "Supply a disposable WordPress installation path.\n");
    exit(2);
}
define('WP_CLI', true);
// Load the audited source manually; avoid duplicate hooks from active copies.
define('WP_PLUGIN_DIR', __DIR__ . '/unloaded-plugins');
$_SERVER['HTTP_HOST'] = '127.0.0.1:8931';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once dirname(__DIR__, 2) . '/qtranslate-next/qtranslate-unified.php';
wp_set_current_user(1);
qtu_register_field_hooks();
$results = array();
function check_case($name, $expected, $actual) {
    global $results;
    $results[] = array('case' => $name, 'pass' => $expected === $actual, 'expected' => $expected, 'actual' => $actual);
}
function settings($overrides = array()) {
    global $q_config, $current_screen;
    $current_screen = null;
    $_POST = $_GET = $_COOKIE = array();
    $defaults = array(
        'home' => 'http://127.0.0.1:8931',
        'qtranslate_enabled_languages' => array('en', 'de'),
        'qtranslate_default_language' => 'en',
        'qtranslate_url_mode' => 2,
        'qtranslate_hide_default_language' => '1',
        'qtranslate_detect_browser_language' => '1',
        'qtranslate_show_displayed_language_prefix' => '0',
        'qtranslate_term_name' => array(),
        'qtranslate_unified_settings' => array('write_format' => 'keep', 'extra_fields' => 'audit_field'),
    );
    foreach (array_merge($defaults, $overrides) as $key => $value) update_option($key, $value);
    qtu_reset_config();
}
function fixture($fields = array()) {
    $_POST = array();
    return wp_insert_post(wp_slash(array_merge(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Audit fixture'), $fields)));
}
function classic_payload($title, $content, $excerpt = array('en' => '', 'de' => '')) {
    $_POST = wp_slash(array(
        'qtu_field_nonce' => wp_create_nonce('qtu_field'), 'qtu_js' => '1', 'qtu_edit_lang' => 'de',
        'qtu_field' => array('title' => $title, 'content' => $content, 'excerpt' => $excerpt),
    ));
}
settings();
foreach (array('bracket', 'comment', 'swirly') as $format) {
    $value = qtu_join(array('en' => 'Hello', 'de' => 'Hallo'), $format);
    check_case('codec round trip ' . $format, array('en' => 'Hello', 'de' => 'Hallo'), qtu_split($value));
}
check_case('plain language merge preserves existing translation', '[:en]Hello[:de]Neu[:]', qtu_merge_field('Neu', '[:en]Hello[:de]Hallo[:]', 'de'));
$id = fixture(array('post_title' => '[:en]Hello[:de]Hallo[:fr]Bonjour[:]'));
classic_payload(array('en' => 'Hello', 'de' => 'Neu'), array('en' => 'Body', 'de' => 'Inhalt'));
wp_update_post(wp_slash(array('ID' => $id, 'post_title' => 'Neu')));
check_case('classic save retains disabled French translation', true, strpos(get_post($id)->post_title, '[:fr]Bonjour') !== false);

settings();
$id = fixture(array('post_title' => '[:en]Hello[:de]Hallo[:]'));
$json = '<!-- wp:paragraph {"metadata":{"name":"a\\\\b"}} --><p>C:\\temp</p><!-- /wp:paragraph -->';
classic_payload(array('en' => "O'Reilly", 'de' => 'Deutsch'), array('en' => $json, 'de' => '<p>Deutsch</p>'));
wp_update_post(wp_slash(array('ID' => $id, 'post_content' => '<p>Deutsch</p>')));
check_case('classic save preserves JSON escaping and backslashes', $json, qtu_split(get_post($id)->post_content)['en']);

settings();
$id = fixture(array('post_content' => '[:en]<p>C:\\temp</p>[:de]Alt[:]'));
$_POST = array('qtu_edit_lang' => 'de');
wp_update_post(wp_slash(array('ID' => $id, 'post_content' => 'Neu')));
check_case('plain merge preserves backslash in untouched English', '<p>C:\\temp</p>', qtu_split(get_post($id)->post_content)['en']);

settings();
classic_payload(array('en' => 'Main title', 'de' => 'Haupttitel'), array('en' => 'Main body', 'de' => 'Haupttext'));
$secondary = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_title' => 'Secondary page', 'post_status' => 'draft')));
check_case('secondary insert does not inherit main form payload', 'Secondary page', get_post($secondary)->post_title);

settings();
qtu_set_language('de');
$id = fixture();
add_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
check_case('metadata display uses selected German', 'Hallo', get_post_meta($id, 'audit_field', true));
update_post_meta($id, 'audit_field', 'Neu');
global $wpdb;
$raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='audit_field' LIMIT 1", $id));
check_case('metadata update retains both languages', array('en' => 'Hello', 'de' => 'Neu'), qtu_split($raw));

settings();
global $current_screen;
$current_screen = WP_Screen::get('post');
$id = fixture();
add_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
$changed = update_post_meta($id, 'audit_field', 'New', 'does-not-match');
$raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='audit_field' LIMIT 1", $id));
check_case('metadata respects previous value condition', '[:en]Hello[:de]Hallo[:]', $raw);
check_case('metadata reports rejected conditional update', false, $changed);
remove_filter('update_post_metadata', 'qtu_filter_update_meta', 10);
update_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
add_filter('update_post_metadata', 'qtu_filter_update_meta', 10, 5);
add_post_meta($id, 'audit_field', '[:en]Second[:de]Zweite[:]');
$current_screen = null;
check_case('metadata non-single read returns every row', array('Hello', 'Second'), get_post_meta($id, 'audit_field', false));
check_case('metadata existence is false for absent configured field', false, metadata_exists('post', $id + 100000, 'audit_field'));

settings(array('qtranslate_term_name' => array('News' => array('en' => 'News', 'de' => 'Nachrichten'))));
qtu_set_language('de');
check_case('legacy term library translates string', 'Nachrichten', qtrans_useTermLib('News'));
check_case('legacy term library translates array', array('Nachrichten'), qtrans_useTermLib(array('News')));

settings(array('qtranslate_term_name' => array('Old' => array('en' => 'Old', 'de' => 'Alt', 'fr' => 'Ancien'))));
$current_screen = WP_Screen::get('term');
$term = wp_insert_term('Old', 'category', array('slug' => uniqid('audit-old-')));
$_POST = array('qtu_term' => array('en' => 'New', 'de' => 'Neu'));
wp_update_term($term['term_id'], 'category', array('name' => 'New'));
$library = get_option('qtranslate_term_name');
check_case('term rename removes obsolete library key', false, isset($library['Old']));
check_case('term rename retains disabled French', 'Ancien', $library['New']['fr'] ?? null);

settings(array('qtranslate_term_name' => array('News' => array('en' => 'News', 'de' => 'Nachrichten'))));
$current_screen = WP_Screen::get('edit-tags');
$term = wp_insert_term('News', 'category', array('slug' => uniqid('audit-news-')));
add_filter('get_term', 'qtu_filter_get_term');
qtu_set_language('de');
$_POST = array('qtu_term' => array('en' => 'News', 'de' => 'Neu'));
wp_update_term($term['term_id'], 'category', array('name' => 'News'));
check_case('AJAX-style term save keeps default-language database name', 'News', $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term['term_id'])));
remove_filter('get_term', 'qtu_filter_get_term');

settings(array('qtranslate_enabled_languages' => array('en', 'xx'), 'qtranslate_default_language' => 'xx'));
qtu_save_settings(array('enabled' => array('en', 'xx'), 'default' => 'xx'));
check_case('settings retain custom legacy language', array('en', 'xx'), qtu_enabled_languages());

settings();
ob_start();
$ret = qtrans_generateLanguageSelectCode('text');
$output = ob_get_clean();
check_case('legacy chooser echoes output', true, strlen($output) > 0);
check_case('URL conversion leaves external host unchanged', 'https://other.example/path?x=1', qtu_convert_url('https://other.example/path?x=1', 'de'));
check_case('URL conversion leaves mailto unchanged', 'mailto:user@example.test', qtu_convert_url('mailto:user@example.test', 'de'));
check_case('URL conversion leaves anchor unchanged', '#section', qtu_convert_url('#section', 'de'));
check_case('URL conversion preserves signed/repeated query bytes', 'http://127.0.0.1:8931/de/path?a=1&a=2&v=a%20b', qtu_convert_url('http://127.0.0.1:8931/path?a=1&a=2&v=a%20b', 'de'));
settings(array('qtranslate_hide_default_language' => '0'));
check_case('qtrans URL wrapper respects visible default setting', 'http://127.0.0.1:8931/en/page/', qtrans_convertURL('http://127.0.0.1:8931/page/', 'en'));
settings(array('home' => 'https://site.example/blog'));
check_case('neutral paths work with WordPress in subdirectory', 'https://site.example/blog/wp-login.php', qtu_convert_url('https://site.example/blog/wp-login.php', 'de'));
check_case('home prefix needs path segment boundary', '/blogger/post', qtu_relative_path('/blogger/post'));

settings();
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de;q=0,en;q=0';
check_case('browser detection rejects q=0 languages', '', qtu_browser_language());
settings(array('qtranslate_enabled_languages' => array('en', 'pt', 'pb')));
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt-PT,pt-BR;q=0.1';
check_case('browser detection respects regional preference weights', 'pt', qtu_browser_language());

settings();
check_case('zero survives joining', '0', qtu_join(array('en' => '0', 'de' => '0')));
check_case('empty tagged translation honors show_empty', '', qtranxf_use('de', '[:en]Hello[:de][:]', false, true));
$missing = array();
foreach (array('qtrans_isEnabled', 'qtranxf_isEnabled', 'qtrans_isAvailableIn', 'qtranxf_isAvailableIn', 'qtranxf_convertURLs', 'qtranxf_isMultilingual') as $fn) {
    if (!function_exists($fn)) $missing[] = $fn;
}
check_case('selected legacy public APIs exist', array(), $missing);
check_case('Catalan catalog flag exists', true, file_exists(QTU_DIR . 'flags/' . qtu_catalog()['ca']['flag']));
check_case('qTranslate-X plain text availability contract', false, qtranxf_getAvailableLanguages('Plain'));
check_case('qTranslate plain text availability contract', array('en', 'de'), qtrans_getAvailableLanguages('Plain'));
check_case('legacy join accepts string input', '<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->', qtrans_join('<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->'));
settings(array('qtranslate_force_markers' => true));
check_case('legacy force_markers option is respected', '[:en]Same[:de]Same[:]', qtranxf_join_b(array('en' => 'Same', 'de' => 'Same')));

settings();
global $current_screen;
$current_screen = WP_Screen::get('post');
wp_set_current_user(1);
classic_payload(array('en' => 'Clean', 'de' => 'Sauber'), array('en' => '<p>Clean</p>', 'de' => '<p>Sauber</p>'));
$author = get_user_by('login', 'qtn_author');
if (!$author) {
    $author_id = wp_insert_user(array('user_login' => 'qtn_author', 'user_pass' => 'qtn-local-author-only', 'role' => 'author'));
} else $author_id = $author->ID;
wp_set_current_user($author_id);
$_POST['qtu_field_nonce'] = wp_create_nonce('qtu_field');
$_POST['qtu_field']['content']['en'] = wp_slash('<script>alert(1)</script><img src="x" onerror="alert(1)"><p>Clean</p>');
$security_id = wp_insert_post(wp_slash(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Security check')));
$raw_security = get_post($security_id)->post_content;
check_case('author classic save strips script and event handlers', false, strpos($raw_security, '<script') !== false || strpos($raw_security, 'onerror') !== false);
wp_set_current_user(1);
settings();
settings(array('qtranslate_hide_untranslated' => '1'));
$empty_id = fixture(array('post_title' => '[:en]Title[:de][:]', 'post_content' => '[:en]Body[:de][:]'));
qtu_set_language('de');
add_filter('posts_where_request', 'qtu_hide_untranslated_where', 10, 2);
$query = new WP_Query(array('post__in' => array($empty_id), 'post_status' => 'draft', 'fields' => 'ids'));
check_case('hide_untranslated excludes empty tagged language', array(), $query->posts);
remove_filter('posts_where_request', 'qtu_hide_untranslated_where', 10);
settings();

if (in_array('--prepare-browser', $argv, true)) {
    update_option('template', 'twentytwentyfive');
    update_option('stylesheet', 'twentytwentyfive');
    update_option('permalink_structure', '/%postname%/');
    update_option('active_plugins', array('qtranslate-next/qtranslate-unified.php'));
    update_user_meta(1, 'qtu_edit_language', 'en');
    $browser_id = fixture(array('post_name' => 'qtn-audit-browser', 'post_status' => 'publish',
        'post_title' => '[:en]Hello[:de]Hallo[:fr]Bonjour[:]',
        'post_content' => '[:en]<!-- wp:paragraph --><p>English body</p><!-- /wp:paragraph -->[:de]<!-- wp:paragraph --><p>German body</p><!-- /wp:paragraph -->[:fr]<!-- wp:paragraph --><p>French body</p><!-- /wp:paragraph -->[:]'));
    flush_rewrite_rules(false);
}

echo json_encode(array('environment' => array('wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION), 'browser_post_id' => $browser_id ?? null, 'browser_post_slug' => isset($browser_id) ? get_post($browser_id)->post_name : null, 'passed' => count(array_filter($results, fn($r) => $r['pass'])), 'total' => count($results), 'cases' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
