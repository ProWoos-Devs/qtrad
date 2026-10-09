<?php
// ACF adapter checks: run on the matrix fixture with ACF and the installed qTrad copy active.
if (empty($argv[1]) || !is_file($argv[1] . '/.qtrad-disposable')) { fwrite(STDERR, "Supply a matrix fixture.\n"); exit(2); }
// Not WP_CLI: ACF would load its CLI commands and fail without the WP-CLI classes.
define('WP_CLI', false);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8931'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!function_exists('acf') || !function_exists('qtrad_acf_split')) { fwrite(STDERR, "ACF and qTrad must both be active.\n"); exit(2); }
wp_set_current_user(1);
$cases = array();
// ACF caches formatted values per request; the language changes between checks.
function acf_language($lang) { qtrad_set_language($lang); if (function_exists('acf_get_store')) acf_get_store('values')->reset(); }
function acf_check($name, $expected, $actual) {
    global $cases; $cases[] = array('case'=>$name, 'pass'=>$expected === $actual, 'expected'=>$expected, 'actual'=>$actual);
}
update_option('qtranslate_enabled_languages', array('en','de','es'));
update_option('qtranslate_default_language', 'en');
update_option('qtranslate_show_displayed_language_prefix', '1');
update_option('qtrad_settings', array('write_format'=>'keep', 'extra_fields'=>'', 'translate_meta'=>false, 'translate_options'=>'none'));
qtrad_reset_config();
acf_check('qTranslate-XT field types are registered', array(true, true, true, true, true, true, true), array_map(function ($type) { return (bool) acf_get_field_type($type); }, array('qtranslate_text','qtranslate_textarea','qtranslate_url','qtranslate_wysiwyg','qtranslate_image','qtranslate_file','qtranslate_post_object')));
if (!acf_get_field_group('group_qtrad_acf')) {
    acf_import_field_group(array('key'=>'group_qtrad_acf', 'title'=>'qTrad ACF', 'location'=>array(array(array('param'=>'post_type','operator'=>'==','value'=>'post'))), 'fields'=>array(
        array('key'=>'field_qtrad_text', 'label'=>'[:en]Subtitle[:de]Untertitel[:]', 'name'=>'qtrad_text', 'type'=>'qtranslate_text', 'required'=>1),
        array('key'=>'field_qtrad_std', 'label'=>'Standard', 'name'=>'qtrad_std', 'type'=>'text', 'qtrad_translate'=>1, 'maxlength'=>20),
        array('key'=>'field_qtrad_url', 'label'=>'Link', 'name'=>'qtrad_url', 'type'=>'url', 'qtrad_translate'=>1),
        array('key'=>'field_qtrad_plain', 'label'=>'Plain', 'name'=>'qtrad_plain', 'type'=>'text'),
        array('key'=>'field_qtrad_image', 'label'=>'Image', 'name'=>'qtrad_image', 'type'=>'qtranslate_image', 'return_format'=>'id'),
        array('key'=>'field_qtrad_post', 'label'=>'Related', 'name'=>'qtrad_post', 'type'=>'qtranslate_post_object', 'return_format'=>'id'),
    )));
}
$post_id = wp_insert_post(array('post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'ACF fixture'));
$related = wp_insert_post(array('post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Related fixture'));
$attachment = wp_insert_post(array('post_type'=>'attachment', 'post_status'=>'inherit', 'post_title'=>'Attachment fixture', 'post_mime_type'=>'image/png'));
update_field('field_qtrad_text', array('en'=>'Hello', 'de'=>'Hallo'), $post_id);
update_field('field_qtrad_std', array('en'=>'Std', 'de'=>'Standard'), $post_id);
update_field('field_qtrad_url', array('en'=>'https://example.com/en', 'de'=>'https://example.com/de'), $post_id);
update_field('field_qtrad_plain', 'Just text', $post_id);
update_field('field_qtrad_image', array('en'=>(string) $attachment, 'de'=>''), $post_id);
update_field('field_qtrad_post', array('en'=>(string) $related, 'de'=>(string) $post_id), $post_id);
acf_check('values are stored with bracket markers', array('[:en]Hello[:de]Hallo[:]', '[:en]Std[:de]Standard[:]', '[:en]https://example.com/en[:de]https://example.com/de[:]', 'Just text', '[:en]' . $attachment . '[:]', '[:en]' . $related . '[:de]' . $post_id . '[:]'), array_map(function ($key) use ($post_id) { return get_post_meta($post_id, $key, true); }, array('qtrad_text','qtrad_std','qtrad_url','qtrad_plain','qtrad_image','qtrad_post')));
$GLOBALS['qtrad_translates_values'] = true;
acf_language('de');
acf_check('get_field returns the current language', array('Hallo', 'Standard', 'https://example.com/de', 'Just text', $post_id), array(get_field('qtrad_text', $post_id), get_field('qtrad_std', $post_id), get_field('qtrad_url', $post_id), get_field('qtrad_plain', $post_id), get_field('qtrad_post', $post_id)));
acf_language('es');
acf_check('missing translation falls back to the default language without a prefix', array('Hello', $attachment, $related), array(get_field('qtrad_text', $post_id), get_field('qtrad_image', $post_id), get_field('qtrad_post', $post_id)));
acf_language('de');
acf_check('image without a translation in the language falls back too', $attachment, get_field('qtrad_image', $post_id));
update_option('qtrad_settings', array('write_format'=>'keep', 'extra_fields'=>'', 'translate_meta'=>true, 'translate_options'=>'none'));
qtrad_reset_config();
qtrad_register_value_hooks();
acf_language('es');
acf_check('with every custom field translated, ACF fields still fall back without a prefix', array('Hello', $related), array(get_field('qtrad_text', $post_id), get_field('qtrad_post', $post_id)));
acf_check('ACF field read as plain custom field falls back without a prefix', 'Hello', get_post_meta($post_id, 'qtrad_text', true));
foreach (array('post') as $type) { remove_filter('get_'.$type.'_metadata', 'qtrad_filter_meta_all', 11); remove_filter('update_'.$type.'_metadata', 'qtrad_filter_update_meta_all', 11); }
remove_filter('get_user_metadata', 'qtrad_filter_meta_all', 11); remove_filter('update_user_metadata', 'qtrad_filter_update_meta_all', 11);
update_option('qtrad_settings', array('write_format'=>'keep', 'extra_fields'=>'', 'translate_meta'=>false, 'translate_options'=>'none'));
qtrad_reset_config();
unset($GLOBALS['qtrad_translates_values']);
acf_language('en');
$field = acf_get_field('field_qtrad_std');
acf_check('standard field with the Translate setting renders per language', 'qtranslate_text', qtrad_acf_prepare_field($field)['type']);
$field = acf_get_field('field_qtrad_plain'); $field['value'] = '[:en]A[:de]B[:]';
acf_check('standard field whose value has markers renders per language', 'qtranslate_text', qtrad_acf_prepare_field($field)['type']);
$field['value'] = 'A';
acf_check('plain standard field stays as it is', 'text', qtrad_acf_prepare_field($field)['type']);
acf_check('field label is shown in the admin language', 'Subtitle', qtrad_acf_prepare_field(acf_get_field('field_qtrad_text'))['label']);
ob_start(); acf_render_field(array_merge(acf_get_field('field_qtrad_text'), array('value'=>'[:en]Hello[:de]Hallo[:]', 'id'=>'acf-field_qtrad_text', 'name'=>'acf[field_qtrad_text]'))); $html = ob_get_clean();
acf_check('one input per language, the default language keeps the field id', array(3, true, true, true), array(substr_count($html, 'class="qtrad-acf-lang"'), strpos($html, 'id="acf-field_qtrad_text" ') !== false || strpos($html, 'id="acf-field_qtrad_text"') !== false, strpos($html, 'name="acf[field_qtrad_text][de]"') !== false && strpos($html, 'value="Hallo"') !== false, strpos($html, '<label class="qtrad-acf-lang__name" for="acf-field_qtrad_text-de">') !== false));
acf_check('only the default language input is required', 1, substr_count($html, 'required="required"') + substr_count($html, ' required '));
acf_check('URL validated per language', true, qtrad_acf_validate_standard(true, '[:en]https://example.com/en[:de]https://example.com/de[:]', acf_get_field('field_qtrad_url'), 'acf[field_qtrad_url]'));
acf_check('invalid URL in one language is reported with its name', 'Deutsch: Value must be a valid URL', qtrad_acf_validate_standard(true, '[:en]https://example.com/en[:de]nope[:]', acf_get_field('field_qtrad_url'), 'acf[field_qtrad_url]'));
acf_check('required field needs the default language', 'English: Subtitle is required', acf_get_field_type('qtranslate_text')->validate_value(true, '[:de]Hallo[:]', acf_get_field('field_qtrad_text'), 'acf[field_qtrad_text]'));
acf_check('character limit applies per language', 'Deutsch: Value must not exceed 20 characters', qtrad_acf_validate_standard(true, '[:en]short[:de]' . str_repeat('x', 30) . '[:]', acf_get_field('field_qtrad_std'), 'acf[field_qtrad_std]'));
// ACF posts its nonce with the screen name; a form save checks it and sets it to false before validating.
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>wp_create_nonce('post'), 'acf'=>array('field_qtrad_text'=>array('en'=>'A', 'de'=>"B\\'s"), 'field_qtrad_plain'=>'p', 'field_rows'=>array('row-0'=>array('field_qtrad_std'=>array('en'=>'x', 'de'=>'y')))));
qtrad_acf_join_posted_values();
acf_check('posted language arrays are joined, also inside rows', array("[:en]A[:de]B\\'s[:]", 'p', '[:en]x[:de]y[:]'), array($_POST['acf']['field_qtrad_text'], $_POST['acf']['field_qtrad_plain'], $_POST['acf']['field_rows']['row-0']['field_qtrad_std']));
$_POST = array('acf'=>array('field_qtrad_text'=>array('en'=>'A', 'de'=>'B')));
qtrad_acf_join_posted_values();
acf_check('posted values without ACF nonce are left alone', array('en'=>'A', 'de'=>'B'), $_POST['acf']['field_qtrad_text']);
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>'forged', 'acf'=>array('field_qtrad_text'=>array('en'=>'A', 'de'=>'B')));
qtrad_acf_join_posted_values();
acf_check('posted values with a wrong ACF nonce are left alone', array('en'=>'A', 'de'=>'B'), $_POST['acf']['field_qtrad_text']);
require_once ABSPATH . 'wp-admin/includes/user.php';
$acf_editor = wp_insert_user(array('user_login'=>'qtrad_acf_author', 'user_pass'=>wp_generate_password(), 'role'=>'author')); wp_set_current_user($acf_editor);
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>wp_create_nonce('post'), 'acf'=>array('field_qtrad_text'=>array('en'=>'<b>A</b><script>x()</script>', 'de'=>'B'), 'field_qtrad_url'=>array('en'=>'https://example.com/<i>a</i>', 'de'=>'')));
if (is_multisite() || !current_user_can('unfiltered_html')) { qtrad_acf_join_posted_values(); $acf_sanitized = array($_POST['acf']['field_qtrad_text'], $_POST['acf']['field_qtrad_url']); } else { $acf_sanitized = 'author has unfiltered_html'; }
wp_set_current_user(1); wp_delete_user($acf_editor);
acf_check('posted language values are sanitized for users without unfiltered_html', array('[:en]<b>A</b>x()[:de]B[:]', '[:en]https://example.com/a[:]'), $acf_sanitized);
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>false, 'acf'=>array('field_qtrad_text'=>array('en'=>'Saved', 'de'=>'Gespeichert'), 'field_qtrad_url'=>array('en'=>'https://example.com/a', 'de'=>'https://example.com/b')));
acf_check('ACF accepts valid per-language values', true, acf_validate_save_post());
acf_save_post($post_id);
acf_check('ACF saves per-language values as one string', array('[:en]Saved[:de]Gespeichert[:]', '[:en]https://example.com/a[:de]https://example.com/b[:]'), array(get_post_meta($post_id, 'qtrad_text', true), get_post_meta($post_id, 'qtrad_url', true)));
acf_reset_validation_errors();
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>wp_create_nonce('post'), 'acf'=>array('field_qtrad_text'=>array('en'=>'', 'de'=>'Nur'), 'field_qtrad_url'=>array('en'=>'bad', 'de'=>'')));
acf_check('ACF rejects a missing default language and an invalid URL', array(false, 2), array(acf_validate_save_post(), count(acf_get_validation_errors())));
acf_reset_validation_errors();
// Saving keeps languages that are not enabled, and fields detected by their markers stay strings.
$keep_id = wp_insert_post(array('post_type'=>'post', 'post_status'=>'draft', 'post_title'=>'ACF keep fixture'));
update_post_meta($keep_id, 'qtrad_std', '[:en]Hello[:de]Hallo[:es]Hola[:]'); update_post_meta($keep_id, '_qtrad_std', 'field_qtrad_std');
update_post_meta($keep_id, 'qtrad_plain', '[:en]Hello[:de]Hallo[:es]Hola[:]'); update_post_meta($keep_id, '_qtrad_plain', 'field_qtrad_plain');
update_option('qtranslate_enabled_languages', array('en','de')); qtrad_reset_config();
$_POST = array('_acf_screen'=>'post', '_acf_nonce'=>false, 'acf'=>array('field_qtrad_std'=>array('en'=>'Hello edited', 'de'=>'Hallo'), 'field_qtrad_plain'=>array('en'=>'Plain edited', 'de'=>'')));
acf_check('ACF accepts per-language values of a marker-detected field', true, acf_validate_save_post());
acf_save_post($keep_id);
acf_check('saving keeps the translation of a language that is not enabled', '[:en]Hello edited[:de]Hallo[:es]Hola[:]', get_post_meta($keep_id, 'qtrad_std', true));
acf_check('marker-detected standard field is stored as a string, an emptied language is cleared', '[:en]Plain edited[:es]Hola[:]', get_post_meta($keep_id, 'qtrad_plain', true));
update_option('qtranslate_enabled_languages', array('en','de','es')); qtrad_reset_config();
acf_reset_validation_errors();
$_POST = array();
// Other validators keep their say.
$rejector = function () { return 'Rejected by custom rule'; };
add_filter('acf/validate_value/key=field_qtrad_std', $rejector, 10, 4);
acf_check('a field key validator still rejects a multilingual value', array(false, 'Rejected by custom rule'), array(acf_validate_value('[:en]Hello[:de]Hallo[:]', acf_get_field('field_qtrad_std'), 'acf[field_qtrad_std]'), implode('|', wp_list_pluck((array) acf_get_validation_errors(), 'message'))));
remove_filter('acf/validate_value/key=field_qtrad_std', $rejector, 10);
acf_reset_validation_errors();
$refuser = function () { return false; };
add_filter('acf/validate_value/type=text', $refuser, 99, 4);
acf_check('a later type validator returning false still rejects', false, acf_validate_value('[:en]Hello[:de]Hallo[:]', acf_get_field('field_qtrad_std'), 'acf[field_qtrad_std]'));
remove_filter('acf/validate_value/type=text', $refuser, 99);
acf_reset_validation_errors();
acf_check('a per-language URL check is not tripped by the markers', true, acf_validate_value('[:en]https://example.com/en[:de]https://example.com/de[:]', acf_get_field('field_qtrad_url'), 'acf[field_qtrad_url]'));
acf_reset_validation_errors();
acf_check('a qTranslate-XT field type keeps its key validators too', false, (function () { $f = function () { return 'No'; }; add_filter('acf/validate_value/key=field_qtrad_text', $f, 10, 4); $r = acf_validate_value('[:en]Hello[:de]Hallo[:]', acf_get_field('field_qtrad_text'), 'acf[field_qtrad_text]'); remove_filter('acf/validate_value/key=field_qtrad_text', $f, 10); return $r; })());
acf_reset_validation_errors();
wp_delete_post($keep_id, true);
$_POST = array();
wp_delete_post($post_id, true); wp_delete_post($related, true); wp_delete_post($attachment, true);
echo json_encode(array('environment'=>array('wordpress'=>get_bloginfo('version'), 'php'=>PHP_VERSION, 'acf'=>acf_get_setting('version')), 'passed'=>count(array_filter($cases, function ($c) { return $c['pass']; })), 'total'=>count($cases), 'cases'=>$cases), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
exit(count(array_filter($cases, function ($c) { return !$c['pass']; })) ? 1 : 0);
