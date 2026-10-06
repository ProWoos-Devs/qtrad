<?php
/**
 * Editing translated slugs and translated URL bases. Values are written the
 * way qTranslate-XT stores them, so both plugins read the same data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_slug_admin_hooks() {
	if ( ! is_admin() ) {
		return;
	}
	add_action( 'add_meta_boxes', 'qtrad_slugs_add_meta_box', 10, 2 );
	add_action( 'save_post', 'qtrad_slugs_save_meta_box', 99 );
	add_action( 'admin_init', 'qtrad_slugs_register_term_fields' );
	add_action( 'created_term', 'qtrad_slugs_save_term', 20, 3 );
	add_action( 'edited_term', 'qtrad_slugs_save_term', 20, 3 );
}

/**
 * Store, change or clear the translated slug of a post or term.
 *
 * @param string          $type   'post' or 'term'.
 * @param WP_Post|WP_Term $target The post or term.
 * @param string          $lang   Language code.
 * @param string          $slug   Sanitized slug, '' to use the stored WordPress slug.
 * @return string The slug in use afterwards, which gets a suffix when it was taken.
 */
function qtrad_slugs_set( $type, $target, $lang, $slug ) {
	$id     = 'term' === $type ? (int) $target->term_id : (int) $target->ID;
	$stored = 'term' === $type ? $target->slug : $target->post_name;
	$keys   = qtrad_slugs_meta_keys( $lang );
	if ( '' === $slug || $slug === $stored ) {
		if ( metadata_exists( $type, $id, $keys[1] ) ) {
			// An empty value hides the slug an older plugin left, without deleting it.
			update_metadata( $type, $id, $keys[0], '' );
		} else {
			delete_metadata( $type, $id, $keys[0] );
		}
		return '';
	}
	if ( qtrad_slugs_get( $type, $id, $lang ) === $slug ) {
		return $slug;
	}
	$slug = qtrad_slugs_unique( $type, $target, $lang, $slug );
	update_metadata( $type, $id, $keys[0], wp_slash( $slug ) );
	return $slug;
}

/** Whether another post or term of the same kind already answers to that slug in the language. */
function qtrad_slugs_is_taken( $type, $target, $lang, $slug ) {
	global $wpdb, $wp_rewrite;
	$reserved = array_merge( is_array( $wp_rewrite->feeds ) ? $wp_rewrite->feeds : array(), array( 'embed', $wp_rewrite->pagination_base, $wp_rewrite->comments_pagination_base ) );
	if ( in_array( $slug, $reserved, true ) ) {
		return true;
	}
	$keys = qtrad_slugs_meta_keys( $lang );
	// Stored and translated slugs are compared in one lookup, which no core API offers.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( 'term' === $type ) {
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id LEFT JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key IN (%s, %s) WHERE tt.taxonomy = %s AND t.term_id <> %d AND ( t.slug = %s OR m.meta_value = %s ) LIMIT 1", $keys[0], $keys[1], $target->taxonomy, $target->term_id, $slug, $slug ) );
	}
	if ( is_post_type_hierarchical( $target->post_type ) ) {
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN (%s, %s) WHERE p.post_type = %s AND p.post_parent = %d AND p.ID <> %d AND p.post_status NOT IN ('trash', 'auto-draft') AND ( p.post_name = %s OR m.meta_value = %s ) LIMIT 1", $keys[0], $keys[1], $target->post_type, $target->post_parent, $target->ID, $slug, $slug ) );
	}
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN (%s, %s) WHERE p.post_type = %s AND p.ID <> %d AND p.post_status NOT IN ('trash', 'auto-draft') AND ( p.post_name = %s OR m.meta_value = %s ) LIMIT 1", $keys[0], $keys[1], $target->post_type, $target->ID, $slug, $slug ) );
	// phpcs:enable
}

/** A free slug, with -2, -3 and so on appended the way WordPress does for its own slugs. */
function qtrad_slugs_unique( $type, $target, $lang, $slug ) {
	$wanted = $slug;
	for ( $number = 2; $number < 100 && qtrad_slugs_is_taken( $type, $target, $lang, $slug ); $number++ ) {
		$slug = substr( $wanted, 0, 200 - strlen( '-' . $number ) ) . '-' . $number;
	}
	return $slug;
}

/** One labelled field per language. Slugs are shown decoded, as WordPress shows its own. */
function qtrad_slugs_fields( $type, $id, $name, $prefix ) {
	foreach ( qtrad_enabled_languages() as $lang ) {
		$field = $prefix . $lang;
		$value = $id ? qtrad_slugs_get( $type, $id, $lang ) : '';
		echo '<p><label for="' . esc_attr( $field ) . '"><bdi>' . esc_html( qtrad_language_name( $lang ) ) . '</bdi></label><br />';
		echo '<input type="text" class="widefat" id="' . esc_attr( $field ) . '" name="' . esc_attr( $name . '[' . $lang . ']' ) . '" value="' . esc_attr( urldecode( $value ) ) . '" autocomplete="off" spellcheck="false" /></p>';
	}
}

function qtrad_slugs_add_meta_box( $post_type, $post ) {
	if ( ! $post instanceof WP_Post || 'attachment' === $post_type || ! is_post_type_viewable( $post_type ) || ! get_option( 'permalink_structure' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	if ( 'post' === $post_type && ! qtrad_slugs_post_structure() ) {
		return;
	}
	add_meta_box( 'qtrad-slugs', esc_html__( 'Translated slugs', 'qtrad' ), 'qtrad_slugs_meta_box', $post_type, 'normal', 'default' );
}

function qtrad_slugs_meta_box( $post ) {
	wp_nonce_field( 'qtrad_slugs_' . $post->ID, 'qtrad_slugs_nonce' );
	echo '<input type="hidden" name="qtrad_slugs_post_id" value="' . esc_attr( $post->ID ) . '" />';
	echo '<p>' . esc_html__( 'The last part of the address in each language. Leave a field empty to use the WordPress slug in that language.', 'qtrad' ) . '</p>';
	qtrad_slugs_fields( 'post', $post->ID, 'qtrad_slugs', 'qtrad-slug-' );
}

function qtrad_slugs_save_meta_box( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id )
		|| empty( $_POST['qtrad_slugs_post_id'] ) || (int) $_POST['qtrad_slugs_post_id'] !== (int) $post_id
		|| ! isset( $_POST['qtrad_slugs_nonce'] ) || ! is_string( $_POST['qtrad_slugs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_slugs_nonce'] ) ), 'qtrad_slugs_' . $post_id )
		|| empty( $_POST['qtrad_slugs'] ) || ! is_array( $_POST['qtrad_slugs'] ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	foreach ( qtrad_enabled_languages() as $lang ) {
		if ( isset( $_POST['qtrad_slugs'][ $lang ] ) && is_string( $_POST['qtrad_slugs'][ $lang ] ) ) {
			qtrad_slugs_set( 'post', $post, $lang, sanitize_title( wp_unslash( $_POST['qtrad_slugs'][ $lang ] ) ) );
		}
	}
}

/** Slug fields follow the term name translations, which carry the nonce. */
function qtrad_slugs_register_term_fields() {
	if ( ! get_option( 'permalink_structure' ) ) {
		return;
	}
	foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $taxonomy ) {
		if ( is_taxonomy_viewable( $taxonomy ) ) {
			add_action( $taxonomy . '_edit_form_fields', 'qtrad_slugs_term_edit_fields', 11 );
			add_action( $taxonomy . '_add_form_fields', 'qtrad_slugs_term_add_fields', 11 );
		}
	}
}

function qtrad_slugs_term_edit_fields( $term ) {
	echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Translated slugs', 'qtrad' ) . '</th><td>';
	qtrad_slugs_fields( 'term', $term->term_id, 'qtrad_term_slugs', 'qtrad-term-slug-' );
	echo '<p class="description">' . esc_html__( 'Leave a field empty to use the WordPress slug in that language.', 'qtrad' ) . '</p>';
	echo '</td></tr>';
}

function qtrad_slugs_term_add_fields() {
	echo '<div class="form-field"><p><strong>' . esc_html__( 'Translated slugs', 'qtrad' ) . '</strong></p>';
	qtrad_slugs_fields( 'term', 0, 'qtrad_term_slugs', 'qtrad-term-slug-' );
	echo '</div>';
}

function qtrad_slugs_save_term( $term_id, $tt_id = 0, $taxonomy = '' ) {
	if ( empty( $_POST['qtrad_term_slugs'] ) || ! is_array( $_POST['qtrad_term_slugs'] ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	if ( ! isset( $_POST['qtrad_term_nonce'] ) || ! is_string( $_POST['qtrad_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_term_nonce'] ) ), 'qtrad_term' ) ) {
		return;
	}
	if ( ! empty( $_POST['qtrad_term_id'] ) && (int) $_POST['qtrad_term_id'] !== (int) $term_id ) {
		return;
	}
	$term = qtrad_get_raw_term( (int) $term_id, $taxonomy );
	if ( ! $term instanceof WP_Term || ! is_taxonomy_viewable( $term->taxonomy ) ) {
		return;
	}
	if ( empty( $_POST['qtrad_term_id'] ) ) {
		// The add form names its taxonomy and term, so other terms created in the same request are left alone.
		$submitted = isset( $_POST['tag-name'] ) && is_string( $_POST['tag-name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ) : '';
		if ( $submitted !== $term->name || ! isset( $_POST['qtrad_term_taxonomy'] ) || ! is_string( $_POST['qtrad_term_taxonomy'] ) || sanitize_key( wp_unslash( $_POST['qtrad_term_taxonomy'] ) ) !== $term->taxonomy ) {
			return;
		}
	}
	foreach ( qtrad_enabled_languages() as $lang ) {
		if ( isset( $_POST['qtrad_term_slugs'][ $lang ] ) && is_string( $_POST['qtrad_term_slugs'][ $lang ] ) ) {
			qtrad_slugs_set( 'term', $term, $lang, sanitize_title( wp_unslash( $_POST['qtrad_term_slugs'][ $lang ] ) ) );
		}
	}
}

/**
 * Post types and taxonomies whose base can be translated, the ones qTranslate-XT offers.
 *
 * @return array[] Label and stored base by "post_type_{name}" or "taxonomy_{name}".
 */
function qtrad_slugs_base_objects() {
	$objects = array();
	foreach ( array( 'post_type' => get_post_types( array( 'public' => true ), 'objects' ), 'taxonomy' => get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'objects' ) ) as $kind => $list ) {
		foreach ( $list as $object ) {
			if ( ! empty( $object->rewrite ) ) {
				$objects[ $kind . '_' . $object->name ] = array(
					'label' => $object->label,
					'base'  => is_array( $object->rewrite ) && ! empty( $object->rewrite['slug'] ) ? trim( $object->rewrite['slug'], '/' ) : $object->name,
				);
			}
		}
	}
	return $objects;
}

/** Settings row with one base field per post type or taxonomy and language. */
function qtrad_slugs_settings_row() {
	$objects = qtrad_slugs_base_objects();
	if ( ! $objects || ! get_option( 'permalink_structure' ) ) {
		return;
	}
	$bases   = qtrad_slugs_all_bases();
	$enabled = qtrad_enabled_languages();
	echo '<tr><th scope="row">' . esc_html__( 'Translated URL bases', 'qtrad' ) . '</th><td>';
	echo '<table class="widefat striped qtrad-bases"><thead><tr><th scope="col">' . esc_html__( 'Base', 'qtrad' ) . '</th>';
	foreach ( $enabled as $lang ) {
		echo '<th scope="col"><bdi>' . esc_html( qtrad_language_name( $lang ) ) . '</bdi></th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $objects as $name => $object ) {
		echo '<tr><th scope="row">' . esc_html( qtrad_use_current( $object['label'] ) ) . ' <code>' . esc_html( $object['base'] ) . '</code></th>';
		foreach ( $enabled as $lang ) {
			/* translators: 1: post type or taxonomy name, 2: language name */
			$label = sprintf( __( '%1$s, %2$s', 'qtrad' ), qtrad_use_current( $object['label'] ), qtrad_language_name( $lang ) );
			echo '<td><input type="text" aria-label="' . esc_attr( $label ) . '" name="slug_bases[' . esc_attr( $name ) . '][' . esc_attr( $lang ) . ']" value="' . esc_attr( urldecode( isset( $bases[ $name ][ $lang ] ) ? $bases[ $name ][ $lang ] : '' ) ) . '" autocomplete="off" spellcheck="false" /></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'The first part of the address of a post type or taxonomy, such as "category". Leave a field empty to keep the base shown in its row.', 'qtrad' ) . '</p>';
	echo '</td></tr>';
}

/** Save the bases from the settings form into the option qTranslate-XT reads. */
function qtrad_slugs_save_bases( $input ) {
	if ( ! isset( $input['slug_bases'] ) || ! is_array( $input['slug_bases'] ) ) {
		return;
	}
	$before = get_option( 'qtranslate_module_slugs', array() );
	$option = is_array( $before ) ? $before : array();
	foreach ( array_keys( qtrad_slugs_base_objects() ) as $name ) {
		if ( ! isset( $input['slug_bases'][ $name ] ) || ! is_array( $input['slug_bases'][ $name ] ) ) {
			continue;
		}
		foreach ( qtrad_enabled_languages() as $lang ) {
			if ( ! isset( $input['slug_bases'][ $name ][ $lang ] ) || ! is_string( $input['slug_bases'][ $name ][ $lang ] ) ) {
				continue;
			}
			$base = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', $input['slug_bases'][ $name ][ $lang ] ) ), 'strlen' ) );
			if ( '' === $base ) {
				unset( $option[ $name ][ $lang ] );
			} else {
				$option[ $name ][ $lang ] = $base;
			}
		}
		if ( isset( $option[ $name ] ) && ! $option[ $name ] ) {
			unset( $option[ $name ] );
		}
	}
	if ( $option !== $before ) {
		update_option( 'qtranslate_module_slugs', $option );
	}
	qtrad_slugs_active( 'refresh' );
}
