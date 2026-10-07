<?php
/**
 * Language inputs for single text settings outside the post editor, such as
 * WooCommerce email subjects and attribute labels. The stored value stays one
 * string with language markers; the script splits it into one input per
 * language and joins it back as the visitor types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_admin_field_hooks() {
	if ( is_admin() ) {
		add_action( 'admin_enqueue_scripts', 'qtrad_admin_fields_enqueue' );
	}
}

/**
 * Fields to split on the current admin screen.
 *
 * @param string $screen_id Current screen id.
 * @return array{selector:string,namePattern:string,always:bool}|null
 */
function qtrad_admin_field_rules( $screen_id ) {
	$rules = array(
		// WooCommerce settings: titles, descriptions and email texts, and any text field that already holds markers.
		'woocommerce_page_wc-settings'    => array(
			'selector'    => '.woocommerce form input[type="text"][name], .woocommerce form textarea[name]',
			'namePattern' => '^woocommerce_(?:email_footer_text|email_from_name|demo_store_notice|[a-z0-9_]+_(?:title|description|instructions|subject|heading|additional_content|subject_full|subject_partial|heading_full|heading_partial|subject_paid|heading_paid))$',
			'always'      => false,
		),
		'product'                         => array(
			'selector'    => '#_purchase_note',
			'namePattern' => '',
			'always'      => true,
		),
		'product_page_product_attributes' => array(
			'selector'    => '#attribute_label',
			'namePattern' => '',
			'always'      => true,
		),
	);
	/**
	 * Screens and fields that get one input per language.
	 *
	 * @param array  $rules     Rules by screen id: selector, namePattern (JavaScript regular expression, empty for none) and always.
	 * @param string $screen_id Current screen id.
	 */
	$rules = apply_filters( 'qtrad_admin_field_rules', $rules, $screen_id );
	return isset( $rules[ $screen_id ] ) && is_array( $rules[ $screen_id ] ) && ! empty( $rules[ $screen_id ]['selector'] ) ? $rules[ $screen_id ] : null;
}

function qtrad_admin_fields_enqueue() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$rule   = $screen && count( qtrad_enabled_languages() ) > 1 ? qtrad_admin_field_rules( $screen->id ) : null;
	if ( ! $rule ) {
		return;
	}
	wp_enqueue_script( 'qtrad-codec', plugins_url( 'assets/js/codec.js', QTRAD_FILE ), array(), QTRAD_VERSION, true );
	wp_enqueue_script( 'qtrad-fields', plugins_url( 'assets/js/fields.js', QTRAD_FILE ), array( 'qtrad-codec' ), QTRAD_VERSION, true );
	wp_enqueue_style( 'qtrad-admin', plugins_url( 'assets/css/admin.css', QTRAD_FILE ), array(), QTRAD_VERSION );
	$names = array();
	$locales = array();
	$directions = array();
	foreach ( qtrad_enabled_languages() as $lang ) {
		$names[ $lang ]      = qtrad_language_name( $lang );
		$locale              = qtrad_config( 'locale' );
		$locales[ $lang ]    = isset( $locale[ $lang ] ) ? str_replace( '_', '-', $locale[ $lang ] ) : $lang;
		$directions[ $lang ] = qtrad_language_direction( $lang );
	}
	wp_localize_script(
		'qtrad-fields',
		'qtradFieldsConfig',
		array(
			'selector'        => (string) $rule['selector'],
			'namePattern'     => isset( $rule['namePattern'] ) ? (string) $rule['namePattern'] : '',
			'always'          => ! empty( $rule['always'] ),
			'enabled'         => qtrad_enabled_languages(),
			'defaultLanguage' => qtrad_default_language(),
			'names'           => $names,
			'locales'         => $locales,
			'directions'      => $directions,
			'forceMarkers'    => (bool) qtrad_setting( 'force_markers', false ),
		)
	);
}
