<?php
/**
 * Plugin Name: qTrad
 * Description: Multilingual content for WordPress. An independent continuation of qTranslate and qTranslate-X that keeps their translations and adds accessible editing and multilingual SEO.
 * Version: 1.9.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Rafael Minuesa
 * Author URI: https://github.com/rafael-minuesa
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qtrad
 *
 * qTranslate stores a language as <!--:en-->text<!--:-->.
 * qTranslate-X stores it as [:en]text[:] or {:en}text{:}.
 * This plugin accepts all three and can keep whichever a field already uses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'QTRAD_VERSION' ) ) {
	return;
}

define( 'QTRAD_VERSION', '1.9.0' );
define( 'QTRAD_FILE', __FILE__ );
define( 'QTRAD_DIR', plugin_dir_path( __FILE__ ) );

define( 'QTRAD_URL_QUERY', 1 );
define( 'QTRAD_URL_PATH', 2 );
define( 'QTRAD_URL_DOMAIN', 3 );
define( 'QTRAD_URL_DOMAINS', 4 );


require_once QTRAD_DIR . 'includes/config.php';
// Refuse before exporting names owned by either predecessor.
register_activation_hook( QTRAD_FILE, 'qtrad_activate' );
if ( qtrad_conflicting_plugin() ) {
	add_action( 'admin_notices', 'qtrad_conflict_notice' );
	add_action( 'network_admin_notices', 'qtrad_conflict_notice' );
	return;
}
// qTranslate-X URL mode constants, for integrations that compare against them.
foreach ( array( 'QTX_URL_QUERY' => 1, 'QTX_URL_PATH' => 2, 'QTX_URL_DOMAIN' => 3, 'QTX_URL_DOMAINS' => 4 ) as $qtrad_constant => $qtrad_value ) {
	if ( ! defined( $qtrad_constant ) ) { define( $qtrad_constant, $qtrad_value ); } // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- qTranslate-X public constants.
}
unset( $qtrad_constant, $qtrad_value );
require_once QTRAD_DIR . 'includes/tokens.php';
require_once QTRAD_DIR . 'includes/runtime.php';
require_once QTRAD_DIR . 'includes/compat.php';
require_once QTRAD_DIR . 'includes/fields.php';
require_once QTRAD_DIR . 'includes/values.php';
require_once QTRAD_DIR . 'includes/acf.php';
require_once QTRAD_DIR . 'includes/woocommerce.php';
require_once QTRAD_DIR . 'includes/dates.php';
require_once QTRAD_DIR . 'includes/seo.php';
require_once QTRAD_DIR . 'includes/admin.php';
require_once QTRAD_DIR . 'includes/widget.php';
require_once QTRAD_DIR . 'includes/menus.php';
require_once QTRAD_DIR . 'includes/slugs.php';
require_once QTRAD_DIR . 'includes/slugs-admin.php';
require_once QTRAD_DIR . 'includes/migration.php';

/**
 * Language has to be known before WordPress parses the request.
 * Admin user meta is applied later, on init, once the user is loaded.
 */
function qtrad_boot() {
	if ( qtrad_conflicting_plugin() ) {
		add_action( 'admin_notices', 'qtrad_conflict_notice' );
		return;
	}
	if ( ! defined( 'QTRANS_INIT' ) ) {
		define( 'QTRANS_INIT', true );
	}
	qtrad_normalize_request();
	qtrad_register_front_filters();
	qtrad_register_field_hooks();
	qtrad_register_value_hooks();
	qtrad_register_acf_hooks();
	qtrad_register_woocommerce_hooks();
	qtrad_register_date_hooks();
	qtrad_register_translator_filters();
	qtrad_register_seo_hooks();
	qtrad_register_admin_hooks();
	qtrad_register_migration_hooks();
	qtrad_register_widget();
	qtrad_register_menu_hooks();
	qtrad_register_slug_hooks();
	qtrad_register_slug_admin_hooks();
	add_action( 'init', 'qtrad_refresh_admin_language', 0 );
	add_action( 'switch_blog', 'qtrad_switch_blog', 0, 3 );
	add_action( 'init', 'qtrad_register_shortcodes' );
	add_action( 'wp_enqueue_scripts', 'qtrad_enqueue_front' );
}

add_action( 'plugins_loaded', 'qtrad_boot', 2 );

function qtrad_refresh_admin_language() {
	if ( is_admin() ) {
		qtrad_set_language( qtrad_admin_language() );
	}
}

function qtrad_enqueue_front() {
	wp_enqueue_script( 'qtrad-switcher', plugins_url( 'assets/js/switcher.js', QTRAD_FILE ), array(), QTRAD_VERSION, true );
	wp_enqueue_style( 'qtrad-front', plugins_url( 'assets/css/front.css', QTRAD_FILE ), array(), QTRAD_VERSION );
}

function qtrad_conflict_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$other = qtrad_conflicting_plugin();
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: %s: plugin basename */
			__( 'qTrad did not start because %s is still active. Deactivate the other qTranslate implementation before using this plugin. Your posts and qtranslate_* settings are left untouched.', 'qtrad' ),
			$other ? $other : __( 'another qTranslate build', 'qtrad' )
		)
	);
	echo '</p></div>';
}

function qtrad_activate() {
	if ( qtrad_conflicting_plugin() ) {
		wp_die( esc_html__( 'Deactivate the other qTranslate implementation first.', 'qtrad' ), esc_html__( 'qTrad', 'qtrad' ), array( 'back_link' => true ) );
	}
	// Translated slugs may have been stored while another qTranslate plugin was active.
	delete_option( 'qtrad_slugs_present' );
}
