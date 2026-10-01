<?php
$root=isset($argv[1])?$argv[1]:'';
if(!is_file($root.'/.qtrad-disposable'))exit(2);
define('WP_CLI',true); define('WP_INSTALLING_NETWORK',true);
$_SERVER['HTTP_HOST']='127.0.0.1:8931';$_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
global $wpdb;
foreach($wpdb->tables('ms_global') as $name=>$table){$wpdb->$name=$table;}
install_network();
$result=populate_network(1,'qtrad.example.test','qtrad@example.invalid','qtrad disposable network','/',false);
if(is_wp_error($result)){fwrite(STDERR,$result->get_error_message());exit(1);}
$config=file_get_contents($root.'/wp-config.php');
$constants="define('MULTISITE',true);define('SUBDOMAIN_INSTALL',false);define('DOMAIN_CURRENT_SITE','qtrad.example.test');define('PATH_CURRENT_SITE','/');define('SITE_ID_CURRENT_SITE',1);define('BLOG_ID_CURRENT_SITE',1);\n";
$config=str_replace("\$table_prefix = 'qtrad_';",$constants."\$table_prefix = 'qtrad_';",$config);
file_put_contents($root.'/wp-config.php',$config);
echo "Converted disposable site to multisite.\n";
