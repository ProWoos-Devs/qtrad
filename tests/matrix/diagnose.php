<?php
$root=isset($argv[1])?$argv[1]:'';
if(!is_file($root.'/.qtn-disposable'))exit(2);
define('WP_CLI',false);
$_SERVER['HTTP_HOST']='127.0.0.1:8931';$_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
global $wp_rewrite;
if(in_array('--refresh',$argv,true))flush_rewrite_rules(false);
$rules=$wp_rewrite->wp_rewrite_rules();
echo json_encode(array('active'=>get_option('active_plugins'),'seo_owner'=>qtu_seo_owner(),
    'yoast_xml'=>class_exists('WPSEO_Options')?WPSEO_Options::get('enable_xml_sitemap'):null,
    'yoast_sitemaps'=>isset($GLOBALS['wpseo_sitemaps']),
    'sitemap_rewrites'=>array_filter((array)$rules,function($key){return strpos($key,'sitemap')!==false;},ARRAY_FILTER_USE_KEY)),JSON_PRETTY_PRINT)."\n";
