<?php
/** Raw persistence is separate from language-dependent display filtering. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qtu_register_field_hooks() {
	add_action( 'wp_after_insert_post', 'qtu_index_languages', 10, 2 );
	add_filter( 'wp_insert_post_data', 'qtu_filter_insert_post', 9, 2 );
	add_action( 'edit_terms', 'qtu_capture_term_name', 10, 2 );
	add_action( 'edited_term', 'qtu_save_term', 10, 3 );
	add_action( 'created_term', 'qtu_save_term', 10, 3 );
	add_action( 'pre_delete_term', 'qtu_capture_term_name', 10, 2 );
	add_action( 'delete_term', 'qtu_delete_term_library', 10, 4 );
	add_filter( 'get_post_metadata', 'qtu_filter_get_meta', 10, 4 );
	add_filter( 'update_post_metadata', 'qtu_filter_update_meta', 10, 5 );
	add_action( 'rest_api_init', 'qtu_register_rest_fields', 20 );
	add_filter( 'rest_request_before_callbacks', 'qtu_rest_language_context', 10, 3 );
	add_filter( 'rest_request_after_callbacks', 'qtu_restore_rest_language', 10, 3 );
	add_filter( 'block_editor_rest_api_preload_paths', 'qtu_editor_preload_context', 10, 2 );
	add_filter( 'rest_request_after_callbacks', 'qtu_editor_preload_response', 20, 3 );
}

/** Let Gutenberg parse the selected language on its first render, not raw markers. */
function qtu_editor_preload_context( $paths, $context ) {
	unset( $GLOBALS['qtu_editor_preload'] );
	if ( qtu_setting( 'editor_mode', 'lsb' ) !== 'raw' && isset( $context->post ) && $context->post instanceof WP_Post ) {
		$GLOBALS['qtu_editor_preload'] = array( 'id' => (int) $context->post->ID, 'route' => rest_get_route_for_post( $context->post ), 'language' => qtu_admin_language() );
	}
	return $paths;
}

function qtu_editor_preload_response( $response, $handler, $request ) {
	$context = isset( $GLOBALS['qtu_editor_preload'] ) ? $GLOBALS['qtu_editor_preload'] : null;
	if ( ! $context || ! is_admin() || is_wp_error( $response ) || ! ( $response instanceof WP_REST_Response )
		|| $request->get_method() !== 'GET' || $request->get_route() !== $context['route'] || $request->get_param( 'context' ) !== 'edit' ) { return $response; }
	$data = $response->get_data();
	if ( ! isset( $data['id'] ) || (int) $data['id'] !== $context['id'] ) { return $response; }
	foreach ( array( 'title', 'content', 'excerpt' ) as $field ) {
		if ( isset( $data[ $field ]['raw'] ) && is_string( $data[ $field ]['raw'] ) ) {
			$texts = qtu_split( $data[ $field ]['raw'], null, false );
			$data[ $field ]['raw'] = isset( $texts[ $context['language'] ] ) ? $texts[ $context['language'] ] : '';
		}
	}
	$response->set_data( $data );
	return $response;
}

function qtu_rest_language_context( $response, $handler, $request ) {
	$GLOBALS['qtu_rest_languages'][] = qtu_current_language();
	$lang = $request->get_param( 'qtu_language' );
	if ( ! is_string( $lang ) || ! qtu_is_enabled( $lang ) ) { $lang = $request->get_param( 'lang' ); }
	if ( is_string( $lang ) && qtu_is_enabled( $lang ) ) { qtu_set_language( $lang ); }
	return $response;
}

function qtu_restore_rest_language( $response, $handler, $request ) {
	if ( ! empty( $GLOBALS['qtu_rest_languages'] ) ) { qtu_set_language( array_pop( $GLOBALS['qtu_rest_languages'] ) ); }
	return $response;
}

function qtu_register_rest_fields() {
	foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $type ) {
		add_filter( 'rest_pre_insert_' . $type, 'qtu_filter_rest_post', 10, 2 );
	}
}

function qtu_request_edit_lang() {
	if ( isset( $_POST['qtu_edit_lang'] ) && is_string( $_POST['qtu_edit_lang'] ) ) {
		$lang = strtolower( sanitize_key( wp_unslash( $_POST['qtu_edit_lang'] ) ) );
		if ( qtu_is_enabled( $lang ) ) { return $lang; }
	}
	return is_admin() ? qtu_admin_language() : qtu_current_language();
}

function qtu_sanitize_posted_text( $value, $kind ) {
	$value = is_string( $value ) ? wp_unslash( $value ) : '';
	if ( $kind === 'title' ) { return sanitize_text_field( $value ); }
	return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
}

function qtu_parent_for_merge( $data, $postarr ) {
	if ( isset( $data['post_type'] ) && $data['post_type'] === 'revision' && ! empty( $postarr['post_parent'] ) ) {
		return get_post( (int) $postarr['post_parent'] );
	}
	if ( ! empty( $postarr['ID'] ) ) { return get_post( (int) $postarr['ID'] ); }
	return null;
}

function qtu_filter_insert_post( $data, $postarr ) {
	if ( ! is_array( $data ) ) { return $data; }
	$existing = qtu_parent_for_merge( $data, $postarr );
	$id = isset( $_POST['qtu_field_post_id'] ) ? absint( $_POST['qtu_field_post_id'] ) : 0;
	$from_js = $id && $existing && $id === (int) $existing->ID
		&& isset( $_POST['qtu_field_nonce'] ) && is_string( $_POST['qtu_field_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtu_field_nonce'] ) ), 'qtu_field_' . $id )
		&& current_user_can( 'edit_post', $id ) && ! empty( $_POST['qtu_js'] )
		&& ! empty( $_POST['qtu_field'] ) && is_array( $_POST['qtu_field'] )
		&& ( ! isset( $data['post_type'] ) || $data['post_type'] !== 'revision' );
	$raw = wp_unslash( $data );
	$map = array( 'post_title' => 'title', 'post_content' => 'content', 'post_excerpt' => 'excerpt' );
	foreach ( $map as $column => $key ) {
		if ( ! isset( $raw[ $column ] ) || ! is_string( $raw[ $column ] ) ) { continue; }
		$previous = $existing && isset( $existing->$column ) ? (string) $existing->$column : '';
		if ( $from_js && isset( $_POST['qtu_field'][ $key ] ) && is_array( $_POST['qtu_field'][ $key ] ) ) {
			$texts = qtu_split( $previous, null, false );
			foreach ( qtu_enabled_languages() as $lang ) {
				if ( array_key_exists( $lang, $_POST['qtu_field'][ $key ] ) ) {
					$texts[ $lang ] = qtu_sanitize_posted_text( $_POST['qtu_field'][ $key ][ $lang ], $key );
				}
			}
			$format = qtu_resolve_format( $previous, qtu_setting( 'write_format', 'keep' ) );
			$raw[ $column ] = $key === 'content' ? qtu_join_content( $texts, $format, qtu_enabled_languages() ) : qtu_join( $texts, $format, qtu_enabled_languages() );
		} elseif ( isset( $data['post_type'] ) && $data['post_type'] === 'revision' && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// A revision is an exact snapshot, including plain historical content.
			continue;
		} else {
			// Ordinary programmatic updates and revision restores replace the supplied
			// fields. Callers can request a partial language update explicitly.
			$lang = isset( $postarr['qtu_language'] ) ? $postarr['qtu_language'] : qtu_request_edit_lang();
			$context = isset( $postarr['qtu_language'] ) || ( $existing && (
				( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] === (int) $existing->ID )
				|| ( $id && $id === (int) $existing->ID )
			) );
			if ( $context && is_string( $lang ) && qtu_is_enabled( $lang ) && qtu_setting( 'editor_mode', 'lsb' ) !== 'raw' ) {
				$raw[ $column ] = qtu_merge_field( $raw[ $column ], $previous, $lang, $key === 'content' ? 'content' : 'text' );
			}
		}
	}
	if ( isset( $raw['post_title'] ) && qtu_has_lang_tags( $raw['post_title'] ) ) {
		$plain = qtu_use_language( $raw['post_title'], qtu_default_language(), false, true );
		$name = isset( $raw['post_name'] ) ? $raw['post_name'] : '';
		if ( $plain !== '' && ( $name === '' || $name === sanitize_title( $raw['post_title'] ) ) ) { $raw['post_name'] = sanitize_title( $plain ); }
	}
	return wp_slash( $raw );
}

/** The REST controller handles authentication, capabilities and sanitization. */
function qtu_filter_rest_post( $prepared, $request ) {
	if ( is_wp_error( $prepared ) || qtu_setting( 'editor_mode', 'lsb' ) === 'raw' ) { return $prepared; }
	$lang = $request->get_param( 'qtu_language' );
	if ( ! is_string( $lang ) || ! qtu_is_enabled( $lang ) ) { $lang = $request->get_param( 'lang' ); }
	if ( ! is_string( $lang ) || ! qtu_is_enabled( $lang ) ) { $lang = qtu_current_language(); }
	$lang = strtolower( $lang );
	$id = (int) $request->get_param( 'id' );
	$nonce = $request->get_param( 'qtu_editor_nonce' );
	if ( $id && is_string( $nonce ) && wp_verify_nonce( $nonce, 'qtu_field_' . $id ) && current_user_can( 'edit_post', $id ) ) {
		update_user_meta( get_current_user_id(), 'qtu_edit_language', $lang );
	}
	$previous = $request->get_param( 'id' ) ? get_post( (int) $request->get_param( 'id' ) ) : null;
	foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $column ) {
		if ( isset( $prepared->$column ) && is_string( $prepared->$column ) ) {
			$prepared->$column = qtu_merge_field( $prepared->$column, $previous ? $previous->$column : '', $lang, $column === 'post_content' ? 'content' : 'text' );
		}
	}
	return $prepared;
}

function qtu_get_raw_term( $id, $taxonomy = '' ) {
	$priority = has_filter( 'get_term', 'qtu_filter_get_term' );
	if ( $priority !== false ) { remove_filter( 'get_term', 'qtu_filter_get_term', $priority ); }
	try { return get_term( $id, $taxonomy ); }
	finally { if ( $priority !== false ) { add_filter( 'get_term', 'qtu_filter_get_term', $priority ); } }
}

function qtu_capture_term_name( $id, $taxonomy ) {
	$term = qtu_get_raw_term( $id, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) { $GLOBALS['qtu_old_term_names'][ $id ] = $term->name; }
}

function qtu_save_term( $term_id, $tt_id = 0, $taxonomy = '' ) {
	if ( empty( $_POST['qtu_term'] ) || ! is_array( $_POST['qtu_term'] ) || ! current_user_can( 'edit_term', $term_id ) ) { return; }
	if ( ! isset( $_POST['qtu_term_nonce'] ) || ! is_string( $_POST['qtu_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtu_term_nonce'] ) ), 'qtu_term' ) ) { return; }
	if ( ! empty( $_POST['qtu_term_id'] ) && (int) $_POST['qtu_term_id'] !== (int) $term_id ) { return; }
	$term = qtu_get_raw_term( (int) $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) { return; }
	if ( empty( $_POST['qtu_term_id'] ) ) {
		$submitted_name = isset( $_POST['tag-name'] ) && is_string( $_POST['tag-name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ) : '';
		if ( $submitted_name !== $term->name || ! isset( $_POST['qtu_term_taxonomy'] ) || $_POST['qtu_term_taxonomy'] !== $taxonomy ) { return; }
	}
	$old = isset( $GLOBALS['qtu_old_term_names'][ $term_id ] ) ? $GLOBALS['qtu_old_term_names'][ $term_id ] : $term->name;
	unset( $GLOBALS['qtu_old_term_names'][ $term_id ] );
	$library = (array) get_option( 'qtranslate_term_name', array() );
	$posted = get_term_meta( $term_id, '_qtn_translations', true );
	if ( ! is_array( $posted ) ) { $posted = isset( $library[ $old ] ) && is_array( $library[ $old ] ) ? $library[ $old ] : array(); }
	foreach ( qtu_enabled_languages() as $lang ) {
		if ( isset( $_POST['qtu_term'][ $lang ] ) && is_string( $_POST['qtu_term'][ $lang ] ) ) { $posted[ $lang ] = sanitize_text_field( wp_unslash( $_POST['qtu_term'][ $lang ] ) ); }
	}
	$default = qtu_default_language();
	$name = isset( $posted[ $default ] ) && $posted[ $default ] !== '' ? $posted[ $default ] : $term->name;
	if ( $name !== $term->name ) {
		remove_action( 'edited_term', 'qtu_save_term', 10 );
		try { $result = wp_update_term( $term_id, $term->taxonomy, array( 'name' => wp_slash( $name ) ) ); }
		finally { add_action( 'edited_term', 'qtu_save_term', 10, 3 ); }
		if ( is_wp_error( $result ) ) { return; }
	}
	global $wpdb;
	if ( $old !== $name && ! $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE name = %s AND term_id <> %d LIMIT 1", $old, $term_id ) ) ) { unset( $library[ $old ] ); }
	$library[ $name ] = $posted;
	update_option( 'qtranslate_term_name', $library );
	update_term_meta( $term_id, '_qtn_translations', wp_slash( $posted ) );
	qtu_reset_config();
}

function qtu_extra_field_keys() {
	$keys = preg_split( '/\r\n|\r|\n|,/', (string) qtu_config( 'extra_fields' ) );
	return array_values( array_unique( array_filter( array_map( 'trim', $keys ), function ( $key ) { return strpos( $key, '_qtn_' ) !== 0 && (bool) preg_match( '/^[A-Za-z0-9_\-]+$/D', $key ); } ) ) );
}

/** Bypass our display filter, retaining other metadata providers. */
function qtu_raw_post_meta( $object_id, $key ) {
	$priority = has_filter( 'get_post_metadata', 'qtu_filter_get_meta' );
	if ( $priority !== false ) { remove_filter( 'get_post_metadata', 'qtu_filter_get_meta', $priority ); }
	try { return get_post_meta( $object_id, $key, false ); }
	finally { if ( $priority !== false ) { add_filter( 'get_post_metadata', 'qtu_filter_get_meta', $priority, 4 ); } }
}

function qtu_filter_get_meta( $value, $object_id, $meta_key, $single ) {
	if ( $value !== null || ! is_string( $meta_key ) || ! in_array( $meta_key, qtu_extra_field_keys(), true ) || ( is_admin() && ! wp_doing_ajax() ) ) { return $value; }
	$raw = qtu_raw_post_meta( $object_id, $meta_key );
	if ( ! is_array( $raw ) || ! $raw ) { return $value; }
	$translated = array_map( function ( $entry ) { return is_string( $entry ) ? qtu_use_current( $entry ) : $entry; }, $raw );
	// A nonempty array also preserves metadata_exists() for the valid value "0".
	return $single ? array( $translated[0] ) : $translated;
}

function qtu_filter_update_meta( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
	if ( $check !== null || ! is_string( $meta_key ) || ! is_string( $meta_value ) || ! in_array( $meta_key, qtu_extra_field_keys(), true ) || qtu_has_lang_tags( $meta_value ) ) { return $check; }
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $object_id, $meta_key ) );
	$lang = qtu_request_edit_lang();
	if ( ! $rows ) {
		if ( ! empty( $prev_value ) ) { return false; }
		$merged = qtu_merge_field( $meta_value, '', $lang );
		return (bool) add_post_meta( $object_id, $meta_key, wp_slash( $merged ) );
	}
	$changed = false;
	foreach ( $rows as $row ) {
		$old = maybe_unserialize( $row->meta_value );
		if ( ! is_string( $old ) || ( ! empty( $prev_value ) && $old !== $prev_value ) ) { continue; }
		$merged = qtu_merge_field( $meta_value, $old, $lang );
		if ( $merged !== $old ) {
			// By-ID updates receive raw values; unlike update_post_meta they do not unslash.
			$changed = update_metadata_by_mid( 'post', $row->meta_id, $merged ) || $changed;
		}
	}
	return $changed;
}

function qtu_delete_term_library( $term_id, $tt_id, $taxonomy, $term ) {
	global $wpdb;
	if ( isset( $GLOBALS['qtu_old_term_names'][ $term_id ] ) ) {
		$term = clone $term;
		$term->name = $GLOBALS['qtu_old_term_names'][ $term_id ];
		unset( $GLOBALS['qtu_old_term_names'][ $term_id ] );
	}
	if ( ! isset( $term->name ) || $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE name = %s LIMIT 1", $term->name ) ) ) { return; }
	$library = (array) get_option( 'qtranslate_term_name', array() );
	unset( $library[ $term->name ] );
	update_option( 'qtranslate_term_name', $library );
	qtu_reset_config();
}
