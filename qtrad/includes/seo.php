<?php
/** Shared language/URL rules for core, Yoast and Rank Math SEO output. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qtu_is_sitemap_request() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$path = qtu_strip_path_language( qtu_relative_path( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) );
	return isset( $_GET['sitemap'] ) || ( isset( $_GET['stylesheet'] ) && $_GET['stylesheet'] === 'sitemap' )
		|| (bool) preg_match( '#^/[a-z0-9_-]*sitemap[a-z0-9_-]*\.(?:xml|xsl)$#iD', $path );
}

function qtu_seo_owner() {
	if ( defined( 'WPSEO_VERSION' ) ) { return 'yoast'; }
	if ( defined( 'RANK_MATH_VERSION' ) ) { return 'rankmath'; }
	return '';
}

/** Strip WordPress locale suffixes and map legacy storage codes to SEO codes. */
function qtu_seo_language_tag( $language ) {
	$locales = qtu_config( 'locale' );
	$parts = preg_split( '/[_-]/', isset( $locales[ $language ] ) ? $locales[ $language ] : $language );
	$primary = strtolower( $parts[0] );
	$aliases = array( 'ua' => 'uk', 'iw' => 'he', 'in' => 'id', 'ji' => 'yi' );
	if ( isset( $aliases[ $primary ] ) ) { $primary = $aliases[ $primary ]; }
	$iso_languages = explode( ' ', 'aa ab ae af ak am an ar as av ay az ba be bg bh bi bm bn bo br bs ca ce ch co cr cs cu cv cy da de dv dz ee el en eo es et eu fa ff fi fj fo fr fy ga gd gl gn gu gv ha he hi ho hr ht hu hy hz ia id ie ig ii ik io is it iu ja jv ka kg ki kj kk kl km kn ko kr ks ku kv kw ky la lb lg li ln lo lt lu lv mg mh mi mk ml mn mr ms mt my na nb nd ne ng nl nn no nr nv ny oc oj om or os pa pi pl ps pt qu rm rn ro ru rw sa sc sd se sg si sk sl sm sn so sq sr ss st su sv sw ta te tg th ti tk tl tn to tr ts tt tw ty ug uk ur uz ve vi vo wa wo xh yi yo za zh zu' );
	if ( ! in_array( $primary, $iso_languages, true ) ) { return ''; }
	$tag = $primary;
	$position = 1;
	if ( isset( $parts[1] ) && in_array( ucfirst( strtolower( $parts[1] ) ), array( 'Latn', 'Cyrl', 'Hans', 'Hant', 'Arab', 'Deva', 'Hebr', 'Grek' ), true ) ) {
		$tag .= '-' . ucfirst( strtolower( $parts[1] ) );
		$position = 2;
	}
	$regions = explode( ' ', 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW' );
	if ( isset( $parts[ $position ] ) && in_array( strtoupper( $parts[ $position ] ), $regions, true ) ) { $tag .= '-' . strtoupper( $parts[ $position ] ); }
	return apply_filters( 'qtranslate_next_seo_language_tag', $tag, $language );
}

function qtu_seo_text( $value ) {
	return qtu_use_language( $value, qtu_current_language(), false, true );
}

/** Get a stable permalink without inheriting the current request language. */
function qtu_seo_post_url( $post, $language ) {
	$previous = ! empty( $GLOBALS['qtn_seo_neutral_url'] );
	$GLOBALS['qtn_seo_neutral_url'] = true;
	try { $url = get_permalink( $post ); }
	finally { $GLOBALS['qtn_seo_neutral_url'] = $previous; }
	return qtu_convert_url( $url, $language, true, false );
}

function qtu_seo_raw_meta( $post_id, $key ) {
	$values = qtu_raw_post_meta( $post_id, $key );
	return $values ? $values[0] : '';
}

function qtu_seo_post_indexable( $post, $language ) {
	if ( ! $post || $post->post_status !== 'publish' || $post->post_password !== '' || ! get_option( 'blog_public' ) ) { return false; }
	$owner = qtu_seo_owner();
	if ( $owner === 'yoast' && (string) qtu_seo_raw_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex' ) === '1' ) { return false; }
	if ( $owner === 'rankmath' && in_array( 'noindex', (array) qtu_seo_raw_meta( $post->ID, 'rank_math_robots' ), true ) ) { return false; }
	$key = $owner === 'yoast' ? '_yoast_wpseo_canonical' : ( $owner === 'rankmath' ? 'rank_math_canonical_url' : '' );
	if ( $key ) {
		$custom = qtu_use_language( qtu_seo_raw_meta( $post->ID, $key ), $language, false, true );
		if ( is_string( $custom ) && $custom !== '' && qtu_convert_url( $custom, $language, true, false ) !== qtu_seo_post_url( $post, $language ) ) { return false; }
	}
	return (bool) apply_filters( 'qtranslate_next_seo_post_indexable', true, $post, $language );
}

function qtu_seo_post_languages( $post ) {
	return array_values( array_filter( qtu_post_available_languages( $post ), function ( $language ) use ( $post ) {
		return qtu_seo_language_tag( $language ) !== '' && qtu_seo_post_indexable( $post, $language );
	} ) );
}

function qtu_seo_missing_translation() {
	return is_singular() && ! in_array( qtu_current_language(), qtu_post_available_languages( get_post( get_queried_object_id() ) ), true );
}

function qtu_seo_canonical( $url ) {
	return is_string( $url ) && $url !== '' ? qtu_convert_url( qtu_seo_text( $url ), qtu_current_language(), true, false ) : $url;
}

/** Canonical archive URLs omit request tracking arguments and retain pagination. */
function qtu_seo_current_url( $language ) {
	if ( is_singular() ) {
		$url = wp_get_canonical_url( get_queried_object_id() );
		return $url ? qtu_convert_url( $url, $language, true, false ) : qtu_seo_post_url( get_post( get_queried_object_id() ), $language );
	}
	if ( is_category() || is_tag() || is_tax() ) { $url = get_term_link( get_queried_object() ); }
	elseif ( is_author() ) { $url = get_author_posts_url( get_queried_object_id() ); }
	elseif ( is_post_type_archive() ) { $url = get_post_type_archive_link( get_queried_object()->name ); }
	elseif ( is_home() && get_option( 'page_for_posts' ) ) { $url = qtu_seo_post_url( get_post( get_option( 'page_for_posts' ) ), $language ); }
	elseif ( is_home() || is_front_page() ) { $url = trailingslashit( get_option( 'home' ) ); }
	else { return qtu_convert_url( '', $language, true, false ); }
	if ( is_wp_error( $url ) || ! is_string( $url ) || $url === '' ) { return ''; }
	$page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	if ( $page > 1 ) {
		global $wp_rewrite;
		$url = $wp_rewrite->using_permalinks() ? trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $page, 'paged' ) : add_query_arg( 'paged', $page, $url );
	}
	return qtu_convert_url( $url, $language, true, false );
}

function qtu_seo_core_robots( $robots ) {
	if ( qtu_seo_missing_translation() ) { unset( $robots['index'] ); $robots['noindex'] = true; }
	return $robots;
}

function qtu_seo_vendor_robots( $robots ) {
	if ( ! qtu_seo_missing_translation() ) { return $robots; }
	if ( is_array( $robots ) ) {
		foreach ( $robots as $key => $value ) { if ( $value === 'index' || $key === 'index' ) { unset( $robots[ $key ] ); } }
		$robots['index'] = 'noindex';
		return $robots;
	}
	$directives = preg_split( '/\s*,\s*/', (string) $robots );
	$directives = array_diff( $directives, array( 'index', 'noindex', '' ) );
	$directives[] = 'noindex';
	return implode( ', ', $directives );
}

function qtu_seo_head_links() {
	if ( is_admin() || is_feed() || is_404() || is_search() || qtu_is_rest_request() || qtu_is_sitemap_request() || ! get_option( 'blog_public' ) || qtu_seo_missing_translation() ) { return; }
	$post = is_singular() ? get_post( get_queried_object_id() ) : null;
	$languages = $post ? qtu_seo_post_languages( $post ) : qtu_enabled_languages();
	$tags = array();
	foreach ( $languages as $language ) {
		$tag = qtu_seo_language_tag( $language );
		if ( $tag === '' || isset( $tags[ $tag ] ) ) { continue; }
		$tags[ $tag ] = true;
		$url = qtu_seo_current_url( $language );
		echo '<link rel="alternate" hreflang="' . esc_attr( $tag ) . '" href="' . esc_url( $url ) . '" />' . "\n";
	}
	$default = qtu_default_language();
	if ( in_array( $default, $languages, true ) && qtu_seo_language_tag( $default ) !== '' ) {
		$url = qtu_seo_current_url( $default );
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '" />' . "\n";
	}
}

function qtu_seo_description() {
	$post = is_singular() ? get_post( get_queried_object_id() ) : null;
	$override = $post ? qtu_seo_override( $post->ID, 'description', qtu_current_language() ) : '';
	if ( $override !== '' ) { return $override; }
	$text = $post ? qtu_seo_text( $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content ) : get_option( 'blogdescription' );
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ), true ), 32, '…' );
}

function qtu_seo_og_locale( $unused = '' ) {
	$tag = qtu_seo_language_tag( qtu_current_language() );
	if ( preg_match( '/^([a-z]{2})(?:-[A-Za-z]{4})?-([A-Z]{2})$/D', $tag, $match ) ) { return $match[1] . '_' . $match[2]; }
	return preg_match( '/^[a-z]{2}_[A-Z]{2}$/D', $unused ) && substr( $unused, 0, 2 ) === $tag ? $unused : '';
}

/** Native fallback output; SEO plugins remain responsible for their own tags. */
function qtu_seo_native_head() {
	if ( qtu_seo_owner() || is_admin() || is_feed() || is_404() || is_search() || qtu_is_rest_request() || qtu_is_sitemap_request() ) { return; }
	$url = qtu_seo_current_url( qtu_current_language() );
	$title = wp_get_document_title();
	$description = qtu_seo_description();
	if ( ! is_singular() ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n"; }
	foreach ( array( 'description' => $description, 'twitter:card' => 'summary', 'twitter:title' => $title, 'twitter:description' => $description ) as $name => $value ) {
		if ( $value !== '' ) { echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />' . "\n"; }
	}
	foreach ( array( 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:locale' => qtu_seo_og_locale(), 'og:type' => 'website' ) as $name => $value ) {
		if ( $value !== '' ) { echo '<meta property="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />' . "\n"; }
	}
	$schema = apply_filters( 'qtranslate_next_seo_schema', array( '@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $description, 'inLanguage' => qtu_seo_language_tag( qtu_current_language() ) ) );
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}

/** Read multilingual SEO overrides from raw storage, independent of indexable caches. */
function qtu_seo_vendor_text( $value ) {
	$keys = array(
		'wpseo_title' => '_yoast_wpseo_title', 'wpseo_metadesc' => '_yoast_wpseo_metadesc',
		'wpseo_opengraph_title' => '_yoast_wpseo_opengraph-title', 'wpseo_opengraph_desc' => '_yoast_wpseo_opengraph-description',
		'wpseo_twitter_title' => '_yoast_wpseo_twitter-title', 'wpseo_twitter_description' => '_yoast_wpseo_twitter-description',
		'rank_math/frontend/title' => 'rank_math_title', 'rank_math/frontend/description' => 'rank_math_description',
		'rank_math/opengraph/facebook/og_title' => 'rank_math_facebook_title', 'rank_math/opengraph/facebook/og_description' => 'rank_math_facebook_description',
		'rank_math/opengraph/twitter/twitter_title' => 'rank_math_twitter_title', 'rank_math/opengraph/twitter/twitter_description' => 'rank_math_twitter_description',
	);
	$hook = current_filter();
	$kind = strpos( $hook, 'title' ) !== false ? 'title' : ( strpos( $hook, 'desc' ) !== false ? 'description' : '' );
	$override = is_singular() && $kind ? qtu_seo_override( get_queried_object_id(), $kind, qtu_current_language() ) : '';
	if ( $override !== '' ) { return $override; }
	if ( is_singular() && isset( $keys[ $hook ] ) ) {
		$raw = qtu_seo_raw_meta( get_queried_object_id(), $keys[ $hook ] );
		if ( is_string( $raw ) && qtu_has_lang_tags( $raw ) ) {
			$value = qtu_seo_text( $raw );
			if ( strpos( $hook, 'wpseo_' ) === 0 && function_exists( 'wpseo_replace_vars' ) ) { $value = wpseo_replace_vars( $value, get_queried_object() ); }
			elseif ( class_exists( 'RankMath\\Helper' ) ) { $value = \RankMath\Helper::replace_vars( $value ); }
		}
	}
	return qtu_seo_text( $value );
}

function qtu_seo_yoast_replacements( $replacements ) {
	if ( is_singular() ) {
		$post = get_post( get_queried_object_id() );
		if ( $post ) { $replacements['%%title%%'] = qtu_seo_text( $post->post_title ); }
	}
	$replacements['%%sitename%%'] = qtu_seo_text( get_option( 'blogname' ) );
	$replacements['%%sitedesc%%'] = qtu_seo_text( get_option( 'blogdescription' ) );
	return $replacements;
}

/** Translate text and site-local identifiers throughout the vendor's existing graph. */
function qtu_seo_schema( $data ) {
	if ( ! is_array( $data ) ) { return qtu_seo_text( $data ); }
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) { $data[ $key ] = qtu_seo_schema( $value ); }
		elseif ( is_string( $value ) ) {
			$data[ $key ] = qtu_seo_text( $value );
			if ( in_array( $key, array( 'url', '@id', 'item' ), true ) ) { $data[ $key ] = qtu_seo_canonical( $data[ $key ] ); }
			if ( $key === 'inLanguage' ) { $data[ $key ] = qtu_seo_language_tag( qtu_current_language() ); }
		}
	}
	$types = isset( $data['@type'] ) ? (array) $data['@type'] : array();
	if ( is_singular() && array_intersect( $types, array( 'WebPage', 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
		foreach ( array( 'name' => 'title', 'headline' => 'title', 'description' => 'description' ) as $key => $kind ) {
			$override = qtu_seo_override( get_queried_object_id(), $kind, qtu_current_language() );
			if ( isset( $data[ $key ] ) && $override !== '' ) { $data[ $key ] = $override; }
		}
	}
	return $data;
}

function qtu_seo_override( $post_id, $kind, $language ) {
	$value = qtu_seo_raw_meta( $post_id, '_qtn_seo_' . $kind );
	return is_string( $value ) ? wp_strip_all_tags( qtu_use_language( $value, $language, false, true ), true ) : '';
}

function qtu_seo_document_title( $parts ) {
	if ( ! qtu_seo_owner() && is_singular() ) {
		$title = qtu_seo_override( get_queried_object_id(), 'title', qtu_current_language() );
		if ( $title !== '' ) { $parts['title'] = $title; }
	}
	return $parts;
}

/** Explicit, labelled fields work in both editors without a JavaScript dependency. */
function qtu_seo_add_meta_box( $post_type, $post ) {
	$type = get_post_type_object( $post_type );
	if ( $type && $type->public && $post_type !== 'attachment' && current_user_can( 'edit_post', $post->ID ) ) {
		add_meta_box( 'qtn-seo', __( 'SEO translations', 'qtrad' ), 'qtu_seo_meta_box', $post_type, 'normal', 'default' );
	}
}

function qtu_seo_meta_box( $post ) {
	wp_nonce_field( 'qtn_seo_' . $post->ID, 'qtn_seo_nonce' );
	echo '<input type="hidden" name="qtn_seo_post_id" value="' . esc_attr( $post->ID ) . '" />';
	echo '<p>' . esc_html__( 'Optional titles and descriptions for search engines and social sharing. Leave blank to use existing SEO settings or automatically generated text.', 'qtrad' ) . '</p>';
	foreach ( qtu_enabled_languages() as $language ) {
		echo '<fieldset><legend><strong><bdi>' . esc_html( qtu_language_name( $language ) ) . '</bdi></strong></legend>';
		foreach ( array( 'title' => __( 'SEO title', 'qtrad' ), 'description' => __( 'SEO description', 'qtrad' ) ) as $kind => $label ) {
			$id = 'qtn-seo-' . $kind . '-' . $language;
			$value = qtu_seo_override( $post->ID, $kind, $language );
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br />';
			$attributes = ' id="' . esc_attr( $id ) . '" name="qtn_seo_fields[' . esc_attr( $kind ) . '][' . esc_attr( $language ) . ']" class="widefat" lang="' . esc_attr( str_replace( '_', '-', qtu_config( 'locale' )[ $language ] ) ) . '" dir="' . esc_attr( qtu_language_direction( $language ) ) . '"';
			if ( $kind === 'title' ) { echo '<input type="text"' . $attributes . ' value="' . esc_attr( $value ) . '" />'; }
			else { echo '<textarea rows="3"' . $attributes . '>' . esc_textarea( $value ) . '</textarea>'; }
			echo '</p>';
		}
		echo '</fieldset>';
	}
}

function qtu_seo_save_meta_box( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id )
		|| empty( $_POST['qtn_seo_post_id'] ) || (int) $_POST['qtn_seo_post_id'] !== (int) $post_id
		|| ! isset( $_POST['qtn_seo_nonce'] ) || ! is_string( $_POST['qtn_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtn_seo_nonce'] ) ), 'qtn_seo_' . $post_id )
		|| empty( $_POST['qtn_seo_fields'] ) || ! is_array( $_POST['qtn_seo_fields'] ) ) { return; }
	foreach ( array( 'title', 'description' ) as $kind ) {
		if ( ! isset( $_POST['qtn_seo_fields'][ $kind ] ) || ! is_array( $_POST['qtn_seo_fields'][ $kind ] ) ) { continue; }
		$key = '_qtn_seo_' . $kind;
		$raw = qtu_seo_raw_meta( $post_id, $key );
		$texts = qtu_split( is_string( $raw ) ? $raw : '', null, false );
		foreach ( qtu_enabled_languages() as $language ) {
			if ( isset( $_POST['qtn_seo_fields'][ $kind ][ $language ] ) && is_string( $_POST['qtn_seo_fields'][ $kind ][ $language ] ) ) {
				$value = wp_unslash( $_POST['qtn_seo_fields'][ $kind ][ $language ] );
				$texts[ $language ] = $kind === 'description' ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			}
		}
		update_post_meta( $post_id, $key, wp_slash( qtu_join( $texts, 'bracket', qtu_enabled_languages() ) ) );
	}
}

function qtu_seo_breadcrumbs( $links ) {
	foreach ( $links as &$link ) {
		if ( isset( $link['url'] ) ) { $link['url'] = qtu_seo_canonical( $link['url'] ); }
		if ( isset( $link['text'] ) ) { $link['text'] = qtu_seo_text( $link['text'] ); }
	}
	unset( $link );
	return $links;
}

/** Leave room for expanded entries and the homepage within the XML URL limit. */
function qtu_seo_sitemap_limit( $limit ) {
	return max( 1, (int) floor( min( 50000, max( 1, (int) $limit ) ) / max( 1, count( qtu_enabled_languages() ) ) ) - 1 );
}

function qtu_seo_rankmath_sitemap_options( $options ) {
	if ( is_array( $options ) ) { $options['items_per_page'] = qtu_seo_sitemap_limit( isset( $options['items_per_page'] ) ? $options['items_per_page'] : 200 ); }
	return $options;
}

function qtu_seo_sitemap_entry( $entry, $type, $object ) {
	if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) { return $entry; }
	$post = $type === 'post' && is_object( $object ) && isset( $object->ID ) ? get_post( (int) $object->ID ) : null;
	$entry['_qtn_languages'] = $post ? qtu_seo_post_languages( $post ) : qtu_enabled_languages();
	return $entry;
}

function qtu_seo_core_post_entry( $entry, $post ) { return qtu_seo_sitemap_entry( $entry, 'post', $post ); }

function qtu_seo_sitemap_query_args( $args ) {
	$args['has_password'] = false;
	$args['qtn_seo_sitemap'] = true;
	return $args;
}

function qtu_seo_sitemap_where( $where, $query ) {
	if ( ! $query->get( 'qtn_seo_sitemap' ) ) { return $where; }
	$conditions = array();
	foreach ( qtu_enabled_languages() as $language ) { $conditions[] = substr( qtu_available_language_where( $language ), 5 ); }
	return $where . ' AND (' . implode( ' OR ', $conditions ) . ')';
}

function qtu_seo_sitemap_urls( $entry ) {
	if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) { return array(); }
	$parts = qtu_parse_local_url( $entry['loc'] );
	if ( ! $parts || ! qtu_is_local_url( $parts ) ) { unset( $entry['_qtn_languages'] ); return array( $entry ); }
	$languages = isset( $entry['_qtn_languages'] ) ? $entry['_qtn_languages'] : qtu_enabled_languages();
	unset( $entry['_qtn_languages'] );
	$urls = array();
	foreach ( $languages as $language ) {
		if ( qtu_seo_language_tag( $language ) === '' ) { continue; }
		$variant = $entry;
		$variant['loc'] = qtu_convert_url( $entry['loc'], $language, true, false );
		$urls[ $variant['loc'] ] = $variant;
	}
	return array_values( $urls );
}

/** Expand a vendor-rendered URL without dropping its image/lastmod extensions. */
function qtu_seo_sitemap_xml( $xml, $entry ) {
	$output = '';
	foreach ( qtu_seo_sitemap_urls( $entry ) as $variant ) {
		$output .= preg_replace_callback( '~<loc>[^<]*</loc>~', function () use ( $variant ) {
			return '<loc>' . htmlspecialchars( $variant['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</loc>';
		}, $xml, 1 );
	}
	return $output;
}

/** Wrap core providers, retaining their exclusions, filters and paging calculations. */
function qtu_seo_core_provider( $provider, $name ) {
	if ( qtu_seo_owner() || ! $provider || ! in_array( $name, array( 'posts', 'taxonomies', 'users' ), true ) || $provider instanceof QTranslateNextSitemapProvider ) { return $provider; }
	return new QTranslateNextSitemapProvider( $provider, $name );
}

class QTranslateNextSitemapProvider extends WP_Sitemaps_Provider {
	private $provider;
	public function __construct( $provider, $name ) {
		$this->provider = $provider;
		$this->name = $name;
		$this->object_type = array( 'posts' => 'post', 'taxonomies' => 'term', 'users' => 'user' )[ $name ];
	}
	public function get_object_subtypes() { return $this->provider->get_object_subtypes(); }
	public function get_max_num_pages( $object_subtype = '' ) { return $this->provider->get_max_num_pages( $object_subtype ); }
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$previous = ! empty( $GLOBALS['qtn_seo_sitemap_build'] );
		$GLOBALS['qtn_seo_sitemap_build'] = true;
		try { $entries = $this->provider->get_url_list( $page_num, $object_subtype ); }
		finally { $GLOBALS['qtn_seo_sitemap_build'] = $previous; }
		$urls = array();
		foreach ( $entries as $entry ) { $urls = array_merge( $urls, qtu_seo_sitemap_urls( $entry ) ); }
		return $urls;
	}
}

function qtu_register_seo_hooks() {
	add_filter( 'get_canonical_url', 'qtu_seo_canonical', 99 );
	add_filter( 'document_title_parts', 'qtu_seo_document_title', 99 );
	add_action( 'add_meta_boxes', 'qtu_seo_add_meta_box', 10, 2 );
	add_action( 'save_post', 'qtu_seo_save_meta_box', 99 );
	add_filter( 'wp_robots', 'qtu_seo_core_robots', 99 );
	add_action( 'wp_head', 'qtu_seo_native_head', 5 );
	add_filter( 'wp_sitemaps_add_provider', 'qtu_seo_core_provider', 99, 2 );
	add_filter( 'wp_sitemaps_max_urls', 'qtu_seo_sitemap_limit', 99 );
	add_filter( 'wp_sitemaps_posts_entry', 'qtu_seo_core_post_entry', 99, 2 );
	add_filter( 'wp_sitemaps_posts_query_args', 'qtu_seo_sitemap_query_args', 99 );
	add_filter( 'posts_where_request', 'qtu_seo_sitemap_where', 99, 2 );
	foreach ( array( 'wpseo_title', 'wpseo_metadesc', 'wpseo_opengraph_title', 'wpseo_opengraph_desc', 'wpseo_opengraph_site_name', 'wpseo_twitter_title', 'wpseo_twitter_description', 'rank_math/frontend/title', 'rank_math/frontend/description', 'rank_math/opengraph/facebook/og_title', 'rank_math/opengraph/facebook/og_description', 'rank_math/opengraph/facebook/og_site_name', 'rank_math/opengraph/twitter/twitter_title', 'rank_math/opengraph/twitter/twitter_description' ) as $hook ) {
		add_filter( $hook, 'qtu_seo_vendor_text', 99 );
	}
	foreach ( array( 'wpseo_canonical', 'wpseo_opengraph_url', 'rank_math/frontend/canonical', 'rank_math/opengraph/facebook/og_url' ) as $hook ) { add_filter( $hook, 'qtu_seo_canonical', 99 ); }
	foreach ( array( 'wpseo_robots', 'rank_math/frontend/robots' ) as $hook ) { add_filter( $hook, 'qtu_seo_vendor_robots', 99 ); }
	foreach ( array( 'wpseo_og_locale', 'rank_math/opengraph/facebook/og_locale' ) as $hook ) { add_filter( $hook, 'qtu_seo_og_locale', 99 ); }
	add_filter( 'wpseo_replacements', 'qtu_seo_yoast_replacements', 99 );
	add_filter( 'wpseo_schema_graph', 'qtu_seo_schema', 99 );
	foreach ( array( 'article', 'webpage', 'website', 'breadcrumb', 'organization', 'person' ) as $piece ) { add_filter( 'wpseo_schema_' . $piece, 'qtu_seo_schema', 99 ); }
	add_filter( 'rank_math/json_ld', 'qtu_seo_schema', 99 );
	add_filter( 'wpseo_breadcrumb_links', 'qtu_seo_breadcrumbs', 99 );
	add_filter( 'wpseo_sitemap_entry', 'qtu_seo_sitemap_entry', 99, 3 );
	add_filter( 'rank_math/sitemap/entry', 'qtu_seo_sitemap_entry', 99, 3 );
	add_filter( 'wpseo_sitemap_url', 'qtu_seo_sitemap_xml', 99, 2 );
	add_filter( 'rank_math/sitemap/url', 'qtu_seo_sitemap_xml', 99, 2 );
	add_filter( 'wpseo_sitemap_entries_per_page', 'qtu_seo_sitemap_limit', 99 );
	add_filter( 'option_rank-math-options-sitemap', 'qtu_seo_rankmath_sitemap_options', 99 );
}
