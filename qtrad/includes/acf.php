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
	add_filter( 'acf/validate_value', 'qtrad_acf_validate_value', 20, 4 );
	add_action( 'acf/validate_save_post', 'qtrad_acf_join_posted_values', 1 );
	add_action( 'acf/input/admin_enqueue_scripts', 'qtrad_acf_enqueue' );
	foreach ( qtrad_acf_switchable_types() as $type ) {
		add_action( 'acf/render_field_settings/type=' . $type, 'qtrad_acf_render_translate_setting' );
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
	return ! empty( $field['qtrad_translate'] ) || ( is_string( $value ) && qtrad_has_lang_tags( $value ) );
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
function qtrad_acf_update_value( $value, $post_id, $field ) {
	return qtrad_acf_is_multilingual( $field ) && qtrad_acf_is_language_array( $value ) ? qtrad_acf_join( $value ) : $value;
}

/**
 * Join the posted language arrays before ACF validates and saves, so its own
 * checks run on the stored form. Repeater and group rows are walked as well.
 */
function qtrad_acf_join_posted_values() {
	if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF verifies its nonce before this action.
		return;
	}
	$_POST['acf'] = qtrad_acf_join_tree( wp_unslash( $_POST['acf'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values stay as posted for ACF to sanitize; only language arrays are joined.
}

function qtrad_acf_join_tree( $values, $slash ) {
	if ( ! is_array( $values ) ) {
		return $values;
	}
	foreach ( $values as $key => $value ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'field_' ) && qtrad_acf_is_language_array( $value ) ) {
			$field = acf_get_field( $key );
			if ( $field && qtrad_acf_is_multilingual( $field ) ) {
				$values[ $key ] = $slash ? wp_slash( qtrad_acf_join( $value ) ) : qtrad_acf_join( $value );
				continue;
			}
		}
		if ( is_array( $value ) ) {
			$values[ $key ] = qtrad_acf_join_tree( $value, $slash );
		} elseif ( $slash && is_string( $value ) ) {
			$values[ $key ] = wp_slash( $value );
		}
	}
	return $values;
}

/**
 * ACF validated the stored form, where a URL check or a length limit fails on
 * the markers. Validate each language on its own instead; a required field
 * needs the default language.
 */
function qtrad_acf_validate_value( $valid, $value, $field, $input ) {
	if ( ! qtrad_acf_is_multilingual( $field, $value ) ) {
		return $valid;
	}
	if ( qtrad_acf_is_language_array( $value ) ) {
		$value = qtrad_acf_join( $value );
	}
	if ( ! is_string( $value ) || ! qtrad_has_lang_tags( $value ) ) {
		return $valid;
	}
	$base   = isset( qtrad_acf_field_types()[ $field['type'] ] ) ? qtrad_acf_field_types()[ $field['type'] ] : $field['type'];
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
		$result = apply_filters( 'acf/validate_value/type=' . $base, true, $text, $single, $input ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- ACF's own filter, applied per language.
		if ( is_string( $result ) && '' !== $result ) {
			return qtrad_language_name( $lang ) . ': ' . $result;
		}
	}
	return true;
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
