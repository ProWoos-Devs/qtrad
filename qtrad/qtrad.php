<?php
/**
 * Plugin Name: qTrad — Multilingual Content
 * Description: An independent continuation of qTranslate and qTranslate-X with accessible multilingual editing and SEO.
 * Version: 1.2.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: qTrad contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qtrad
 *
 * qTranslate stores a language as <!--:en-->text<!--:-->.
 * qTranslate-X stores it as [:en]text[:] or {:en}text{:}.
 * This plugin accepts all three and can keep whichever a field already uses.
 */

if ( ! defined( 'ABSPATH' ) || defined( 'QTU_VERSION' ) ) {
	return;
}

define( 'QTU_VERSION', '1.2.1' );
define( 'QTU_FILE', __FILE__ );
define( 'QTU_DIR', plugin_dir_path( __FILE__ ) );

define( 'QTU_URL_QUERY', 1 );
define( 'QTU_URL_PATH', 2 );
define( 'QTU_URL_DOMAIN', 3 );
define( 'QTU_URL_DOMAINS', 4 );


require_once QTU_DIR . 'includes/config.php';
// Refuse before exporting names owned by either predecessor.
register_activation_hook( QTU_FILE, 'qtu_activate' );
if ( qtu_conflicting_plugin() ) {
	add_action( 'admin_notices', 'qtu_conflict_notice' );
	add_action( 'network_admin_notices', 'qtu_conflict_notice' );
	return;
}
foreach ( array( 'QTX_URL_QUERY' => 1, 'QTX_URL_PATH' => 2, 'QTX_URL_DOMAIN' => 3, 'QTX_URL_DOMAINS' => 4 ) as $constant => $value ) {
	if ( ! defined( $constant ) ) { define( $constant, $value ); }
}
require_once QTU_DIR . 'includes/tokens.php';
require_once QTU_DIR . 'includes/runtime.php';
require_once QTU_DIR . 'includes/compat.php';
require_once QTU_DIR . 'includes/fields.php';
require_once QTU_DIR . 'includes/seo.php';
require_once QTU_DIR . 'includes/admin.php';
require_once QTU_DIR . 'includes/widget.php';

/**
 * Language has to be known before WordPress parses the request.
 * Admin user meta is applied later, on init, once the user is loaded.
 */
function qtu_boot() {
	if ( qtu_conflicting_plugin() ) {
		add_action( 'admin_notices', 'qtu_conflict_notice' );
		return;
	}
	if ( ! defined( 'QTRANS_INIT' ) ) {
		define( 'QTRANS_INIT', true );
	}
	qtu_normalize_request();
	qtu_register_front_filters();
	qtu_register_field_hooks();
	qtu_register_seo_hooks();
	qtu_register_admin_hooks();
	qtu_register_widget();
	add_action( 'init', 'qtu_refresh_admin_language', 0 );
	add_action( 'switch_blog', 'qtu_switch_blog', 0, 3 );
	add_action( 'init', 'qtu_register_shortcodes' );
	add_action( 'init', 'qtu_load_textdomain' );
	add_action( 'wp_enqueue_scripts', 'qtu_enqueue_front' );
}

add_action( 'plugins_loaded', 'qtu_boot', 2 );

function qtu_refresh_admin_language() {
	if ( is_admin() ) {
		qtu_set_language( qtu_admin_language() );
	}
}

function qtu_enqueue_front() {
	wp_enqueue_script( 'qtu-switcher', plugins_url( 'assets/js/switcher.js', QTU_FILE ), array(), QTU_VERSION, true );
	wp_enqueue_style( 'qtu-front', plugins_url( 'assets/css/front.css', QTU_FILE ), array(), QTU_VERSION );
}

function qtu_conflict_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$other = qtu_conflicting_plugin();
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: %s: plugin basename */
			__( 'qTrad did not start because %s is still active. Deactivate the other qTranslate implementation, including former Next or Unified builds, before using this plugin. Your posts and qtranslate_* settings are left untouched.', 'qtrad' ),
			$other ? $other : __( 'another qTranslate build', 'qtrad' )
		)
	);
	echo '</p></div>';
}

function qtu_activate() {
	if ( qtu_conflicting_plugin() ) {
		wp_die( esc_html__( 'Deactivate the other qTranslate implementation, including former Next or Unified builds, first.', 'qtrad' ), esc_html__( 'qTrad', 'qtrad' ), array( 'back_link' => true ) );
	}
}

function qtu_load_textdomain() {
	load_plugin_textdomain( 'qtrad', false, dirname( plugin_basename( QTU_FILE ) ) . '/languages' );
}
