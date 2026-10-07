<?php
/**
 * ACF field types with one input per language. Loaded by qtrad_acf_register_field_types()
 * once ACF's own classes exist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'acf_field_text' ) ) {
	return;
}

/** Shared rendering: the base field once per enabled language, the default language keeps the field's id. */
function qtrad_acf_render_languages( $instance, $field, $base, $labelled ) {
	$values  = qtrad_acf_split( isset( $field['value'] ) ? $field['value'] : '' );
	$default = qtrad_default_language();
	$locales = qtrad_config( 'locale' );
	echo '<div class="qtrad-acf-langs">';
	foreach ( qtrad_enabled_languages() as $lang ) {
		$sub             = $field;
		$sub['type']     = $base;
		$sub['name']     = $field['name'] . '[' . $lang . ']';
		$sub['id']       = $lang === $default ? $field['id'] : $field['id'] . '-' . $lang;
		$sub['value']    = $values[ $lang ];
		$sub['required'] = $lang === $default ? ( isset( $field['required'] ) ? $field['required'] : 0 ) : 0;
		$tag             = isset( $locales[ $lang ] ) ? str_replace( '_', '-', $locales[ $lang ] ) : $lang;
		echo '<div class="qtrad-acf-lang" data-lang="' . esc_attr( $lang ) . '">';
		echo $labelled ? '<label class="qtrad-acf-lang__name" for="' . esc_attr( $sub['id'] ) . '">' : '<span class="qtrad-acf-lang__name">';
		echo '<bdi lang="' . esc_attr( $tag ) . '">' . esc_html( qtrad_language_name( $lang ) ) . '</bdi>';
		echo $labelled ? '</label>' : '</span>';
		$instance->qtrad_render_base( $sub );
		echo '</div>';
	}
	echo '</div>';
}

class Qtrad_Acf_Field_Text extends acf_field_text {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_text';
		$this->label    = __( 'Text (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'text' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'text', true );
	}
}

class Qtrad_Acf_Field_Textarea extends acf_field_textarea {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_textarea';
		$this->label    = __( 'Text area (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'textarea' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'textarea', true );
	}
}

class Qtrad_Acf_Field_Url extends acf_field_url {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_url';
		$this->label    = __( 'URL (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'url' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'url', true );
	}
}

class Qtrad_Acf_Field_Wysiwyg extends acf_field_wysiwyg {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_wysiwyg';
		$this->label    = __( 'WYSIWYG editor (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'wysiwyg' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'wysiwyg', true );
	}
}

class Qtrad_Acf_Field_Image extends acf_field_image {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_image';
		$this->label    = __( 'Image (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'image' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'image', false );
	}
	public function update_value( $value, $post_id, $field ) {
		return qtrad_acf_update_attachments( $this, $value, $post_id, $field );
	}
	public function qtrad_update_base( $value, $post_id, $field ) {
		return parent::update_value( $value, $post_id, $field );
	}
}

class Qtrad_Acf_Field_File extends acf_field_file {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_file';
		$this->label    = __( 'File (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'file' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'file', false );
	}
	public function update_value( $value, $post_id, $field ) {
		return qtrad_acf_update_attachments( $this, $value, $post_id, $field );
	}
	public function qtrad_update_base( $value, $post_id, $field ) {
		return parent::update_value( $value, $post_id, $field );
	}
}

/** One attachment id per language; each one is connected to the post as ACF does. */
function qtrad_acf_update_attachments( $instance, $value, $post_id, $field ) {
	if ( qtrad_acf_is_language_array( $value ) ) {
		$value = qtrad_acf_join( $value );
	}
	if ( ! is_string( $value ) || ! qtrad_has_lang_tags( $value ) ) {
		return $instance->qtrad_update_base( $value, $post_id, $field );
	}
	if ( function_exists( 'acf_connect_attachment_to_post' ) ) {
		foreach ( qtrad_acf_split( $value ) as $id ) {
			if ( is_numeric( $id ) ) {
				acf_connect_attachment_to_post( (int) $id, $post_id );
			}
		}
	}
	return $value;
}

class Qtrad_Acf_Field_Post_Object extends acf_field_post_object {
	public function initialize() {
		parent::initialize();
		$this->name     = 'qtranslate_post_object';
		$this->label    = __( 'Post object (per language)', 'qtrad' );
		$this->category = 'qTrad';
	}
	public function qtrad_render_base( $field ) {
		$field['multiple'] = 0;
		parent::render_field( $field );
	}
	/** Each language is checked on its own; the marker string as a whole would fail checks such as a valid URL. */
	public function validate_value( $valid, $value, $field, $input ) {
		$marked = qtrad_acf_marker_value( $value );
		if ( null === $marked ) {
			return method_exists( get_parent_class( $this ), 'validate_value' ) ? parent::validate_value( $valid, $value, $field, $input ) : $valid;
		}
		return true !== $valid ? $valid : qtrad_acf_validate_languages( $marked, $field, $input, 'post_object' );
	}
	public function render_field( $field ) {
		qtrad_acf_render_languages( $this, $field, 'post_object', false );
	}
	/** The stored string stays a string; the base class would turn it into an id. */
	public function load_value( $value, $post_id, $field ) {
		return is_string( $value ) && qtrad_has_lang_tags( $value ) ? $value : parent::load_value( $value, $post_id, $field );
	}
	public function update_value( $value, $post_id, $field ) {
		if ( qtrad_acf_is_language_array( $value ) ) {
			$value = qtrad_acf_join( $value );
		}
		return is_string( $value ) && qtrad_has_lang_tags( $value ) ? $value : parent::update_value( $value, $post_id, $field );
	}
}
