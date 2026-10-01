<?php
/**
 * Language chooser. The widget id base is "qtranslate", which is the id
 * both qTranslate and qTranslate-X registered, so an existing sidebar keeps its switcher.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtu_register_widget() {
	add_action( 'widgets_init', 'qtu_widgets_init' );
	add_action( 'admin_bar_menu', 'qtu_admin_bar', 80 );
}

function qtu_widgets_init() {
	register_widget( 'qTradWidget' );
}

function qtu_register_shortcodes() {
	add_shortcode( 'qtrad_switcher', 'qtu_shortcode_switcher' );
	add_shortcode( 'qtranslate_switcher', 'qtu_shortcode_switcher' );
	add_shortcode( 'qtu_switcher', 'qtu_shortcode_switcher' );
}

function qtu_shortcode_switcher( $atts ) {
	$atts = shortcode_atts( array( 'style' => 'both', 'id' => '' ), $atts, 'qtranslate_switcher' );
	return qtu_language_chooser( $atts['style'], $atts['id'] );
}

function qtu_admin_bar( $bar ) {
	if ( ! $bar instanceof WP_Admin_Bar ) {
		return;
	}
	$editing = is_admin() ? qtu_admin_language() : qtu_current_language();
	$bar->add_node(
		array(
			'id'    => 'qtu-lang',
			'title' => esc_html( qtu_language_name( $editing ) ),
			'href'  => is_admin() ? admin_url( 'options-general.php?page=qtrad' ) : qtu_convert_url( '', $editing, false, true ),
		)
	);
	foreach ( qtu_enabled_languages() as $lang ) {
		if ( is_admin() ) {
			$url = wp_nonce_url( add_query_arg( 'qtu_lang', $lang ), 'qtu_language' );
		} else {
			$url = qtu_convert_url( '', $lang, false, true );
		}
		$bar->add_node(
			array(
				'id'     => 'qtu-lang-' . $lang,
				'parent' => 'qtu-lang',
				'title'  => esc_html( qtu_language_name( $lang ) ),
				'href'   => esc_url( $url ),
			)
		);
	}
}

class qTradWidget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'qtranslate',
			__( 'qTrad Language Chooser', 'qtrad' ),
			array(
				'description' => __( 'Switch language. Replaces the qTranslate and qTranslate-X widget in sidebars that already had it.', 'qtrad' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$style = isset( $instance['type'] ) ? $instance['type'] : 'both';
		echo $args['before_widget'];
		if ( $title !== '' && empty( $instance['hide-title'] ) ) {
			echo $args['before_title'] . esc_html( qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $title ) ) . $args['after_title'];
		}
		echo qtu_language_chooser( $style, $this->id );
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$type  = isset( $instance['type'] ) ? $instance['type'] : 'both';
		$types = array(
			'text'     => __( 'Text only', 'qtrad' ),
			'image'    => __( 'Flags', 'qtrad' ),
			'both'     => __( 'Flags and text', 'qtrad' ),
			'short'    => __( 'Language codes', 'qtrad' ),
			'dropdown' => __( 'Dropdown', 'qtrad' ),
		);
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html__( 'Title', 'qtrad' ) . '</label>';
		echo '<input class="widefat" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" type="text" value="' . esc_attr( $title ) . '" /></p>';
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'type' ) ) . '">' . esc_html__( 'Display', 'qtrad' ) . '</label>';
		echo '<select class="widefat" id="' . esc_attr( $this->get_field_id( 'type' ) ) . '" name="' . esc_attr( $this->get_field_name( 'type' ) ) . '">';
		foreach ( $types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="' . esc_attr( $this->get_field_name( 'hide-title' ) ) . '" value="1"' . checked( ! empty( $instance['hide-title'] ), true, false ) . ' /> ' . esc_html__( 'Hide title', 'qtrad' ) . '</label></p>';
	}

	public function update( $new_instance, $old_instance ) {
		$old_instance = is_array( $old_instance ) ? $old_instance : array();
		$allowed = array( 'text', 'image', 'both', 'short', 'dropdown' );
		$type    = isset( $new_instance['type'] ) ? $new_instance['type'] : 'both';
		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'both';
		}
		return array_merge( $old_instance, array(
			'hide-title' => ! empty( $new_instance['hide-title'] ),
			'title' => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'type'  => $type,
		) );
	}
}

// Retain former class names for integrations without registering another widget.
if ( ! class_exists( 'qTranslateNextWidget' ) ) {
	class qTranslateNextWidget extends qTradWidget {}
}

if ( ! class_exists( 'qTranslateWidget' ) ) {
	class qTranslateWidget extends qTranslateNextWidget {}
}

if ( ! class_exists( 'qTranslateXWidget' ) ) {
	class qTranslateXWidget extends qTranslateNextWidget {}
}

if ( ! class_exists( 'qTranslateUnifiedWidget' ) ) {
	class qTranslateUnifiedWidget extends qTranslateNextWidget {}
}
