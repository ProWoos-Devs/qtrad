<?php
/**
 * Uninstall leaves qtranslate_* options and post content in place.
 * Those belong to the site, not to this plugin. Deleting them would
 * destroy the copy qTranslate and qTranslate-X still know how to read.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
