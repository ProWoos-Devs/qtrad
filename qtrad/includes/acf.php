<?php
/**
 * Advanced Custom Fields. Values are stored the way qTranslate-XT's ACF module
 * stores them, one string with language markers, so a site can move in either
 * direction. Field types qtranslate_text, qtranslate_textarea, qtranslate_url,
 * qtranslate_wysiwyg, qtranslate_image, qtranslate_file and qtranslate_post_object
 * show one input per language; standard text, textarea, URL and WYSIWYG fields
 * can do the same with the "Translate" field setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_acf_hooks() {
	// ACF may come from a theme, so wait until themes are set up.
	add_action( 'after_setup_theme', 'qtrad_acf_setup', 20 );
}

function qtrad_acf_setup() {
	if ( ! function_exists( 'acf' ) || ! function_exists( 'acf_register_field_type' ) ) {
		return;
	}
	add_action( 'acf/include_field_types', 'qtrad_acf_register_field_types' );
	add_filter( 'acf/format_value', 'qtrad_acf_format_value', 5 );
	add_filter( 'acf/prepare_field', 'qtrad_acf_prepare_field', 5 );
	add_filter( 'acf/update_value', 'qtrad_acf_update_value', 5, 3 );
	add_action( 'acf/validate_save_post', 'qtrad_acf_join_posted_values', 1 );
	add_action( 'acf/input/admin_enqueue_scripts', 'qtrad_acf_enqueue' );
	foreach ( qtrad_acf_switchable_types() as $type ) {
		add_action( 'acf/render_field_settings/type=' . $type, 'qtrad_acf_render_translate_setting' );
		// Right after ACF's own check of the type, so field name, field key and generic validators still run afterwards.
		add_filter( 'acf/validate_value/type=' . $type, 'qtrad_acf_validate_standard', 11, 4 );
	}
}

/** Standard field types that can show one input per language. */
function qtrad_acf_switchable_types() {
	return array( 'text', 'textarea', 'url', 'wysiwyg' );
}

/** The qtranslate_* field types and the standard type each one is based on. */
function qtrad_acf_field_types() {
	return array(
		'qtranslate_text'        => 'text',
		'qtranslate_textarea'    => 'textarea',
		'qtranslate_url'         => 'url',
		'qtranslate_wysiwyg'     => 'wysiwyg',
		'qtranslate_image'       => 'image',
		'qtranslate_file'        => 'file',
		'qtranslate_post_object' => 'post_object',
	);
}

function qtrad_acf_register_field_types() {
	require_once QTRAD_DIR . 'includes/acf-fields.php';
	foreach ( array( 'Qtrad_Acf_Field_Text', 'Qtrad_Acf_Field_Textarea', 'Qtrad_Acf_Field_Url', 'Qtrad_Acf_Field_Wysiwyg', 'Qtrad_Acf_Field_Image', 'Qtrad_Acf_Field_File', 'Qtrad_Acf_Field_Post_Object' ) as $class ) {
		if ( class_exists( $class ) ) {
			acf_register_field_type( $class );
		}
	}
}

/** Whether a field is one of ours, or a standard field set or stored as multilingual. */
function qtrad_acf_is_multilingual( $field, $value = null ) {
	if ( ! is_array( $field ) || empty( $field['type'] ) ) {
		return false;
	}
	if ( isset( qtrad_acf_field_types()[ $field['type'] ] ) ) {
		return true;
	}
	if ( ! in_array( $field['type'], qtrad_acf_switchable_types(), true ) ) {
		return false;
	}
	// A value with markers, or one posted by the per-language inputs, marks the field as multilingual wherever it is rendered, validated or saved.
	return ! empty( $field['qtrad_translate'] ) || ( is_string( $value ) && qtrad_has_lang_tags( $value ) ) || qtrad_acf_is_language_array( $value );
}

/** One value per enabled language from a stored string. */
function qtrad_acf_split( $value ) {
	$values = array();
	$parts  = is_string( $value ) ? qtrad_split( $value, null, false ) : array();
	foreach ( qtrad_enabled_languages() as $lang ) {
		$values[ $lang ] = isset( $parts[ $lang ] ) && is_string( $parts[ $lang ] ) ? $parts[ $lang ] : ( $lang === qtrad_default_language() && ! is_string( $value ) && is_scalar( $value ) ? (string) $value : '' );
	}
	return $values;
}

/** The posted values of one field, keyed by language, as one stored string with bracket markers. */
function qtrad_acf_join( $values ) {
	$texts = array();
	foreach ( qtrad_enabled_languages() as $lang ) {
		$texts[ $lang ] = isset( $values[ $lang ] ) && is_scalar( $values[ $lang ] ) ? (string) $values[ $lang ] : '';
	}
	return qtrad_join( $texts, 'bracket', qtrad_enabled_languages() );
}

/** Whether a posted value is an array keyed by enabled languages only. */
function qtrad_acf_is_language_array( $value ) {
	if ( ! is_array( $value ) || ! $value ) {
		return false;
	}
	foreach ( array_keys( $value ) as $key ) {
		if ( ! is_string( $key ) || ! qtrad_is_enabled( $key ) ) {
			return false;
		}
	}
	return true;
}

/** Values read with get_field() come in the current language. */
function qtrad_acf_format_value( $value ) {
	// ACF values are typed (ids, URLs, numbers), so a missing translation falls back without the language-name prefix.
	return qtrad_translates_values() ? qtrad_translate_deep( $value, false ) : $value;
}

/** Before a field is rendered: standard fields set as multilingual render as the qtranslate_* type, labels show in the admin language. */
function qtrad_acf_prepare_field( $field ) {
	if ( ! is_array( $field ) || empty( $field['type'] ) ) {
		return $field;
	}
	if ( in_array( $field['type'], qtrad_acf_switchable_types(), true ) && qtrad_acf_is_multilingual( $field, isset( $field['value'] ) ? $field['value'] : null ) ) {
		$field['type'] = 'qtranslate_' . $field['type'];
	}
	foreach ( array( 'label', 'instructions' ) as $key ) {
		if ( isset( $field[ $key ] ) && is_string( $field[ $key ] ) && qtrad_has_lang_tags( $field[ $key ] ) ) {
			$field[ $key ] = qtrad_use_language( $field[ $key ], is_admin() ? qtrad_admin_language() : qtrad_current_language() );
		}
	}
	return $field;
}

/** A field posted with one value per language is stored as one string. */
/**
 * A multilingual value is stored as one string. Languages that are not
 * enabled have no input, so their stored translations are kept.
 */
function qtrad_acf_update_value( $value, $post_id, $field ) {
	if ( ! qtrad_acf_is_multilingual( $field, $value ) ) {
		return $value;
	}
	if ( qtrad_acf_is_language_array( $value ) ) {
		$value = qtrad_acf_join( $value );
	}
	return is_string( $value ) && qtrad_has_lang_tags( $value ) ? qtrad_acf_merge( $value, qtrad_acf_raw_value( $post_id, $field ) ) : $value;
}

/** The stored value of a field before it is updated, without any translation applied. */
function qtrad_acf_raw_value( $post_id, $field ) {
	if ( ! function_exists( 'acf_get_metadata' ) || empty( $field['name'] ) ) {
		return null;
	}
	$flags = array( 'qtrad_raw_meta' => ! empty( $GLOBALS['qtrad_raw_meta'] ), 'qtrad_raw_options' => ! empty( $GLOBALS['qtrad_raw_options'] ) );
	$GLOBALS['qtrad_raw_meta']    = true;
	$GLOBALS['qtrad_raw_options'] = true;
	$priority = has_filter( 'get_post_metadata', 'qtrad_filter_get_meta' );
	if ( false !== $priority ) {
		remove_filter( 'get_post_metadata', 'qtrad_filter_get_meta', $priority );
	}
	try {
		return acf_get_metadata( $post_id, $field['name'] );
	} finally {
		$GLOBALS['qtrad_raw_meta']    = $flags['qtrad_raw_meta'];
		$GLOBALS['qtrad_raw_options'] = $flags['qtrad_raw_options'];
		if ( false !== $priority ) {
			add_filter( 'get_post_metadata', 'qtrad_filter_get_meta', $priority, 4 );
		}
	}
}

/**
 * Merge a new multilingual value into the stored one: enabled languages take
 * the new text, also when it is empty; other languages keep their stored text
 * unless the new value carries them.
 */
function qtrad_acf_merge( $new, $old ) {
	if ( ! is_string( $old ) || ! qtrad_has_lang_tags( $old ) ) {
		return $new;
	}
	$enabled = qtrad_enabled_languages();
	$texts   = qtrad_split( $old, $enabled, false );
	$fresh   = qtrad_split( $new, $enabled, false );
	foreach ( $enabled as $lang ) {
		$texts[ $lang ] = isset( $fresh[ $lang ] ) ? $fresh[ $lang ] : '';
	}
	foreach ( $fresh as $lang => $text ) {
		if ( ! in_array( $lang, $enabled, true ) && is_string( $text ) && '' !== $text ) {
			$texts[ $lang ] = $text;
		}
	}
	return qtrad_join( $texts, 'bracket', $enabled );
}

/**
 * Join the posted language arrays before ACF validates and saves, so its own
 * checks run on the stored form. Repeater and group rows are walked as well.
 */
function qtrad_acf_join_posted_values() {
	// ACF checks its form nonce before it validates: AJAX validation with the
	// acf_nonce action, a form save with the screen name, after which ACF sets
	// _acf_nonce to false so it is used only once.
	if ( wp_doing_ajax() ) {
		$verified = isset( $_REQUEST['nonce'] ) && is_string( $_REQUEST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'acf_nonce' );
	} elseif ( isset( $_POST['_acf_nonce'] ) && is_string( $_POST['_acf_nonce'] ) ) {
		$screen   = isset( $_POST['_acf_screen'] ) && is_string( $_POST['_acf_screen'] ) ? sanitize_key( wp_unslash( $_POST['_acf_screen'] ) ) : '';
		$verified = '' !== $screen && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_acf_nonce'] ) ), $screen );
	} else {
		$verified = isset( $_POST['_acf_nonce'] ) && false === $_POST['_acf_nonce'];
	}
	if ( ! $verified || empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
		return;
	}
	// Only the language arrays of multilingual fields are used, and each value is sanitized for its field type before it goes back.
	$posted = wp_unslash( $_POST['acf'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each used value is sanitized in qtrad_acf_sanitize_languages(); the rest is not used.
	foreach ( qtrad_acf_posted_language_values( $posted, array() ) as $item ) {
		list( $path, $field, $values ) = $item;
		$joined = wp_slash( qtrad_acf_join( qtrad_acf_sanitize_languages( $values, $field ) ) );
		$target = &$_POST['acf']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Written to, not read: the joined value replaces the language array.
		foreach ( $path as $key ) {
			$target = &$target[ $key ];
		}
		$target = $joined;
		unset( $target );
	}
}

/**
 * The posted language arrays of multilingual fields, with their place in the
 * posted tree. Repeater and group rows are walked as well.
 *
 * @return array List of array( path, field, values by language ).
 */
function qtrad_acf_posted_language_values( $values, $path ) {
	$found = array();
	foreach ( $values as $key => $value ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'field_' ) && qtrad_acf_is_language_array( $value ) ) {
			$field = acf_get_field( $key );
			if ( $field && qtrad_acf_is_multilingual( $field ) ) {
				$found[] = array( array_merge( $path, array( $key ) ), $field, $value );
				continue;
			}
		}
		if ( is_array( $value ) ) {
			$found = array_merge( $found, qtrad_acf_posted_language_values( $value, array_merge( $path, array( $key ) ) ) );
		}
	}
	return $found;
}

/**
 * Each language value sanitized for the field's base type. Text keeps the
 * HTML a user may save in post content: any for users with unfiltered_html,
 * as core allows, otherwise what wp_kses_post() permits.
 */
function qtrad_acf_sanitize_languages( $values, $field ) {
	$types = qtrad_acf_field_types();
	$base  = isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : $field['type'];
	foreach ( $values as $lang => $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( 'url' === $base ) {
			// Tags removed only; ACF's URL check of each language then rejects anything that is not a URL.
			$values[ $lang ] = trim( wp_strip_all_tags( $value ) );
		} elseif ( in_array( $base, array( 'image', 'file', 'post_object' ), true ) ) {
			$values[ $lang ] = '' === $value ? '' : (string) absint( $value );
		} elseif ( ! current_user_can( 'unfiltered_html' ) ) {
			$values[ $lang ] = wp_kses_post( $value );
		} else {
			$values[ $lang ] = $value;
		}
	}
	return $values;
}

/**
 * Each language is checked with the base field type's own rules, such as a
 * valid URL or a character limit; a required field needs the default language.
 *
 * @return true|false|string True, false or an error message, as ACF validators return.
 */
function qtrad_acf_validate_languages( $value, $field, $input, $base ) {
	$type   = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( $base ) : null;
	$single = $field;
	$single['type'] = $base;
	foreach ( qtrad_acf_split( $value ) as $lang => $text ) {
		if ( '' === $text ) {
			if ( ! empty( $field['required'] ) && $lang === qtrad_default_language() ) {
				/* translators: 1: language name, 2: field label */
				return sprintf( __( '%1$s: %2$s is required', 'qtrad' ), qtrad_language_name( $lang ), qtrad_use_language( (string) $field['label'], is_admin() ? qtrad_admin_language() : qtrad_current_language() ) );
			}
			continue;
		}
		$result = $type && method_exists( $type, 'validate_value' ) ? $type->validate_value( true, $text, $single, $input ) : true;
		if ( false === $result ) {
			return false;
		}
		if ( is_string( $result ) && '' !== $result ) {
			return qtrad_language_name( $lang ) . ': ' . $result;
		}
	}
	return true;
}

/** A multilingual value as one marker string, or null when the value is not one. */
function qtrad_acf_marker_value( $value ) {
	if ( qtrad_acf_is_language_array( $value ) ) {
		$value = qtrad_acf_join( $value );
	}
	return is_string( $value ) && qtrad_has_lang_tags( $value ) ? $value : null;
}

/**
 * Runs after ACF checked a standard text, text area, URL or WYSIWYG field.
 * ACF tested the marker string as a whole, where a URL check or a length limit
 * fails on the markers; that result is replaced by a check of each language.
 * A failure from anything else, such as another validator for the type, stays.
 */
function qtrad_acf_validate_standard( $valid, $value, $field, $input ) {
	$marked = qtrad_acf_is_multilingual( $field, $value ) ? qtrad_acf_marker_value( $value ) : null;
	if ( null === $marked ) {
		return $valid;
	}
	$type     = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( $field['type'] ) : null;
	$combined = $type && method_exists( $type, 'validate_value' ) ? $type->validate_value( true, $marked, $field, $input ) : true;
	if ( true !== $valid && $valid !== $combined ) {
		return $valid;
	}
	return qtrad_acf_validate_languages( $marked, $field, $input, $field['type'] );
}

function qtrad_acf_render_translate_setting( $field ) {
	acf_render_field_setting(
		$field,
		array(
			'label'        => __( 'Translate', 'qtrad' ),
			'instructions' => __( 'One value per language, stored with language markers.', 'qtrad' ),
			'type'         => 'true_false',
			'name'         => 'qtrad_translate',
			'ui'           => 1,
		)
	);
}

function qtrad_acf_enqueue() {
	wp_enqueue_script( 'qtrad-acf', plugins_url( 'assets/js/acf.js', QTRAD_FILE ), array( 'acf-input', 'jquery' ), QTRAD_VERSION, true );
	wp_localize_script( 'qtrad-acf', 'qtradAcf', array( 'default' => qtrad_default_language(), 'types' => array_keys( qtrad_acf_field_types() ) ) );
	wp_add_inline_style( 'acf-input', '.qtrad-acf-lang { margin-block-end: 12px; } .qtrad-acf-lang:last-child { margin-block-end: 0; } .qtrad-acf-lang__name { display: block; margin-block-end: 4px; font-weight: 600; }' );
}
