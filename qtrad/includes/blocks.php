<?php
/**
 * Language switcher block, for block themes and anywhere blocks are used.
 * It prints the same accessible chooser as the widget and the shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_block_hooks() {
	add_action( 'init', 'qtrad_register_blocks' );
}

function qtrad_register_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	wp_register_style( 'qtrad-front', plugins_url( 'assets/css/front.css', QTRAD_FILE ), array(), QTRAD_VERSION );
	wp_register_script( 'qtrad-switcher-block', plugins_url( 'assets/js/switcher-block.js', QTRAD_FILE ), array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ), QTRAD_VERSION, true );
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'qtrad-switcher-block', 'qtrad' );
	}
	register_block_type( QTRAD_DIR . 'blocks/language-switcher', array( 'render_callback' => 'qtrad_render_switcher_block' ) );
}

function qtrad_render_switcher_block( $attributes ) {
	$display = isset( $attributes['display'] ) && is_string( $attributes['display'] ) ? $attributes['display'] : 'text';
	$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'qtrad-switcher-block' ) ) : 'class="qtrad-switcher-block"';
	return '<div ' . $wrapper . '>' . wp_kses( qtrad_language_chooser( $display ), qtrad_chooser_allowed_html() ) . '</div>';
}
