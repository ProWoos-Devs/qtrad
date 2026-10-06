<?php
/**
 * WooCommerce. Product names, descriptions, attributes, gateways, shipping
 * rates and email texts with language markers are shown in the visitor's
 * language, also in the cart and checkout blocks (Store API) and in mini-cart
 * fragments. Orders keep the customer's language in _user_language, the meta
 * key qTranslate-XT's WooCommerce module used, and customer emails are sent in
 * it; emails to the shop use the site's default language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_woocommerce_hooks() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	qtrad_wc_store_api_language();
	$text = array(
		'woocommerce_product_get_name',
		'woocommerce_product_variation_get_name',
		'woocommerce_product_get_short_description',
		'woocommerce_product_variation_get_description',
		'woocommerce_product_get_purchase_note',
		'woocommerce_short_description',
		'woocommerce_product_title',
		'woocommerce_cart_item_name',
		'woocommerce_order_item_name',
		'woocommerce_attribute_label',
		'woocommerce_attribute',
		'woocommerce_variation_option_name',
		'woocommerce_gateway_title',
		'woocommerce_gateway_description',
		'woocommerce_shipping_rate_label',
		'woocommerce_cart_shipping_method_full_label',
		'woocommerce_order_item_display_meta_key',
		'woocommerce_order_item_display_meta_value',
		'woocommerce_order_shipping_to_display',
		'woocommerce_page_title',
		'woocommerce_rate_label',
		'woocommerce_format_content',
		'woocommerce_get_privacy_policy_text',
		'woocommerce_demo_store',
		'woocommerce_email_footer_text',
		'woocommerce_email_from_name',
		'woocommerce_structured_data_product',
		'woocommerce_order_get_payment_method_title',
	);
	foreach ( $text as $hook ) {
		add_filter( $hook, 'qtrad_wc_translate', 20 );
	}
	add_filter( 'woocommerce_email_get_option', 'qtrad_wc_translate', 20 );
	// Saving reads some fields in view context; stored values must stay multilingual.
	foreach ( array( 'product', 'product_variation', 'order' ) as $type ) {
		add_action( 'woocommerce_before_' . $type . '_object_save', 'qtrad_wc_saving_start', 0 );
		add_action( 'woocommerce_after_' . $type . '_object_save', 'qtrad_wc_saving_end', PHP_INT_MAX );
	}
	add_filter( 'woocommerce_cart_hash', 'qtrad_wc_cart_hash', 20 );
	add_filter( 'woocommerce_get_checkout_order_received_url', 'qtrad_wc_order_url', 20, 2 );
	add_filter( 'woocommerce_get_view_order_url', 'qtrad_wc_order_url', 20, 2 );
	add_action( 'woocommerce_checkout_create_order', 'qtrad_wc_set_order_language', 10 );
	add_action( 'woocommerce_store_api_checkout_update_order_meta', 'qtrad_wc_set_order_language', 10 );
	add_action( 'woocommerce_checkout_create_order_line_item', 'qtrad_wc_order_line_item', 10, 4 );
	add_filter( 'woocommerce_email_classes', 'qtrad_wc_email_hooks', 999 );
	add_action( 'woocommerce_email_sent', 'qtrad_wc_email_done' );
	add_action( 'woocommerce_email_skipped', 'qtrad_wc_email_done' );
	add_filter( 'woocommerce_email_restore_locale', 'qtrad_wc_email_restore', 1 );
}

/** Whether a request goes to the Store API, which the cart and checkout blocks use. */
function qtrad_wc_is_store_api() {
	return qtrad_is_rest_request() && (bool) preg_match( '#/wc/store(?:/|$)#', (string) wp_parse_url( qtrad_request_uri(), PHP_URL_PATH ) . '/' . ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
}

/**
 * Store API requests are made by scripts on a page in some language but their
 * addresses carry none, so they use the language the visitor last chose.
 */
function qtrad_wc_store_api_language() {
	if ( ! qtrad_wc_is_store_api() ) {
		return;
	}
	$path = qtrad_relative_path( (string) wp_parse_url( qtrad_request_uri(), PHP_URL_PATH ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only language selection.
	$explicit = qtrad_strip_path_language( $path ) !== $path || ( isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) && qtrad_is_enabled( sanitize_key( wp_unslash( $_GET['lang'] ) ) ) );
	$cookie   = qtrad_cookie_front();
	if ( ! $explicit && '' !== $cookie ) {
		qtrad_set_language( $cookie );
	}
}

/** Where WooCommerce output is shown in one language: visitor pages, wc-ajax fragments, the Store API and emails. */
function qtrad_wc_translates() {
	if ( ! empty( $GLOBALS['qtrad_wc_email'] ) || qtrad_translates_values() || qtrad_wc_is_store_api() ) {
		return true;
	}
	// wc-ajax requests define DOING_AJAX but render for the visitor.
	return ! is_admin() && isset( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the endpoint.
}

function qtrad_wc_saving_start() {
	$GLOBALS['qtrad_wc_saving'] = isset( $GLOBALS['qtrad_wc_saving'] ) ? $GLOBALS['qtrad_wc_saving'] + 1 : 1;
}

function qtrad_wc_saving_end() {
	$GLOBALS['qtrad_wc_saving'] = max( 0, ( isset( $GLOBALS['qtrad_wc_saving'] ) ? $GLOBALS['qtrad_wc_saving'] : 1 ) - 1 );
}

function qtrad_wc_translate( $value ) {
	if ( ! empty( $GLOBALS['qtrad_wc_saving'] ) || ! qtrad_wc_translates() ) {
		return $value;
	}
	return is_string( $value ) ? qtrad_use_current( $value ) : qtrad_translate_deep( $value );
}

/** Mini-cart fragments are cached in the browser by this hash, so each language gets its own. */
function qtrad_wc_cart_hash( $hash ) {
	return is_string( $hash ) && '' !== $hash ? md5( $hash . '|' . qtrad_current_language() ) : $hash;
}

function qtrad_wc_set_order_language( $order ) {
	if ( is_object( $order ) && method_exists( $order, 'update_meta_data' ) ) {
		$order->update_meta_data( '_user_language', qtrad_current_language() );
	}
}

/** The block checkout builds the thank-you address in a request without a language; it opens in the order's language. */
function qtrad_wc_order_url( $url, $order = null ) {
	$lang = qtrad_wc_order_language( $order );
	return is_string( $url ) && '' !== $lang ? qtrad_convert_url( $url, $lang, true, null ) : $url;
}

/** Order items keep the customer's language, also for custom attribute values. */
function qtrad_wc_order_line_item( $item, $cart_item_key, $values, $order ) {
	if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta_data' ) ) {
		return;
	}
	foreach ( $item->get_meta_data() as $meta ) {
		$data = $meta->get_data();
		if ( isset( $data['value'] ) && is_string( $data['value'] ) && qtrad_has_lang_tags( $data['value'] ) ) {
			$item->update_meta_data( $data['key'], qtrad_use_current( $data['value'] ), isset( $data['id'] ) ? $data['id'] : 0 );
		}
	}
}

/** Language a customer email for this order goes out in. */
function qtrad_wc_order_language( $order ) {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return '';
	}
	$lang = (string) $order->get_meta( '_user_language', true );
	return qtrad_is_enabled( $lang ) ? $lang : '';
}

function qtrad_wc_email_hooks( $emails ) {
	foreach ( (array) $emails as $email ) {
		if ( is_object( $email ) && ! empty( $email->id ) ) {
			add_filter( 'woocommerce_email_recipient_' . $email->id, 'qtrad_wc_email_start', 1, 3 );
		}
	}
	return $emails;
}

/**
 * WooCommerce asks for the recipient right before it builds the subject and
 * content, which is when a customer email switches to the order's language.
 */
function qtrad_wc_email_start( $recipient, $object = null, $email = null ) {
	if ( ! empty( $GLOBALS['qtrad_wc_email'] ) || ! qtrad_wc_sending() ) {
		return $recipient;
	}
	// Customer emails go out in the order's language, emails to the shop in the site's default language.
	$customer = is_object( $email ) && method_exists( $email, 'is_customer_email' ) && $email->is_customer_email();
	$lang     = $customer ? qtrad_wc_order_language( $object ) : qtrad_default_language();
	if ( $lang === qtrad_current_language() ) {
		$lang = '';
	}
	$GLOBALS['qtrad_wc_email'] = array( 'language' => qtrad_current_language(), 'locale' => false );
	if ( '' !== $lang ) {
		// Switch the locale first: WordPress compares with determine_locale(), which follows the qTrad language.
		$locales = qtrad_config( 'locale' );
		if ( ! empty( $locales[ $lang ] ) && function_exists( 'switch_to_locale' ) ) {
			$GLOBALS['qtrad_wc_email']['locale'] = switch_to_locale( $locales[ $lang ] );
		}
		qtrad_set_language( $lang );
		// The site title placeholder was filled when the email object was built, in the request's language.
		if ( isset( $email->placeholders ) && is_array( $email->placeholders ) && method_exists( $email, 'get_blogname' ) ) {
			$email->placeholders['{site_title}'] = $email->get_blogname();
		}
	}
	return $recipient;
}

/** The recipient is also read to display settings; only a send switches the language. */
function qtrad_wc_sending() {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Finds the caller, nothing is printed.
		if ( isset( $frame['class'], $frame['function'] ) && in_array( $frame['function'], array( 'trigger', 'send_notification' ), true ) && is_a( $frame['class'], 'WC_Email', true ) ) {
			return true;
		}
	}
	return false;
}

function qtrad_wc_email_done() {
	if ( empty( $GLOBALS['qtrad_wc_email'] ) || ! is_array( $GLOBALS['qtrad_wc_email'] ) ) {
		return;
	}
	$state = $GLOBALS['qtrad_wc_email'];
	unset( $GLOBALS['qtrad_wc_email'] );
	if ( $state['locale'] ) {
		restore_previous_locale();
	}
	qtrad_set_language( $state['language'] );
}

/** Runs before WooCommerce restores its own locale, so the switches unwind in order. */
function qtrad_wc_email_restore( $restore ) {
	qtrad_wc_email_done();
	return $restore;
}
