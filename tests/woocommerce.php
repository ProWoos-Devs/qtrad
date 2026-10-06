<?php
// WooCommerce adapter checks: run on the matrix fixture with WooCommerce and the installed qTrad copy active.
if (empty($argv[1]) || !is_file($argv[1] . '/.qtrad-disposable')) { fwrite(STDERR, "Supply a matrix fixture.\n"); exit(2); }
// Not WP_CLI: plugins may load CLI-only code when it is defined.
define('WP_CLI', false);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8931'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/wp-load.php';
if (!class_exists('WooCommerce') || !function_exists('qtrad_wc_translate')) { fwrite(STDERR, "WooCommerce and qTrad must both be active.\n"); exit(2); }
wp_set_current_user(1);
$cases = array();
function wc_check($name, $expected, $actual) {
    global $cases; $cases[] = array('case'=>$name, 'pass'=>$expected === $actual, 'expected'=>$expected, 'actual'=>$actual);
}
$mails = array();
add_filter('pre_wp_mail', function ($return, $atts) { global $mails; $mails[] = array('to'=>$atts['to'], 'subject'=>$atts['subject'], 'locale'=>determine_locale(), 'body'=>wp_strip_all_tags($atts['message'])); return true; }, 10, 2);
update_option('qtranslate_enabled_languages', array('en','de','es'));
update_option('qtranslate_default_language', 'en');
update_option('qtranslate_show_displayed_language_prefix', '1');
update_option('qtrad_settings', array('write_format'=>'keep', 'extra_fields'=>'', 'translate_meta'=>true, 'translate_options'=>'none'));
update_option('woocommerce_currency', 'EUR');
qtrad_reset_config();
$GLOBALS['qtrad_translates_values'] = true;
// Products as qTranslate-XT sites store them.
$attr_id = wc_attribute_taxonomy_id_by_name('qcolor');
if (!$attr_id) { $attr_id = wc_create_attribute(array('name'=>'[:en]Color[:de]Farbe[:es]Color[:]', 'slug'=>'qcolor')); }
register_taxonomy('pa_qcolor', 'product');
delete_transient('wc_attribute_taxonomies');
wp_cache_flush();
foreach (array('red'=>'[:en]Red[:de]Rot[:es]Rojo[:]', 'blue'=>'[:en]Blue[:de]Blau[:es]Azul[:]') as $slug => $name) { if (!term_exists($slug, 'pa_qcolor')) wp_insert_term($name, 'pa_qcolor', array('slug'=>$slug)); }
$simple = new WC_Product_Simple();
$simple->set_props(array('name'=>'[:en]Red wine[:de]Rotwein[:es]Vino tinto[:]', 'slug'=>'qtrad-red-wine', 'regular_price'=>'12', 'short_description'=>'[:en]A dry red.[:de]Ein trockener Roter.[:]', 'purchase_note'=>'[:en]Thanks[:de]Danke[:]', 'status'=>'publish'));
$simple_id = $simple->save();
$variable = new WC_Product_Variable();
$global = new WC_Product_Attribute(); $global->set_id($attr_id); $global->set_name('pa_qcolor'); $global->set_options(array(get_term_by('slug', 'red', 'pa_qcolor')->term_id, get_term_by('slug', 'blue', 'pa_qcolor')->term_id)); $global->set_visible(true); $global->set_variation(true);
$custom = new WC_Product_Attribute(); $custom->set_name('[:en]Size[:de]Größe[:]'); $custom->set_options(array('[:en]Small[:de]Klein[:]', '[:en]Large[:de]Groß[:]')); $custom->set_visible(true); $custom->set_variation(true);
$variable->set_props(array('name'=>'[:en]T-shirt[:de]T-Shirt[:]', 'slug'=>'qtrad-tshirt', 'status'=>'publish'));
$variable->set_attributes(array($global, $custom));
$variable_id = $variable->save();
$size_key = sanitize_title('[:en]Size[:de]Größe[:]');
$variation = new WC_Product_Variation(); $variation->set_parent_id($variable_id); $variation->set_attributes(array('pa_qcolor'=>'red', $size_key=>'[:en]Small[:de]Klein[:]')); $variation->set_regular_price('10'); $variation->set_status('publish');
$variation_id = $variation->save();
WC_Product_Variable::sync($variable_id);
update_option('woocommerce_cod_settings', array('enabled'=>'yes', 'title'=>'[:en]Cash on delivery[:de]Nachnahme[:]', 'description'=>'[:en]Pay on delivery.[:de]Zahlung bei Lieferung.[:]', 'instructions'=>''));
qtrad_set_language('de');
wc_delete_product_transients($simple_id);
$product = wc_get_product($simple_id);
wc_check('product name, short description and purchase note in the current language', array('Rotwein', 'Ein trockener Roter.', 'Danke'), array($product->get_name(), $product->get_short_description(), $product->get_purchase_note()));
wc_check('edit context keeps the stored value', '[:en]Red wine[:de]Rotwein[:es]Vino tinto[:]', $product->get_name('edit'));
qtrad_set_language('es');
wc_check('missing translation falls back with the language name, like titles', '(English) Thanks', wc_get_product($simple_id)->get_purchase_note());
qtrad_set_language('de');
// The global label comes from WooCommerce's attribute cache, which this process filled before the attribute existed; the HTTP check reads it on a page.
wc_check('attribute labels in the current language', array('Farbe', 'Größe'), array(apply_filters('woocommerce_attribute_label', '[:en]Color[:de]Farbe[:es]Color[:]', 'pa_qcolor', null), wc_attribute_label('[:en]Size[:de]Größe[:]')));
wc_check('global attribute term names in the current language', 'Rot', get_term_by('slug', 'red', 'pa_qcolor')->name);
wc_check('custom option text in the current language, its value stays stored', array('Klein', '[:en]Small[:de]Klein[:]'), array(apply_filters('woocommerce_variation_option_name', '[:en]Small[:de]Klein[:]', null, $size_key, wc_get_product($variable_id)), wc_get_product($variation_id)->get_attributes()[$size_key]));
$stored = get_post_meta($variable_id, '_product_attributes', true);
wc_check('attribute meta stays raw when every custom field is translated', '[:en]Size[:de]Größe[:]', $stored[$size_key]['name']);
wc_check('variation attribute meta stays raw', '[:en]Small[:de]Klein[:]', get_post_meta($variation_id, 'attribute_' . $size_key, true));
$gateways = WC()->payment_gateways()->payment_gateways();
wc_check('gateway title in the current language', 'Nachnahme', $gateways['cod']->get_title());
$data = apply_filters('woocommerce_structured_data_product', array('name'=>'[:en]Red wine[:de]Rotwein[:]', 'offers'=>array(array('name'=>'[:en]A[:de]B[:]'))), $product);
wc_check('structured data in the current language', array('Rotwein', 'B'), array($data['name'], $data['offers'][0]['name']));
wc_check('cart hash differs per language', true, qtrad_wc_cart_hash('abc') !== (function () { qtrad_set_language('es'); $h = qtrad_wc_cart_hash('abc'); qtrad_set_language('de'); return $h; })());
wc_check('empty cart hash stays empty', '', qtrad_wc_cart_hash(''));
// An order as the checkout creates it.
$order = wc_create_order(array('customer_id'=>0));
qtrad_wc_set_order_language($order);
$item = new WC_Order_Item_Product();
$item->set_props(array('product'=>wc_get_product($variation_id), 'quantity'=>1, 'name'=>wc_get_product($variation_id)->get_name(), 'subtotal'=>'10', 'total'=>'10'));
$item->add_meta_data($size_key, '[:en]Small[:de]Klein[:]');
qtrad_wc_order_line_item($item, '', array(), $order);
$order->add_item($item);
$order->set_billing_email('kunde@example.com'); $order->set_billing_first_name('Anna');
$order->set_payment_method($gateways['cod']);
$order->set_payment_method_title('[:en]Cash on delivery[:de]Nachnahme[:]');
$order->calculate_totals();
$order->save();
wc_check('order stores the language in _user_language', 'de', wc_get_order($order->get_id())->get_meta('_user_language'));
wc_check('custom attribute value of an order item is stored in the customer language', 'Klein', $item->get_meta($size_key));
qtrad_set_language('en');
wc_check('thank-you address opens in the order language', true, strpos(wc_get_order($order->get_id())->get_checkout_order_received_url(), 'http://127.0.0.1:8931/de/') === 0);
unset($GLOBALS['qtrad_translates_values']);
$mails = array();
WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
$customer_mail = null; $shop_mail = null;
foreach ($mails as $mail) { if ($mail['to'] === 'kunde@example.com') $customer_mail = $mail; else $shop_mail = $mail; }
wc_check('customer email is sent', true, (bool) $customer_mail);
wc_check('customer email uses the order language for markers and the locale', array(true, true, false, 'de_DE'), $customer_mail ? array(strpos($customer_mail['body'], 'Nachnahme') !== false, strpos($customer_mail['body'], 'Danke') !== false || strpos($customer_mail['body'], 'Rot') !== false, strpos($customer_mail['body'], '[:') !== false, $customer_mail['locale']) : null);
wc_check('shop email uses the default language', $shop_mail ? array(false, true, false) : 'no shop email', $shop_mail ? array(strpos($shop_mail['body'], 'Nachnahme') !== false, strpos($shop_mail['body'], 'Cash on delivery') !== false, strpos($shop_mail['body'], '[:') !== false) : 'no shop email');
wc_check('language and locale are restored after sending', array('en', 'en_US'), array(qtrad_current_language(), determine_locale()));
$GLOBALS['qtrad_translates_values'] = false;
wc_check('wp-admin keeps stored values', '[:en]Red wine[:de]Rotwein[:es]Vino tinto[:]', wc_get_product($simple_id)->get_name());
unset($GLOBALS['qtrad_translates_values']);
update_option('qtrad_settings', array('write_format'=>'keep', 'extra_fields'=>'', 'translate_meta'=>false, 'translate_options'=>'none'));
echo json_encode(array('environment'=>array('wordpress'=>get_bloginfo('version'), 'php'=>PHP_VERSION, 'woocommerce'=>WC()->version), 'passed'=>count(array_filter($cases, function ($c) { return $c['pass']; })), 'total'=>count($cases), 'cases'=>$cases, 'fixtures'=>array('simple'=>get_post_field('post_name', $simple_id), 'variable'=>get_post_field('post_name', $variable_id), 'size_key'=>$size_key)), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
exit(count(array_filter($cases, function ($c) { return !$c['pass']; })) ? 1 : 0);
