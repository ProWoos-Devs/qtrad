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
	update_option( 'qtrad_migration_report', array( 'version' => QTRAD_VERSION, 'items' => qtrad_migration_scan(), 'dismissed' => false ), false );
}

/** @return string[] One plain-text line per kind of data qTrad will not display. */
function qtrad_migration_scan() {
	global $wpdb;
	$items = array();

	$modules = get_option( 'qtranslate_modules_state', array() );
	$names   = array(
		'acf'                 => 'ACF',
		'all-in-one-seo-pack' => 'All in One SEO Pack',
		'events-made-easy'    => 'Events Made Easy',
		'google-site-kit'     => 'Google Site Kit',
		'gravity-forms'       => 'Gravity Forms',
		'jetpack'             => 'Jetpack',
		'slugs'               => 'Slugs translation',
		'woo-commerce'        => 'WooCommerce',
		'wp-seo'              => 'Yoast SEO',
	);
	$active = array();
	foreach ( is_array( $modules ) ? $modules : array() as $id => $state ) {
		if ( 1 === (int) $state && 'slugs' !== $id ) { // QTX_MODULE_STATE_ACTIVE. qTrad reads the translated slugs itself.
			$active[] = isset( $names[ $id ] ) ? $names[ $id ] : (string) $id;
		}
	}
	if ( $active ) {
		/* translators: %s: comma-separated list of qTranslate-XT integration modules */
		$items[] = sprintf( __( 'qTranslate-XT integration modules were active: %s. qTrad has no equivalent for these modules, so content they translated may show raw language markers.', 'qtrad' ), implode( ', ', $active ) );
	}

	// One-time read-only scan; no core API counts meta by key pattern.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$acf = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_content LIKE %s", '%' . $wpdb->esc_like( '"qtranslate_' ) . '%' ) );
	if ( $acf ) {
		/* translators: %d: number of ACF fields */
		$items[] = sprintf( _n( '%d ACF field uses a qTranslate field type. ACF cannot render it without qTranslate-XT.', '%d ACF fields use qTranslate field types. ACF cannot render them without qTranslate-XT.', $acf, 'qtrad' ), $acf );
	}

	$markers = array( '%' . $wpdb->esc_like( '[:' ) . '%' . $wpdb->esc_like( ']' ) . '%', '%' . $wpdb->esc_like( '<!--:' ) . '%', '%' . $wpdb->esc_like( '{:' ) . '%' . $wpdb->esc_like( '}' ) . '%' );
	$extra   = array_merge( qtrad_extra_field_keys(), array( '_qtrad_seo_title', '_qtrad_seo_description' ) );
	$keys    = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s ) LIMIT 50", $wpdb->esc_like( '_menu_item_' ) . '%', $markers[0], $markers[1], $markers[2] ) );
	$keys    = array_values( array_diff( $keys, $extra ) );
	if ( $keys ) {
		/* translators: %s: comma-separated list of custom field names */
		$items[] = sprintf( __( 'Custom fields contain language markers but are not configured in qTrad: %s. Add the ones that hold text to "Custom fields" in Settings → Languages.', 'qtrad' ), implode( ', ', array_slice( $keys, 0, 10 ) ) . ( count( $keys ) > 10 ? ', …' : '' ) );
	}

	$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN ('blogname', 'blogdescription') AND ( option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s ) LIMIT 50", $wpdb->esc_like( 'qtranslate_' ) . '%', $wpdb->esc_like( '_transient' ) . '%', $wpdb->esc_like( 'widget_' ) . '%', $markers[0], $markers[1], $markers[2] ) );
	if ( $options ) {
		/* translators: %s: comma-separated list of option names */
		$items[] = sprintf( __( 'Settings stored by the theme or other plugins contain language markers, which qTranslate-XT translated automatically: %s. qTrad translates only the site title and tagline, so these may show raw markers.', 'qtrad' ), implode( ', ', array_slice( $options, 0, 10 ) ) . ( count( $options ) > 10 ? ', …' : '' ) );
	}

	$users = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s", $markers[0], $markers[1], $markers[2] ) );
	if ( $users ) {
		/* translators: %d: number of users */
		$items[] = sprintf( _n( '%d user profile (for example a biography) contains language markers. qTrad does not translate user profiles.', '%d user profiles (for example biographies) contain language markers. qTrad does not translate user profiles.', $users, 'qtrad' ), $users );
	}
	// phpcs:enable

	foreach ( array( 'qtranslate_date_formats', 'qtranslate_time_formats' ) as $option ) {
		if ( array_filter( (array) get_option( $option, array() ) ) ) {
			$items[] = __( 'Per-language date and time formats are set. qTrad uses the WordPress date and time formats with each language\'s locale.', 'qtrad' );
			break;
		}
	}
	if ( 2 === (int) get_option( 'qtranslate_editor_mode', 0 ) ) {
		$items[] = __( 'qTranslate-XT was set to single-language editing. qTrad shows language buttons in the editor instead; the setting is kept for qTranslate-XT.', 'qtrad' );
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
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'qTrad found qTranslate data it keeps but does not display', 'qtrad' ) . '</strong></p><ul class="ul-disc">';
	foreach ( $report['items'] as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul><p>' . esc_html__( 'Nothing was changed or deleted. Reactivating qTranslate-XT shows this data again.', 'qtrad' ) . ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'qtrad' ) . '</a></p></div>';
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
