<?php
// Destructive fixtures: use only the matrix runner's disposable WordPress site.
if (empty($argv[1]) || !is_file($argv[1] . '/.qtrad-disposable')) { fwrite(STDERR, "Supply a matrix fixture.\n"); exit(2); }
define('WP_CLI', true);
define('WP_PLUGIN_DIR', __DIR__ . '/unloaded-plugins');
$_SERVER['HTTP_HOST'] = '127.0.0.1:8931'; $_SERVER['REQUEST_URI'] = '/';
require $argv[1] . '/wp-load.php';
require dirname(__DIR__) . '/qtrad/qtrad.php';
qtrad_register_front_filters(); qtrad_register_seo_hooks(); qtrad_register_field_hooks();
wp_set_current_user(1);
$cases = array();
function seo_check($name, $expected, $actual) {
    global $cases; $cases[] = array('case'=>$name,'pass'=>$expected === $actual,'expected'=>$expected,'actual'=>$actual);
}
foreach (array('home'=>'http://127.0.0.1:8931','siteurl'=>'http://127.0.0.1:8931','blog_public'=>'1',
    'permalink_structure'=>'/%postname%/','show_on_front'=>'posts','qtranslate_enabled_languages'=>array('en','de','es'),
    'qtranslate_default_language'=>'en','qtranslate_url_mode'=>2,'qtranslate_hide_default_language'=>'1',
    'qtranslate_hide_untranslated'=>'1','qtranslate_detect_browser_language'=>'0','qtranslate_show_displayed_language_prefix'=>'0',
    'qtranslate_locales'=>array(),'qtranslate_flags'=>array(),'active_plugins'=>array('qtrad/qtrad.php')) as $key=>$value) update_option($key,$value);
qtrad_reset_config();
$posts = array();
$definitions = array(
    'translated'=>array('post_title'=>'[:en]English title[:de]Deutscher Titel[:es]Título español[:]', 'post_content'=>'[:en]<p>English body</p>[:de]<p>Deutscher Inhalt</p>[:es]<p>Contenido español</p>[:]', 'post_excerpt'=>'[:en]English description[:de]Deutsche Beschreibung[:es]Descripción española[:]'),
    'missing'=>array('post_title'=>'[:en]English only[:de][:es][:]', 'post_content'=>'[:en]English only body[:de]  [:es][:]'),
    'german'=>array('post_title'=>'[:en][:de]Nur Deutsch[:es][:]', 'post_content'=>'[:de]Nur deutscher Inhalt[:]'),
    'zero'=>array('post_title'=>'[:en]Zero[:de]Null[:]', 'post_content'=>'[:en]Value[:de]0[:]'),
    'empty'=>array('post_title'=>'[:en][:de][:es][:]', 'post_content'=>'[:en][:de][:es][:]'),
    'disabled'=>array('post_title'=>'[:fr]Français[:]', 'post_content'=>'[:fr]Seulement français[:]'),
    'draft'=>array('post_status'=>'draft'), 'private'=>array('post_status'=>'private'),
    'password'=>array('post_password'=>'fixture-only'), 'book'=>array('post_type'=>'qtrad_book'),
);
foreach ($definitions as $slug=>$fields) {
    $id = wp_insert_post(wp_slash(array_merge(array('post_type'=>'post','post_status'=>'publish','post_name'=>'qtrad-seo-'.$slug,'post_title'=>'[:en]English title[:de]Deutscher Titel[:es]Título español[:]', 'post_content'=>'[:en]English body[:de]Deutscher Inhalt[:es]Contenido español[:]'),$fields)));
    if (is_wp_error($id) || !$id) { fwrite(STDERR, 'Fixture creation failed'); exit(2); }
    $posts[$slug] = array('id'=>$id,'slug'=>get_post($id)->post_name,'url'=>qtrad_seo_post_url(get_post($id),'en'));
}
$id = $posts['translated']['id'];
foreach (array('_yoast_wpseo_title'=>'[:en]English SEO title[:de]Deutscher SEO Titel[:es]Título SEO español[:]',
    '_yoast_wpseo_metadesc'=>'[:en]English SEO description[:de]Deutsche SEO Beschreibung[:es]Descripción SEO española[:]',
    'rank_math_title'=>'[:en]English SEO title[:de]Deutscher SEO Titel[:es]Título SEO español[:]',
    'rank_math_description'=>'[:en]English SEO description[:de]Deutsche SEO Beschreibung[:es]Descripción SEO española[:]') as $key=>$value) update_post_meta($id,$key,$value);
$term = wp_insert_term('SEO category','category',array('slug'=>'qtrad-seo-category'));
if (is_wp_error($term)) { $term_id = (int) $term->get_error_data('term_exists'); } else { $term_id = $term['term_id']; }
wp_set_post_terms($id,array($term_id),'category');
seo_check('Legacy Brazilian code has valid hreflang','pt-BR',qtrad_seo_language_tag('pb'));
seo_check('Legacy Taiwanese code has valid hreflang','zh-TW',qtrad_seo_language_tag('tw'));
seo_check('Legacy Ukrainian code has valid hreflang','uk',qtrad_seo_language_tag('ua'));
update_option('qtranslate_locales',array('en'=>'en_US_formal','xx'=>'xxx_BAD','zz'=>'en_GB')); qtrad_reset_config();
seo_check('WordPress locale suffix is omitted','en-US',qtrad_seo_language_tag('en'));
seo_check('Unknown ISO language is omitted','',qtrad_seo_language_tag('xx'));
seo_check('Custom storage code uses real locale','en-GB',qtrad_seo_language_tag('zz'));
update_option('qtranslate_locales',array()); qtrad_reset_config();
qtrad_set_language('de');
seo_check('Stable English URL from German context',$posts['translated']['url'],qtrad_seo_post_url(get_post($id),'en'));
seo_check('Canonical uses requested language','http://127.0.0.1:8931/de/'.$posts['translated']['slug'].'/',qtrad_seo_canonical($posts['translated']['url']));
seo_check('External canonical is respected','https://external.example/canonical',qtrad_seo_canonical('https://external.example/canonical'));
seo_check('Canonical retains unrelated query bytes','http://127.0.0.1:8931/de/path?a=1&a=2&x=a%20b',qtrad_seo_canonical('http://127.0.0.1:8931/path?a=1&a=2&x=a%20b'));
seo_check('SEO selection does not show fallback','',qtrad_seo_text('[:en]English only[:]'));
seo_check('Disabled translation is not an alternate',array('en','de','es'),qtrad_seo_post_languages(get_post($id)));
seo_check('Empty translation is not an alternate',array('en'),qtrad_seo_post_languages(get_post($posts['missing']['id'])));
seo_check('Zero translation remains indexable',array('en','de'),qtrad_seo_post_languages(get_post($posts['zero']['id'])));
foreach (array('draft','private','password','empty','disabled') as $slug) seo_check($slug.' omitted',array(),qtrad_seo_post_languages(get_post($posts[$slug]['id'])));
$GLOBALS['wp_query'] = new WP_Query(array('p'=>$posts['missing']['id'])); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
seo_check('Missing translation adds noindex',array('max-image-preview'=>'large','noindex'=>true),qtrad_seo_core_robots(array('index'=>true,'max-image-preview'=>'large')));
seo_check('Vendor string robots retains directives','follow, max-image-preview:large, noindex',qtrad_seo_vendor_robots('index, follow, max-image-preview:large'));
seo_check('Vendor array robots retains directives',array('follow'=>'follow','index'=>'noindex'),qtrad_seo_vendor_robots(array('index'=>'index','follow'=>'follow')));
$GLOBALS['wp_query'] = new WP_Query(array('p'=>$id)); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
seo_check('Description selects current language','Deutsche Beschreibung',qtrad_seo_description());
ob_start(); qtrad_head_links(); $head = ob_get_clean();
seo_check('Alternates include current language',true,strpos($head,'hreflang="de-DE"') !== false);
seo_check('Alternates include canonical default',true,strpos($head,'hreflang="en-US" href="'.$posts['translated']['url'].'"') !== false);
$before = qtrad_raw_post_meta($id,'_yoast_wpseo_title');
seo_check('Yoast title adapter selects raw translation','Deutscher SEO Titel',apply_filters('wpseo_title','Cached English title'));
seo_check('Rank Math description adapter selects raw translation','Deutsche SEO Beschreibung',apply_filters('rank_math/frontend/description','Cached English description'));
seo_check('SEO reads leave stored metadata intact',$before,qtrad_raw_post_meta($id,'_yoast_wpseo_title'));
// Language SEO fields use their own nonce and retain disabled translations.
$seo_id=$posts['book']['id'];
update_post_meta($seo_id,'_qtrad_seo_title','[:en]Original[:de]Alt[:fr]Ancien[:]');
$_POST=wp_slash(array('qtrad_seo_post_id'=>$seo_id,'qtrad_seo_nonce'=>wp_create_nonce('qtrad_seo_'.$seo_id),
    'qtrad_seo_fields'=>array('title'=>array('en'=>'qTrad English title','de'=>'qTrad German title','es'=>''),'description'=>array('en'=>'qTrad English description','de'=>'qTrad German description','es'=>''))));
qtrad_seo_save_meta_box($seo_id);
seo_check('SEO field retains disabled French','Ancien',qtrad_split(qtrad_seo_raw_meta($seo_id,'_qtrad_seo_title'))['fr']);
seo_check('SEO field selects requested translation','qTrad German title',qtrad_seo_override($seo_id,'title','de'));
$saved=qtrad_seo_raw_meta($seo_id,'_qtrad_seo_title');
$_POST['qtrad_seo_nonce']='invalid'; $_POST['qtrad_seo_fields']['title']['de']='Wrong'; qtrad_seo_save_meta_box($seo_id);
seo_check('SEO field rejects invalid nonce',$saved,qtrad_seo_raw_meta($seo_id,'_qtrad_seo_title'));
$_POST['qtrad_seo_nonce']=wp_create_nonce('qtrad_seo_'.$seo_id); $_POST['qtrad_seo_post_id']=$id; qtrad_seo_save_meta_box($seo_id);
seo_check('SEO field rejects mismatched identity',$saved,qtrad_seo_raw_meta($seo_id,'_qtrad_seo_title'));
$_POST=array();
$schema = qtrad_seo_schema(array('name'=>'[:en]English[:de]Deutsch[:]','url'=>$posts['translated']['url'],'inLanguage'=>'en-US','nested'=>array('@id'=>$posts['translated']['url'].'#webpage')));
seo_check('Schema text translated','Deutsch',$schema['name']); seo_check('Schema locale translated','de-DE',$schema['inLanguage']);
seo_check('Schema identifiers translated','http://127.0.0.1:8931/de/'.$posts['translated']['slug'].'/#webpage',$schema['nested']['@id']);
update_option('qtranslate_flags',array('en'=>'gb.png','ar'=>'arle.png','gl'=>'galego.png','ca'=>'catalonia.png')); qtrad_reset_config();
foreach (array('en'=>'gb.svg','ar'=>'arab.svg','gl'=>'es-ga.svg','ca'=>'es-ct.svg') as $code=>$file) seo_check('Legacy flag alias '.$code,true,substr(qtrad_flag_url($code),-strlen($file)) === $file);
update_option('qtranslate_flags',array()); qtrad_reset_config();
seo_check('Vendor sitemap path is neutral',true,qtrad_is_neutral_path('/post-sitemap2.xml'));
$_GET['sitemap']='posts'; seo_check('Plain sitemap query is neutral',true,qtrad_is_sitemap_request()); unset($_GET['sitemap']);
// Tiny page sizes exercise count consistency, expansion and first-page homepage handling.
add_filter('wp_sitemaps_max_urls',function(){return 6;},1);
$GLOBALS['wp_sitemaps'] = new WP_Sitemaps(); $GLOBALS['wp_sitemaps']->register_sitemaps();
$provider = $GLOBALS['wp_sitemaps']->registry->get_provider('posts');
seo_check('Core provider wrapped',true,$provider instanceof Qtrad_Sitemap_Provider);
$urls = array(); $page_sizes = array();
foreach (array('post','page','qtrad_book') as $type) {
    for ($page=1;$page<=$provider->get_max_num_pages($type);$page++) {
        $entries=$provider->get_url_list($page,$type); $page_sizes[]=count($entries);
        foreach ($entries as $entry) { $urls[]=$entry['loc']; seo_check('Internal sitemap data removed '.$type.'/'.$page,false,isset($entry['_qtrad_languages'])); }
    }
}
seo_check('Expanded pages respect configured six-URL bound',true,max($page_sizes)<=6);
foreach (array('en','de','es') as $language) seo_check('Sitemap contains translated '.$language,true,in_array(qtrad_seo_post_url(get_post($id),$language),$urls,true));
seo_check('Sitemap includes German-only post',true,in_array(qtrad_seo_post_url(get_post($posts['german']['id']),'de'),$urls,true));
seo_check('Sitemap excludes missing German',false,in_array(qtrad_seo_post_url(get_post($posts['missing']['id']),'de'),$urls,true));
foreach (array('draft','private','password','empty','disabled') as $slug) seo_check('Sitemap excludes '.$slug,false,in_array($posts[$slug]['url'],$urls,true));
seo_check('Paginated sitemap has no duplicates',count($urls),count(array_unique($urls)));
seo_check('XML expansion retains extensions',true,strpos(qtrad_seo_sitemap_xml('<url><loc>old</loc><lastmod>2026-10-01</lastmod><image:image><image:loc>https://example.test/image.jpg</image:loc></image:image></url>',qtrad_seo_sitemap_entry(array('loc'=>$posts['translated']['url']),'post',get_post($id))),'<lastmod>2026-10-01</lastmod>')!==false);
remove_all_filters('wp_sitemaps_max_urls'); add_filter('wp_sitemaps_max_urls','qtrad_seo_sitemap_limit',99);
qtrad_set_language('en'); flush_rewrite_rules(false);
echo json_encode(array('environment'=>array('wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION),'passed'=>count(array_filter($cases,function($c){return $c['pass'];})),'total'=>count($cases),'cases'=>$cases,'fixtures'=>$posts),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($cases,function($c){return !$c['pass'];}))?1:0);
