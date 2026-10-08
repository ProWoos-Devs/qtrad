<?php
/**
 * One-time report of qTranslate-XT data that qTrad keeps but does not display,
 * so an administrator learns about it before editors meet raw markers or 404s.
 * The scan only reads; nothing is converted or deleted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_migration_hooks() {
	if ( ! is_admin() ) {
		return;
	}
	add_action( 'admin_init', 'qtrad_migration_maybe_scan' );
	add_action( 'admin_init', 'qtrad_migration_dismiss' );
	add_action( 'admin_notices', 'qtrad_migration_notice' );
}

/** Runs once per site, on the first admin request after activation. */
function qtrad_migration_maybe_scan() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'qtrad_migration_report', false ) !== false ) {
		return;
	}
	update_option( 'qtrad_migration_report', array( 'version' => QTRAD_VERSION, 'items' => qtrad_migration_scan( true ), 'dismissed' => false ), false );
}

/**
 * What a site that used qTranslate-X or qTranslate-XT should know after the switch.
 *
 * @param bool $apply Turn on the translation of custom fields, user profiles and options
 *                    where such data is found and the admin has not chosen yet.
 * @return string[] One plain-text line per finding.
 */
function qtrad_migration_scan( $apply = false ) {
	global $wpdb;
	$items = array();
	$own   = get_option( 'qtrad_settings', array() );
	$own   = is_array( $own ) ? $own : array();
	$saved = $own;

	$modules = get_option( 'qtranslate_modules_state', array() );
	$names   = array(
		'all-in-one-seo-pack' => 'All in One SEO Pack',
		'events-made-easy'    => 'Events Made Easy',
		'google-site-kit'     => 'Google Site Kit',
		'gravity-forms'       => 'Gravity Forms',
		'jetpack'             => 'Jetpack',
		'slugs'               => 'Slugs translation',
		'wp-seo'              => 'Yoast SEO',
	);
	$active = array();
	foreach ( is_array( $modules ) ? $modules : array() as $id => $state ) {
		if ( 1 === (int) $state && ! in_array( $id, array( 'slugs', 'acf', 'woo-commerce' ), true ) ) { // QTX_MODULE_STATE_ACTIVE. qTrad covers these itself.
			$active[] = isset( $names[ $id ] ) ? $names[ $id ] : (string) $id;
		}
	}
	if ( $active ) {
		/* translators: %s: comma-separated list of qTranslate-XT integration modules */
		$items[] = sprintf( __( 'qTranslate-XT integration modules were active: %s. qTrad has no equivalent for these modules, so content they translated may show raw language markers.', 'qtrad' ), implode( ', ', $active ) );
	}

	// One-time read-only scan; no core API counts meta by key pattern.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$markers = array( '%' . $wpdb->esc_like( '[:' ) . '%' . $wpdb->esc_like( ']' ) . '%', '%' . $wpdb->esc_like( '<!--:' ) . '%', '%' . $wpdb->esc_like( '{:' ) . '%' . $wpdb->esc_like( '}' ) . '%' );
	$extra   = array_merge( qtrad_extra_field_keys(), array( '_qtrad_seo_title', '_qtrad_seo_description' ) );
	$keys    = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s ) LIMIT 50", $wpdb->esc_like( '_menu_item_' ) . '%', $markers[0], $markers[1], $markers[2] ) );
	// Keys qTrad reads as stored on purpose, such as WooCommerce attribute data, are not listed.
	$keys    = array_values( array_filter( array_diff( $keys, $extra ), function ( $key ) { return ! qtrad_meta_key_is_raw( $key ) && 0 !== strpos( $key, 'attribute_' ); } ) );
	$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN ('blogname', 'blogdescription') AND ( option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s ) LIMIT 50", $wpdb->esc_like( 'qtranslate_' ) . '%', $wpdb->esc_like( '_transient' ) . '%', $wpdb->esc_like( 'widget_' ) . '%', $markers[0], $markers[1], $markers[2] ) );
	$users = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s", $markers[0], $markers[1], $markers[2] ) );
	if ( $apply && ( $keys || $users ) && ! array_key_exists( 'translate_meta', $own ) ) {
		$own['translate_meta'] = true;
	}
	if ( $apply && $options && ! array_key_exists( 'translate_options', $own ) ) {
		$own['translate_options'] = 'all';
	}
	$meta_on    = ! empty( $own['translate_meta'] );
	$options_on = isset( $own['translate_options'] ) && in_array( $own['translate_options'], array( 'list', 'all' ), true );
	if ( $keys ) {
		$list = implode( ', ', array_slice( $keys, 0, 10 ) ) . ( count( $keys ) > 10 ? ', …' : '' );
		/* translators: %s: comma-separated list of custom field names */
		$items[] = sprintf( $meta_on ? __( 'Custom fields contain language markers: %s. The public site shows them in the visitor\'s language. To edit one per language in wp-admin, add its key to "Custom fields" in Settings → Languages.', 'qtrad' ) : __( 'Custom fields contain language markers: %s. They are shown as stored. In Settings → Languages, add their keys to "Custom fields", or tick the box below it to show every such field in the visitor\'s language.', 'qtrad' ), $list );
	}
	if ( $options ) {
		$list = implode( ', ', array_slice( $options, 0, 10 ) ) . ( count( $options ) > 10 ? ', …' : '' );
		/* translators: %s: comma-separated list of option names */
		$items[] = sprintf( $options_on ? __( 'Settings stored by the theme or other plugins contain language markers: %s. The public site shows them in the visitor\'s language, see "Options" in Settings → Languages.', 'qtrad' ) : __( 'Settings stored by the theme or other plugins contain language markers: %s. They are shown as stored. Choose what to translate under "Options" in Settings → Languages.', 'qtrad' ), $list );
	}
	if ( $users ) {
		/* translators: %d: number of users */
		$items[] = sprintf( $meta_on ? _n( '%d user profile (for example a biography) contains language markers. The public site shows it in the visitor\'s language.', '%d user profiles (for example biographies) contain language markers. The public site shows them in the visitor\'s language.', $users, 'qtrad' ) : _n( '%d user profile (for example a biography) contains language markers and is shown as stored. Tick the box below "Custom fields" in Settings → Languages to show it in the visitor\'s language.', '%d user profiles (for example biographies) contain language markers and are shown as stored. Tick the box below "Custom fields" in Settings → Languages to show them in the visitor\'s language.', $users, 'qtrad' ), $users );
	}
	// phpcs:enable

	if ( 2 === (int) get_option( 'qtranslate_editor_mode', 0 ) ) {
		$items[] = __( 'qTranslate-XT was set to single-language editing. qTrad shows language buttons in the editor instead; the setting is kept for qTranslate-XT.', 'qtrad' );
	}
	if ( $own !== $saved ) {
		update_option( 'qtrad_settings', $own );
		qtrad_reset_config();
	}
	return $items;
}

function qtrad_migration_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$report = get_option( 'qtrad_migration_report', false );
	if ( ! is_array( $report ) || ! empty( $report['dismissed'] ) || empty( $report['items'] ) ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'qtrad_dismiss_report', '1' ), 'qtrad_dismiss_report' );
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'qTrad checked the qTranslate data on this site', 'qtrad' ) . '</strong></p><ul class="ul-disc">';
	foreach ( $report['items'] as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	$previous = qtrad_migration_predecessor();
	/* translators: %s: plugin name, such as qTranslate-X */
	$footer = '' !== $previous ? sprintf( __( 'No content was changed or deleted, so %s can still be reactivated.', 'qtrad' ), $previous ) : __( 'No content was changed or deleted, so the previous qTranslate plugin can still be reactivated.', 'qtrad' );
	echo '</ul><p>' . esc_html( $footer ) . ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'qtrad' ) . '</a></p></div>';
}

/**
 * Name of the qTranslate plugin the site used, from the plugins still
 * installed: the newest of qTranslate-XT, qTranslate-X and qTranslate.
 */
function qtrad_migration_predecessor() {
	$folders = array(
		'qtranslate-xt' => 'qTranslate-XT',
		'qtranslate-x'  => 'qTranslate-X',
		'qtranslate'    => 'qTranslate',
	);
	foreach ( $folders as $folder => $name ) {
		if ( is_file( WP_PLUGIN_DIR . '/' . $folder . '/qtranslate.php' ) ) {
			return $name;
		}
	}
	return '';
}

function qtrad_migration_dismiss() {
	if ( ! isset( $_GET['qtrad_dismiss_report'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'qtrad_dismiss_report' );
	$report = get_option( 'qtrad_migration_report', array() );
	$report = is_array( $report ) ? $report : array();
	$report['dismissed'] = true;
	update_option( 'qtrad_migration_report', $report, false );
	wp_safe_redirect( remove_query_arg( array( 'qtrad_dismiss_report', '_wpnonce' ) ) );
	exit;
}
