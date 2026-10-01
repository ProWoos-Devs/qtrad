<?php
/**
 * Language chooser. The widget id base is "qtranslate", which is the id
 * both qTranslate and qTranslate-X registered, so an existing sidebar keeps its switcher.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_widget() {
	add_action( 'widgets_init', 'qtrad_widgets_init' );
	add_action( 'admin_bar_menu', 'qtrad_admin_bar', 80 );
}

function qtrad_widgets_init() {
	register_widget( 'Qtrad_Widget' );
}

function qtrad_register_shortcodes() {
	add_shortcode( 'qtrad_switcher', 'qtrad_shortcode_switcher' );
}

function qtrad_shortcode_switcher( $atts ) {
	$atts = shortcode_atts( array( 'style' => 'both', 'id' => '' ), $atts, 'qtrad_switcher' );
	return qtrad_language_chooser( $atts['style'], $atts['id'] );
}

function qtrad_admin_bar( $bar ) {
	if ( ! $bar instanceof WP_Admin_Bar ) {
		return;
	}
	$editing = is_admin() ? qtrad_admin_language() : qtrad_current_language();
	$bar->add_node(
		array(
			'id'    => 'qtrad-lang',
			'title' => esc_html( qtrad_language_name( $editing ) ),
			'href'  => is_admin() ? admin_url( 'options-general.php?page=qtrad' ) : qtrad_convert_url( '', $editing, false, true ),
		)
	);
	foreach ( qtrad_enabled_languages() as $lang ) {
		if ( is_admin() ) {
			$url = wp_nonce_url( add_query_arg( 'qtrad_lang', $lang ), 'qtrad_language' );
		} else {
			$url = qtrad_convert_url( '', $lang, false, true );
		}
		$bar->add_node(
			array(
				'id'     => 'qtrad-lang-' . $lang,
				'parent' => 'qtrad-lang',
				'title'  => esc_html( qtrad_language_name( $lang ) ),
				'href'   => esc_url( $url ),
			)
		);
	}
}

class Qtrad_Widget extends WP_Widget {
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
		echo wp_kses_post( $args['before_widget'] );
		if ( $title !== '' && empty( $instance['hide-title'] ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $title ) ) . wp_kses_post( $args['after_title'] );
		}
		echo wp_kses( qtrad_language_chooser( $style, $this->id ), qtrad_chooser_allowed_html() );
		echo wp_kses_post( $args['after_widget'] );
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

// qTranslate and qTranslate-X widget classes, for integrations that reference them.
if ( ! class_exists( 'qTranslateWidget' ) ) {
	class qTranslateWidget extends Qtrad_Widget {}
}

if ( ! class_exists( 'qTranslateXWidget' ) ) {
	class qTranslateXWidget extends Qtrad_Widget {}
}
