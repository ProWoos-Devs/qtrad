<?php
$root=isset($argv[1])?$argv[1]:'';
if(!is_file($root.'/.qtn-disposable'))exit(2);
define('WP_CLI',true);
$_SERVER['HTTP_HOST']='qtn.example.test';$_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
if(!is_multisite()){fwrite(STDERR,'Expected a multisite fixture');exit(2);}
$cases=array();
function network_check($name,$expected,$actual){global $cases;$cases[]=array('case'=>$name,'pass'=>$expected===$actual,'expected'=>$expected,'actual'=>$actual);}
update_site_option('active_sitewide_plugins',array('qtrad/qtrad.php'=>time()));
update_option('qtranslate_enabled_languages',array('en','de'));
update_option('qtranslate_default_language','en');qtu_reset_config();qtu_set_language('de');
$site=wp_insert_site(array('domain'=>'qtn.example.test','path'=>'/child/','public'=>1));
if(is_wp_error($site)){fwrite(STDERR,$site->get_error_message());exit(2);}
switch_to_blog($site);
update_option('qtranslate_enabled_languages',array('en','fr'));
update_option('qtranslate_default_language','fr');
update_option('qtranslate_url_mode',2); update_option('qtranslate_hide_default_language','1');
qtu_reset_config();
network_check('Child site languages are separate',array('en','fr'),qtu_enabled_languages());
network_check('Child site default language is separate','fr',qtu_current_language());
$home=get_option('home');
network_check('Child URLs use child origin/path',$home.'/en/page/',qtu_convert_url($home.'/page/','en',true));
restore_current_blog();
network_check('Restore keeps caller selected language','de',qtu_current_language());
network_check('Restore keeps caller enabled languages',array('en','de'),qtu_enabled_languages());
switch_to_blog($site);
network_check('Repeated switch reloads child settings','fr',qtu_current_language());
switch_to_blog(1);qtu_set_language('en');restore_current_blog();
network_check('Nested restore keeps child context','fr',qtu_current_language());
restore_current_blog();
network_check('Nested restore keeps original context','de',qtu_current_language());
$old=get_site_option('active_sitewide_plugins');
update_site_option('active_sitewide_plugins',array_merge($old,array('qtranslate-x/qtranslate.php'=>time())));
network_check('Network predecessor conflict detected','qtranslate-x/qtranslate.php',qtu_conflicting_plugin());
update_site_option('active_sitewide_plugins',$old);
network_check('Network qTrad alone has no conflict','',qtu_conflicting_plugin());
echo json_encode(array('environment'=>array('wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION),'passed'=>count(array_filter($cases,function($c){return $c['pass'];})),'total'=>count($cases),'cases'=>$cases),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit(count(array_filter($cases,function($c){return !$c['pass'];}))?1:0);
