<?php
/** Shared language/URL rules for core, Yoast and Rank Math SEO output. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qtrad_is_sitemap_request() {
	$path = qtrad_strip_path_language( qtrad_relative_path( (string) wp_parse_url( qtrad_request_uri(), PHP_URL_PATH ) ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sitemap detection.
	return isset( $_GET['sitemap'] ) || ( isset( $_GET['stylesheet'] ) && is_string( $_GET['stylesheet'] ) && sanitize_key( wp_unslash( $_GET['stylesheet'] ) ) === 'sitemap' )
		|| (bool) preg_match( '#^/[a-z0-9_-]*sitemap[a-z0-9_-]*\.(?:xml|xsl)$#iD', $path );
}

function qtrad_seo_owner() {
	if ( defined( 'WPSEO_VERSION' ) ) { return 'yoast'; }
	if ( defined( 'RANK_MATH_VERSION' ) ) { return 'rankmath'; }
	return '';
}

/** Strip WordPress locale suffixes and map legacy storage codes to SEO codes. */
function qtrad_seo_language_tag( $language ) {
	$locales = qtrad_config( 'locale' );
	$parts = preg_split( '/[_-]/', isset( $locales[ $language ] ) ? $locales[ $language ] : $language );
	$primary = strtolower( $parts[0] );
	$aliases = array( 'iw' => 'he', 'in' => 'id', 'ji' => 'yi' );
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
	return apply_filters( 'qtrad_seo_language_tag', $tag, $language );
}

function qtrad_seo_text( $value ) {
	return qtrad_use_language( $value, qtrad_current_language(), false, true );
}

/** Get a stable permalink without inheriting the current request language. */
function qtrad_seo_post_url( $post, $language ) {
	$previous = ! empty( $GLOBALS['qtrad_seo_neutral_url'] );
	$GLOBALS['qtrad_seo_neutral_url'] = true;
	try { $url = qtrad_slugs_in_language( $language, function () use ( $post ) { return get_permalink( $post ); } ); }
	finally { $GLOBALS['qtrad_seo_neutral_url'] = $previous; }
	return qtrad_convert_url( $url, $language, true, false );
}

function qtrad_seo_raw_meta( $post_id, $key ) {
	$values = qtrad_raw_post_meta( $post_id, $key );
	return $values ? $values[0] : '';
}

function qtrad_seo_post_indexable( $post, $language ) {
	if ( ! $post || $post->post_status !== 'publish' || $post->post_password !== '' || ! get_option( 'blog_public' ) ) { return false; }
	$owner = qtrad_seo_owner();
	if ( $owner === 'yoast' && (string) qtrad_seo_raw_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex' ) === '1' ) { return false; }
	if ( $owner === 'rankmath' && in_array( 'noindex', (array) qtrad_seo_raw_meta( $post->ID, 'rank_math_robots' ), true ) ) { return false; }
	$key = $owner === 'yoast' ? '_yoast_wpseo_canonical' : ( $owner === 'rankmath' ? 'rank_math_canonical_url' : '' );
	if ( $key ) {
		$custom = qtrad_use_language( qtrad_seo_raw_meta( $post->ID, $key ), $language, false, true );
		if ( is_string( $custom ) && $custom !== '' && qtrad_convert_url( $custom, $language, true, false ) !== qtrad_seo_post_url( $post, $language ) ) { return false; }
	}
	return (bool) apply_filters( 'qtrad_seo_post_indexable', true, $post, $language );
}

function qtrad_seo_post_languages( $post ) {
	return array_values( array_filter( qtrad_post_available_languages( $post ), function ( $language ) use ( $post ) {
		return qtrad_seo_language_tag( $language ) !== '' && qtrad_seo_post_indexable( $post, $language );
	} ) );
}

function qtrad_seo_missing_translation() {
	return is_singular() && ! in_array( qtrad_current_language(), qtrad_post_available_languages( get_post( get_queried_object_id() ) ), true );
}

function qtrad_seo_canonical( $url ) {
	return is_string( $url ) && $url !== '' ? qtrad_convert_url( qtrad_slugs_localize_url( qtrad_seo_text( $url ), qtrad_current_language() ), qtrad_current_language(), true, false ) : $url;
}

/** Canonical archive URLs omit request tracking arguments and retain pagination. */
function qtrad_seo_current_url( $language ) {
	if ( is_singular() ) {
		$url = wp_get_canonical_url( get_queried_object_id() );
		return $url ? qtrad_convert_url( qtrad_slugs_localize_url( $url, $language ), $language, true, false ) : qtrad_seo_post_url( get_post( get_queried_object_id() ), $language );
	}
	if ( is_category() || is_tag() || is_tax() ) { $url = get_term_link( get_queried_object() ); }
	elseif ( is_author() ) { $url = get_author_posts_url( get_queried_object_id() ); }
	elseif ( is_post_type_archive() ) { $url = get_post_type_archive_link( get_queried_object()->name ); }
	elseif ( is_home() && get_option( 'page_for_posts' ) ) { $url = qtrad_seo_post_url( get_post( get_option( 'page_for_posts' ) ), $language ); }
	elseif ( is_home() || is_front_page() ) { $url = trailingslashit( get_option( 'home' ) ); }
	else { return qtrad_convert_url( '', $language, true, false ); }
	if ( is_wp_error( $url ) || ! is_string( $url ) || $url === '' ) { return ''; }
	$page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	if ( $page > 1 ) {
		global $wp_rewrite;
		$url = $wp_rewrite->using_permalinks() ? trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $page, 'paged' ) : add_query_arg( 'paged', $page, $url );
	}
	return qtrad_convert_url( qtrad_slugs_localize_url( $url, $language ), $language, true, false );
}

function qtrad_seo_core_robots( $robots ) {
	if ( qtrad_seo_missing_translation() ) { unset( $robots['index'] ); $robots['noindex'] = true; }
	return $robots;
}

function qtrad_seo_vendor_robots( $robots ) {
	if ( ! qtrad_seo_missing_translation() ) { return $robots; }
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

function qtrad_seo_head_links() {
	if ( is_admin() || is_feed() || is_404() || is_search() || qtrad_is_rest_request() || qtrad_is_sitemap_request() || ! get_option( 'blog_public' ) || qtrad_seo_missing_translation() ) { return; }
	$post = is_singular() ? get_post( get_queried_object_id() ) : null;
	$languages = $post ? qtrad_seo_post_languages( $post ) : qtrad_enabled_languages();
	$tags = array();
	foreach ( $languages as $language ) {
		$tag = qtrad_seo_language_tag( $language );
		if ( $tag === '' || isset( $tags[ $tag ] ) ) { continue; }
		$tags[ $tag ] = true;
		$url = qtrad_seo_current_url( $language );
		echo '<link rel="alternate" hreflang="' . esc_attr( $tag ) . '" href="' . esc_url( $url ) . '" />' . "\n";
	}
	$default = qtrad_default_language();
	if ( in_array( $default, $languages, true ) && qtrad_seo_language_tag( $default ) !== '' ) {
		$url = qtrad_seo_current_url( $default );
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '" />' . "\n";
	}
}

function qtrad_seo_description() {
	$post = is_singular() ? get_post( get_queried_object_id() ) : null;
	$override = $post ? qtrad_seo_override( $post->ID, 'description', qtrad_current_language() ) : '';
	if ( $override !== '' ) { return $override; }
	$text = $post ? qtrad_seo_text( $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content ) : get_option( 'blogdescription' );
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ), true ), 32, '…' );
}

function qtrad_seo_og_locale( $unused = '' ) {
	$tag = qtrad_seo_language_tag( qtrad_current_language() );
	if ( preg_match( '/^([a-z]{2,3})(?:-[A-Za-z]{4})?-([A-Z]{2})$/D', $tag, $match ) ) { return $match[1] . '_' . $match[2]; }
	return preg_match( '/^([a-z]{2,3})_[A-Z]{2}$/D', $unused, $match ) && $match[1] === $tag ? $unused : '';
}

/** Native fallback output; SEO plugins remain responsible for their own tags. */
function qtrad_seo_native_head() {
	if ( qtrad_seo_owner() || is_admin() || is_feed() || is_404() || is_search() || qtrad_is_rest_request() || qtrad_is_sitemap_request() ) { return; }
	$url = qtrad_seo_current_url( qtrad_current_language() );
	$title = wp_get_document_title();
	$description = qtrad_seo_description();
	if ( ! is_singular() ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n"; }
	foreach ( array( 'description' => $description, 'twitter:card' => 'summary', 'twitter:title' => $title, 'twitter:description' => $description ) as $name => $value ) {
		if ( $value !== '' ) { echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />' . "\n"; }
	}
	foreach ( array( 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:locale' => qtrad_seo_og_locale(), 'og:type' => 'website' ) as $name => $value ) {
		if ( $value !== '' ) { echo '<meta property="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />' . "\n"; }
	}
	$schema = apply_filters( 'qtrad_seo_schema', array( '@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $description, 'inLanguage' => qtrad_seo_language_tag( qtrad_current_language() ) ) );
	wp_print_inline_script_tag( wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), array( 'type' => 'application/ld+json' ) );
}

/** Read multilingual SEO overrides from raw storage, independent of indexable caches. */
function qtrad_seo_vendor_text( $value ) {
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
	$override = is_singular() && $kind ? qtrad_seo_override( get_queried_object_id(), $kind, qtrad_current_language() ) : '';
	if ( $override !== '' ) { return $override; }
	if ( is_singular() && isset( $keys[ $hook ] ) ) {
		$raw = qtrad_seo_raw_meta( get_queried_object_id(), $keys[ $hook ] );
		if ( is_string( $raw ) && qtrad_has_lang_tags( $raw ) ) {
			$value = qtrad_seo_text( $raw );
			if ( strpos( $hook, 'wpseo_' ) === 0 && function_exists( 'wpseo_replace_vars' ) ) { $value = wpseo_replace_vars( $value, get_queried_object() ); }
			elseif ( class_exists( 'RankMath\\Helper' ) ) { $value = \RankMath\Helper::replace_vars( $value ); }
		}
	}
	return qtrad_seo_text( $value );
}

function qtrad_seo_yoast_replacements( $replacements ) {
	if ( is_singular() ) {
		$post = get_post( get_queried_object_id() );
		if ( $post ) { $replacements['%%title%%'] = qtrad_seo_text( $post->post_title ); }
	}
	$replacements['%%sitename%%'] = qtrad_seo_text( get_option( 'blogname' ) );
	$replacements['%%sitedesc%%'] = qtrad_seo_text( get_option( 'blogdescription' ) );
	return $replacements;
}

/** Translate text and site-local identifiers throughout the vendor's existing graph. */
function qtrad_seo_schema( $data ) {
	if ( ! is_array( $data ) ) { return qtrad_seo_text( $data ); }
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) { $data[ $key ] = qtrad_seo_schema( $value ); }
		elseif ( is_string( $value ) ) {
			$data[ $key ] = qtrad_seo_text( $value );
			if ( in_array( $key, array( 'url', '@id', 'item' ), true ) ) { $data[ $key ] = qtrad_seo_canonical( $data[ $key ] ); }
			if ( $key === 'inLanguage' ) { $data[ $key ] = qtrad_seo_language_tag( qtrad_current_language() ); }
		}
	}
	$types = isset( $data['@type'] ) ? (array) $data['@type'] : array();
	if ( is_singular() && array_intersect( $types, array( 'WebPage', 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
		foreach ( array( 'name' => 'title', 'headline' => 'title', 'description' => 'description' ) as $key => $kind ) {
			$override = qtrad_seo_override( get_queried_object_id(), $kind, qtrad_current_language() );
			if ( isset( $data[ $key ] ) && $override !== '' ) { $data[ $key ] = $override; }
		}
	}
	return $data;
}

function qtrad_seo_override( $post_id, $kind, $language ) {
	$value = qtrad_seo_raw_meta( $post_id, '_qtrad_seo_' . $kind );
	return is_string( $value ) ? wp_strip_all_tags( qtrad_use_language( $value, $language, false, true ), true ) : '';
}

function qtrad_seo_document_title( $parts ) {
	if ( ! qtrad_seo_owner() && is_singular() ) {
		$title = qtrad_seo_override( get_queried_object_id(), 'title', qtrad_current_language() );
		if ( $title !== '' ) { $parts['title'] = $title; }
	}
	return $parts;
}

/** Explicit, labelled fields work in both editors without a JavaScript dependency. */
function qtrad_seo_add_meta_box( $post_type, $post ) {
	$type = get_post_type_object( $post_type );
	if ( $type && $type->public && $post_type !== 'attachment' && current_user_can( 'edit_post', $post->ID ) ) {
		add_meta_box( 'qtrad-seo', esc_html__( 'SEO translations', 'qtrad' ), 'qtrad_seo_meta_box', $post_type, 'normal', 'default' );
	}
}

function qtrad_seo_meta_box( $post ) {
	wp_nonce_field( 'qtrad_seo_' . $post->ID, 'qtrad_seo_nonce' );
	echo '<input type="hidden" name="qtrad_seo_post_id" value="' . esc_attr( $post->ID ) . '" />';
	echo '<p>' . esc_html__( 'Optional titles and descriptions for search engines and social sharing. Leave blank to use existing SEO settings or automatically generated text.', 'qtrad' ) . '</p>';
	foreach ( qtrad_enabled_languages() as $language ) {
		echo '<fieldset><legend><strong><bdi>' . esc_html( qtrad_language_name( $language ) ) . '</bdi></strong></legend>';
		foreach ( array( 'title' => __( 'SEO title', 'qtrad' ), 'description' => __( 'SEO description', 'qtrad' ) ) as $kind => $label ) {
			$id = 'qtrad-seo-' . $kind . '-' . $language;
			$value = qtrad_seo_override( $post->ID, $kind, $language );
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br />';
			$attributes = array(
				'id'    => $id,
				'name'  => 'qtrad_seo_fields[' . $kind . '][' . $language . ']',
				'class' => 'widefat',
				'lang'  => str_replace( '_', '-', qtrad_config( 'locale' )[ $language ] ),
				'dir'   => qtrad_language_direction( $language ),
			);
			echo $kind === 'title' ? '<input type="text"' : '<textarea rows="3"';
			foreach ( $attributes as $attribute => $attribute_value ) {
				echo ' ' . esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
			if ( $kind === 'title' ) { echo ' value="' . esc_attr( $value ) . '" />'; }
			else { echo '>' . esc_textarea( $value ) . '</textarea>'; }
			echo '</p>';
		}
		echo '</fieldset>';
	}
}

function qtrad_seo_save_meta_box( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id )
		|| empty( $_POST['qtrad_seo_post_id'] ) || (int) $_POST['qtrad_seo_post_id'] !== (int) $post_id
		|| ! isset( $_POST['qtrad_seo_nonce'] ) || ! is_string( $_POST['qtrad_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_seo_nonce'] ) ), 'qtrad_seo_' . $post_id )
		|| empty( $_POST['qtrad_seo_fields'] ) || ! is_array( $_POST['qtrad_seo_fields'] ) ) { return; }
	foreach ( array( 'title', 'description' ) as $kind ) {
		if ( ! isset( $_POST['qtrad_seo_fields'][ $kind ] ) || ! is_array( $_POST['qtrad_seo_fields'][ $kind ] ) ) { continue; }
		$key = '_qtrad_seo_' . $kind;
		$raw = qtrad_seo_raw_meta( $post_id, $key );
		$texts = qtrad_split( is_string( $raw ) ? $raw : '', null, false );
		foreach ( qtrad_enabled_languages() as $language ) {
			if ( isset( $_POST['qtrad_seo_fields'][ $kind ][ $language ] ) && is_string( $_POST['qtrad_seo_fields'][ $kind ][ $language ] ) ) {
				$texts[ $language ] = $kind === 'description' ? sanitize_textarea_field( wp_unslash( $_POST['qtrad_seo_fields'][ $kind ][ $language ] ) ) : sanitize_text_field( wp_unslash( $_POST['qtrad_seo_fields'][ $kind ][ $language ] ) );
			}
		}
		update_post_meta( $post_id, $key, wp_slash( qtrad_join( $texts, 'bracket', qtrad_enabled_languages() ) ) );
	}
}

function qtrad_seo_breadcrumbs( $links ) {
	foreach ( $links as &$link ) {
		if ( isset( $link['url'] ) ) { $link['url'] = qtrad_seo_canonical( $link['url'] ); }
		if ( isset( $link['text'] ) ) { $link['text'] = qtrad_seo_text( $link['text'] ); }
	}
	unset( $link );
	return $links;
}

/** Leave room for expanded entries and the homepage within the XML URL limit. */
function qtrad_seo_sitemap_limit( $limit ) {
	return max( 1, (int) floor( min( 50000, max( 1, (int) $limit ) ) / max( 1, count( qtrad_enabled_languages() ) ) ) - 1 );
}

function qtrad_seo_rankmath_sitemap_options( $options ) {
	if ( is_array( $options ) ) { $options['items_per_page'] = qtrad_seo_sitemap_limit( isset( $options['items_per_page'] ) ? $options['items_per_page'] : 200 ); }
	return $options;
}

function qtrad_seo_sitemap_entry( $entry, $type, $object ) {
	if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) { return $entry; }
	$post = $type === 'post' && is_object( $object ) && isset( $object->ID ) ? get_post( (int) $object->ID ) : null;
	$entry['_qtrad_languages'] = $post ? qtrad_seo_post_languages( $post ) : qtrad_enabled_languages();
	if ( $post ) { $entry['_qtrad_object'] = array( 'post', (int) $post->ID ); }
	elseif ( $type === 'term' && $object instanceof WP_Term ) { $entry['_qtrad_object'] = array( 'term', (int) $object->term_id ); }
	return $entry;
}

function qtrad_seo_core_post_entry( $entry, $post ) { return qtrad_seo_sitemap_entry( $entry, 'post', $post ); }

/** Before WordPress 6.0 the second argument is the term, later its id. */
function qtrad_seo_core_term_entry( $entry, $term, $taxonomy = '' ) { return qtrad_seo_sitemap_entry( $entry, 'term', is_object( $term ) ? $term : get_term( (int) $term, $taxonomy ) ); }

function qtrad_seo_sitemap_query_args( $args ) {
	$args['has_password'] = false;
	$args['qtrad_seo_sitemap'] = true;
	return $args;
}

function qtrad_seo_sitemap_where( $where, $query ) {
	if ( ! $query->get( 'qtrad_seo_sitemap' ) ) { return $where; }
	$conditions = array();
	foreach ( qtrad_enabled_languages() as $language ) { $conditions[] = substr( qtrad_available_language_where( $language ), 5 ); }
	return $where . ' AND (' . implode( ' OR ', $conditions ) . ')';
}

function qtrad_seo_sitemap_urls( $entry ) {
	if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) { return array(); }
	$parts = qtrad_parse_local_url( $entry['loc'] );
	$object = isset( $entry['_qtrad_object'] ) && qtrad_slugs_active() ? $entry['_qtrad_object'] : null;
	if ( ! $parts || ! qtrad_is_local_url( $parts ) ) { unset( $entry['_qtrad_languages'], $entry['_qtrad_object'] ); return array( $entry ); }
	$languages = isset( $entry['_qtrad_languages'] ) ? $entry['_qtrad_languages'] : qtrad_enabled_languages();
	unset( $entry['_qtrad_languages'], $entry['_qtrad_object'] );
	$urls = array();
	foreach ( $languages as $language ) {
		if ( qtrad_seo_language_tag( $language ) === '' ) { continue; }
		$variant = $entry;
		$loc = $object ? qtrad_slugs_object_url( $object[0], $object[1], $language ) : '';
		$variant['loc'] = qtrad_convert_url( $loc !== '' ? $loc : $entry['loc'], $language, true, false );
		$urls[ $variant['loc'] ] = $variant;
	}
	return array_values( $urls );
}

/** Expand a vendor-rendered URL without dropping its image/lastmod extensions. */
function qtrad_seo_sitemap_xml( $xml, $entry ) {
	$output = '';
	foreach ( qtrad_seo_sitemap_urls( $entry ) as $variant ) {
		$output .= preg_replace_callback( '~<loc>[^<]*</loc>~', function () use ( $variant ) {
			return '<loc>' . htmlspecialchars( $variant['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</loc>';
		}, $xml, 1 );
	}
	return $output;
}

/** Wrap core providers, retaining their exclusions, filters and paging calculations. */
function qtrad_seo_core_provider( $provider, $name ) {
	if ( qtrad_seo_owner() || ! $provider || ! in_array( $name, array( 'posts', 'taxonomies', 'users' ), true ) || $provider instanceof Qtrad_Sitemap_Provider ) { return $provider; }
	return new Qtrad_Sitemap_Provider( $provider, $name );
}

class Qtrad_Sitemap_Provider extends WP_Sitemaps_Provider {
	private $provider;
	public function __construct( $provider, $name ) {
		$this->provider = $provider;
		$this->name = $name;
		$this->object_type = array( 'posts' => 'post', 'taxonomies' => 'term', 'users' => 'user' )[ $name ];
	}
	public function get_object_subtypes() { return $this->provider->get_object_subtypes(); }
	public function get_max_num_pages( $object_subtype = '' ) { return $this->provider->get_max_num_pages( $object_subtype ); }
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$previous = ! empty( $GLOBALS['qtrad_seo_sitemap_build'] );
		$GLOBALS['qtrad_seo_sitemap_build'] = true;
		try { $entries = $this->provider->get_url_list( $page_num, $object_subtype ); }
		finally { $GLOBALS['qtrad_seo_sitemap_build'] = $previous; }
		$urls = array();
		foreach ( $entries as $entry ) { $urls = array_merge( $urls, qtrad_seo_sitemap_urls( $entry ) ); }
		return $urls;
	}
}

function qtrad_register_seo_hooks() {
	add_filter( 'get_canonical_url', 'qtrad_seo_canonical', 99 );
	add_filter( 'document_title_parts', 'qtrad_seo_document_title', 99 );
	add_action( 'add_meta_boxes', 'qtrad_seo_add_meta_box', 10, 2 );
	add_action( 'save_post', 'qtrad_seo_save_meta_box', 99 );
	add_filter( 'wp_robots', 'qtrad_seo_core_robots', 99 );
	add_action( 'wp_head', 'qtrad_seo_native_head', 5 );
	add_filter( 'wp_sitemaps_add_provider', 'qtrad_seo_core_provider', 99, 2 );
	add_filter( 'wp_sitemaps_max_urls', 'qtrad_seo_sitemap_limit', 99 );
	add_filter( 'wp_sitemaps_posts_entry', 'qtrad_seo_core_post_entry', 99, 2 );
	add_filter( 'wp_sitemaps_taxonomies_entry', 'qtrad_seo_core_term_entry', 99, 3 );
	add_filter( 'wp_sitemaps_posts_query_args', 'qtrad_seo_sitemap_query_args', 99 );
	add_filter( 'posts_where_request', 'qtrad_seo_sitemap_where', 99, 2 );
	foreach ( array( 'wpseo_title', 'wpseo_metadesc', 'wpseo_opengraph_title', 'wpseo_opengraph_desc', 'wpseo_opengraph_site_name', 'wpseo_twitter_title', 'wpseo_twitter_description', 'rank_math/frontend/title', 'rank_math/frontend/description', 'rank_math/opengraph/facebook/og_title', 'rank_math/opengraph/facebook/og_description', 'rank_math/opengraph/facebook/og_site_name', 'rank_math/opengraph/twitter/twitter_title', 'rank_math/opengraph/twitter/twitter_description' ) as $hook ) {
		add_filter( $hook, 'qtrad_seo_vendor_text', 99 );
	}
	foreach ( array( 'wpseo_canonical', 'wpseo_opengraph_url', 'rank_math/frontend/canonical', 'rank_math/opengraph/facebook/og_url' ) as $hook ) { add_filter( $hook, 'qtrad_seo_canonical', 99 ); }
	foreach ( array( 'wpseo_robots', 'rank_math/frontend/robots' ) as $hook ) { add_filter( $hook, 'qtrad_seo_vendor_robots', 99 ); }
	foreach ( array( 'wpseo_og_locale', 'rank_math/opengraph/facebook/og_locale' ) as $hook ) { add_filter( $hook, 'qtrad_seo_og_locale', 99 ); }
	add_filter( 'wpseo_replacements', 'qtrad_seo_yoast_replacements', 99 );
	add_filter( 'wpseo_schema_graph', 'qtrad_seo_schema', 99 );
	foreach ( array( 'article', 'webpage', 'website', 'breadcrumb', 'organization', 'person' ) as $piece ) { add_filter( 'wpseo_schema_' . $piece, 'qtrad_seo_schema', 99 ); }
	add_filter( 'rank_math/json_ld', 'qtrad_seo_schema', 99 );
	add_filter( 'wpseo_breadcrumb_links', 'qtrad_seo_breadcrumbs', 99 );
	add_filter( 'wpseo_sitemap_entry', 'qtrad_seo_sitemap_entry', 99, 3 );
	add_filter( 'rank_math/sitemap/entry', 'qtrad_seo_sitemap_entry', 99, 3 );
	add_filter( 'wpseo_sitemap_url', 'qtrad_seo_sitemap_xml', 99, 2 );
	add_filter( 'rank_math/sitemap/url', 'qtrad_seo_sitemap_xml', 99, 2 );
	add_filter( 'wpseo_sitemap_entries_per_page', 'qtrad_seo_sitemap_limit', 99 );
	add_filter( 'option_rank-math-options-sitemap', 'qtrad_seo_rankmath_sitemap_options', 99 );
}
