<?php
// Enable exactly one real SEO plugin on the disposable matrix fixture.
$root = isset($argv[1]) ? $argv[1] : '';
$owner = isset($argv[2]) ? $argv[2] : '';
if (!is_file($root . '/.qtrad-disposable') || !in_array($owner,array('core','yoast','rankmath','acf','woocommerce'),true)) { exit(2); }
define('WP_CLI',false);
$_SERVER['HTTP_HOST']='127.0.0.1:8931'; $_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
// No other plugins exist in this freshly created fixture.
foreach ((array)get_option('active_plugins') as $plugin) {
    if ($plugin !== 'qtrad/qtrad.php') deactivate_plugins($plugin,true);
}
if ($owner === 'yoast') {
    $result=activate_plugin('wordpress-seo/wp-seo.php','',false,false);
    if (is_wp_error($result)) { fwrite(STDERR,$result->get_error_message()); exit(1); }
    $options=get_option('wpseo',array()); $options['first_time_install']=false; $options['enable_xml_sitemap']=true; update_option('wpseo',$options);
    $xml=get_option('wpseo_xml',array()); $xml['enablexmlsitemap']=true; update_option('wpseo_xml',$xml);
    $titles=get_option('wpseo_titles',array()); $titles['opengraph']=true; $titles['twitter']=true; update_option('wpseo_titles',$titles);
} elseif ($owner === 'woocommerce') {
    $result=activate_plugin('woocommerce/woocommerce.php','',false,false);
    if (is_wp_error($result)) { fwrite(STDERR,$result->get_error_message()); exit(1); }
    if (class_exists('WC_Install')) { WC_Install::install(); }
} elseif ($owner === 'acf') {
    $result=activate_plugin('advanced-custom-fields/acf.php','',false,false);
    if (is_wp_error($result)) { fwrite(STDERR,$result->get_error_message()); exit(1); }
} elseif ($owner === 'rankmath') {
    update_option('rank_math_registration_skip',true);
    update_option('rank_math_modules',array('sitemap','rich-snippet','link-counter'));
    update_option('rank-math-options-sitemap',array('items_per_page'=>200,'pt_post_sitemap'=>true,'pt_page_sitemap'=>true,'pt_qtrad_book_sitemap'=>true,'tax_category_sitemap'=>true));
    $result=activate_plugin('seo-by-rank-math/rank-math.php','',false,false);
    if (is_wp_error($result)) { fwrite(STDERR,$result->get_error_message()); exit(1); }
}
// Vendor rewrite hooks take effect on the next complete WordPress bootstrap.
delete_option('rewrite_rules');
echo json_encode(array('owner'=>$owner,'active'=>get_option('active_plugins')))."\n";
