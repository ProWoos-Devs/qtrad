<?php
/**
 * Uninstall leaves qtranslate_* options, post content, term translations,
 * SEO translations and qTrad settings in place. Those belong to the site:
 * qTranslate and qTranslate-X still know how to read the shared data, and
 * reinstalling qTrad picks up where it left off.
 *
 * Only data qTrad derives or caches for itself is removed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, 'qtrad_edit_language', '', true );

$qtrad_sites = is_multisite() ? get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) : array( get_current_blog_id() );
foreach ( $qtrad_sites as $qtrad_site ) {
	if ( is_multisite() ) {
		switch_to_blog( $qtrad_site );
	}
	delete_metadata( 'post', 0, '_qtrad_available_languages', '', true );
	delete_transient( 'qtrad_conflict_scan' );
	delete_option( 'qtrad_migration_report' );
	delete_option( 'qtrad_slugs_present' );
	delete_option( 'qtrad_marked_options' );
	if ( is_multisite() ) {
		restore_current_blog();
	}
}
