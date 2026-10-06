<?php
/**
 * Language markers outside the post fields: custom fields, user profile
 * fields, options and text passed through other filters. The stored values
 * stay multilingual; only what a visitor's page reads is in one language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_value_hooks() {
	foreach ( qtrad_text_filters() as $hook ) {
		add_filter( $hook, 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' );
	}
	if ( qtrad_setting( 'translate_meta', false ) ) {
		// After the filter for the custom fields listed in the settings.
		add_filter( 'get_post_metadata', 'qtrad_filter_meta_all', 11, 5 );
		add_filter( 'get_user_metadata', 'qtrad_filter_meta_all', 11, 5 );
		add_filter( 'update_post_metadata', 'qtrad_filter_update_meta_all', 11, 5 );
		add_filter( 'update_user_metadata', 'qtrad_filter_update_meta_all', 11, 5 );
	}
	if ( qtrad_translates_values() ) {
		foreach ( qtrad_option_names_to_translate() as $name ) {
			add_filter( 'option_' . $name, 'qtrad_translate_option', 5 );
			add_filter( 'pre_update_option_' . $name, 'qtrad_filter_update_option', 5, 3 );
		}
	}
}

/**
 * Only pages rendered for a visitor read translated values. wp-admin, AJAX,
 * REST, cron and WP-CLI keep reading what is stored, since they also write it back.
 */
function qtrad_translates_values() {
	if ( isset( $GLOBALS['qtrad_translates_values'] ) ) {
		return (bool) $GLOBALS['qtrad_translates_values'];
	}
	return ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! qtrad_is_rest_request() && ! ( defined( 'WP_CLI' ) && WP_CLI );
}

/** Names from a setting stored as an array or as text separated by spaces. */
function qtrad_name_list( $value, $pattern ) {
	$names = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
	return array_values( array_unique( array_filter( array_map( 'trim', array_filter( $names, 'is_string' ) ), function ( $name ) use ( $pattern ) { return (bool) preg_match( $pattern, $name ); } ) ) );
}

/** Filter hooks whose text is shown in the current language, qTranslate-X's "text field filters". */
function qtrad_text_filters() {
	return qtrad_name_list( qtrad_config( 'text_field_filters' ), '/^[A-Za-z0-9_.:\/\-]+$/D' );
}

/** Option names to translate when only listed options are, with % and _ as SQL LIKE wildcards. */
function qtrad_option_patterns() {
	return qtrad_name_list( qtrad_config( 'filter_options' ), '/^[A-Za-z0-9_.:%\-]+$/D' );
}

/** Options qTrad reads itself or that are no content. */
function qtrad_option_is_raw( $name ) {
	return in_array( $name, array( 'cron', 'blogname', 'blogdescription', 'rewrite_rules', 'active_plugins' ), true ) || (bool) preg_match( '/^(?:qtranslate_|qtrad_|_transient_|_site_transient_)/', $name );
}

function qtrad_option_names_to_translate() {
	$mode = qtrad_setting( 'translate_options', 'none' );
	if ( 'all' !== $mode && 'list' !== $mode ) {
		return array();
	}
	$options = wp_load_alloptions();
	$names   = array();
	if ( 'all' === $mode ) {
		// Autoloaded options are in memory already, so this costs no query.
		foreach ( $options as $name => $value ) {
			if ( is_string( $value ) && ( false !== strpos( $value, '[:' ) || false !== strpos( $value, '<!--:' ) || false !== strpos( $value, '{:' ) ) && qtrad_has_lang_tags( $value ) ) {
				$names[] = (string) $name;
			}
		}
	} else {
		foreach ( qtrad_option_patterns() as $pattern ) {
			if ( false === strpos( $pattern, '%' ) ) {
				$names[] = $pattern;
				continue;
			}
			$regex = '/^' . str_replace( array( '%', '_' ), array( '.*', '.' ), preg_quote( $pattern, '/' ) ) . '$/D';
			foreach ( array_keys( $options ) as $name ) {
				if ( preg_match( $regex, (string) $name ) ) {
					$names[] = (string) $name;
				}
			}
		}
	}
	return array_values( array_unique( array_filter( $names, function ( $name ) { return ! qtrad_option_is_raw( $name ); } ) ) );
}

/** Strings with language markers, anywhere in nested arrays, in the current language. */
function qtrad_translate_deep( $value ) {
	if ( is_string( $value ) ) {
		if ( ! qtrad_has_lang_tags( $value ) ) {
			return $value;
		}
		if ( is_serialized( $value ) ) {
			$inner = maybe_unserialize( $value );
			return is_array( $inner ) ? maybe_serialize( qtrad_translate_deep( $inner ) ) : $value;
		}
		return qtrad_use_current( $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = qtrad_translate_deep( $item );
		}
	}
	return $value;
}

/**
 * Undo the translation of everything that was not changed: where a new value
 * equals the stored one as shown in the current language, the stored
 * multilingual value is kept. Code that reads a translated value and writes
 * it back then leaves the other languages in place.
 */
function qtrad_restore_raw( $new, $raw ) {
	if ( is_string( $raw ) ) {
		return is_string( $new ) && qtrad_has_lang_tags( $raw ) && ! qtrad_has_lang_tags( $new ) && qtrad_translate_deep( $raw ) === $new ? $raw : $new;
	}
	if ( is_array( $raw ) && is_array( $new ) ) {
		foreach ( $new as $key => $item ) {
			if ( array_key_exists( $key, $raw ) ) {
				$new[ $key ] = qtrad_restore_raw( $item, $raw[ $key ] );
			}
		}
	}
	return $new;
}

function qtrad_translate_option( $value ) {
	return empty( $GLOBALS['qtrad_raw_options'] ) ? qtrad_translate_deep( $value ) : $value;
}

/** Stored value of an option that visitors' pages read translated. */
function qtrad_raw_option( $name ) {
	$previous = ! empty( $GLOBALS['qtrad_raw_options'] );
	$GLOBALS['qtrad_raw_options'] = true;
	try {
		return get_option( $name );
	} finally {
		$GLOBALS['qtrad_raw_options'] = $previous;
	}
}

function qtrad_filter_update_option( $value, $old_value, $option ) {
	return qtrad_restore_raw( $value, qtrad_raw_option( $option ) );
}

/** Custom fields qTrad reads raw itself, or whose value is no text. */
function qtrad_meta_key_is_raw( $key ) {
	return '_menu_item_url' === $key || 0 === strpos( $key, '_qtrad_' );
}

/** Stored rows of all custom fields of a post or user, from the object cache. */
function qtrad_meta_rows( $type, $object_id ) {
	$cache = wp_cache_get( $object_id, $type . '_meta' );
	if ( ! is_array( $cache ) ) {
		$cache = update_meta_cache( $type, array( $object_id ) );
		$cache = is_array( $cache ) && isset( $cache[ $object_id ] ) && is_array( $cache[ $object_id ] ) ? $cache[ $object_id ] : array();
	}
	return $cache;
}

/** Any custom field of a post or user that holds language markers is read in the current language. */
function qtrad_filter_meta_all( $value, $object_id, $meta_key, $single, $type = 'post' ) {
	if ( null !== $value || ! empty( $GLOBALS['qtrad_raw_meta'] ) || ! qtrad_translates_values() ) {
		return $value;
	}
	$rows = qtrad_meta_rows( $type, $object_id );
	if ( '' === $meta_key || null === $meta_key ) {
		$changed = false;
		foreach ( $rows as $key => $values ) {
			if ( ! is_array( $values ) || qtrad_meta_key_is_raw( (string) $key ) ) {
				continue;
			}
			foreach ( $values as $index => $raw ) {
				if ( is_string( $raw ) && qtrad_has_lang_tags( $raw ) ) {
					// Without a key WordPress returns the rows as stored, serialized where they are arrays.
					$rows[ $key ][ $index ] = maybe_serialize( qtrad_translate_deep( maybe_unserialize( $raw ) ) );
					$changed                = true;
				}
			}
		}
		if ( ! $changed ) {
			return $value;
		}
		return $single ? array( $rows ) : $rows;
	}
	if ( ! is_string( $meta_key ) || empty( $rows[ $meta_key ] ) || ! is_array( $rows[ $meta_key ] ) || qtrad_meta_key_is_raw( $meta_key ) ) {
		return $value;
	}
	$marked = false;
	foreach ( $rows[ $meta_key ] as $raw ) {
		if ( is_string( $raw ) && qtrad_has_lang_tags( $raw ) ) {
			$marked = true;
			break;
		}
	}
	if ( ! $marked ) {
		return $value;
	}
	$translated = array();
	foreach ( $rows[ $meta_key ] as $raw ) {
		$translated[] = qtrad_translate_deep( maybe_unserialize( $raw ) );
	}
	return $single ? array( $translated[0] ) : $translated;
}

/**
 * A visitor's page that writes back a custom field it read translated would
 * drop the other languages. Unchanged parts keep their stored value.
 */
function qtrad_filter_update_meta_all( $check, $object_id, $meta_key, $meta_value, $prev_value, $type = '' ) {
	static $busy = false;
	if ( null !== $check || $busy || ! is_string( $meta_key ) || qtrad_meta_key_is_raw( $meta_key ) || ! qtrad_translates_values() ) {
		return $check;
	}
	$type = $type ? $type : ( 'update_user_metadata' === current_filter() ? 'user' : 'post' );
	$rows = qtrad_meta_rows( $type, $object_id );
	if ( empty( $rows[ $meta_key ] ) || ! is_array( $rows[ $meta_key ] ) || 1 !== count( $rows[ $meta_key ] ) || ! is_string( $rows[ $meta_key ][0] ) || ! qtrad_has_lang_tags( $rows[ $meta_key ][0] ) ) {
		return $check;
	}
	// WordPress has unslashed the value before this filter.
	$restored = qtrad_restore_raw( $meta_value, maybe_unserialize( $rows[ $meta_key ][0] ) );
	if ( $restored === $meta_value ) {
		return $check;
	}
	$busy = true;
	try {
		return (bool) update_metadata( $type, $object_id, $meta_key, wp_slash( $restored ), $prev_value );
	} finally {
		$busy = false;
	}
}
