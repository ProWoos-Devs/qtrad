<?php
/** Raw persistence is separate from language-dependent display filtering. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qtrad_register_field_hooks() {
	add_action( 'wp_after_insert_post', 'qtrad_index_languages', 10, 2 );
	add_filter( 'wp_insert_post_data', 'qtrad_filter_insert_post', 9, 2 );
	add_action( 'edit_terms', 'qtrad_capture_term_name', 10, 2 );
	add_action( 'edited_term', 'qtrad_save_term', 10, 3 );
	add_action( 'created_term', 'qtrad_save_term', 10, 3 );
	add_action( 'pre_delete_term', 'qtrad_capture_term_name', 10, 2 );
	add_action( 'delete_term', 'qtrad_delete_term_library', 10, 4 );
	add_filter( 'get_post_metadata', 'qtrad_filter_get_meta', 10, 4 );
	add_filter( 'update_post_metadata', 'qtrad_filter_update_meta', 10, 5 );
	add_action( 'rest_api_init', 'qtrad_register_rest_fields', 20 );
	add_filter( 'rest_request_before_callbacks', 'qtrad_rest_language_context', 10, 3 );
	add_filter( 'rest_request_after_callbacks', 'qtrad_restore_rest_language', 10, 3 );
	add_filter( 'block_editor_rest_api_preload_paths', 'qtrad_editor_preload_context', 10, 2 );
	add_filter( 'rest_request_after_callbacks', 'qtrad_editor_preload_response', 20, 3 );
}

/** Let Gutenberg parse the selected language on its first render, not raw markers. */
function qtrad_editor_preload_context( $paths, $context ) {
	unset( $GLOBALS['qtrad_editor_preload'] );
	if ( qtrad_setting( 'editor_mode', 'lsb' ) !== 'raw' && isset( $context->post ) && $context->post instanceof WP_Post ) {
		$GLOBALS['qtrad_editor_preload'] = array( 'id' => (int) $context->post->ID, 'route' => rest_get_route_for_post( $context->post ), 'language' => qtrad_admin_language() );
	}
	return $paths;
}

function qtrad_editor_preload_response( $response, $handler, $request ) {
	$context = isset( $GLOBALS['qtrad_editor_preload'] ) ? $GLOBALS['qtrad_editor_preload'] : null;
	if ( ! $context || ! is_admin() || is_wp_error( $response ) || ! ( $response instanceof WP_REST_Response )
		|| $request->get_method() !== 'GET' || $request->get_route() !== $context['route'] || $request->get_param( 'context' ) !== 'edit' ) { return $response; }
	$data = $response->get_data();
	if ( ! isset( $data['id'] ) || (int) $data['id'] !== $context['id'] ) { return $response; }
	foreach ( array( 'title', 'content', 'excerpt' ) as $field ) {
		if ( isset( $data[ $field ]['raw'] ) && is_string( $data[ $field ]['raw'] ) ) {
			$texts = qtrad_split( $data[ $field ]['raw'], null, false );
			$data[ $field ]['raw'] = isset( $texts[ $context['language'] ] ) ? $texts[ $context['language'] ] : '';
		}
	}
	$response->set_data( $data );
	return $response;
}

function qtrad_rest_language_context( $response, $handler, $request ) {
	$GLOBALS['qtrad_rest_languages'][] = qtrad_current_language();
	$lang = $request->get_param( 'qtrad_language' );
	if ( ! is_string( $lang ) || ! qtrad_is_enabled( $lang ) ) { $lang = $request->get_param( 'lang' ); }
	if ( is_string( $lang ) && qtrad_is_enabled( $lang ) ) { qtrad_set_language( $lang ); }
	return $response;
}

function qtrad_restore_rest_language( $response, $handler, $request ) {
	if ( ! empty( $GLOBALS['qtrad_rest_languages'] ) ) { qtrad_set_language( array_pop( $GLOBALS['qtrad_rest_languages'] ) ); }
	return $response;
}

function qtrad_register_rest_fields() {
	foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $type ) {
		add_filter( 'rest_pre_insert_' . $type, 'qtrad_filter_rest_post', 10, 2 );
	}
}

function qtrad_request_edit_lang() {
	$id = isset( $_POST['qtrad_field_post_id'] ) ? absint( $_POST['qtrad_field_post_id'] ) : 0;
	if ( $id && isset( $_POST['qtrad_edit_lang'], $_POST['qtrad_field_nonce'] ) && is_string( $_POST['qtrad_edit_lang'] ) && is_string( $_POST['qtrad_field_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_field_nonce'] ) ), 'qtrad_field_' . $id ) ) {
		$lang = strtolower( sanitize_key( wp_unslash( $_POST['qtrad_edit_lang'] ) ) );
		if ( qtrad_is_enabled( $lang ) ) { return $lang; }
	}
	return is_admin() ? qtrad_admin_language() : qtrad_current_language();
}

function qtrad_parent_for_merge( $data, $postarr ) {
	if ( isset( $data['post_type'] ) && $data['post_type'] === 'revision' && ! empty( $postarr['post_parent'] ) ) {
		return get_post( (int) $postarr['post_parent'] );
	}
	if ( ! empty( $postarr['ID'] ) ) { return get_post( (int) $postarr['ID'] ); }
	return null;
}

function qtrad_filter_insert_post( $data, $postarr ) {
	if ( ! is_array( $data ) ) { return $data; }
	$existing = qtrad_parent_for_merge( $data, $postarr );
	$id = isset( $_POST['qtrad_field_post_id'] ) ? absint( $_POST['qtrad_field_post_id'] ) : 0;
	$from_js = $id && $existing && $id === (int) $existing->ID
		&& isset( $_POST['qtrad_field_nonce'] ) && is_string( $_POST['qtrad_field_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_field_nonce'] ) ), 'qtrad_field_' . $id )
		&& current_user_can( 'edit_post', $id ) && ! empty( $_POST['qtrad_js'] )
		&& ! empty( $_POST['qtrad_field'] ) && is_array( $_POST['qtrad_field'] )
		&& ( ! isset( $data['post_type'] ) || $data['post_type'] !== 'revision' );
	$raw = wp_unslash( $data );
	$map = array( 'post_title' => 'title', 'post_content' => 'content', 'post_excerpt' => 'excerpt' );
	foreach ( $map as $column => $key ) {
		if ( ! isset( $raw[ $column ] ) || ! is_string( $raw[ $column ] ) ) { continue; }
		$previous = $existing && isset( $existing->$column ) ? (string) $existing->$column : '';
		if ( $from_js && isset( $_POST['qtrad_field'][ $key ] ) && is_array( $_POST['qtrad_field'][ $key ] ) ) {
			$texts = qtrad_split( $previous, null, false );
			foreach ( qtrad_enabled_languages() as $lang ) {
				if ( array_key_exists( $lang, $_POST['qtrad_field'][ $key ] ) ) {
					if ( ! is_string( $_POST['qtrad_field'][ $key ][ $lang ] ) ) {
						$texts[ $lang ] = '';
					} elseif ( 'title' === $key ) {
						$texts[ $lang ] = sanitize_text_field( wp_unslash( $_POST['qtrad_field'][ $key ][ $lang ] ) );
					} elseif ( ! current_user_can( 'unfiltered_html' ) ) {
						$texts[ $lang ] = wp_kses_post( wp_unslash( $_POST['qtrad_field'][ $key ][ $lang ] ) );
					} else {
						// Users with unfiltered_html save post content and excerpts as written, as core lets them.
						$texts[ $lang ] = wp_unslash( $_POST['qtrad_field'][ $key ][ $lang ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unfiltered_html, as core treats post content.
					}
				}
			}
			$format = qtrad_resolve_format( $previous, qtrad_setting( 'write_format', 'keep' ) );
			$raw[ $column ] = $key === 'content' ? qtrad_join_content( $texts, $format, qtrad_enabled_languages() ) : qtrad_join( $texts, $format, qtrad_enabled_languages() );
		} elseif ( isset( $data['post_type'] ) && $data['post_type'] === 'revision' && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// A revision is an exact snapshot, including plain historical content.
			continue;
		} else {
			// Ordinary programmatic updates and revision restores replace the supplied
			// fields. Callers can request a partial language update explicitly.
			$lang = isset( $postarr['qtrad_language'] ) ? $postarr['qtrad_language'] : qtrad_request_edit_lang();
			$context = isset( $postarr['qtrad_language'] ) || ( $existing && (
				( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] === (int) $existing->ID )
				|| ( $id && $id === (int) $existing->ID )
			) );
			if ( $context && is_string( $lang ) && qtrad_is_enabled( $lang ) && qtrad_setting( 'editor_mode', 'lsb' ) !== 'raw' ) {
				$raw[ $column ] = qtrad_merge_field( $raw[ $column ], $previous, $lang, $key === 'content' ? 'content' : 'text' );
			}
		}
	}
	if ( isset( $raw['post_title'] ) && qtrad_has_lang_tags( $raw['post_title'] ) ) {
		$plain = qtrad_use_language( $raw['post_title'], qtrad_default_language(), false, true );
		$name = isset( $raw['post_name'] ) ? $raw['post_name'] : '';
		if ( $plain !== '' && ( $name === '' || $name === sanitize_title( $raw['post_title'] ) ) ) { $raw['post_name'] = sanitize_title( $plain ); }
	}
	return wp_slash( $raw );
}

/** The REST controller handles authentication, capabilities and sanitization. */
function qtrad_filter_rest_post( $prepared, $request ) {
	if ( is_wp_error( $prepared ) || qtrad_setting( 'editor_mode', 'lsb' ) === 'raw' ) { return $prepared; }
	$lang = $request->get_param( 'qtrad_language' );
	if ( ! is_string( $lang ) || ! qtrad_is_enabled( $lang ) ) { $lang = $request->get_param( 'lang' ); }
	if ( ! is_string( $lang ) || ! qtrad_is_enabled( $lang ) ) { $lang = qtrad_current_language(); }
	$lang = strtolower( $lang );
	$id = (int) $request->get_param( 'id' );
	$nonce = $request->get_param( 'qtrad_editor_nonce' );
	if ( $id && is_string( $nonce ) && wp_verify_nonce( $nonce, 'qtrad_field_' . $id ) && current_user_can( 'edit_post', $id ) ) {
		update_user_meta( get_current_user_id(), 'qtrad_edit_language', $lang );
	}
	$previous = $request->get_param( 'id' ) ? get_post( (int) $request->get_param( 'id' ) ) : null;
	foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $column ) {
		if ( isset( $prepared->$column ) && is_string( $prepared->$column ) ) {
			$prepared->$column = qtrad_merge_field( $prepared->$column, $previous ? $previous->$column : '', $lang, $column === 'post_content' ? 'content' : 'text' );
		}
	}
	return $prepared;
}

function qtrad_get_raw_term( $id, $taxonomy = '' ) {
	$priority = has_filter( 'get_term', 'qtrad_filter_get_term' );
	if ( $priority !== false ) { remove_filter( 'get_term', 'qtrad_filter_get_term', $priority ); }
	try { return get_term( $id, $taxonomy ); }
	finally { if ( $priority !== false ) { add_filter( 'get_term', 'qtrad_filter_get_term', $priority ); } }
}

function qtrad_capture_term_name( $id, $taxonomy ) {
	$term = qtrad_get_raw_term( $id, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) { $GLOBALS['qtrad_old_term_names'][ $id ] = $term->name; }
}

function qtrad_save_term( $term_id, $tt_id = 0, $taxonomy = '' ) {
	if ( empty( $_POST['qtrad_term'] ) || ! is_array( $_POST['qtrad_term'] ) || ! current_user_can( 'edit_term', $term_id ) ) { return; }
	if ( ! isset( $_POST['qtrad_term_nonce'] ) || ! is_string( $_POST['qtrad_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_term_nonce'] ) ), 'qtrad_term' ) ) { return; }
	if ( ! empty( $_POST['qtrad_term_id'] ) && (int) $_POST['qtrad_term_id'] !== (int) $term_id ) { return; }
	$term = qtrad_get_raw_term( (int) $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) { return; }
	if ( empty( $_POST['qtrad_term_id'] ) ) {
		$submitted_name = isset( $_POST['tag-name'] ) && is_string( $_POST['tag-name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ) : '';
		if ( $submitted_name !== $term->name || ! isset( $_POST['qtrad_term_taxonomy'] ) || ! is_string( $_POST['qtrad_term_taxonomy'] ) || sanitize_key( wp_unslash( $_POST['qtrad_term_taxonomy'] ) ) !== $taxonomy ) { return; }
	}
	$old = isset( $GLOBALS['qtrad_old_term_names'][ $term_id ] ) ? $GLOBALS['qtrad_old_term_names'][ $term_id ] : $term->name;
	unset( $GLOBALS['qtrad_old_term_names'][ $term_id ] );
	$library = (array) get_option( 'qtranslate_term_name', array() );
	$posted = get_term_meta( $term_id, '_qtrad_translations', true );
	if ( ! is_array( $posted ) ) { $posted = isset( $library[ $old ] ) && is_array( $library[ $old ] ) ? $library[ $old ] : array(); }
	foreach ( qtrad_enabled_languages() as $lang ) {
		if ( isset( $_POST['qtrad_term'][ $lang ] ) && is_string( $_POST['qtrad_term'][ $lang ] ) ) { $posted[ $lang ] = sanitize_text_field( wp_unslash( $_POST['qtrad_term'][ $lang ] ) ); }
	}
	$default = qtrad_default_language();
	$name = isset( $posted[ $default ] ) && $posted[ $default ] !== '' ? $posted[ $default ] : $term->name;
	if ( $name !== $term->name ) {
		remove_action( 'edited_term', 'qtrad_save_term', 10 );
		try { $result = wp_update_term( $term_id, $term->taxonomy, array( 'name' => wp_slash( $name ) ) ); }
		finally { add_action( 'edited_term', 'qtrad_save_term', 10, 3 ); }
		if ( is_wp_error( $result ) ) { return; }
	}
	global $wpdb;
	// Exact name match across all taxonomies, which no core term API offers.
	if ( $old !== $name && ! $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE name = %s AND term_id <> %d LIMIT 1", $old, $term_id ) ) ) { unset( $library[ $old ] ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$library[ $name ] = $posted;
	update_option( 'qtranslate_term_name', $library );
	update_term_meta( $term_id, '_qtrad_translations', wp_slash( $posted ) );
	qtrad_reset_config();
}

function qtrad_extra_field_keys() {
	$keys = preg_split( '/\r\n|\r|\n|,/', (string) qtrad_config( 'extra_fields' ) );
	return array_values( array_unique( array_filter( array_map( 'trim', $keys ), function ( $key ) { return strpos( $key, '_qtrad_' ) !== 0 && (bool) preg_match( '/^[A-Za-z0-9_\-]+$/D', $key ); } ) ) );
}

/** Bypass our display filter, retaining other metadata providers. */
function qtrad_raw_post_meta( $object_id, $key ) {
	$priority = has_filter( 'get_post_metadata', 'qtrad_filter_get_meta' );
	$previous = ! empty( $GLOBALS['qtrad_raw_meta'] );
	$GLOBALS['qtrad_raw_meta'] = true;
	if ( $priority !== false ) { remove_filter( 'get_post_metadata', 'qtrad_filter_get_meta', $priority ); }
	try { return get_post_meta( $object_id, $key, false ); }
	finally {
		$GLOBALS['qtrad_raw_meta'] = $previous;
		if ( $priority !== false ) { add_filter( 'get_post_metadata', 'qtrad_filter_get_meta', $priority, 4 ); }
	}
}

function qtrad_filter_get_meta( $value, $object_id, $meta_key, $single ) {
	if ( $value !== null || ! is_string( $meta_key ) || ! in_array( $meta_key, qtrad_extra_field_keys(), true ) || ( is_admin() && ! wp_doing_ajax() ) ) { return $value; }
	$raw = qtrad_raw_post_meta( $object_id, $meta_key );
	if ( ! is_array( $raw ) || ! $raw ) { return $value; }
	$translated = array_map( function ( $entry ) { return is_string( $entry ) ? qtrad_use_current( $entry ) : $entry; }, $raw );
	// A nonempty array also preserves metadata_exists() for the valid value "0".
	return $single ? array( $translated[0] ) : $translated;
}

function qtrad_filter_update_meta( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
	if ( $check !== null || ! is_string( $meta_key ) || ! is_string( $meta_value ) || ! in_array( $meta_key, qtrad_extra_field_keys(), true ) || qtrad_has_lang_tags( $meta_value ) ) { return $check; }
	global $wpdb;
	// Raw rows, bypassing the display filters and object cache, so a write merges into stored values.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $object_id, $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$lang = qtrad_request_edit_lang();
	if ( ! $rows ) {
		if ( ! empty( $prev_value ) ) { return false; }
		$merged = qtrad_merge_field( $meta_value, '', $lang );
		return (bool) add_post_meta( $object_id, $meta_key, wp_slash( $merged ) );
	}
	$changed = false;
	foreach ( $rows as $row ) {
		$old = maybe_unserialize( $row->meta_value );
		if ( ! is_string( $old ) || ( ! empty( $prev_value ) && $old !== $prev_value ) ) { continue; }
		$merged = qtrad_merge_field( $meta_value, $old, $lang );
		if ( $merged !== $old ) {
			// By-ID updates receive raw values; unlike update_post_meta they do not unslash.
			$changed = update_metadata_by_mid( 'post', $row->meta_id, $merged ) || $changed;
		}
	}
	return $changed;
}

function qtrad_delete_term_library( $term_id, $tt_id, $taxonomy, $term ) {
	global $wpdb;
	if ( isset( $GLOBALS['qtrad_old_term_names'][ $term_id ] ) ) {
		$term = clone $term;
		$term->name = $GLOBALS['qtrad_old_term_names'][ $term_id ];
		unset( $GLOBALS['qtrad_old_term_names'][ $term_id ] );
	}
	if ( ! isset( $term->name ) || $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE name = %s LIMIT 1", $term->name ) ) ) { return; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact name match across all taxonomies.
	$library = (array) get_option( 'qtranslate_term_name', array() );
	unset( $library[ $term->name ] );
	update_option( 'qtranslate_term_name', $library );
	qtrad_reset_config();
}
