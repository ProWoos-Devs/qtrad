<?php
/**
 * Translated slugs, stored the way qTranslate-XT stores them: post and term
 * meta qtranslate_slug_{lang}, and translated URL bases of post types and
 * taxonomies in the option qtranslate_module_slugs. Slugs left by the older
 * qTranslate Slug plugin (_qts_slug_{lang}) are read as well.
 *
 * WordPress keeps working with the stored post and term slugs. A request for a
 * translated address is mapped to the stored one before WordPress parses it,
 * and links leave in the slugs of the language they are generated for.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_slug_hooks() {
	foreach ( array( 'added_post_meta', 'updated_post_meta', 'added_term_meta', 'updated_term_meta' ) as $hook ) {
		add_action( $hook, 'qtrad_slugs_note_meta', 10, 3 );
	}
	add_filter( 'post_link', 'qtrad_slugs_filter_post_link', 20, 3 );
	add_filter( 'page_link', 'qtrad_slugs_filter_page_link', 20, 3 );
	add_filter( 'post_type_link', 'qtrad_slugs_filter_post_type_link', 20, 4 );
	add_filter( 'term_link', 'qtrad_slugs_filter_term_link', 20, 2 );
	if ( is_admin() ) {
		return;
	}
	add_filter( 'do_parse_request', 'qtrad_slugs_resolve_request', PHP_INT_MAX - 1 );
	add_filter( 'redirect_canonical', 'qtrad_slugs_filter_redirect_canonical', 11 );
	add_action( 'template_redirect', 'qtrad_slugs_redirect', 1 );
}

function qtrad_slugs_meta_keys( $lang ) {
	return array( 'qtranslate_slug_' . $lang, '_qts_slug_' . $lang );
}

/**
 * Whether the site stores any translated slug or base. Sites without them skip all of this.
 *
 * @param string $state 'found' records that a slug was stored, 'refresh' looks again.
 */
function qtrad_slugs_active( $state = '' ) {
	static $active = array();
	$blog = get_current_blog_id();
	if ( 'found' === $state ) {
		update_option( 'qtrad_slugs_present', '1' );
		$active[ $blog ] = true;
		return true;
	}
	if ( 'refresh' === $state ) {
		unset( $active[ $blog ] );
		delete_option( 'qtrad_slugs_present' );
	}
	if ( isset( $active[ $blog ] ) ) {
		return $active[ $blog ];
	}
	$stored = get_option( 'qtrad_slugs_present', '' );
	if ( '1' !== $stored && '0' !== $stored ) {
		global $wpdb;
		$like = array( $wpdb->esc_like( 'qtranslate_slug_' ) . '%', $wpdb->esc_like( '_qts_slug_' ) . '%' );
		// One-time presence check; no core API looks up meta by key pattern.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = qtrad_slugs_all_bases()
			|| $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s LIMIT 1", $like[0], $like[1] ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->termmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s LIMIT 1", $like[0], $like[1] ) );
		// phpcs:enable
		$stored = $found ? '1' : '0';
		update_option( 'qtrad_slugs_present', $stored );
	}
	$active[ $blog ] = '1' === $stored;
	return $active[ $blog ];
}

function qtrad_slugs_note_meta( $meta_id, $object_id, $meta_key ) {
	if ( is_string( $meta_key ) && ( 0 === strpos( $meta_key, 'qtranslate_slug_' ) || 0 === strpos( $meta_key, '_qts_slug_' ) ) && ! qtrad_slugs_active() ) {
		qtrad_slugs_active( 'found' );
	}
}

/**
 * Post links take a translated slug only where the request can be mapped back:
 * the post name has to be a path segment of its own, optionally with an extension.
 */
function qtrad_slugs_post_structure() {
	return (bool) preg_match( '~(?:^|/)%postname%(?:\.[a-z0-9]+)?(?:/|$)~i', (string) get_option( 'permalink_structure' ) );
}

/**
 * Translated slug of a post or term, or '' when it has none in that language.
 * An empty qtranslate_slug_{lang} means none, even if an older _qts_slug_{lang} is left.
 */
function qtrad_slugs_get( $type, $id, $lang ) {
	if ( '' === $lang ) {
		return '';
	}
	foreach ( qtrad_slugs_meta_keys( $lang ) as $key ) {
		if ( metadata_exists( $type, $id, $key ) ) {
			$slug = get_metadata( $type, $id, $key, true );
			return is_string( $slug ) ? $slug : '';
		}
	}
	return '';
}

/**
 * Translated URL bases as qTranslate-XT stores them.
 *
 * @return array[] Bases by "post_type_{name}" or "taxonomy_{name}", then by language.
 */
function qtrad_slugs_all_bases() {
	$bases = array();
	foreach ( (array) get_option( 'qtranslate_module_slugs', array() ) as $name => $translations ) {
		if ( ! is_string( $name ) || ! is_array( $translations ) || ! preg_match( '/^(?:post_type|taxonomy)_./', $name ) ) {
			continue;
		}
		foreach ( $translations as $lang => $base ) {
			$base = is_string( $base ) ? trim( $base, '/' ) : '';
			if ( '' !== $base ) {
				$bases[ $name ][ $lang ] = $base;
			}
		}
	}
	return $bases;
}

/** Translated base of a post type or taxonomy in a language, '' when it has none. */
function qtrad_slugs_base( $kind, $name, $lang ) {
	if ( ! empty( $GLOBALS['qtrad_slugs_stored_bases'] ) ) {
		return '';
	}
	$bases = qtrad_slugs_all_bases();
	return '' !== $lang && isset( $bases[ $kind . '_' . $name ][ $lang ] ) ? $bases[ $kind . '_' . $name ][ $lang ] : '';
}

/** The address qTranslate-XT builds under a translated base: the base, then the slug path. */
function qtrad_slugs_based_url( $base, $path, $type = '' ) {
	return home_url( user_trailingslashit( '/' . $base . '/' . $path, $type ) );
}

/**
 * Language whose slugs a link gets: the current one wherever links also carry
 * the language, '' (the stored slugs) in wp-admin, REST, sitemaps and while
 * WordPress parses the request.
 */
function qtrad_slugs_language() {
	if ( isset( $GLOBALS['qtrad_slugs_language'] ) ) {
		return $GLOBALS['qtrad_slugs_language'];
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || qtrad_is_rest_request() || ! empty( $GLOBALS['qtrad_seo_neutral_url'] ) || ! empty( $GLOBALS['qtrad_parsing_request'] ) || ! empty( $GLOBALS['qtrad_seo_sitemap_build'] ) || qtrad_is_sitemap_request() ) {
		return '';
	}
	return qtrad_current_language();
}

/** Run $callback with links generated in the slugs of $lang ('' for the stored slugs). */
function qtrad_slugs_in_language( $lang, $callback ) {
	$previous = isset( $GLOBALS['qtrad_slugs_language'] ) ? $GLOBALS['qtrad_slugs_language'] : null;
	$GLOBALS['qtrad_slugs_language'] = $lang;
	try {
		return $callback();
	} finally {
		if ( null === $previous ) {
			unset( $GLOBALS['qtrad_slugs_language'] );
		} else {
			$GLOBALS['qtrad_slugs_language'] = $previous;
		}
	}
}

function qtrad_slugs_post_name( $post, $lang ) {
	$slug = qtrad_slugs_get( 'post', $post->ID, $lang );
	return '' !== $slug ? $slug : $post->post_name;
}

/** get_page_uri() with each page in the path under its translated slug. */
function qtrad_slugs_page_uri( $post, $lang ) {
	$uri = qtrad_slugs_post_name( $post, $lang );
	foreach ( get_post_ancestors( $post ) as $ancestor ) {
		$ancestor = get_post( $ancestor );
		if ( $ancestor && $ancestor->post_name ) {
			$uri = qtrad_slugs_post_name( $ancestor, $lang ) . '/' . $uri;
		}
	}
	return $uri;
}

function qtrad_slugs_term_path( $term, $lang, $hierarchical = true ) {
	$slug = qtrad_slugs_get( 'term', $term->term_id, $lang );
	$path = array( '' !== $slug ? $slug : $term->slug );
	if ( $hierarchical ) {
		foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor ) {
			$ancestor = get_term( $ancestor, $term->taxonomy );
			if ( $ancestor instanceof WP_Term ) {
				$slug = qtrad_slugs_get( 'term', $ancestor->term_id, $lang );
				array_unshift( $path, '' !== $slug ? $slug : $ancestor->slug );
			}
		}
	}
	return implode( '/', $path );
}

/** Replace the first or last whole occurrence of a path inside the path of a URL. */
function qtrad_slugs_swap_path( $url, $from, $to, $last = false ) {
	if ( '' === $from || $from === $to || ! preg_match( '~^((?:[a-z][a-z0-9+.-]*:)?//[^/?#]*)?([^?#]*)(.*)$~is', $url, $match ) ) {
		return $url;
	}
	$path = preg_replace_callback(
		'~^(.*' . ( $last ? '' : '?' ) . ')/' . preg_quote( $from, '~' ) . '(?=/|$)~s',
		function ( $found ) use ( $to ) {
			return $found[1] . '/' . $to;
		},
		$match[2],
		1
	);
	return $match[1] . $path . $match[3];
}

/** The category WordPress puts into %category%, as its stored and its translated path. */
function qtrad_slugs_post_category_paths( $post, $lang ) {
	$categories = get_the_category( $post->ID );
	if ( $categories ) {
		$categories = wp_list_sort( $categories, array( 'term_id' => 'ASC' ) );
		/** This filter is documented in wp-includes/link-template.php */
		$category = get_term( apply_filters( 'post_link_category', $categories[0], $categories, $post ), 'category' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied as core does.
	} else {
		$category = get_term( (int) get_option( 'default_category' ), 'category' );
	}
	return $category instanceof WP_Term ? array( qtrad_slugs_term_path( $category, '' ), qtrad_slugs_term_path( $category, $lang ) ) : null;
}

function qtrad_slugs_filter_post_link( $permalink, $post, $leavename ) {
	$lang = qtrad_slugs_language();
	if ( $leavename || '' === $lang || ! $post instanceof WP_Post || ! qtrad_slugs_active() ) {
		return $permalink;
	}
	$slug     = qtrad_slugs_post_structure() ? qtrad_slugs_get( 'post', $post->ID, $lang ) : '';
	$category = false !== strpos( (string) get_option( 'permalink_structure' ), '%category%' ) ? qtrad_slugs_post_category_paths( $post, $lang ) : null;
	if ( $category && $category[0] === $category[1] ) {
		$category = null;
	}
	if ( '' === $slug && ! $category ) {
		return $permalink;
	}
	if ( '' !== $slug ) {
		// With $leavename WordPress leaves the placeholder where the slug goes.
		$template = get_permalink( $post, true );
		if ( is_string( $template ) && false !== strpos( $template, '%postname%' ) ) {
			$permalink = str_replace( '%postname%', $slug, $template );
		}
	}
	return $category ? qtrad_slugs_swap_path( $permalink, $category[0], $category[1] ) : $permalink;
}

function qtrad_slugs_filter_page_link( $link, $post_id, $sample = false ) {
	static $busy = false;
	$lang = qtrad_slugs_language();
	if ( $busy || '' === $lang || ! qtrad_slugs_active() ) {
		return $link;
	}
	$post = get_post( $post_id );
	if ( ! $post || ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID ) ) {
		return $link;
	}
	$translated = qtrad_slugs_page_uri( $post, $lang );
	if ( get_page_uri( $post ) === $translated ) {
		return $link;
	}
	$busy     = true;
	$template = get_page_link( $post, true, $sample );
	$busy     = false;
	return is_string( $template ) && false !== strpos( $template, '%pagename%' ) ? str_replace( '%pagename%', $translated, $template ) : $link;
}

function qtrad_slugs_filter_post_type_link( $link, $post, $leavename = false, $sample = false ) {
	$lang = qtrad_slugs_language();
	if ( $leavename || '' === $lang || ! $post instanceof WP_Post || ! qtrad_slugs_active() ) {
		return $link;
	}
	$type         = get_post_type_object( $post->post_type );
	$hierarchical = $type && $type->hierarchical;
	$translated   = $hierarchical ? qtrad_slugs_page_uri( $post, $lang ) : qtrad_slugs_post_name( $post, $lang );
	$base         = qtrad_slugs_base( 'post_type', $post->post_type, $lang );
	if ( '' !== $base && is_string( $link ) && false === strpos( $link, '?' ) ) {
		return qtrad_slugs_based_url( $base, $translated );
	}
	if ( ( $hierarchical ? get_page_uri( $post ) : $post->post_name ) === $translated ) {
		return $link;
	}
	$template = get_post_permalink( $post, true, $sample );
	$tag      = '%' . $post->post_type . '%';
	return is_string( $template ) && false !== strpos( $template, $tag ) ? str_replace( $tag, $translated, $template ) : $link;
}

function qtrad_slugs_filter_term_link( $link, $term ) {
	$lang = qtrad_slugs_language();
	if ( '' === $lang || ! $term instanceof WP_Term || ! is_string( $link ) || ! get_option( 'permalink_structure' ) || ! qtrad_slugs_active() ) {
		return $link;
	}
	$taxonomy     = get_taxonomy( $term->taxonomy );
	$hierarchical = $taxonomy && ! empty( $taxonomy->rewrite['hierarchical'] );
	$base         = qtrad_slugs_base( 'taxonomy', $term->taxonomy, $lang );
	if ( '' !== $base && false === strpos( $link, '?' ) ) {
		return qtrad_slugs_based_url( $base, qtrad_slugs_term_path( $term, $lang, $hierarchical ), 'category' );
	}
	return qtrad_slugs_swap_path( $link, qtrad_slugs_term_path( $term, '', $hierarchical ), qtrad_slugs_term_path( $term, $lang, $hierarchical ), true );
}

/** Address of a post or term without a language, in the slugs of $lang ('' for the stored ones). */
function qtrad_slugs_object_url( $type, $id, $lang ) {
	$previous = ! empty( $GLOBALS['qtrad_seo_neutral_url'] );
	$GLOBALS['qtrad_seo_neutral_url'] = true;
	try {
		$url = qtrad_slugs_in_language(
			$lang,
			function () use ( $type, $id ) {
				return 'term' === $type ? get_term_link( (int) $id ) : get_permalink( (int) $id );
			}
		);
	} finally {
		$GLOBALS['qtrad_seo_neutral_url'] = $previous;
	}
	return is_string( $url ) ? $url : '';
}

/** Path segments in one spelling, so encoded and plain requests compare equal. */
function qtrad_slugs_segments( $path ) {
	$segments = array();
	foreach ( explode( '/', (string) $path ) as $segment ) {
		if ( '' !== $segment ) {
			$segments[] = strtolower( rawurlencode( rawurldecode( $segment ) ) );
		}
	}
	return $segments;
}

/** Path below the home address, as segments. Empty for addresses that are not pretty permalinks. */
function qtrad_slugs_url_segments( $url ) {
	if ( '' === $url || false !== strpos( $url, '?' ) ) {
		return array();
	}
	return qtrad_slugs_segments( qtrad_relative_path( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
}

/**
 * The post or term whose address in $lang starts the requested path.
 *
 * @return array|null Object type, id and the number of segments its address takes.
 */
function qtrad_slugs_find_object( $segments, $lang ) {
	global $wpdb;
	$keys   = qtrad_slugs_meta_keys( $lang );
	$values = $segments;
	foreach ( $segments as $segment ) {
		// A permalink structure may end the post name with an extension.
		$values[] = preg_replace( '/\.[a-z0-9]+$/', '', $segment );
		$values[] = rawurldecode( $segment );
	}
	$values = array_values( array_unique( array_filter( $values, 'strlen' ) ) );
	$in     = implode( ',', array_fill( 0, count( $values ), '%s' ) );
	$args   = array_merge( $keys, $values );
	// Slugs are looked up by value across objects. $in holds one %s per value, and $args carries the two keys followed by the values.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	$posts = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) AND meta_value IN ($in) LIMIT 50", $args ) );
	$terms = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE meta_key IN (%s, %s) AND meta_value IN ($in) LIMIT 50", $args ) );
	// phpcs:enable
	if ( ! $posts && ! $terms && ! qtrad_slugs_starts_with_base( $segments, $lang ) ) {
		return null;
	}
	$match = qtrad_slugs_best_match( $segments, $lang, $posts, $terms );
	if ( $match ) {
		return $match;
	}
	// Only a parent, the category or the base is translated: find the object by its stored slug.
	$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
	$posts = $types ? get_posts( array( 'post_name__in' => $values, 'post_type' => $types, 'post_status' => array( 'publish', 'private' ), 'fields' => 'ids', 'posts_per_page' => 50, 'orderby' => 'none', 'no_found_rows' => true ) ) : array();
	$taxes = get_taxonomies( array( 'public' => true ) );
	$terms = $taxes ? get_terms( array( 'taxonomy' => $taxes, 'slug' => $values, 'hide_empty' => false, 'fields' => 'ids', 'number' => 50 ) ) : array();
	return qtrad_slugs_best_match( $segments, $lang, $posts, is_array( $terms ) ? $terms : array() );
}

/** Whether the path starts with a translated base of that language. */
function qtrad_slugs_starts_with_base( $segments, $lang ) {
	foreach ( qtrad_slugs_all_bases() as $translations ) {
		$base = isset( $translations[ $lang ] ) ? qtrad_slugs_segments( $translations[ $lang ] ) : array();
		if ( $base && array_slice( $segments, 0, count( $base ) ) === $base ) {
			return true;
		}
	}
	return false;
}

function qtrad_slugs_best_match( $segments, $lang, $posts, $terms ) {
	$best  = null;
	$bases = (bool) qtrad_slugs_all_bases();
	foreach ( array( 'post' => $posts, 'term' => $terms ) as $type => $ids ) {
		foreach ( $ids as $id ) {
			if ( 'post' === $type ) {
				$post = get_post( (int) $id );
				if ( ! $post || 'attachment' === $post->post_type || ! is_post_type_viewable( $post->post_type ) ) {
					continue;
				}
			}
			$paths = array( qtrad_slugs_url_segments( qtrad_slugs_object_url( $type, $id, $lang ) ) );
			if ( $bases ) {
				// A translated slug under the stored base is an address from before the base was translated.
				$GLOBALS['qtrad_slugs_stored_bases'] = true;
				$paths[] = qtrad_slugs_url_segments( qtrad_slugs_object_url( $type, $id, $lang ) );
				unset( $GLOBALS['qtrad_slugs_stored_bases'] );
			}
			foreach ( $paths as $path ) {
				$length = count( $path );
				if ( $length && $length <= count( $segments ) && array_slice( $segments, 0, $length ) === $path && ( ! $best || $length > $best[2] ) ) {
					$best = array( $type, (int) $id, $length );
				}
			}
		}
	}
	return $best;
}

/**
 * Map a request for a translated address to the stored one, so WordPress
 * finds the post or term. Whatever follows the address (page numbers, feeds,
 * endpoints) is kept.
 */
function qtrad_slugs_resolve_request( $parse ) {
	if ( ! $parse || ! empty( $GLOBALS['qtrad_slugs_rewritten'] ) || is_admin() || ! get_option( 'permalink_structure' ) || ! qtrad_slugs_active() || ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
		return $parse;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared with stored slugs and written back as a request path.
	$uri   = wp_unslash( $_SERVER['REQUEST_URI'] );
	$query = strpos( $uri, '?' );
	$path  = false === $query ? $uri : substr( $uri, 0, $query );
	$rel   = qtrad_relative_path( $path );
	if ( qtrad_is_rest_request() || qtrad_is_sitemap_request() ) {
		return $parse;
	}
	$segments = qtrad_slugs_segments( $rel );
	if ( ! $segments || count( $segments ) > 12 ) {
		return $parse;
	}
	$match = qtrad_slugs_find_object( $segments, qtrad_current_language() );
	if ( ! $match ) {
		return $parse;
	}
	$stored = qtrad_slugs_url_segments( qtrad_slugs_object_url( $match[0], $match[1], '' ) );
	if ( ! $stored || array_slice( $segments, 0, $match[2] ) === $stored ) {
		return $parse;
	}
	$raw  = array_values( array_filter( explode( '/', $rel ), 'strlen' ) );
	$new  = '/' . implode( '/', array_merge( $stored, array_slice( $raw, $match[2] ) ) ) . ( '/' === substr( $rel, -1 ) ? '/' : '' );
	$home = qtrad_home_parts();
	$_SERVER['REQUEST_URI'] = wp_slash( $home['path'] . $new . ( false === $query ? '' : substr( $uri, $query ) ) );
	foreach ( array( 'PATH_INFO', 'PHP_SELF' ) as $name ) {
		if ( empty( $_SERVER[ $name ] ) || ! is_string( $_SERVER[ $name ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) );
		foreach ( array_unique( array( $rel, rawurldecode( $rel ) ) ) as $old ) {
			if ( substr( $value, -strlen( $old ) ) === $old ) {
				$_SERVER[ $name ] = wp_slash( substr( $value, 0, -strlen( $old ) ) . $new );
				break;
			}
		}
	}
	$GLOBALS['qtrad_slugs_rewritten'] = true;
	return $parse;
}

/** The post or term the current page is about, once the main query has run. */
function qtrad_slugs_current_object() {
	if ( ! did_action( 'wp' ) || ! qtrad_slugs_active() || is_front_page() ) {
		return null;
	}
	$object = ( is_singular() || is_home() || is_category() || is_tag() || is_tax() ) ? get_queried_object() : null;
	if ( $object instanceof WP_Post ) {
		return 'attachment' === $object->post_type ? null : array( 'post', (int) $object->ID );
	}
	return $object instanceof WP_Term ? array( 'term', (int) $object->term_id ) : null;
}

/**
 * A path of the current page with the part that names the queried object in
 * the slugs of $lang. Paths that do not start with that object come back unchanged.
 *
 * @param string $rel  Path below the home address, without a language.
 * @param string $lang Language for the slugs, '' for the stored ones.
 */
function qtrad_slugs_translate_path( $rel, $lang ) {
	static $paths = array();
	$object = qtrad_slugs_current_object();
	if ( ! $object ) {
		return $rel;
	}
	$key = get_current_blog_id() . ':' . implode( ':', $object );
	if ( ! isset( $paths[ $key ] ) ) {
		$paths[ $key ] = array();
		foreach ( array_merge( array( '' ), qtrad_enabled_languages() ) as $code ) {
			$paths[ $key ][ $code ] = qtrad_slugs_url_segments( qtrad_slugs_object_url( $object[0], $object[1], $code ) );
		}
	}
	if ( empty( $paths[ $key ][ $lang ] ) ) {
		return $rel;
	}
	$segments = qtrad_slugs_segments( $rel );
	foreach ( $paths[ $key ] as $path ) {
		$length = count( $path );
		if ( $length && array_slice( $segments, 0, $length ) === $path ) {
			if ( $path === $paths[ $key ][ $lang ] ) {
				return $rel;
			}
			$raw = array_values( array_filter( explode( '/', $rel ), 'strlen' ) );
			return '/' . implode( '/', array_merge( $paths[ $key ][ $lang ], array_slice( $raw, $length ) ) ) . ( '/' === substr( $rel, -1 ) ? '/' : '' );
		}
	}
	return $rel;
}

/** A local URL without its language, the queried object's part in the slugs of $lang. Other URLs come back unchanged. */
function qtrad_slugs_localize_url( $url, $lang ) {
	if ( ! is_string( $url ) || '' === $url || ! qtrad_slugs_current_object() ) {
		return $url;
	}
	$parts = qtrad_parse_local_url( $url );
	if ( ! $parts || ! qtrad_is_local_url( $parts ) ) {
		return $url;
	}
	$rel = qtrad_strip_path_language( qtrad_relative_path( $parts['path'] ) );
	$new = qtrad_slugs_translate_path( $rel, $lang );
	if ( $new === $rel ) {
		return $url;
	}
	$home           = qtrad_home_parts();
	$parts['path']  = $home['path'] . $new;
	$parts['query'] = qtrad_strip_query_lang( $parts['query'] );
	return qtrad_assemble_url( $parts );
}

/** The current page without a language, in the slugs of $lang. '' when the generic URL conversion is right. */
function qtrad_slugs_current_url( $lang ) {
	if ( ! qtrad_slugs_active() || ! did_action( 'wp' ) || ( empty( $GLOBALS['qtrad_slugs_rewritten'] ) && ! qtrad_slugs_current_object() ) ) {
		return '';
	}
	$home  = qtrad_home_parts();
	$parts = wp_parse_url( qtrad_request_uri() );
	$path  = is_array( $parts ) && isset( $parts['path'] ) ? $parts['path'] : '/';
	$home['path']    .= qtrad_slugs_translate_path( qtrad_relative_path( $path ), $lang );
	$home['query']    = is_array( $parts ) && isset( $parts['query'] ) ? $parts['query'] : '';
	$home['fragment'] = '';
	return qtrad_assemble_url( $home );
}

/** Core canonical redirects are computed on the stored address; send them to the translated one. */
function qtrad_slugs_filter_redirect_canonical( $redirect ) {
	if ( ! is_string( $redirect ) || '' === $redirect || ! qtrad_slugs_active() ) {
		return $redirect;
	}
	$lang      = qtrad_current_language();
	$localized = qtrad_slugs_localize_url( $redirect, $lang );
	if ( $localized !== $redirect ) {
		$redirect = qtrad_convert_url( $localized, $lang, false, null );
	}
	return $redirect === qtrad_slugs_requested_url() ? false : $redirect;
}

/** The address the visitor asked for, before the language and the slugs were mapped. */
function qtrad_slugs_requested_url() {
	$uri = isset( $GLOBALS['qtrad_original_uri'] ) ? $GLOBALS['qtrad_original_uri'] : qtrad_request_uri();
	return ( is_ssl() ? 'https' : 'http' ) . '://' . qtrad_request_host() . $uri;
}

/** A page reached under another language's slugs, or the stored ones, moves to its address in the current language. */
function qtrad_slugs_redirect() {
	if ( ! qtrad_slugs_active() || is_preview() || is_404() || qtrad_is_api_request() || ! qtrad_slugs_current_object() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$lang      = qtrad_current_language();
	$requested = qtrad_slugs_requested_url();
	$parts     = qtrad_parse_local_url( $requested );
	// Feeds and other paths without a language are served where they were asked for.
	if ( ! $parts || ! qtrad_is_local_url( $parts ) || qtrad_is_neutral_path( $parts['path'] ) ) {
		return;
	}
	// The request as WordPress resolved it, in the stored slugs, is the reference.
	$rel = qtrad_strip_path_language( qtrad_relative_path( $parts['path'] ) );
	$new = qtrad_slugs_translate_path( qtrad_relative_path( (string) wp_parse_url( qtrad_request_uri(), PHP_URL_PATH ) ), $lang );
	if ( qtrad_slugs_segments( $new ) === qtrad_slugs_segments( $rel ) ) {
		return;
	}
	$home           = qtrad_home_parts();
	$parts['path']  = $home['path'] . $new;
	$parts['query'] = qtrad_strip_query_lang( $parts['query'] );
	$target         = qtrad_convert_url( qtrad_assemble_url( $parts ), $lang, false, null );
	if ( '' === $target || $target === $requested ) {
		return;
	}
	add_filter( 'allowed_redirect_hosts', 'qtrad_allowed_redirect_hosts' );
	wp_safe_redirect( $target, 301, 'qTrad' );
	exit;
}
