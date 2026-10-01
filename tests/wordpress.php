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
require_once dirname(__DIR__) . '/qtrad/qtranslate-next.php';
require_once dirname(__DIR__) . '/qtrad/qtranslate-unified.php';
wp_set_current_user(1);
qtu_register_field_hooks();
$results = array();
function check_case($name, $expected, $actual) {
    global $results;
    $results[] = array('case' => $name, 'pass' => $expected === $actual, 'expected' => $expected, 'actual' => $actual);
}
$entries = array();
foreach (glob(QTU_DIR . '*.php') as $entry) {
    $header = get_file_data($entry, array('name'=>'Plugin Name'));
    if ($header['name'] !== '') $entries[basename($entry)] = $header['name'];
}
check_case('Former bootstraps keep canonical activation file', 'qtrad.php', basename(QTU_FILE));
check_case('WordPress discovers only one activatable entry', array('qtrad.php'=>'qTrad — Multilingual Content'), $entries);
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
        'qtranslate_next_settings' => array('write_format' => 'keep', 'extra_fields' => 'audit_field'),
    );
    foreach (array_merge($defaults, $overrides) as $key => $value) update_option($key, $value);
    qtu_reset_config();
}
function fixture($fields = array()) {
    $_POST = array();
    return wp_insert_post(wp_slash(array_merge(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Audit fixture'), $fields)));
}
function classic_payload($title, $content, $excerpt = array('en' => '', 'de' => '')) {
    global $id;
    $_POST = wp_slash(array(
        'qtu_field_post_id' => $id, 'qtu_field_nonce' => wp_create_nonce('qtu_field_' . $id), 'qtu_js' => '1', 'qtu_edit_lang' => 'de',
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
$_POST = array('qtu_edit_lang' => 'de', 'post_ID' => $id);
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
$_POST = array('qtu_term' => array('en' => 'New', 'de' => 'Neu'), 'qtu_term_nonce' => wp_create_nonce('qtu_term'), 'qtu_term_id' => $term['term_id']);
wp_update_term($term['term_id'], 'category', array('name' => 'New'));
$library = get_option('qtranslate_term_name');
check_case('term rename removes obsolete library key', false, isset($library['Old']));
check_case('term rename retains disabled French', 'Ancien', $library['New']['fr'] ?? null);

settings(array('qtranslate_term_name' => array('News' => array('en' => 'News', 'de' => 'Nachrichten'))));
$current_screen = WP_Screen::get('edit-tags');
$term = wp_insert_term('News', 'category', array('slug' => uniqid('audit-news-')));
add_filter('get_term', 'qtu_filter_get_term');
qtu_set_language('de');
$_POST = array('qtu_term' => array('en' => 'News', 'de' => 'Neu'), 'qtu_term_nonce' => wp_create_nonce('qtu_term'), 'qtu_term_id' => $term['term_id']);
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
check_case('Catalan missing flag has visible text fallback', true, strpos(qtu_language_chooser('image'), 'alt=""') !== false);
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
$saved_post = $_POST;
$security_id = fixture(array('post_author' => $author_id, 'post_title' => 'Security check'));
$_POST = $saved_post;
$_POST['qtu_field_post_id'] = $security_id;
$_POST['qtu_field_nonce'] = wp_create_nonce('qtu_field_' . $security_id);
$_POST['qtu_field']['content']['en'] = wp_slash('<script>alert(1)</script><img src="x" onerror="alert(1)"><p>Clean</p>');
wp_update_post(wp_slash(array('ID' => $security_id, 'post_content' => 'Clean')));
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

// Regression coverage for additional storage, API and routing boundaries.
settings();
check_case('quicktags=false keeps bracket markers literal', array('en'=>'[:en]Hello[:de]Hallo[:]', 'de'=>'[:en]Hello[:de]Hallo[:]'), qtrans_split('[:en]Hello[:de]Hallo[:]', false));
check_case('use disabled language leaves complete source intact', '[:en]Hello[:fr]Bonjour[:]', qtranxf_use('fr', '[:en]Hello[:fr]Bonjour[:]'));
check_case('shared footer survives in disabled language', 'Bonjour footer', qtu_split('[:en]Hello[:fr]Bonjour[:] footer')['fr']);
check_case('new translated field uses original-compatible comments', '<!--:en-->Hello<!--:--><!--:de-->Hallo<!--:-->', qtu_merge_field('Hallo', 'Hello', 'de'));
$parts = array('en'=>'Intro<!--more-->End', 'de'=>'Anfang<!--more-->Ende', 'fr'=>'Début<!--more-->Fin');
check_case('more separator survives disabled language', $parts, qtu_split(qtu_join_content($parts, 'comment', array('en','de')), null, false));
check_case('chooser supports legacy options array', true, strpos(qtu_language_chooser(array('type'=>'dropdown','id'=>'example')), 'id="example"') !== false);
check_case('chooser boolean requests flags', true, strpos(qtu_language_chooser(true), 'qtu-style-image') !== false);
$widget = new qTradWidget();
check_case('qTrad widget preserves existing sidebar identity', 'qtranslate', $widget->id_base);
check_case('former Next widget class preserves sidebar identity', $widget->id_base, (new qTranslateNextWidget())->id_base);
qtu_register_shortcodes();
check_case('qTrad switcher matches legacy shortcode output', do_shortcode('[qtranslate_switcher style="both" id="branding"]'), do_shortcode('[qtrad_switcher style="both" id="branding"]'));
$updated = $widget->update(array('title'=>'New','type'=>'text','hide-title'=>true), array('format'=>'legacy-format','widget-css'=>'legacy-css'));
check_case('widget retains unimplemented legacy options for rollback', 'legacy-format', $updated['format']);
check_case('widget updates hide-title', true, $updated['hide-title']);
$id = fixture(array('post_title'=>'[:en]Old[:de]Alt[:]'));
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Full replacement')));
check_case('programmatic full replacement remains plain', 'Full replacement', get_post($id)->post_title);
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Neu', 'qtu_language'=>'de')));
check_case('programmatic partial update requires explicit context', array('en'=>'Full replacement','de'=>'Neu'), qtu_split(get_post($id)->post_title));
$id = fixture(array('post_title'=>'[:en]Hello[:de]Hallo[:]'));
classic_payload(array('en'=>'Wrong','de'=>'Falsch'), array('en'=>'Wrong','de'=>'Falsch'));
$_POST['qtu_field_post_id'] = $id + 10000;
wp_update_post(wp_slash(array('ID'=>$id, 'post_title'=>'Full value')));
check_case('wrong form identity cannot overwrite target', 'Full value', get_post($id)->post_title);
settings();
$id = fixture();
qtu_set_language('de');
add_post_meta($id, 'audit_field', '[:en]First[:de]Erste[:]');
add_post_meta($id, 'audit_field', '[:en]Second[:de]Zweite[:]');
update_post_meta($id, 'audit_field', wp_slash('C:\\temp'), '[:en]Second[:de]Zweite[:]');
check_case('metadata conditional update selects one raw row', array('[:en]First[:de]Erste[:]', '[:en]Second[:de]C:\\temp[:]'), qtu_raw_post_meta($id,'audit_field'));
add_post_meta($id,'audit_field','0');
check_case('zero metadata exists', true, metadata_exists('post',$id,'audit_field'));
check_case('zero metadata is displayed', '0', get_post_meta($id,'audit_field',false)[2]);
function preceding_meta_provider($value,$post_id,$key) { return $key === 'audit_field' ? array('Provider value') : $value; }
add_filter('get_post_metadata','preceding_meta_provider',5,3);
check_case('metadata respects earlier provider', 'Provider value', get_post_meta($id,'audit_field',true));
remove_filter('get_post_metadata','preceding_meta_provider',5);
settings(array('qtranslate_na_messages'=>array('de'=>'Nur %LANG:, : und %.'), 'qtranslate_show_alternative_content_message'=>true));
qtu_set_language('de');
$notice = qtu_use_current('[:en]English[:]',true);
check_case('legacy missing-translation message is retained', true, strpos($notice, 'Nur <a') !== false);
check_case('legacy policy does not append alternative content', false, preg_match('~</p>.+~s', $notice) === 1);
settings(array('qtranslate_show_alternative_content_message'=>true,'qtranslate_show_alternative_content'=>true));
qtu_set_language('de');
check_case('legacy alternative content policy is honored', true, strpos(qtu_use_current('[:en]English[:]',true), 'English</div>') !== false);
settings();
$before = get_option('qtranslate_enabled_languages');
$error = qtu_save_settings(array('enabled'=>array('en'), 'new_language'=>array('code'=>'bad','name'=>'Invalid','locale'=>'bad!')));
check_case('invalid custom language returns an error', true, is_wp_error($error));
check_case('invalid settings do not change enabled languages', $before, get_option('qtranslate_enabled_languages'));
$error = qtu_save_settings(array('enabled'=>array('en'), 'domains'=>"de = https://unsafe.example/path"));
check_case('invalid mapped domain returns an error', true, is_wp_error($error));
check_case('invalid domains do not partially save', $before, get_option('qtranslate_enabled_languages'));
settings(array('qtranslate_url_mode'=>4, 'qtranslate_domains'=>array('en'=>'en.example:8931','de'=>'de.example:8931')));
check_case('domain map switches configured language origins', 'http://de.example:8931/path?x=1', qtu_convert_url('http://en.example:8931/path?x=1','de'));
check_case('mapped default always uses its mapped origin', 'http://en.example:8931/path', qtu_convert_url('http://de.example:8931/path','en'));
settings();
check_case('different port is an external origin', 'http://127.0.0.1:9999/path', qtu_convert_url('http://127.0.0.1:9999/path','de'));
check_case('credential-bearing URL is preserved', 'http://user:pass@127.0.0.1:8931/path', qtu_convert_url('http://user:pass@127.0.0.1:8931/path','de'));
check_case('tel URL is preserved', 'tel:+34000000000', qtu_convert_url('tel:+34000000000','de'));
check_case('language query removal preserves duplicate bytes', 'x=1&x=2&v=a%20b', qtu_strip_query_lang('x=1&lang=de&x=2&v=a%20b'));
settings(array('qtranslate_hide_untranslated'=>'1'));
$id = fixture(array('post_title'=>'[:en]Title[:de][:]', 'post_content'=>'[:en]Body[:de]  [:]'));
qtu_set_language('de');
delete_post_meta($id,'_qtn_available_languages');
add_filter('posts_where_request','qtu_hide_untranslated_where',10,2);
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('unindexed legacy empty translation is excluded', array(), $query->posts);
$_POST = array();
wp_update_post(wp_slash(array('ID'=>$id,'post_content'=>'[:en]Body[:de]0[:]')));
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('indexed zero translation remains visible', array($id), $query->posts);
delete_post_meta($id,'_qtn_available_languages');
$query = new WP_Query(array('post__in'=>array($id),'post_status'=>'draft','fields'=>'ids'));
check_case('legacy zero translation remains visible', array($id), $query->posts);
remove_filter('posts_where_request','qtu_hide_untranslated_where',10);
settings();
register_post_type('qtn_book',array('public'=>true,'show_in_rest'=>true,'rest_base'=>'books','supports'=>array('title','editor','excerpt')));
$id = fixture(array('post_type'=>'qtn_book','post_title'=>'[:en]Hello[:de]Hallo[:fr]Bonjour[:]', 'post_content'=>'[:en]Body[:de]Inhalt[:]'));
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id);
$request->set_param('id',$id); $request->set_param('qtu_language','de'); $request->set_param('title','REST title');
$response = rest_do_request($request);
check_case('custom post type REST update succeeds', 200, $response->get_status());
check_case('custom post type REST preserves disabled language', '[:en]Hello[:de]REST title[:fr]Bonjour[:]', get_post($id)->post_title);
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id.'/autosaves');
$request->set_param('id',$id); $request->set_param('qtu_language','de'); $request->set_param('title','Autosave title');
$response = rest_do_request($request);
check_case('custom post type REST autosave succeeds', 200, $response->get_status());
$autosave = $response->get_data();
check_case('autosave retains multilingual title', true, isset($autosave['title']['raw']) && strpos($autosave['title']['raw'],'[:fr]Bonjour') !== false);
settings(array('qtranslate_next_settings'=>array('editor_mode'=>'raw','extra_fields'=>'audit_field','write_format'=>'keep')));
$request = new WP_REST_Request('POST','/wp/v2/books/'.$id);
$request->set_param('id',$id); $request->set_param('title','Raw replacement');
$response = rest_do_request($request);
check_case('raw mode REST permits full plain replacement', 'Raw replacement', get_post($id)->post_title);
settings();
unregister_post_type('qtn_book');

settings(array('qtranslate_term_name'=>array('Delete raw'=>array('en'=>'Delete raw','de'=>'Löschen'))));
$term = wp_insert_term('Delete raw', 'category', array('slug'=>uniqid('delete-raw-')));
qtu_set_language('de');
add_filter('get_term','qtu_filter_get_term');
wp_delete_term($term['term_id'],'category');
check_case('deleting a displayed translation cleans the raw library key', false, isset(get_option('qtranslate_term_name')['Delete raw']));
remove_filter('get_term','qtu_filter_get_term');
settings();
qtu_set_language('de');
check_case('root custom language attribute survives', 'data-lang="custom" lang="de-DE" dir="ltr"', qtu_filter_language_attributes('data-lang="custom" lang="en-US"'));
check_case('root language attributes retain unrelated and XHTML attributes', 'data-test="kept" lang="de-DE" xml:lang="de-DE" dir="ltr"', qtu_filter_language_attributes('data-test="kept" lang="en-US" xml:lang="en-US" dir="ltr"'));
settings();
if (in_array('--prepare-browser', $argv, true)) {
    update_option('blogname', '[:en]qTrad Audit[:de]qTrad Audit DE[:fr]Titre français[:]');
    $themes = wp_get_themes();
    $theme = isset($themes['twentytwentyone']) ? 'twentytwentyone' : (isset($themes['twentytwentyfive']) ? 'twentytwentyfive' : key($themes));
    update_option('template', $theme);
    update_option('stylesheet', $theme);
    update_option('permalink_structure', '/%postname%/');
    update_option('active_plugins', array('qtrad/qtrad.php'));
    update_option('qtranslate_enabled_languages', array('en', 'de', 'ar', 'ca'));
    qtu_reset_config();
    update_user_meta(1, 'qtu_edit_language', 'en');
    $auditor = get_user_by('login', 'qtn_auditor');
    $auditor_id = $auditor ? wp_update_user(array('ID'=>$auditor->ID, 'role'=>'administrator', 'user_pass'=>'qtn-local-audit-only')) : wp_insert_user(array('user_login'=>'qtn_auditor', 'role'=>'administrator', 'user_pass'=>'qtn-local-audit-only'));
    if (is_wp_error($auditor_id)) { fwrite(STDERR,$auditor_id->get_error_message()); exit(2); }
    update_user_meta($auditor_id, 'qtu_edit_language', 'en');
    $browser_id = fixture(array('post_name' => 'qtn-audit-browser', 'post_status' => 'publish',
        'post_title' => '[:en]Hello[:de]Hallo[:fr]Bonjour[:]',
        'post_content' => '[qtrad_switcher style="both"] [qtranslate_switcher style="image"] [qtranslate_switcher style="short"] [qtranslate_switcher style="dropdown"] [:en]<!-- wp:paragraph --><p>English body</p><!-- /wp:paragraph -->[:de]<!-- wp:paragraph --><p>German body</p><!-- /wp:paragraph -->[:fr]<!-- wp:paragraph --><p>French body</p><!-- /wp:paragraph -->[:]'));
    flush_rewrite_rules(false);
}

echo json_encode(array('environment' => array('wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION), 'browser_post_id' => $browser_id ?? null, 'browser_post_slug' => isset($browser_id) ? get_post($browser_id)->post_name : null, 'passed' => count(array_filter($results, fn($r) => $r['pass'])), 'total' => count($results), 'cases' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

exit(count(array_filter($results, fn($r) => !$r['pass'])) ? 1 : 0);
