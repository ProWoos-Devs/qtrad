<?php
/**
 * Run only against a disposable WordPress installation:
 * php tests/wordpress.php /path/to/disposable/wordpress
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
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once dirname(__DIR__) . '/qtrad/qtrad.php';
wp_set_current_user(1);
qtrad_register_field_hooks();
$results = array();
function check_case($name, $expected, $actual) {
    global $results;
    $results[] = array('case' => $name, 'pass' => $expected === $actual, 'expected' => $expected, 'actual' => $actual);
}
$entries = array();
foreach (glob(QTRAD_DIR . '*.php') as $entry) {
    $header = get_file_data($entry, array('name'=>'Plugin Name'));
    if ($header['name'] !== '') $entries[basename($entry)] = $header['name'];
}
check_case('Canonical activation file is qtrad.php', 'qtrad.php', basename(QTRAD_FILE));
check_case('WordPress discovers only one activatable entry', array('qtrad.php'=>'qTrad: qTranslate compatible multilingual sites'), $entries);
function settings($overrides = array()) {
    global $q_config, $current_screen;
    $current_screen = null;
    $_POST = $_GET = $_COOKIE = array();
    $defaults = array(
        'home' => 'http://127.0.0.1:8931',
        'permalink_structure' => '/%postname%/',
        'qtranslate_enabled_languages' => array('en', 'de'),
        'qtranslate_default_language' => 'en',
        'qtranslate_url_mode' => 2,
        'qtranslate_hide_default_language' => '1',
        'qtranslate_detect_browser_language' => '1',
        'qtranslate_show_displayed_language_prefix' => '0',
        'qtranslate_term_name' => array(),
        'qtranslate_language_names' => array(),
        'qtranslate_locales' => array(),
        'qtranslate_flags' => array(),
        'qtranslate_hide_untranslated' => '0',
        'qtranslate_force_markers' => false,
        'qtranslate_show_alternative_content_message' => false,
        'qtranslate_show_alternative_content' => false,
        'qtranslate_editor_mode' => 0,
        'qtrad_settings' => array('write_format' => 'keep', 'extra_fields' => 'audit_field'),
    );
    foreach (array_merge($defaults, $overrides) as $key => $value) update_option($key, $value);
    qtrad_reset_config();
}
function fixture($fields = array()) {
    $_POST = array();
    return wp_insert_post(wp_slash(array_merge(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Audit fixture'), $fields)));
}
function classic_payload($title, $content, $excerpt = array('en' => '', 'de' => '')) {
    global $id;
    $_POST = wp_slash(array(
        'qtrad_field_post_id' => $id, 'qtrad_field_nonce' => wp_create_nonce('qtrad_field_' . $id), 'qtrad_js' => '1', 'qtrad_edit_lang' => 'de',
        'qtrad_field' => array('title' => $title, 'content' => $content, 'excerpt' => $excerpt),
    ));
}
settings();
foreach (array('bracket', 'comment', 'swirly') as $format) {
    $value = qtrad_join(array('en' => 'Hello', 'de' => 'Hallo'), $format);
    check_case('codec round trip ' . $format, array('en' => 'Hello', 'de' => 'Hallo'), qtrad_split($value));
}
check_case('plain language merge preserves existing translation', '[:en]Hello[:de]Neu[:]', qtrad_merge_field('Neu', '[:en]Hello[:de]Hallo[:]', 'de'));
$id = fixture(array('post_title' => '[:en]Hello[:de]Hallo[:es]Hola[:]'));
classic_payload(array('en' => 'Hello', 'de' => 'Neu'), array('en' => 'Body', 'de' => 'Inhalt'));
wp_update_post(wp_slash(array('ID' => $id, 'post_title' => 'Neu')));
check_case('classic save retains disabled Spanish translation', true, strpos(get_post($id)->post_title, '[:es]Hola') !== false);

settings();
$id = fixture(array('post_title' => '[:en]Hello[:de]Hallo[:]'));
$json = '<!-- wp:paragraph {"metadata":{"name":"a\\\\b"}} --><p>C:\\temp</p><!-- /wp:paragraph -->';
classic_payload(array('en' => "O'Reilly", 'de' => 'Deutsch'), array('en' => $json, 'de' => '<p>Deutsch</p>'));
wp_update_post(wp_slash(array('ID' => $id, 'post_content' => '<p>Deutsch</p>')));
check_case('classic save preserves JSON escaping and backslashes', $json, qtrad_split(get_post($id)->post_content)['en']);

settings();
$id = fixture(array('post_content' => '[:en]<p>C:\\temp</p>[:de]Alt[:]'));
$_POST = array('qtrad_edit_lang' => 'de', 'post_ID' => $id, 'qtrad_field_post_id' => $id, 'qtrad_field_nonce' => wp_create_nonce('qtrad_field_' . $id));
wp_update_post(wp_slash(array('ID' => $id, 'post_content' => 'Neu')));
check_case('plain merge preserves backslash in untouched English', '<p>C:\\temp</p>', qtrad_split(get_post($id)->post_content)['en']);

settings();
$id = fixture(array('post_content' => '[:en]English[:de]Alt[:]'));
$_POST = array('qtrad_edit_lang' => 'de', 'post_ID' => $id);
wp_update_post(wp_slash(array('ID' => $id, 'post_content' => 'Neu')));
check_case('posted edit language without nonce is ignored', array('en' => 'Neu', 'de' => 'Alt'), array_intersect_key(qtrad_split(get_post($id)->post_content), array('en' => 1, 'de' => 1)));

settings();
classic_payload(array('en' => 'Main title', 'de' => 'Haupttitel'), array('en' => 'Main body', 'de' => 'Haupttext'));
$secondary = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_title' => 'Secondary page', 'post_status' => 'draft')));
check_case('secondary insert does not inherit main form payload', 'Secondary page', get_post($secondary)->post_title);

settings();
qtrad_set_language('de');
$id = fixture();
add_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
check_case('metadata display uses selected German', 'Hallo', get_post_meta($id, 'audit_field', true));
update_post_meta($id, 'audit_field', 'Neu');
global $wpdb;
$raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='audit_field' LIMIT 1", $id));
check_case('metadata update retains both languages', array('en' => 'Hello', 'de' => 'Neu'), qtrad_split($raw));

settings();
global $current_screen;
$current_screen = WP_Screen::get('post');
$id = fixture();
add_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
$changed = update_post_meta($id, 'audit_field', 'New', 'does-not-match');
$raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='audit_field' LIMIT 1", $id));
check_case('metadata respects previous value condition', '[:en]Hello[:de]Hallo[:]', $raw);
check_case('metadata reports rejected conditional update', false, $changed);
remove_filter('update_post_metadata', 'qtrad_filter_update_meta', 10);
update_post_meta($id, 'audit_field', '[:en]Hello[:de]Hallo[:]');
add_filter('update_post_metadata', 'qtrad_filter_update_meta', 10, 5);
add_post_meta($id, 'audit_field', '[:en]Second[:de]Zweite[:]');
$current_screen = null;
check_case('metadata non-single read returns every row', array('Hello', 'Second'), get_post_meta($id, 'audit_field', false));
check_case('metadata existence is false for absent configured field', false, metadata_exists('post', $id + 100000, 'audit_field'));

settings(array('qtranslate_term_name' => array('News' => array('en' => 'News', 'de' => 'Nachrichten'))));
qtrad_set_language('de');
check_case('legacy term library translates string', 'Nachrichten', qtrans_useTermLib('News'));
check_case('legacy term library translates array', array('Nachrichten'), qtrans_useTermLib(array('News')));

settings(array('qtranslate_term_name' => array('Old' => array('en' => 'Old', 'de' => 'Alt', 'es' => 'Antiguo'))));
$current_screen = WP_Screen::get('term');
$term = wp_insert_term('Old', 'category', array('slug' => uniqid('audit-old-')));
$_POST = array('qtrad_term' => array('en' => 'New', 'de' => 'Neu'), 'qtrad_term_nonce' => wp_create_nonce('qtrad_term'), 'qtrad_term_id' => $term['term_id']);
wp_update_term($term['term_id'], 'category', array('name' => 'New'));
$library = get_option('qtranslate_term_name');
check_case('term rename removes obsolete library key', false, isset($library['Old']));
check_case('term rename retains disabled Spanish', 'Antiguo', $library['New']['es'] ?? null);

settings(array('qtranslate_term_name' => array('News' => array('en' => 'News', 'de' => 'Nachrichten'))));
$current_screen = WP_Screen::get('edit-tags');
$term = wp_insert_term('News', 'category', array('slug' => uniqid('audit-news-')));
add_filter('get_term', 'qtrad_filter_get_term');
qtrad_set_language('de');
$_POST = array('qtrad_term' => array('en' => 'News', 'de' => 'Neu'), 'qtrad_term_nonce' => wp_create_nonce('qtrad_term'), 'qtrad_term_id' => $term['term_id']);
wp_update_term($term['term_id'], 'category', array('name' => 'News'));
check_case('AJAX-style term save keeps default-language database name', 'News', $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term['term_id'])));
remove_filter('get_term', 'qtrad_filter_get_term');

settings(array('qtranslate_enabled_languages' => array('en', 'xx'), 'qtranslate_default_language' => 'xx'));
qtrad_save_settings(array('enabled' => array('en', 'xx'), 'default' => 'xx'));
check_case('settings retain custom legacy language, default first', array('xx', 'en'), qtrad_enabled_languages());
settings(array('qtranslate_enabled_languages' => array('es', 'en', 'de')));
check_case('languages show default first, then A to Z by native name', array('en', 'de', 'es'), qtrad_enabled_languages());

settings(array('qtranslate_editor_mode' => 2, 'qtranslate_enabled_languages' => array('en', 'de', 'lt')));
check_case('qTranslate-XT built-in Lithuanian has its locale', 'lt_LT', qtrad_config('locale')['lt']);
qtrad_save_settings(array('qtrad_settings' => 1, 'enabled' => array('en', 'de', 'lt'), 'default' => 'en', 'editor_mode' => 'lsb', 'languages' => array('en' => array('name' => 'English', 'locale' => 'en_US'), 'lt' => array('name' => 'Lietuvių', 'locale' => 'lt_LT'))));
check_case('unchanged qTranslate-XT editor mode 2 is kept', 2, (int) get_option('qtranslate_editor_mode'));
check_case('unchanged language names and locales are not written', array(array(), array()), array(get_option('qtranslate_language_names', array()), get_option('qtranslate_locales', array())));
qtrad_save_settings(array('qtrad_settings' => 1, 'enabled' => array('en', 'de', 'lt'), 'default' => 'en', 'editor_mode' => 'raw', 'languages' => array('lt' => array('name' => 'Lithuanian', 'locale' => 'lt_LT'))));
check_case('changed editor mode is written', 1, (int) get_option('qtranslate_editor_mode'));
check_case('only the changed language name is written', array('lt' => 'Lithuanian'), get_option('qtranslate_language_names'));

settings();
ob_start();
$ret = qtrans_generateLanguageSelectCode('text');
$output = ob_get_clean();
check_case('legacy chooser echoes output', true, strlen($output) > 0);
check_case('URL conversion leaves external host unchanged', 'https://other.example/path?x=1', qtrad_convert_url('https://other.example/path?x=1', 'de'));
check_case('URL conversion leaves mailto unchanged', 'mailto:user@example.test', qtrad_convert_url('mailto:user@example.test', 'de'));
foreach (array('/wp-comments-post.php', '/wp-cron.php', '/wp-signup.php', '/wp-activate.php', '/oauth/authorize', '/media/clip.mp4', '/files/archive.7z') as $qtrad_path) {
    check_case('neutral path ' . $qtrad_path, true, qtrad_is_neutral_path($qtrad_path));
}
check_case('ordinary page is not neutral', false, qtrad_is_neutral_path('/wp-guide/'));
$qtrad_login_filter = function () { return home_url('/secret-entry/'); };
add_filter('login_url', $qtrad_login_filter);
qtrad_login_admin_paths(true);
check_case('moved login page is neutral', true, in_array('/secret-entry', qtrad_login_admin_paths(), true));
remove_filter('login_url', $qtrad_login_filter);
qtrad_login_admin_paths(true);
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
check_case('XMLHttpRequest is recognized as a script request', true, qtrad_is_api_request());
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
check_case('ordinary request is not a script request', false, qtrad_is_api_request());
qtrad_set_language('de');
$qtrad_home = get_option('home');
$qtrad_localized_home = qtrad_filter_home_url($qtrad_home, '');
qtrad_parse_request_start(true);
$qtrad_parsing_home = qtrad_filter_home_url($qtrad_home, '');
qtrad_parse_request_end();
check_case('home_url is localized outside request parsing', true, $qtrad_localized_home !== $qtrad_home && strpos($qtrad_localized_home, '/de') !== false);
check_case('home_url stays unlocalized while core parses the request (subdirectory installs)', $qtrad_home, $qtrad_parsing_home);
check_case('home_url is localized again after parsing', $qtrad_localized_home, qtrad_filter_home_url($qtrad_home, ''));

settings(array('qtranslate_enabled_languages' => array('en', 'de', 'spa'), 'qtranslate_language_names' => array('spa' => 'Español'), 'qtranslate_locales' => array('spa' => 'es_ES')));
check_case('three-letter code is an enabled language', true, qtrad_is_enabled('spa'));
check_case('three-letter path prefix is stripped', '/sample/', qtrad_strip_path_language('/spa/sample/'));
check_case('three-letter path prefix leaves look-alike segments', '/space/', qtrad_strip_path_language('/space/'));
check_case('URL conversion adds three-letter prefix', 'http://127.0.0.1:8931/spa/sample/', qtrad_convert_url('http://127.0.0.1:8931/de/sample/', 'spa'));
$id = fixture(array('post_title' => '[:en]Hi[:spa]Hola[:]'));
qtrad_set_language('spa');
check_case('three-letter title is displayed', 'Hola', qtrad_use_current(get_post($id)->post_title));
settings();
check_case('URL conversion leaves anchor unchanged', '#section', qtrad_convert_url('#section', 'de'));
check_case('URL conversion preserves signed/repeated query bytes', 'http://127.0.0.1:8931/de/path?a=1&a=2&v=a%20b', qtrad_convert_url('http://127.0.0.1:8931/path?a=1&a=2&v=a%20b', 'de'));
settings(array('qtranslate_hide_default_language' => '0'));
check_case('qtrans URL wrapper respects visible default setting', 'http://127.0.0.1:8931/en/page/', qtrans_convertURL('http://127.0.0.1:8931/page/', 'en'));
settings(array('home' => 'https://site.example/blog'));
check_case('neutral paths work with WordPress in subdirectory', 'https://site.example/blog/wp-login.php', qtrad_convert_url('https://site.example/blog/wp-login.php', 'de'));
check_case('home prefix needs path segment boundary', '/blogger/post', qtrad_relative_path('/blogger/post'));

settings();
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de;q=0,en;q=0';
check_case('browser detection rejects q=0 languages', '', qtrad_browser_language());
settings(array('qtranslate_enabled_languages' => array('en', 'pt', 'pb')));
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt-PT,pt-BR;q=0.1';
check_case('browser detection respects regional preference weights', 'pt', qtrad_browser_language());

settings();
check_case('zero survives joining', '0', qtrad_join(array('en' => '0', 'de' => '0')));
check_case('empty tagged translation honors show_empty', '', qtranxf_use('de', '[:en]Hello[:de][:]', false, true));
$missing = array();
foreach (array('qtrans_isEnabled', 'qtranxf_isEnabled', 'qtrans_isAvailableIn', 'qtranxf_isAvailableIn', 'qtranxf_convertURLs', 'qtranxf_isMultilingual') as $fn) {
    if (!function_exists($fn)) $missing[] = $fn;
}
check_case('selected legacy public APIs exist', array(), $missing);
check_case('Catalan missing flag has visible text fallback', true, strpos(qtrad_language_chooser('image'), 'alt=""') !== false);
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
$author = get_user_by('login', 'qtrad_author');
if (!$author) {
    $author_id = wp_insert_user(array('user_login' => 'qtrad_author', 'user_pass' => 'qtrad-local-author-only', 'role' => 'author'));
} else $author_id = $author->ID;
wp_set_current_user($author_id);
$saved_post = $_POST;
$security_id = fixture(array('post_author' => $author_id, 'post_title' => 'Security check'));
$_POST = $saved_post;
$_POST['qtrad_field_post_id'] = $security_id;
$_POST['qtrad_field_nonce'] = wp_create_nonce('qtrad_field_' . $security_id);
$_POST['qtrad_field']['content']['en'] = wp_slash('<script>alert(1)</script><img src="x" onerror="alert(1)"><p>Clean</p>');
wp_update_post(wp_slash(array('ID' => $security_id, 'post_content' => 'Clean')));
$raw_security = get_post($security_id)->post_content;
check_case('author classic save strips script and event handlers', false, strpos($raw_security, '<script') !== false || strpos($raw_security, 'onerror') !== false);
wp_set_current_user(1);
settings();
settings(array('qtranslate_hide_untranslated' => '1'));
$empty_id = fixture(array('post_title' => '[:en]Title[:de][:]', 'post_content' => '[:en]Body[:de][:]'));
qtrad_set_language('de');
add_filter('posts_where_request', 'qtrad_hide_untranslated_where', 10, 2);
$query = new WP_Query(array('post__in' => array($empty_id), 'post_status' => 'draft', 'fields' => 'ids'));
check_case('hide_untranslated excludes empty tagged language', array(), $query->posts);
remove_filter('posts_where_request', 'qtrad_hide_untranslated_where', 10);
settings();

// Regression coverage for additional storage, API and routing boundaries.
settings();
check_case('quicktags=false keeps bracket markers literal', array('en'=>'[:en]Hello[:de]Hallo[:]', 'de'=>'[:en]Hello[:de]Hallo[:]'), qtrans_split('[:en]Hello[:de]Hallo[:]', false));
check_case('use disabled language leaves complete source intact', '[:en]Hello[:es]Hola[:]', qtranxf_use('es', '[:en]Hello[:es]Hola[:]'));
check_case('shared footer survives in disabled language', 'Hola footer', qtrad_split('[:en]Hello[:es]Hola[:] footer')['es']);
check_case('new translated field uses original-compatible comments', '<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->', qtrad_merge_field('Hallo', 'Hello', 'de'));
$parts = array('en'=>'Intro<!--more-->End', 'de'=>'Anfang<!--more-->Ende', 'es'=>'Inicio<!--more-->Fin');
check_case('more separator survives disabled language', $parts, qtrad_split(qtrad_join_content($parts, 'comment', array('en','de')), null, false));
check_case('chooser supports legacy options array', true, strpos(qtrad_language_chooser(array('type'=>'dropdown','id'=>'example')), 'id="example"') !== false);
check_case('chooser boolean requests flags', true, strpos(qtrad_language_chooser(true), 'qtrad-style-image') !== false);
$widget = new Qtrad_Widget();
check_case('qTrad widget preserves existing sidebar identity', 'qtranslate', $widget->id_base);
check_case('qTranslate-X widget class preserves sidebar identity', $widget->id_base, (new qTranslateXWidget())->id_base);
qtrad_register_shortcodes();
$updated = $widget->update(array('title'=>'New','type'=>'text','hide-title'=>true), array('format'=>'legacy-format','widget-css'=>'legacy-css'));
check_case('widget retains unimplemented legacy options for rollback', 'legacy-format', $updated['format']);
check_case('widget updates hide-title', true, $updated['hide-title']);
$id = fixture(array('post_title'=>'[:en]Old[:de]Alt[:]'));
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Full replacement')));
check_case('programmatic full replacement remains plain', 'Full replacement', get_post($id)->post_title);
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Neu', 'qtrad_language'=>'de')));
check_case('programmatic partial update requires explicit context', array('en'=>'Full replacement','de'=>'Neu'), qtrad_split(get_post($id)->post_title));
$id = fixture(array('post_title'=>'[:en]Hello[:de]Hallo[:]'));
classic_payload(array('en'=>'Wrong','de'=>'Falsch'), array('en'=>'Wrong','de'=>'Falsch'));
$_POST['qtrad_field_post_id'] = $id + 10000;
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Full value')));
check_case('wrong form identity cannot overwrite target', 'Full value', get_post($id)->post_title);
settings();
$id = fixture();
qtrad_set_language('de');
add_post_meta($id, 'audit_field', '[:en]First[:de]Erste[:]');
add_post_meta($id, 'audit_field', '[:en]Second[:de]Zweite[:]');
update_post_meta($id, 'audit_field', wp_slash('C:\\temp'), '[:en]Second[:de]Zweite[:]');
check_case('metadata conditional update selects one raw row', array('[:en]First[:de]Erste[:]', '[:en]Second[:de]C:\\temp[:]'), qtrad_raw_post_meta($id,'audit_field'));
add_post_meta($id,'audit_field','0');
check_case('zero metadata exists', true, metadata_exists('post',$id,'audit_field'));
check_case('zero metadata is displayed', '0', get_post_meta($id,'audit_field',false)[2]);
function preceding_meta_provider($value,$post_id,$key) { return $key === 'audit_field' ? array('Provider value') : $value; }
add_filter('get_post_metadata','preceding_meta_provider',5,3);
check_case('metadata respects earlier provider', 'Provider value', get_post_meta($id,'audit_field',true));
remove_filter('get_post_metadata','preceding_meta_provider',5);
settings(array('qtranslate_na_messages'=>array('de'=>'Nur %LANG:, : und %.'), 'qtranslate_show_alternative_content_message'=>true));
qtrad_set_language('de');
$notice = qtrad_use_current('[:en]English[:]',true);
check_case('legacy missing-translation message is retained', true, strpos($notice, 'Nur <a') !== false);
check_case('legacy policy does not append alternative content', false, preg_match('~</p>.+~s', $notice) === 1);
settings(array('qtranslate_show_alternative_content_message'=>true,'qtranslate_show_alternative_content'=>true));
qtrad_set_language('de');
check_case('legacy alternative content policy is honored', true, strpos(qtrad_use_current('[:en]English[:]',true), 'English</div>') !== false);
settings();
$before = get_option('qtranslate_enabled_languages');
$error = qtrad_save_settings(array('enabled'=>array('en'), 'new_language'=>array('code'=>'bad','name'=>'Invalid','locale'=>'bad!')));
check_case('invalid custom language returns an error', true, is_wp_error($error));
check_case('invalid settings do not change enabled languages', $before, get_option('qtranslate_enabled_languages'));
$error = qtrad_save_settings(array('enabled'=>array('en'), 'domains'=>"de = https://unsafe.example/path"));
check_case('invalid mapped domain returns an error', true, is_wp_error($error));
check_case('invalid domains do not partially save', $before, get_option('qtranslate_enabled_languages'));
settings(array('qtranslate_url_mode'=>4, 'qtranslate_domains'=>array('en'=>'en.example:8931','de'=>'de.example:8931')));
check_case('domain map switches configured language origins', 'http://de.example:8931/path?x=1', qtrad_convert_url('http://en.example:8931/path?x=1','de'));
check_case('mapped default always uses its mapped origin', 'http://en.example:8931/path', qtrad_convert_url('http://de.example:8931/path','en'));
settings();
check_case('different port is an external origin', 'http://127.0.0.1:9999/path', qtrad_convert_url('http://127.0.0.1:9999/path','de'));
check_case('credential-bearing URL is preserved', 'http://user:pass@127.0.0.1:8931/path', qtrad_convert_url('http://user:pass@127.0.0.1:8931/path','de'));
check_case('tel URL is preserved', 'tel:+34000000000', qtrad_convert_url('tel:+34000000000','de'));
check_case('language query removal preserves duplicate bytes', 'x=1&x=2&v=a%20b', qtrad_strip_query_lang('x=1&lang=de&x=2&v=a%20b'));
settings(array('qtranslate_hide_untranslated'=>'1'));
$id = fixture(array('post_title'=>'[:en]Title[:de][:]', 'post_content'=>'[:en]Body[:de]  [:]'));
qtrad_set_language('de');
delete_post_meta($id,'_qtrad_available_languages');
add_filter('posts_where_request','qtrad_hide_untranslated_where',10,2);
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('unindexed legacy empty translation is excluded', array(), $query->posts);
$_POST = array();
wp_update_post(wp_slash(array('ID'=>$id,'post_content'=>'[:en]Body[:de]0[:]')));
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('indexed zero translation remains visible', array($id), $query->posts);
delete_post_meta($id,'_qtrad_available_languages');
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('legacy zero translation remains visible', array($id), $query->posts);
remove_filter('posts_where_request','qtrad_hide_untranslated_where',10);
settings();
register_post_type('qtrad_book',array('public'=>true,'show_in_rest'=>true,'rest_base'=>'books','supports'=>array('title','editor','excerpt')));
$id = fixture(array('post_type'=>'qtrad_book','post_title'=>'[:en]Hello[:de]Hallo[:es]Hola[:]', 'post_content'=>'[:en]Body[:de]Inhalt[:]'));
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id);
$request->set_param('id',$id); $request->set_param('qtrad_language','de'); $request->set_param('title','REST title');
$response = rest_do_request($request);
check_case('custom post type REST update succeeds', 200, $response->get_status());
check_case('custom post type REST preserves disabled language', '[:en]Hello[:de]REST title[:es]Hola[:]', get_post($id)->post_title);
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id.'/autosaves');
$request->set_param('id',$id); $request->set_param('qtrad_language','de'); $request->set_param('title','Autosave title');
$response = rest_do_request($request);
check_case('custom post type REST autosave succeeds', 200, $response->get_status());
$autosave = $response->get_data();
check_case('autosave retains multilingual title', true, isset($autosave['title']['raw']) && strpos($autosave['title']['raw'],'[:es]Hola') !== false);
settings(array('qtrad_settings'=>array('editor_mode'=>'raw','extra_fields'=>'audit_field','write_format'=>'keep')));
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id);
$request->set_param('id',$id); $request->set_param('title','Raw replacement');
$response = rest_do_request($request);
check_case('raw mode REST permits full plain replacement', 'Raw replacement', get_post($id)->post_title);
settings();
unregister_post_type('qtrad_book');

settings(array('qtranslate_term_name'=>array('Delete raw'=>array('en'=>'Delete raw','de'=>'Löschen'))));
$term = wp_insert_term('Delete raw', 'category', array('slug'=>uniqid('delete-raw-')));
qtrad_set_language('de');
add_filter('get_term','qtrad_filter_get_term');
wp_delete_term($term['term_id'],'category');
check_case('deleting a displayed translation cleans the raw library key', false, isset(get_option('qtranslate_term_name')['Delete raw']));
remove_filter('get_term','qtrad_filter_get_term');
settings();
qtrad_set_language('de');
check_case('root custom language attribute survives', 'data-lang="custom" lang="de-DE" dir="ltr"', qtrad_filter_language_attributes('data-lang="custom" lang="en-US"'));
check_case('root language attributes retain unrelated and XHTML attributes', 'data-test="kept" lang="de-DE" xml:lang="de-DE" dir="ltr"', qtrad_filter_language_attributes('data-test="kept" lang="en-US" xml:lang="en-US" dir="ltr"'));
// Navigation menus: qTranslate-X switcher items and custom links.
settings(array('qtranslate_enabled_languages'=>array('en','de','es')));
qtrad_set_language('de');
$GLOBALS['qtrad_original_uri'] = '/de/sample/?x=1';
function menu_item($url, $title = 'Language Menu', $id = 501) {
    $item = new WP_Post((object) array('ID'=>$id, 'post_type'=>'nav_menu_item', 'post_title'=>$title, 'menu_order'=>$id - 500));
    foreach (array('db_id'=>$id, 'menu_item_parent'=>'0', 'type'=>'custom', 'object'=>'custom', 'title'=>$title, 'url'=>$url, 'classes'=>array(''), 'attr_title'=>'', 'description'=>'') as $key => $value) $item->$key = $value;
    return $item;
}
$urls = fn($items) => array_values(array_map(fn($item) => $item->url, $items));
$menu = qtrad_filter_nav_menu_items(array(menu_item('#qtransLangSw'), menu_item('http://127.0.0.1:8931/contact/', '[:en]Contact[:de]Kontakt[:]', 502)));
check_case('language menu lists every enabled language under its item', array('#', 'http://127.0.0.1:8931/contact/', 'http://127.0.0.1:8931/en/sample/?x=1', 'http://127.0.0.1:8931/de/sample/?x=1', 'http://127.0.0.1:8931/es/sample/?x=1'), $urls($menu));
check_case('language menu children belong to the switcher item', array('501','501','501'), array_map(fn($item) => $item->menu_item_parent, array_slice($menu, 2)));
check_case('language menu children sort after the stored items', array(3,4,5), array_map(fn($item) => $item->menu_order, array_slice($menu, 2)));
check_case('language menu item keeps the default title and shows the current flag', true, strpos($menu[0]->title, 'Language:&nbsp;<img class="qtranxs-flag"') === 0 && strpos($menu[0]->title, 'alt="Deutsch"') !== false);
check_case('language menu keeps qTranslate-X classes', true, in_array('qtranxs-lang-menu-de', $menu[0]->classes, true) && in_array('qtranxs-lang-menu-item-es', $menu[4]->classes, true));
check_case('language menu is not expanded twice', 5, count(qtrad_filter_nav_menu_items($menu)));
$atts = qtrad_filter_nav_menu_link_attributes(array('href'=>$menu[3]->url), $menu[3]);
check_case('current language item is marked for assistive technology', array('de-DE','de-DE','true'), array($atts['hreflang'], $atts['lang'], $atts['aria-current']));
check_case('ordinary menu items get no language attributes', array('href'=>'/x'), qtrad_filter_nav_menu_link_attributes(array('href'=>'/x'), $menu[1]));
$menu = qtrad_filter_nav_menu_items(array(menu_item('#qtransLangSw?type=AL&flags=none')));
check_case('alternative language item links to the first other language', array('http://127.0.0.1:8931/en/sample/?x=1', 'http://127.0.0.1:8931/es/sample/?x=1'), $urls($menu));
check_case('alternative language item is named after its language', array('English','Español'), array($menu[0]->title, $menu[1]->title));
$menu = qtrad_filter_nav_menu_items(array(menu_item('#qtransLangSw?title=none&current=hidden&names=hidden&colon=hidden', '[:en]Languages[:de]Sprachen[:]')));
check_case('hidden current language is left out of the language menu', array('#', 'http://127.0.0.1:8931/en/sample/?x=1', 'http://127.0.0.1:8931/es/sample/?x=1'), $urls($menu));
check_case('flag-only language items carry the name as text alternative', true, strpos($menu[1]->title, 'alt="English"') !== false && strpos($menu[1]->title, '>English') === false);
$menu = qtrad_filter_nav_menu_items(array(menu_item('#qtransLangSw?flags=items', '[:en]Languages[:de]Sprachen[:]')));
check_case('translated switcher title is used without the top flag', 'Sprachen', $menu[0]->title);
$menu = qtrad_filter_nav_menu_objects(array(menu_item('http://127.0.0.1:8931/contact/'), menu_item('/contact/?setlang=no', 'Plain', 502), menu_item('#?lang=es', 'Spanish', 503), menu_item('https://example.org/contact/', 'External', 504), menu_item('#top', 'Anchor', 505), menu_item('http://127.0.0.1:8931/wp-content/uploads/a.pdf', 'File', 506)));
check_case('custom links follow the current language', array('http://127.0.0.1:8931/de/contact/', '/contact/', 'http://127.0.0.1:8931/es/sample/?x=1', 'https://example.org/contact/', '#top', 'http://127.0.0.1:8931/wp-content/uploads/a.pdf'), $urls($menu));
check_case('link to another language declares it without claiming the text language', array('hreflang'=>'es-ES'), qtrad_filter_nav_menu_link_attributes(array(), $menu[2]));
$menu = array(menu_item('http://127.0.0.1:8931/de/contact/'));
$menu[0]->type = 'post_type';
check_case('only custom links are converted', array('http://127.0.0.1:8931/de/contact/'), $urls(qtrad_filter_nav_menu_objects($menu)));
unset($GLOBALS['qtrad_original_uri']);
// Translated slugs stored by qTranslate-XT and the qTranslate Slug plugin.
settings(array('qtranslate_enabled_languages'=>array('en','de','es')));
function slug_structure($structure) {
    global $wp_rewrite;
    update_option('permalink_structure', $structure);
    $wp_rewrite->init();
    if (!$wp_rewrite->get_extra_permastruct('category')) $wp_rewrite->add_permastruct('category', 'category/%category%', array('with_front'=>true, 'hierarchical'=>true, 'ep_mask'=>EP_CATEGORIES));
}
function slug_term($name, $slug, $parent = 0) {
    $term = wp_insert_term($name, 'category', array('slug'=>$slug, 'parent'=>$parent));
    return is_wp_error($term) ? (int) $term->get_error_data('term_exists') : (int) $term['term_id'];
}
function slug_resolve($uri, $lang, $server = array()) {
    unset($GLOBALS['qtrad_slugs_rewritten'], $_SERVER['PATH_INFO'], $_SERVER['PHP_SELF']);
    qtrad_set_language($lang);
    $_SERVER['REQUEST_URI'] = $uri;
    foreach ($server as $key => $value) $_SERVER[$key] = $value;
    qtrad_slugs_resolve_request(true);
    return $_SERVER['REQUEST_URI'];
}
slug_structure('/%postname%/');
qtrad_register_slug_hooks();
// Other suites leave translated bases on the shared fixture site.
$bases_before = get_option('qtranslate_module_slugs', null);
delete_option('qtranslate_module_slugs');
$base = 'http://127.0.0.1:8931';
$unique = uniqid('s');
$slug_post = fixture(array('post_status'=>'publish', 'post_name'=>$unique.'-hello'));
$plain_post = fixture(array('post_status'=>'publish', 'post_name'=>$unique.'-plain'));
add_post_meta($slug_post, 'qtranslate_slug_de', $unique.'-hallo');
check_case('storing a translated slug turns slug handling on', array(true, '1'), array(qtrad_slugs_active(), get_option('qtrad_slugs_present')));
add_post_meta($slug_post, '_qts_slug_es', $unique.'-hola');
check_case('post link takes the slug of its language', $base.'/'.$unique.'-hallo/', qtrad_slugs_object_url('post', $slug_post, 'de'));
check_case('qTranslate Slug meta is read', $base.'/'.$unique.'-hola/', qtrad_slugs_object_url('post', $slug_post, 'es'));
check_case('language without a translated slug keeps the stored one', $base.'/'.$unique.'-hello/', qtrad_slugs_object_url('post', $slug_post, 'en'));
qtrad_set_language('de');
check_case('links follow the current language', $base.'/'.$unique.'-hallo/', get_permalink($slug_post));
$GLOBALS['qtrad_seo_neutral_url'] = true;
check_case('language-neutral links keep the stored slug', $base.'/'.$unique.'-hello/', get_permalink($slug_post));
unset($GLOBALS['qtrad_seo_neutral_url']);
check_case('SEO URL of another language uses its slug', true, strpos(qtrad_seo_post_url(get_post($slug_post), 'es'), '/'.$unique.'-hola/') !== false);
$parent = fixture(array('post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$unique.'-parent'));
$child = fixture(array('post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$unique.'-child', 'post_parent'=>$parent));
add_post_meta($parent, 'qtranslate_slug_de', $unique.'-eltern');
check_case('child page link uses the translated slug of its parent', $base.'/'.$unique.'-eltern/'.$unique.'-child/', qtrad_slugs_object_url('post', $child, 'de'));
add_post_meta($child, 'qtranslate_slug_de', $unique.'-kind');
check_case('page link translates every level', $base.'/'.$unique.'-eltern/'.$unique.'-kind/', qtrad_slugs_object_url('post', $child, 'de'));
$cat = slug_term($unique.' cat', $unique.'-cat');
$sub = slug_term($unique.' sub', $unique.'-sub', $cat);
add_term_meta($cat, 'qtranslate_slug_de', $unique.'-kategorie');
check_case('category link uses the translated slug of its parent', $base.'/category/'.$unique.'-kategorie/'.$unique.'-sub/', qtrad_slugs_object_url('term', $sub, 'de'));
check_case('category link without a translation is unchanged', $base.'/category/'.$unique.'-cat/'.$unique.'-sub/', qtrad_slugs_object_url('term', $sub, 'es'));
check_case('path swap leaves host and query alone', 'http://news.example/x/nachrichten/?news=1', qtrad_slugs_swap_path('http://news.example/x/news/?news=1', 'news', 'nachrichten', true));
check_case('translated post address maps to the stored one', '/'.$unique.'-hello/', slug_resolve('/'.$unique.'-hallo/', 'de'));
check_case('page number and query string survive the mapping', '/'.$unique.'-hello/2/?x=1', slug_resolve('/'.$unique.'-hallo/2/?x=1', 'de'));
check_case('slug of another language is not mapped', '/'.$unique.'-hallo/', slug_resolve('/'.$unique.'-hallo/', 'es'));
check_case('stored address is left alone', '/'.$unique.'-hello/', slug_resolve('/'.$unique.'-hello/', 'de'));
check_case('translated page path maps to the stored path', '/'.$unique.'-parent/'.$unique.'-child/', slug_resolve('/'.$unique.'-eltern/'.$unique.'-kind/', 'de'));
check_case('translated parent with a stored child maps', '/'.$unique.'-parent/'.$unique.'-child/', slug_resolve('/'.$unique.'-eltern/'.$unique.'-child/', 'de'));
check_case('translated category feed maps to the stored path', '/category/'.$unique.'-cat/'.$unique.'-sub/feed/', slug_resolve('/category/'.$unique.'-kategorie/'.$unique.'-sub/feed/', 'de'));
slug_resolve('/'.$unique.'-hallo/', 'de', array('PATH_INFO'=>'/'.$unique.'-hallo/', 'PHP_SELF'=>'/index.php/'.$unique.'-hallo/'));
check_case('PATH_INFO and PHP_SELF follow the mapped path', array('/'.$unique.'-hello/', '/index.php/'.$unique.'-hello/'), array($_SERVER['PATH_INFO'], $_SERVER['PHP_SELF']));
slug_structure('/%category%/%postname%/');
wp_set_post_terms($slug_post, array($sub), 'category');
wp_set_post_terms($plain_post, array($sub), 'category');
check_case('category in the post address is translated', $base.'/'.$unique.'-kategorie/'.$unique.'-sub/'.$unique.'-hallo/', qtrad_slugs_object_url('post', $slug_post, 'de'));
check_case('post under a translated category maps to the stored address', '/'.$unique.'-cat/'.$unique.'-sub/'.$unique.'-plain/', slug_resolve('/'.$unique.'-kategorie/'.$unique.'-sub/'.$unique.'-plain/', 'de'));
slug_structure('/%postname%-%post_id%/');
check_case('post slug stays stored where a request could not be mapped back', $base.'/'.$unique.'-hello-'.$slug_post.'/', qtrad_slugs_object_url('post', $slug_post, 'de'));
slug_structure('/%postname%.html');
check_case('post name with an extension is translated', $base.'/'.$unique.'-hallo.html', qtrad_slugs_object_url('post', $slug_post, 'de'));
check_case('post name with an extension maps to the stored address', '/'.$unique.'-hello.html', slug_resolve('/'.$unique.'-hallo.html', 'de'));
slug_structure('/%postname%/');
// Editing translated slugs and translated URL bases.
$other_post = fixture(array('post_status'=>'publish', 'post_name'=>$unique.'-other'));
check_case('new translated slug is stored in the qTranslate-XT key', array($unique.'-neu', $unique.'-neu'), array(qtrad_slugs_set('post', get_post($other_post), 'de', $unique.'-neu'), get_post_meta($other_post, 'qtranslate_slug_de', true)));
check_case('slug taken by a translation in that language gets a suffix', $unique.'-hallo-2', qtrad_slugs_set('post', get_post($other_post), 'de', $unique.'-hallo'));
check_case('slug taken by a stored slug gets a suffix', $unique.'-plain-2', qtrad_slugs_set('post', get_post($other_post), 'de', $unique.'-plain'));
check_case('same slug is free in another language', $unique.'-hallo', qtrad_slugs_set('post', get_post($other_post), 'es', $unique.'-hallo'));
check_case('unchanged slug keeps its value', $unique.'-plain-2', qtrad_slugs_set('post', get_post($other_post), 'de', $unique.'-plain-2'));
check_case('feed names are not usable as slugs', 'feed-2', qtrad_slugs_set('post', get_post($other_post), 'de', 'feed'));
qtrad_slugs_set('post', get_post($other_post), 'de', '');
check_case('empty slug removes the translation', false, metadata_exists('post', $other_post, 'qtranslate_slug_de'));
qtrad_slugs_set('post', get_post($other_post), 'es', $unique.'-other');
check_case('slug equal to the stored one is not kept', false, metadata_exists('post', $other_post, 'qtranslate_slug_es'));
qtrad_slugs_set('post', get_post($slug_post), 'es', '');
check_case('clearing hides an older qTranslate Slug value without deleting it', array('', $unique.'-hola', $base.'/'.$unique.'-hello/'), array(qtrad_slugs_get('post', $slug_post, 'es'), get_post_meta($slug_post, '_qts_slug_es', true), qtrad_slugs_object_url('post', $slug_post, 'es')));
$sibling = fixture(array('post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$unique.'-sibling', 'post_parent'=>$parent));
$elsewhere = fixture(array('post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$unique.'-elsewhere'));
check_case('page slug is unique among pages with the same parent', $unique.'-kind-2', qtrad_slugs_set('post', get_post($sibling), 'de', $unique.'-kind'));
check_case('page slug may repeat under another parent', $unique.'-kind', qtrad_slugs_set('post', get_post($elsewhere), 'de', $unique.'-kind'));
check_case('term slug is unique within its taxonomy', $unique.'-kategorie-2', qtrad_slugs_set('term', get_term($sub, 'category'), 'de', $unique.'-kategorie'));
qtrad_slugs_set('term', get_term($sub, 'category'), 'de', '');
$_POST = wp_slash(array('qtrad_slugs_post_id'=>$other_post, 'qtrad_slugs_nonce'=>wp_create_nonce('qtrad_slugs_'.$other_post), 'qtrad_slugs'=>array('de'=>' '.$unique.' Über Uns ', 'es'=>'')));
qtrad_slugs_save_meta_box($other_post);
check_case('meta box saves a sanitized slug', $unique.'-uber-uns', get_post_meta($other_post, 'qtranslate_slug_de', true));
$_POST = wp_slash(array('qtrad_slugs_post_id'=>$other_post, 'qtrad_slugs_nonce'=>'wrong', 'qtrad_slugs'=>array('de'=>'changed')));
qtrad_slugs_save_meta_box($other_post);
$_POST = wp_slash(array('qtrad_slugs_post_id'=>$slug_post, 'qtrad_slugs_nonce'=>wp_create_nonce('qtrad_slugs_'.$slug_post), 'qtrad_slugs'=>array('de'=>'changed')));
qtrad_slugs_save_meta_box($other_post);
check_case('meta box ignores a wrong nonce or another post', $unique.'-uber-uns', get_post_meta($other_post, 'qtranslate_slug_de', true));
$_POST = wp_slash(array('qtrad_term_id'=>$sub, 'qtrad_term_nonce'=>wp_create_nonce('qtrad_term'), 'qtrad_term_slugs'=>array('de'=>$unique.' Unter')));
qtrad_slugs_save_term($sub, 0, 'category');
check_case('term form saves a sanitized slug', $unique.'-unter', get_term_meta($sub, 'qtranslate_slug_de', true));
$_POST = array();
qtrad_slugs_set('term', get_term($sub, 'category'), 'de', '');
update_option('qtranslate_module_slugs', array('other_setting'=>'kept', 'taxonomy_category'=>array('es'=>'old-es')));
register_post_type('qtrad_slugbook', array('public'=>true, 'label'=>'Slug books', 'rewrite'=>array('slug'=>'slugbooks'), 'supports'=>array('title')));
qtrad_slugs_save_bases(array('slug_bases'=>array('taxonomy_category'=>array('de'=>' Rubrik / Thema ', 'es'=>''), 'post_type_qtrad_slugbook'=>array('de'=>'Bücher'), 'unknown'=>array('de'=>'x'))));
check_case('bases are saved sanitized, next to settings qTrad does not own', array('other_setting'=>'kept', 'taxonomy_category'=>array('de'=>'rubrik/thema'), 'post_type_qtrad_slugbook'=>array('de'=>'bucher')), get_option('qtranslate_module_slugs'));
check_case('category link uses the translated base', $base.'/rubrik/thema/'.$unique.'-kategorie/'.$unique.'-sub/', qtrad_slugs_object_url('term', $sub, 'de'));
check_case('language without a translated base keeps the stored one', $base.'/category/'.$unique.'-cat/'.$unique.'-sub/', qtrad_slugs_object_url('term', $sub, 'es'));
check_case('address under a translated base maps to the stored one', '/category/'.$unique.'-cat/'.$unique.'-sub/', slug_resolve('/rubrik/thema/'.$unique.'-kategorie/'.$unique.'-sub/', 'de'));
check_case('address from before the base was translated still maps', '/category/'.$unique.'-cat/'.$unique.'-sub/page/2/', slug_resolve('/category/'.$unique.'-kategorie/'.$unique.'-sub/page/2/', 'de'));
$plain_cat = slug_term($unique.' plain', $unique.'-plaincat');
check_case('term without a translated slug maps under a translated base', '/category/'.$unique.'-plaincat/', slug_resolve('/rubrik/thema/'.$unique.'-plaincat/', 'de'));
$book = fixture(array('post_type'=>'qtrad_slugbook', 'post_status'=>'publish', 'post_name'=>$unique.'-book'));
check_case('custom post type link uses the translated base', $base.'/bucher/'.$unique.'-book/', qtrad_slugs_object_url('post', $book, 'de'));
check_case('custom post type address under a translated base maps to the stored one', '/slugbooks/'.$unique.'-book/', slug_resolve('/bucher/'.$unique.'-book/', 'de'));
add_post_meta($book, 'qtranslate_slug_de', $unique.'-buch');
check_case('custom post type address with translated base and slug maps', '/slugbooks/'.$unique.'-book/', slug_resolve('/bucher/'.$unique.'-buch/', 'de'));
unregister_post_type('qtrad_slugbook');
if (null === $bases_before) delete_option('qtranslate_module_slugs'); else update_option('qtranslate_module_slugs', $bases_before);
unset($GLOBALS['qtrad_slugs_rewritten'], $_SERVER['PATH_INFO'], $_SERVER['PHP_SELF']);
$_SERVER['REQUEST_URI'] = '/';
// Custom fields, user profiles, options and extra text filters with language markers.
$value_settings = array('write_format'=>'keep', 'extra_fields'=>'audit_field');
settings(array('qtranslate_enabled_languages'=>array('en','de','es')));
$GLOBALS['qtrad_translates_values'] = true;
qtrad_set_language('de');
$value_post = fixture();
$value_list = array('label'=>'[:en]One[:de]Eins[:]', 'n'=>3, 'deep'=>array('<!--:en-->Two<!--:--><!--:de-->Zwei<!--:-->'));
add_post_meta($value_post, 'value_plain', 'Plain');
add_post_meta($value_post, 'value_text', '[:en]Sub[:de]Unter[:]');
add_post_meta($value_post, 'value_list', $value_list);
check_case('custom field with markers is read in the current language', array('Unter'), qtrad_filter_meta_all(null, $value_post, 'value_text', true, 'post'));
check_case('nested custom field values are translated', array(array('label'=>'Eins', 'n'=>3, 'deep'=>array('Zwei'))), qtrad_filter_meta_all(null, $value_post, 'value_list', false, 'post'));
check_case('custom field without markers is left to WordPress', null, qtrad_filter_meta_all(null, $value_post, 'value_plain', true, 'post'));
$value_all = qtrad_filter_meta_all(null, $value_post, '', false, 'post');
check_case('all custom fields at once come translated, as stored rows', array('Unter', serialize(array('label'=>'Eins', 'n'=>3, 'deep'=>array('Zwei'))), 'Plain'), array($value_all['value_text'][0], $value_all['value_list'][0], $value_all['value_plain'][0]));
check_case('value of an earlier metadata provider is kept', 'given', qtrad_filter_meta_all('given', $value_post, 'value_text', true, 'post'));
$GLOBALS['qtrad_translates_values'] = false;
check_case('wp-admin, REST and cron read custom fields as stored', null, qtrad_filter_meta_all(null, $value_post, 'value_text', true, 'post'));
$GLOBALS['qtrad_translates_values'] = true;
foreach (array('post', 'user') as $value_type) {
    add_filter('get_'.$value_type.'_metadata', 'qtrad_filter_meta_all', 11, 5);
    add_filter('update_'.$value_type.'_metadata', 'qtrad_filter_update_meta_all', 11, 5);
}
check_case('get_post_meta returns the current language', 'Unter', get_post_meta($value_post, 'value_text', true));
check_case('raw reads still see every language', array('[:en]Sub[:de]Unter[:]'), qtrad_raw_post_meta($value_post, 'value_text'));
update_post_meta($value_post, 'value_text', get_post_meta($value_post, 'value_text', true));
check_case('writing back a translated custom field keeps the other languages', array('[:en]Sub[:de]Unter[:]'), qtrad_raw_post_meta($value_post, 'value_text'));
$value_read = get_post_meta($value_post, 'value_list', true);
$value_read['n'] = 4;
update_post_meta($value_post, 'value_list', $value_read);
check_case('changing one part of a nested custom field keeps the stored rest', array(array('label'=>'[:en]One[:de]Eins[:]', 'n'=>4, 'deep'=>array('<!--:en-->Two<!--:--><!--:de-->Zwei<!--:-->'))), qtrad_raw_post_meta($value_post, 'value_list'));
update_post_meta($value_post, 'value_text', wp_slash('C:\\neu'));
check_case('a changed custom field is stored as given', array('C:\\neu'), qtrad_raw_post_meta($value_post, 'value_text'));
$value_bio = get_user_meta(1, 'description', true);
update_user_meta(1, 'description', '[:en]Bio[:de]Biografie[:]');
check_case('user profile field is read in the current language', 'Biografie', get_user_meta(1, 'description', true));
update_user_meta(1, 'description', get_user_meta(1, 'description', true));
$GLOBALS['qtrad_raw_meta'] = true;
check_case('writing back a translated profile field keeps the other languages', '[:en]Bio[:de]Biografie[:]', get_user_meta(1, 'description', true));
unset($GLOBALS['qtrad_raw_meta']);
update_user_meta(1, 'description', wp_slash((string) $value_bio));
foreach (array('post', 'user') as $value_type) {
    remove_filter('get_'.$value_type.'_metadata', 'qtrad_filter_meta_all', 11);
    remove_filter('update_'.$value_type.'_metadata', 'qtrad_filter_update_meta_all', 11);
}
$value_saved = array('qtranslate_filter_options'=>get_option('qtranslate_filter_options', null), 'qtranslate_text_field_filters'=>get_option('qtranslate_text_field_filters', null));
update_option('value_test_mods', array('note'=>'[:en]Thanks[:de]Danke[:]', 'n'=>1));
update_option('value_test_plain', 'plain');
settings(array('qtranslate_enabled_languages'=>array('en','de','es'), 'qtrad_settings'=>$value_settings + array('translate_options'=>'all')));
$value_names = qtrad_option_names_to_translate();
check_case('every option with markers is translated, except the ones qTrad reads itself', array(true, false, false), array(in_array('value_test_mods', $value_names, true), in_array('value_test_plain', $value_names, true), in_array('blogname', $value_names, true)));
update_option('qtranslate_filter_options', array('value_test_%', 'value_exact', 'blogname', 'bad name!'));
settings(array('qtranslate_enabled_languages'=>array('en','de','es'), 'qtrad_settings'=>$value_settings + array('translate_options'=>'list')));
$value_names = qtrad_option_names_to_translate();
sort($value_names);
check_case('listed options are matched by name and by pattern', array('value_exact', 'value_test_mods', 'value_test_plain'), $value_names);
settings(array('qtranslate_enabled_languages'=>array('en','de','es')));
check_case('options stay as stored unless translation is turned on', array(), qtrad_option_names_to_translate());
add_filter('option_value_test_mods', 'qtrad_translate_option', 5);
add_filter('pre_update_option_value_test_mods', 'qtrad_filter_update_option', 5, 3);
qtrad_set_language('de');
check_case('translated option is read in the current language', array('note'=>'Danke', 'n'=>1), get_option('value_test_mods'));
$value_read = get_option('value_test_mods');
$value_read['n'] = 2;
update_option('value_test_mods', $value_read);
check_case('saving a translated option keeps the other languages of what did not change', array('note'=>'[:en]Thanks[:de]Danke[:]', 'n'=>2), qtrad_raw_option('value_test_mods'));
remove_filter('option_value_test_mods', 'qtrad_translate_option', 5);
remove_filter('pre_update_option_value_test_mods', 'qtrad_filter_update_option', 5);
// Options that are not autoloaded are not in memory; an index finds them.
delete_option('qtrad_marked_options');
add_option('value_test_lazy', '[:en]Visible[:de]Sichtbar[:]', '', false);
settings(array('qtranslate_enabled_languages'=>array('en','de','es'), 'qtrad_settings'=>$value_settings + array('translate_options'=>'all')));
check_case('an option that is not autoloaded is translated in all mode', true, in_array('value_test_lazy', qtrad_option_names_to_translate(), true));
qtrad_register_value_hooks();
add_option('value_test_lazy_late', array('note'=>'[:en]Later[:de]Später[:]'), '', false);
check_case('an option saved later joins the index', true, in_array('value_test_lazy_late', get_option('qtrad_marked_options'), true));
update_option('qtranslate_filter_options', array('value_test_lazy%'));
settings(array('qtranslate_enabled_languages'=>array('en','de','es'), 'qtrad_settings'=>$value_settings + array('translate_options'=>'list')));
$value_names = qtrad_option_names_to_translate(); sort($value_names);
check_case('a listed pattern matches options that are not autoloaded', array('value_test_lazy', 'value_test_lazy_late'), $value_names);
add_filter('option_value_test_lazy', 'qtrad_translate_option', 5);
qtrad_set_language('de');
check_case('an option that is not autoloaded is read in the current language', 'Sichtbar', get_option('value_test_lazy'));
remove_filter('option_value_test_lazy', 'qtrad_translate_option', 5);
remove_action('added_option', 'qtrad_note_added_option', 10); remove_action('updated_option', 'qtrad_note_updated_option', 10);
foreach (qtrad_text_filters() as $value_hook) remove_filter($value_hook, 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage');
delete_option('value_test_lazy'); delete_option('value_test_lazy_late'); delete_option('qtrad_marked_options');
update_option('qtranslate_text_field_filters', array('value_test_filter', 'bad name!'));
qtrad_reset_config();
check_case('text filter names are validated', array('value_test_filter'), qtrad_text_filters());
qtrad_register_value_hooks();
qtrad_set_language('de');
check_case('extra text filter shows the current language', 'B', apply_filters('value_test_filter', '[:en]A[:de]B[:]'));
remove_filter('value_test_filter', 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage');
qtrad_save_settings(array('qtrad_settings'=>'1', 'enabled'=>array('en','de','es'), 'default'=>'en', 'translate_meta'=>'1', 'translate_options'=>'all', 'filter_options'=>'widget_%  my_option bad!name', 'text_field_filters'=>'my_filter'));
check_case('settings form saves the value translation choices', array(true, 'all', array('widget_%', 'my_option'), array('my_filter')), array(qtrad_config('translate_meta'), qtrad_config('translate_options'), get_option('qtranslate_filter_options'), get_option('qtranslate_text_field_filters')));
qtrad_save_settings(array('enabled'=>array('en','de','es')));
check_case('a partial settings save keeps the value translation choices', array(true, 'all'), array(qtrad_config('translate_meta'), qtrad_config('translate_options')));
delete_option('qtrad_settings');
qtrad_reset_config();
qtrad_migration_scan(true);
$value_own = get_option('qtrad_settings');
check_case('first scan turns value translation on where such data exists', array(true, 'all'), array(!empty($value_own['translate_meta']), isset($value_own['translate_options']) ? $value_own['translate_options'] : null));
update_option('qtrad_settings', array('translate_meta'=>false, 'translate_options'=>'none'));
qtrad_reset_config();
qtrad_migration_scan(true);
check_case('scan leaves a choice the admin made', array('translate_meta'=>false, 'translate_options'=>'none'), get_option('qtrad_settings'));
foreach ($value_saved as $value_name => $value_before) { if (null === $value_before) delete_option($value_name); else update_option($value_name, $value_before); }
delete_option('value_test_mods');
delete_option('value_test_plain');
unset($GLOBALS['qtrad_translates_values']);
// Date and time formats per language, and qTranslate-XT helpers.
settings(array('qtranslate_enabled_languages'=>array('en','de','es'), 'date_format'=>'F j, Y', 'time_format'=>'g:i a'));
check_case('strftime formats of qTranslate-X become PHP date formats', array('l, \d\e\r j. F Y', 'l F jS, Y', 'h:i A', 'd \d\e F \d\e Y', 'Y-m-d \a\t H:i', 'j. F Y'), array(qtrad_php_date_format('%A, \d\e\r %e. %B %Y'), qtrad_php_date_format('%A %B %e%q, %Y'), qtrad_php_date_format('%I:%M %p'), qtrad_php_date_format('%d \d\e %B \d\e %Y'), qtrad_php_date_format('%F at %R'), qtrad_php_date_format('j. F Y')));
update_option('qtranslate_date_formats', array('de'=>'%e. %B %Y', 'es'=>''));
update_option('qtranslate_time_formats', array('de'=>'H:i'));
qtrad_reset_config();
add_filter('option_date_format', 'qtrad_filter_date_format', 5);
add_filter('option_time_format', 'qtrad_filter_time_format', 5);
$GLOBALS['qtrad_translates_values'] = true;
qtrad_set_language('de');
check_case('site date and time formats follow the current language', array('j. F Y', 'H:i'), array(get_option('date_format'), get_option('time_format')));
qtrad_set_language('es');
check_case('a language without a format keeps the site format', array('F j, Y', 'g:i a'), array(get_option('date_format'), get_option('time_format')));
$GLOBALS['qtrad_translates_values'] = false;
qtrad_set_language('de');
check_case('wp-admin keeps the site formats', 'F j, Y', get_option('date_format'));
remove_filter('option_date_format', 'qtrad_filter_date_format', 5);
remove_filter('option_time_format', 'qtrad_filter_time_format', 5);
qtrad_save_language_formats(array('languages'=>array('de'=>array('date_format'=>' d.m.Y ', 'time_format'=>''), 'es'=>array('date_format'=>'j \d\e F'), 'xx'=>array('date_format'=>'Y'))));
check_case('settings save formats per enabled language, empty removes one', array(array('de'=>'d.m.Y', 'es'=>'j \d\e F'), array()), array(get_option('qtranslate_date_formats'), get_option('qtranslate_time_formats')));
qtrad_set_language('de');
check_case('qtranxf_use_language takes the language first', 'Hallo', qtranxf_use_language('de', '[:en]Hello[:de]Hallo[:]'));
check_case('qtranxf_split_languages builds the language map from blocks', array('en'=>'Hello', 'de'=>'Hallo'), array_intersect_key(qtranxf_split_languages(qtranxf_get_language_blocks('[:en]Hello[:de]Hallo[:]')), array('en'=>1, 'de'=>1)));
check_case('qtranxf_translate_deep handles nested arrays', array('a'=>array('Hallo')), qtranxf_translate_deep(array('a'=>array('[:en]Hello[:de]Hallo[:]')), 'de'));
$helper_post = get_post(fixture(array('post_title'=>'[:en]Hello[:de]Hallo[:]', 'post_content'=>'[:en]Body[:de]Inhalt[:]')));
qtranxf_translate_post($helper_post, 'de');
check_case('qtranxf_translate_post translates the object in place', array('Hallo', 'Inhalt'), array($helper_post->post_title, $helper_post->post_content));
check_case('qtranxf_get_url_for_language', 'http://127.0.0.1:8931/es/sample/', qtranxf_get_url_for_language('http://127.0.0.1:8931/sample/', 'es'));
update_option('qtranslate_term_name', array('Books'=>array('en'=>'Books', 'de'=>'Bücher')));
qtrad_reset_config();
check_case('qtranxf_term_use reads markers and the term library', array('Bücher', 'Rot'), array(qtranxf_term_use('de', 'Books'), qtranxf_term_use('de', '[:en]Red[:de]Rot[:]')));
qtrad_register_translator_filters();
qtrad_set_language('de');
check_case('translate_text filter', array('Hallo', 'Hola', ''), array(apply_filters('translate_text', '[:en]Hello[:de]Hallo[:es]Hola[:]'), apply_filters('translate_text', '[:en]Hello[:de]Hallo[:es]Hola[:]', 'es'), apply_filters('translate_text', '[:en]Hello[:]', 'de', QTX_TRANSLATOR_SHOW_EMPTY)));
check_case('translate_term and translate_url filters', array('Bücher', 'http://127.0.0.1:8931/es/sample/'), array(apply_filters('translate_term', 'Books'), apply_filters('translate_url', 'http://127.0.0.1:8931/sample/', 'es')));
check_case('get_language and set_language filters', array('de', 'de', 'es'), array(apply_filters('get_language', null), apply_filters('set_language', 'es'), qtrad_current_language()));
foreach (array('translate_text', 'translate_term', 'translate_url', 'get_language', 'set_language') as $translator_filter) remove_all_filters($translator_filter);
delete_option('qtranslate_date_formats'); delete_option('qtranslate_time_formats');
unset($GLOBALS['qtrad_translates_values']);
settings();
if (in_array('--prepare-browser', $argv, true)) {
    update_option('blogname', '[:en]qTrad Audit[:de]qTrad Audit DE[:es]Título español[:]');
    $themes = wp_get_themes();
    $theme = isset($themes['twentytwentyone']) ? 'twentytwentyone' : (isset($themes['twentytwentyfive']) ? 'twentytwentyfive' : key($themes));
    update_option('template', $theme);
    update_option('stylesheet', $theme);
    update_option('permalink_structure', '/%postname%/');
    update_option('active_plugins', array('qtrad/qtrad.php'));
    update_option('qtranslate_enabled_languages', array('en', 'de', 'ar', 'ca'));
    qtrad_reset_config();
    update_user_meta(1, 'qtrad_edit_language', 'en');
    $auditor = get_user_by('login', 'qtrad_auditor');
    $auditor_id = $auditor ? wp_update_user(array('ID'=>$auditor->ID, 'role'=>'administrator', 'user_pass'=>'qtrad-local-audit-only')) : wp_insert_user(array('user_login'=>'qtrad_auditor', 'role'=>'administrator', 'user_pass'=>'qtrad-local-audit-only'));
    if (is_wp_error($auditor_id)) { fwrite(STDERR,$auditor_id->get_error_message()); exit(2); }
    update_user_meta($auditor_id, 'qtrad_edit_language', 'en');
    $browser_id = fixture(array('post_name' => 'qtrad-audit-browser', 'post_status' => 'publish',
        'post_title' => '[:en]Hello[:de]Hallo[:es]Hola[:]',
        'post_content' => '[qtrad_switcher style="both"] [qtrad_switcher style="image"] [qtrad_switcher style="short"] [qtrad_switcher style="dropdown"] [:en]<!-- wp:paragraph --><p>English body</p><!-- /wp:paragraph -->[:de]<!-- wp:paragraph --><p>German body</p><!-- /wp:paragraph -->[:es]<!-- wp:paragraph --><p>Spanish body</p><!-- /wp:paragraph -->[:]'));
    flush_rewrite_rules(false);
}

echo json_encode(array('environment' => array('wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION), 'browser_post_id' => $browser_id ?? null, 'browser_post_slug' => isset($browser_id) ? get_post($browser_id)->post_name : null, 'passed' => count(array_filter($results, fn($r) => $r['pass'])), 'total' => count($results), 'cases' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

exit(count(array_filter($results, fn($r) => !$r['pass'])) ? 1 : 0);
