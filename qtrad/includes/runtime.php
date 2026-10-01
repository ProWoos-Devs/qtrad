<?php
/** Language routing only transforms recognized site origins and content URLs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qtu_home_parts() {
	$parts = wp_parse_url( (string) get_option( 'home' ) );
	$parts = is_array( $parts ) ? $parts : array();
	return array( 'scheme' => isset( $parts['scheme'] ) ? $parts['scheme'] : ( is_ssl() ? 'https' : 'http' ),
		'host' => isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '',
		'port' => isset( $parts['port'] ) ? (int) $parts['port'] : 0,
		'path' => isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '' );
}

function qtu_relative_path( $path ) {
	$home = qtu_home_parts();
	$path = '/' . ltrim( (string) $path, '/' );
	$base = $home['path'];
	if ( $base !== '' && ( $path === $base || strpos( $path, $base . '/' ) === 0 ) ) {
		return $path === $base ? '/' : substr( $path, strlen( $base ) );
	}
	return $path;
}

function qtu_strip_path_language( $path ) {
	if ( preg_match( '#^/([a-z]{2})(/|$)#i', $path, $match ) && qtu_is_enabled( strtolower( $match[1] ) ) ) {
		return substr( $path, 3 ) ?: '/';
	}
	return $path;
}

function qtu_is_neutral_path( $path ) {
	$path = qtu_strip_path_language( qtu_relative_path( $path ) );
	return (bool) preg_match( '#^/(?:wp-admin|wp-login\.php|wp-json|wp-content|wp-includes|xmlrpc\.php|feed)(?:/|$)|/(?:feed|rss2?|atom|rdf)/?$|^/(?:[a-z0-9_-]*sitemap[a-z0-9_-]*\.(?:xml|xsl)|robots\.txt|favicon\.ico)$|\.(?:css|js|png|gif|jpe?g|svg|webp|woff2?|ico|pdf|zip)$#i', $path );
}

function qtu_is_rest_request() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return true; }
	if ( isset( $_GET['rest_route'] ) ) { return true; }
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$path = qtu_strip_path_language( qtu_relative_path( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) );
	$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
	return (bool) preg_match( '#^/(?:index\.php/)?' . preg_quote( $prefix, '#' ) . '(?:/|$)#', $path );
}

function qtu_browser_language() {
	$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && is_string( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
	$ranked = array();
	foreach ( explode( ',', $header ) as $i => $entry ) {
		if ( ! preg_match( '/^\s*([a-z]{2,8}(?:-[a-z0-9]{1,8})*)\s*(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?\s*$/iD', $entry, $match ) ) { continue; }
		$q = isset( $match[2] ) ? (float) $match[2] : 1;
		if ( $q > 0 ) { $ranked[] = array( 'tag' => strtolower( $match[1] ), 'q' => $q, 'i' => $i ); }
	}
	usort( $ranked, function ( $a, $b ) { return $a['q'] === $b['q'] ? $a['i'] - $b['i'] : ( $a['q'] < $b['q'] ? 1 : -1 ); } );
	$locales = qtu_config( 'locale' );
	foreach ( $ranked as $row ) {
		foreach ( qtu_enabled_languages() as $code ) {
			if ( isset( $locales[ $code ] ) && strtolower( str_replace( '_', '-', $locales[ $code ] ) ) === $row['tag'] ) { return $code; }
		}
		$code = explode( '-', $row['tag'] )[0];
		if ( qtu_is_enabled( $code ) ) { return $code; }
	}
	return '';
}

function qtu_cookie_front() {
	$lang = isset( $_COOKIE['qtrans_front_language'] ) && is_string( $_COOKIE['qtrans_front_language'] ) ? strtolower( sanitize_key( wp_unslash( $_COOKIE['qtrans_front_language'] ) ) ) : '';
	return qtu_is_enabled( $lang ) ? $lang : '';
}

function qtu_set_front_cookie( $lang ) {
	if ( headers_sent() || wp_doing_ajax() || wp_doing_cron() || qtu_is_rest_request() || qtu_cookie_front() === $lang ) { return; }
	$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	setcookie( 'qtrans_front_language', $lang, array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => $path, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	$_COOKIE['qtrans_front_language'] = $lang;
}

function qtu_assemble_url( $parts ) {
	$url = $parts['scheme'] . '://' . $parts['host'];
	$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
	if ( $port && ! ( ( $parts['scheme'] === 'http' && $port === 80 ) || ( $parts['scheme'] === 'https' && $port === 443 ) ) ) { $url .= ':' . $port; }
	$url .= isset( $parts['path'] ) && $parts['path'] !== '' ? $parts['path'] : '/';
	if ( isset( $parts['query'] ) && $parts['query'] !== '' ) { $url .= '?' . $parts['query']; }
	if ( isset( $parts['fragment'] ) && $parts['fragment'] !== '' ) { $url .= '#' . $parts['fragment']; }
	return $url;
}

function qtu_domain_parts( $domain ) {
	if ( ! is_string( $domain ) ) { return false; }
	$parts = wp_parse_url( 'http://' . trim( $domain ) );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['path'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) { return false; }
	if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[a-f0-9:]+\])$/iD', $parts['host'] ) || ( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) ) { return false; }
	return array( 'host' => strtolower( $parts['host'] ), 'port' => isset( $parts['port'] ) ? (int) $parts['port'] : 0 );
}

function qtu_allowed_origins() {
	$home = qtu_home_parts();
	$origins = array( $home );
	if ( (int) qtu_config( 'url_mode' ) === QTU_URL_DOMAIN ) {
		foreach ( qtu_enabled_languages() as $lang ) { $origin = $home; $origin['host'] = $lang . '.' . $home['host']; $origins[] = $origin; }
	}
	if ( (int) qtu_config( 'url_mode' ) === QTU_URL_DOMAINS ) {
		foreach ( (array) qtu_config( 'domains' ) as $domain ) {
			$origin = qtu_domain_parts( $domain );
			if ( $origin ) { $origin['scheme'] = $home['scheme']; $origins[] = $origin; }
		}
	}
	return $origins;
}

function qtu_effective_port( $parts ) {
	return ! empty( $parts['port'] ) ? (int) $parts['port'] : ( isset( $parts['scheme'] ) && $parts['scheme'] === 'https' ? 443 : 80 );
}

function qtu_is_local_url( $parts ) {
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) { return false; }
	$home = qtu_home_parts();
	if ( $home['path'] !== '' && $parts['path'] !== $home['path'] && strpos( $parts['path'], $home['path'] . '/' ) !== 0 ) { return false; }
	foreach ( qtu_allowed_origins() as $origin ) {
		if ( strcasecmp( $parts['host'], $origin['host'] ) === 0 && qtu_effective_port( $parts ) === qtu_effective_port( $origin ) ) { return true; }
	}
	return false;
}

function qtu_parse_local_url( $url ) {
	$home = qtu_home_parts();
	if ( $url === '' ) {
		$uri = isset( $GLOBALS['qtu_original_uri'] ) ? $GLOBALS['qtu_original_uri'] : ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' );
		$parts = wp_parse_url( $uri );
		// Host input is accepted only if it is a configured origin.
		$incoming = isset( $_SERVER['HTTP_HOST'] ) ? qtu_domain_parts( $_SERVER['HTTP_HOST'] ) : false;
		if ( $incoming ) {
			$incoming['scheme'] = is_ssl() ? 'https' : 'http';
			foreach ( qtu_allowed_origins() as $origin ) {
				if ( $incoming['host'] === $origin['host'] && qtu_effective_port( $incoming ) === qtu_effective_port( $origin ) ) { $home['host'] = $origin['host']; $home['port'] = $origin['port']; break; }
			}
		}
		return array_merge( $home, array( 'path' => isset( $parts['path'] ) ? $parts['path'] : '/', 'query' => isset( $parts['query'] ) ? $parts['query'] : '', 'fragment' => '' ) );
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) { return false; }
	$absolute = isset( $parts['host'] );
	$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	if ( ! $absolute && $path !== '/' && substr( $path, 0, 1 ) !== '/' ) {
		$current = qtu_parse_local_url( '' );
		$path = trailingslashit( dirname( $current['path'] ) ) . $path;
	}
	return array( 'scheme' => isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : $home['scheme'],
		'host' => $absolute ? strtolower( $parts['host'] ) : $home['host'],
		'port' => isset( $parts['port'] ) ? (int) $parts['port'] : ( $absolute ? 0 : $home['port'] ),
		'path' => $path, 'query' => isset( $parts['query'] ) ? $parts['query'] : '', 'fragment' => isset( $parts['fragment'] ) ? $parts['fragment'] : '' );
}

/** Remove only lang, preserving duplicate keys and unrelated encoded bytes. */
function qtu_strip_query_lang( $query ) {
	$parts = explode( '&', str_replace( '&amp;', '&', $query ) );
	return implode( '&', array_filter( $parts, function ( $part ) { return rawurldecode( explode( '=', $part, 2 )[0] ) !== 'lang'; } ) );
}

function qtu_url_for_language( $url, $lang, $show_language ) {
	$parts = qtu_parse_local_url( $url );
	if ( ! $parts || ! qtu_is_local_url( $parts ) ) { return $url; }
	$home = qtu_home_parts();
	$mode = (int) qtu_config( 'url_mode' );
	if ( $mode === QTU_URL_PATH && get_option( 'permalink_structure', '' ) === '' ) { $mode = QTU_URL_QUERY; }
	$parts['query'] = qtu_strip_query_lang( $parts['query'] );
	$rel = qtu_strip_path_language( qtu_relative_path( $parts['path'] ) );
	$parts['path'] = $home['path'] . $rel;
	$parts['host'] = $home['host'];
	$parts['port'] = $home['port'];
	if ( $mode === QTU_URL_DOMAINS ) {
		$domains = (array) qtu_config( 'domains' );
		$domain = isset( $domains[ $lang ] ) ? qtu_domain_parts( $domains[ $lang ] ) : false;
		if ( $domain ) { $parts['host'] = $domain['host']; $parts['port'] = $domain['port']; }
		elseif ( $lang !== qtu_default_language() || $show_language ) { $parts['query'] .= ( $parts['query'] !== '' ? '&' : '' ) . 'lang=' . rawurlencode( $lang ); }
	} elseif ( $show_language ) {
		if ( $mode === QTU_URL_PATH ) { $parts['path'] = $home['path'] . '/' . $lang . $rel; }
		elseif ( $mode === QTU_URL_DOMAIN ) { $parts['host'] = $lang . '.' . $home['host']; }
		else { $parts['query'] .= ( $parts['query'] !== '' ? '&' : '' ) . 'lang=' . rawurlencode( $lang ); }
	}
	return qtu_assemble_url( $parts );
}

function qtu_convert_url( $url = '', $lang = '', $forceadmin = false, $show_default = null ) {
	if ( is_string( $url ) && preg_match( '~^(?:https?:)?//[^/]*@~i', $url ) ) { return $url; }
	if ( is_admin() && ! $forceadmin ) { return $url; }
	if ( ! is_string( $url ) ) { return ''; }
	if ( $url !== '' && ( $url[0] === '#' || $url[0] === '?' || preg_match( '/^(?!https?:)[a-z][a-z0-9+.-]*:/i', $url ) ) ) { return $url; }
	$lang = $lang === '' ? qtu_current_language() : strtolower( $lang );
	if ( ! qtu_is_enabled( $lang ) ) { return ''; }
	$parts = qtu_parse_local_url( $url );
	if ( ! $parts || ! qtu_is_local_url( $parts ) || qtu_is_neutral_path( $parts['path'] ) ) { return $url !== '' ? $url : ( $parts ? qtu_assemble_url( $parts ) : '' ); }
	$show = ! qtu_config( 'hide_default_language' ) || $lang !== qtu_default_language() || $show_default === true;
	return qtu_url_for_language( $url, $lang, $show );
}

function qtu_normalize_request() {
	if ( is_admin() ) { qtu_set_language( qtu_default_language() ); return; }
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) { qtu_set_language( qtu_default_language() ); return; }
	$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	$GLOBALS['qtu_original_uri'] = $uri;
	$parts = qtu_parse_local_url( '' );
	$rel = qtu_relative_path( $parts['path'] );
	$bare = qtu_strip_path_language( $rel );
	$mode = (int) qtu_config( 'url_mode' );
	$lang = '';
	$explicit = false;
	if ( isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) && qtu_is_enabled( strtolower( $_GET['lang'] ) ) ) { $lang = strtolower( $_GET['lang'] ); $explicit = true; }
	if ( ! $lang && $bare !== $rel ) { $lang = strtolower( substr( $rel, 1, 2 ) ); $explicit = true; }
	if ( ! $lang && $mode === QTU_URL_DOMAIN && preg_match( '/^([a-z]{2})\./', $parts['host'], $match ) && qtu_is_enabled( $match[1] ) ) { $lang = $match[1]; $explicit = true; }
	if ( ! $lang && $mode === QTU_URL_DOMAINS ) {
		foreach ( (array) qtu_config( 'domains' ) as $code => $domain ) {
			$origin = qtu_domain_parts( $domain );
			if ( $origin && $origin['host'] === $parts['host'] && qtu_effective_port( $origin + array( 'scheme' => $parts['scheme'] ) ) === qtu_effective_port( $parts ) && qtu_is_enabled( $code ) ) { $lang = $code; $explicit = true; break; }
		}
	}
	if ( $bare !== $rel ) { $_SERVER['REQUEST_URI'] = qtu_home_parts()['path'] . $bare . ( $parts['query'] !== '' ? '?' . $parts['query'] : '' ); }
	if ( qtu_is_rest_request() || qtu_is_neutral_path( $parts['path'] ) || qtu_is_sitemap_request() ) { qtu_set_language( qtu_is_sitemap_request() ? qtu_default_language() : ( $lang ?: qtu_default_language() ) ); return; }
	$is_home = $bare === '/';
	$negotiated = false;
	if ( ! $lang && $is_home ) {
		$lang = qtu_cookie_front();
		if ( ! $lang && qtu_config( 'detect_browser_language' ) ) { $lang = qtu_browser_language(); }
		$negotiated = $lang !== '';
	}
	qtu_set_language( $lang ?: qtu_default_language() );
	$lang = qtu_current_language();
	if ( $explicit || $negotiated ) { qtu_set_front_cookie( $lang ); }
	$target = qtu_url_for_language( '', $lang, ! qtu_config( 'hide_default_language' ) || $lang !== qtu_default_language() );
	global $q_config;
	$q_config['url_info'] = array_merge( $parts, array( 'url' => $target, 'original_url' => qtu_assemble_url( $parts ), 'wp-path' => $bare, 'language' => $lang, 'doing_front_end' => true ) );
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
	$current = ( is_ssl() ? 'https' : 'http' ) . '://' . ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' ) . $uri;
	if ( $is_home && ! $explicit ) {
		// The default-language 200 response is personalized too, not just redirects.
		nocache_headers();
		header( 'Vary: Accept-Language, Cookie', false );
	}
	if ( in_array( $method, array( 'GET', 'HEAD' ), true ) && $current !== $target ) {
		// Target host can only come from qtu_allowed_origins() or the canonical home.
		wp_redirect( $target, 302, 'qTrad' );
		exit;
	}
	$_SERVER['REQUEST_URI'] = qtu_home_parts()['path'] . $bare . ( qtu_strip_query_lang( $parts['query'] ) !== '' ? '?' . qtu_strip_query_lang( $parts['query'] ) : '' );
}

function qtu_filter_home_url( $url, $path = '' ) {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || qtu_is_rest_request() || ! empty( $GLOBALS['qtn_seo_neutral_url'] ) || qtu_is_sitemap_request() ) {
		return $url;
	}
	$path = (string) $path;
	if ( $path !== '' && qtu_is_neutral_path( '/' . ltrim( $path, '/' ) ) ) {
		return $url;
	}
	$url_path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( qtu_is_neutral_path( $url_path ) ) {
		return $url;
	}
	return qtu_convert_url( $url, qtu_current_language(), false, null );
}

function qtu_filter_redirect_canonical( $redirect, $requested ) {
	if ( ! $redirect ) {
		return $redirect;
	}
	$strip = function ( $url ) {
		return qtu_url_for_language( $url, qtu_default_language(), false );
	};
	if ( $strip( $redirect ) === $strip( $requested ) ) {
		return false;
	}
	return qtu_convert_url( $redirect, qtu_current_language(), false, null );
}

function qtu_filter_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}
	$lang   = qtu_current_language();
	$locales = qtu_config( 'locale' );
	$locale = ( is_array( $locales ) && ! empty( $locales[ $lang ] ) ) ? $locales[ $lang ] : $lang;
	$html   = str_replace( '_', '-', $locale );
	$dir    = qtu_language_direction( $lang );
	$xml = strpos( $output, 'xml:lang=' ) !== false;
	$output = preg_replace( '/(?:^|\s+)(?:(?:xml:)?lang|dir)=(["\']).*?\1/i', '', $output );
	return trim( $output ) . ' lang="' . esc_attr( $html ) . '"' . ( $xml ? ' xml:lang="' . esc_attr( $html ) . '"' : '' ) . ' dir="' . esc_attr( $dir ) . '"';
}

function qtu_flag_css() {
	$css = '';
	foreach ( qtu_enabled_languages() as $lang ) {
		$url = qtu_flag_url( $lang );
		if ( ! $url ) {
			continue;
		}
		$css .= '.qtrans_flag_' . $lang . ',.qtranxs_flag_' . $lang . '{background-image:url(' . esc_url( $url ) . ');background-repeat:no-repeat;background-position:center;background-size:contain;}';
	}
	return $css;
}

function qtu_flag_url( $lang ) {
	$flags = qtu_config( 'flag' );
	$file  = ( is_array( $flags ) && ! empty( $flags[ $lang ] ) ) ? basename( $flags[ $lang ] ) : '';
	// Resolve predecessor PNG settings without rewriting shared options.
	$aliases = array( 'arle.png' => 'arab.svg', 'galego.png' => 'es-ga.svg', 'catalonia.png' => 'es-ct.svg', 'basque.png' => 'es-pv.svg', 'wales.png' => 'gb-wls.svg' );
	if ( isset( $aliases[ $file ] ) ) {
		$file = $aliases[ $file ];
	} elseif ( preg_match( '/^[a-z]{2}\.png$/D', $file ) && is_file( QTU_DIR . 'flags/' . substr( $file, 0, -4 ) . '.svg' ) ) {
		$file = substr( $file, 0, -4 ) . '.svg';
	}
	if ( $file === '' || ! file_exists( QTU_DIR . 'flags/' . $file ) ) {
		return '';
	}
	return plugins_url( 'flags/' . $file, QTU_FILE );
}

function qtu_post_available_languages( $post ) {
	if ( ! $post ) { return array(); }
	$text = qtu_has_lang_tags( $post->post_content ) ? $post->post_content : $post->post_title;
	if ( ! qtu_has_lang_tags( $text ) ) { return qtu_enabled_languages(); }
	$found = array();
	qtu_split_blocks( qtu_language_blocks( $text ), qtu_enabled_languages(), $found );
	return array_values( array_filter( qtu_enabled_languages(), function ( $lang ) use ( $found ) { return ! empty( $found[ $lang ] ); } ) );
}

function qtu_head_links() {
	qtu_seo_head_links();
}

/** Cache exact codec availability when a post changes; existing posts need no migration. */
function qtu_index_languages( $post_id, $post ) {
	if ( $post->post_type === 'revision' ) { return; }
	$text = qtu_has_lang_tags( $post->post_content ) ? $post->post_content : $post->post_title;
	$found = array();
	qtu_split_blocks( qtu_language_blocks( $text ), qtu_enabled_languages(), $found );
	$available = array_keys( array_filter( $found ) );
	update_post_meta( $post_id, '_qtn_available_languages', qtu_has_lang_tags( $text ) ? '|' . implode( '|', $available ) . '|' : '*' );
}

function qtu_hide_untranslated_where( $where, $query ) {
	if ( is_admin() || ! qtu_config( 'hide_untranslated' ) || ! ( $query instanceof WP_Query ) || $query->is_singular() || $query->get( 'qtn_seo_sitemap' ) || qtu_is_sitemap_request() || ! empty( $GLOBALS['qtn_seo_sitemap_build'] ) ) { return $where; }
	return $where . qtu_available_language_where( qtu_current_language() );
}

/** SQL availability shared by archive filtering and language-neutral sitemaps. */
function qtu_available_language_where( $lang ) {
	global $wpdb;
	$marker = '(\\[:[a-z]{2}\\]|<!--:[a-z]{2}-->|\\{:[a-z]{2}\\})';
	// Exclude whitespace and immediately following markers, including empty blocks.
	$content = '(\\[:' . $lang . '\\][[:space:]]*([^[:space:]\\[]|\\[[^:])|<!--:' . $lang . '-->[[:space:]]*([^[:space:]<]|<[^!]|<![^-]|<!-[^-]|<!--[^:])|\\{:' . $lang . '\\}[[:space:]]*([^[:space:]{}]|\\{[^:]))';
	$field = "CASE WHEN {$wpdb->posts}.post_content REGEXP %s THEN {$wpdb->posts}.post_content ELSE {$wpdb->posts}.post_title END";
	return $wpdb->prepare(
		" AND (EXISTS (SELECT 1 FROM {$wpdb->postmeta} qtn_available WHERE qtn_available.post_id = {$wpdb->posts}.ID AND qtn_available.meta_key = '_qtn_available_languages' AND (qtn_available.meta_value = '*' OR qtn_available.meta_value LIKE %s))
		OR (NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} qtn_index WHERE qtn_index.post_id = {$wpdb->posts}.ID AND qtn_index.meta_key = '_qtn_available_languages')
		AND (($field) NOT REGEXP %s OR ($field) REGEXP %s)))",
		'%|' . $lang . '|%', $marker, $marker, $marker, $content
	);
}

function qtu_filter_get_term( $term ) {
	if ( ! is_object( $term ) || ! isset( $term->name ) || qtu_skip_term_translate( $term ) ) {
		return $term;
	}
	$term = clone $term;
	$translations = isset( $term->term_id ) ? get_term_meta( $term->term_id, '_qtn_translations', true ) : array();
	$language = qtu_current_language();
	$term->name = is_array( $translations ) && isset( $translations[ $language ] ) && $translations[ $language ] !== '' ? $translations[ $language ] : qtu_translate_term_name( $term->name );
	if ( isset( $term->description ) ) {
		$term->description = qtu_use_current( $term->description );
	}
	return $term;
}

function qtu_filter_get_terms( $terms ) {
	if ( ! is_array( $terms ) ) {
		return $terms;
	}
	foreach ( $terms as $index => $term ) {
		$terms[ $index ] = qtu_filter_get_term( $term );
	}
	return $terms;
}

function qtu_skip_term_translate( $term ) {
	if ( defined( 'QTU_RAW_TERMS' ) && QTU_RAW_TERMS ) {
		return true;
	}
	if ( ! is_admin() ) {
		return false;
	}
	return ! wp_doing_ajax();
}

function qtu_register_front_filters() {
	$title = 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage';
	$body  = 'qtranxf_useCurrentLanguageIfNotFoundShowAvailable';
	foreach ( array( 'the_content', 'the_excerpt', 'category_description', 'term_description' ) as $filter ) {
		add_filter( $filter, $body, 0 );
	}
	foreach (
		array(
			'the_title',
			'widget_title',
			'widget_text',
			'widget_block_content',
			'nav_menu_item_title',
			'wp_nav_menu_items',
			'single_post_title',
			'list_cats',
			'single_cat_title',
			'single_tag_title',
			'document_title_parts',
		) as $filter
	) {
		add_filter( $filter, $title, 0 );
	}
	if ( ! is_admin() ) {
		add_filter( 'option_blogname', $title, 0 );
		add_filter( 'option_blogdescription', $title, 0 );
	}
	add_filter( 'home_url', 'qtu_filter_home_url', 0, 2 );
	add_filter( 'redirect_canonical', 'qtu_filter_redirect_canonical', 10, 2 );
	add_filter( 'language_attributes', 'qtu_filter_language_attributes' );
	add_filter( 'determine_locale', 'qtu_filter_locale' );
	add_action( 'wp_head', 'qtu_head_links', 2 );
	add_filter( 'get_term', 'qtu_filter_get_term' );
	add_filter( 'get_terms', 'qtu_filter_get_terms' );
	if ( qtu_config( 'hide_untranslated' ) ) {
		add_filter( 'posts_where_request', 'qtu_hide_untranslated_where', 10, 2 );
	}
	add_filter( 'body_class', 'qtu_body_class' );
}

function qtu_filter_locale( $locale ) {
	if ( is_admin() ) {
		return $locale;
	}
	$locales = qtu_config( 'locale' );
	$lang    = qtu_current_language();
	if ( is_array( $locales ) && ! empty( $locales[ $lang ] ) ) {
		return $locales[ $lang ];
	}
	return $locale;
}

function qtu_body_class( $classes ) {
	$classes[] = 'qtu-lang-' . sanitize_html_class( qtu_current_language() );
	return $classes;
}
