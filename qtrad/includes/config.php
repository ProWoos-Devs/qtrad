<?php
/**
 * Shared language options from qTranslate and qTranslate-X are retained.
 * The repository docs/COMPATIBILITY.md describes the separate backward-write policies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_catalog() {
	return array(
		'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb.svg' ),
		'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de.svg' ),
		'fr' => array( 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr.svg' ),
		'es' => array( 'name' => 'Español', 'locale' => 'es_ES', 'flag' => 'es.svg' ),
		'it' => array( 'name' => 'Italiano', 'locale' => 'it_IT', 'flag' => 'it.svg' ),
		'pt' => array( 'name' => 'Português', 'locale' => 'pt_PT', 'flag' => 'pt.svg' ),
		'pb' => array( 'name' => 'Português do Brasil', 'locale' => 'pt_BR', 'flag' => 'br.svg' ),
		'nl' => array( 'name' => 'Nederlands', 'locale' => 'nl_NL', 'flag' => 'nl.svg' ),
		'sv' => array( 'name' => 'Svenska', 'locale' => 'sv_SE', 'flag' => 'se.svg' ),
		'pl' => array( 'name' => 'Polski', 'locale' => 'pl_PL', 'flag' => 'pl.svg' ),
		'ru' => array( 'name' => 'Русский', 'locale' => 'ru_RU', 'flag' => 'ru.svg' ),
		'zh' => array( 'name' => '中文', 'locale' => 'zh_CN', 'flag' => 'cn.svg' ),
		'ja' => array( 'name' => '日本語', 'locale' => 'ja', 'flag' => 'jp.svg' ),
		'el' => array( 'name' => 'Ελληνικά', 'locale' => 'el', 'flag' => 'gr.svg' ),
		'tr' => array( 'name' => 'Türkçe', 'locale' => 'tr_TR', 'flag' => 'tr.svg' ),
		'ro' => array( 'name' => 'Română', 'locale' => 'ro_RO', 'flag' => 'ro.svg' ),
		'hu' => array( 'name' => 'Magyar', 'locale' => 'hu_HU', 'flag' => 'hu.svg' ),
		'fi' => array( 'name' => 'suomi', 'locale' => 'fi', 'flag' => 'fi.svg' ),
		'hr' => array( 'name' => 'Hrvatski', 'locale' => 'hr', 'flag' => 'hr.svg' ),
		'sk' => array( 'name' => 'Slovenčina', 'locale' => 'sk_SK', 'flag' => 'sk.svg' ),
		'sr' => array( 'name' => 'Српски', 'locale' => 'sr_RS', 'flag' => 'rs.svg' ),
		'vi' => array( 'name' => 'Tiếng Việt', 'locale' => 'vi', 'flag' => 'vn.svg' ),
		'ar' => array( 'name' => 'العربية', 'locale' => 'ar', 'flag' => 'arab.svg', 'rtl' => true ),
		'et' => array( 'name' => 'Eesti', 'locale' => 'et', 'flag' => 'ee.svg' ),
		'cs' => array( 'name' => 'Čeština', 'locale' => 'cs_CZ', 'flag' => 'cz.svg' ),
		'da' => array( 'name' => 'Dansk', 'locale' => 'da_DK', 'flag' => 'dk.svg' ),
		'ca' => array( 'name' => 'Català', 'locale' => 'ca', 'flag' => '' ),
		'gl' => array( 'name' => 'Galego', 'locale' => 'gl_ES', 'flag' => 'es-ga.svg' ),
		'eu' => array( 'name' => 'Euskera', 'locale' => 'eu', 'flag' => '' ),
		'cy' => array( 'name' => 'Cymraeg', 'locale' => 'cy', 'flag' => '' ),
		'he' => array( 'name' => 'עברית', 'locale' => 'he_IL', 'flag' => 'il.svg', 'rtl' => true ),
		'ko' => array( 'name' => '한국어', 'locale' => 'ko_KR', 'flag' => 'kr.svg' ),
		'tw' => array( 'name' => '繁體中文', 'locale' => 'zh_TW', 'flag' => 'tw.svg' ),
		// Built into qTranslate-XT.
		'md' => array( 'name' => 'Moldovenească', 'locale' => 'ro_RO', 'flag' => 'md.svg' ),
		'lt' => array( 'name' => 'Lietuvių', 'locale' => 'lt_LT', 'flag' => 'lt.svg' ),
		'kk' => array( 'name' => 'Қазақ тілі', 'locale' => 'kk', 'flag' => 'kz.svg' ),
	);
}

function qtrad_bool_option( $name, $default ) {
	$value = get_option( $name, null );
	if ( $value === null ) {
		return $default;
	}
	if ( $value === '0' || $value === 0 || $value === false || $value === 'false' ) {
		return false;
	}
	if ( $value === '1' || $value === 1 || $value === true || $value === 'true' ) {
		return true;
	}
	return (bool) $value;
}

function qtrad_language_from_locale( $locale ) {
	$locale = (string) $locale;
	if ( stripos( $locale, 'pt_BR' ) === 0 ) {
		return 'pb';
	}
	if ( stripos( $locale, 'zh_TW' ) === 0 ) {
		return 'tw';
	}
	$code = strtolower( substr( $locale, 0, 2 ) );
	return isset( qtrad_catalog()[ $code ] ) ? $code : 'en';
}

function qtrad_load_config() {
	$catalog = qtrad_catalog();
	$names   = get_option( 'qtranslate_language_names', array() );
	$locales = get_option( 'qtranslate_locales', array() );
	$flags   = get_option( 'qtranslate_flags', array() );
	if ( ! is_array( $names ) ) {
		$names = array();
	}
	if ( ! is_array( $locales ) ) {
		$locales = array();
	}
	if ( ! is_array( $flags ) ) {
		$flags = array();
	}
	foreach ( $catalog as $code => $meta ) {
		if ( empty( $names[ $code ] ) ) {
			$names[ $code ] = $meta['name'];
		}
		if ( empty( $locales[ $code ] ) ) {
			$locales[ $code ] = $meta['locale'];
		}
		if ( ! isset( $flags[ $code ] ) ) {
			$flags[ $code ] = $meta['flag'];
		}
	}

	$enabled = get_option( 'qtranslate_enabled_languages', null );
	if ( ! is_array( $enabled ) || ! $enabled ) {
		$guess   = qtrad_language_from_locale( get_locale() );
		$enabled = array_values( array_unique( array( 'en', $guess ) ) );
	}
	$clean = array();
	foreach ( $enabled as $code ) {
		$code = is_string( $code ) ? strtolower( sanitize_key( $code ) ) : '';
		if ( preg_match( '/^[a-z]{2,3}$/D', $code ) && ! in_array( $code, $clean, true ) ) {
			$clean[] = $code;
			if ( empty( $names[ $code ] ) ) {
				$names[ $code ] = strtoupper( $code );
			}
			if ( empty( $locales[ $code ] ) ) { $locales[ $code ] = $code; }
		}
	}
	if ( ! $clean ) {
		$clean = array( 'en' );
	}

	$default = strtolower( (string) get_option( 'qtranslate_default_language', $clean[0] ) );
	if ( ! in_array( $default, $clean, true ) ) {
		$default = $clean[0];
	}

	$url_mode = (int) get_option( 'qtranslate_url_mode', 2 );
	if ( $url_mode < 1 || $url_mode > 4 ) {
		$url_mode = 2;
	}

	$own = get_option( 'qtrad_settings', array() );
	if ( ! is_array( $own ) ) {
		$own = array();
	}
	$formats = array( 'keep', 'bracket', 'comment', 'swirly' );
	$write   = isset( $own['write_format'] ) ? $own['write_format'] : 'keep';
	if ( ! in_array( $write, $formats, true ) ) {
		$write = 'keep';
	}
	$editor_mode = isset( $own['editor_mode'] ) ? $own['editor_mode'] : '';
	if ( $editor_mode === '' ) {
		$legacy = get_option( 'qtranslate_editor_mode', 0 );
		$editor_mode = ( (int) $legacy === 1 ) ? 'raw' : 'lsb';
	}
	if ( ! in_array( $editor_mode, array( 'lsb', 'raw' ), true ) ) {
		$editor_mode = 'lsb';
	}

	$extra = isset( $own['extra_fields'] ) ? $own['extra_fields'] : '';
	$domains = get_option( 'qtranslate_domains', array() );
	if ( ! is_array( $domains ) ) {
		$domains = array();
	}
	$terms = get_option( 'qtranslate_term_name', array() );
	if ( ! is_array( $terms ) ) {
		$terms = array();
	}

	return array(
		'qtrad_loaded'         => true,
		'enabled_languages'  => $clean,
		'default_language'   => $default,
		'language'           => $default,
		'language_name'      => $names,
		'locale'             => $locales,
		'flag'               => $flags,
		'url_mode'           => $url_mode,
		'hide_default_language' => qtrad_bool_option( 'qtranslate_hide_default_language', true ),
		'detect_browser_language' => qtrad_bool_option( 'qtranslate_detect_browser_language', true ),
		'hide_untranslated'  => qtrad_bool_option( 'qtranslate_hide_untranslated', false ),
		'term_name'          => $terms,
		'domains'            => $domains,
		'write_format'       => $write,
		'editor_mode'        => $editor_mode,
		'extra_fields'       => $extra,
		'force_markers'      => qtrad_bool_option( 'qtranslate_force_markers', false ),
		'not_available'      => (array) get_option( 'qtranslate_na_messages', array() ),
		'show_prefix'        => isset( $own['show_prefix'] ) ? (bool) $own['show_prefix'] : qtrad_bool_option( 'qtranslate_show_displayed_language_prefix', true ),
		'show_alt_message'   => isset( $own['show_alt_message'] ) ? (bool) $own['show_alt_message'] : qtrad_bool_option( 'qtranslate_show_alternative_content_message', true ),
		'show_alt_content'   => qtrad_bool_option( 'qtranslate_show_alternative_content', false ),
		'url_info'           => array(),
	);
}

function qtrad_config( $key = null ) {
	global $q_config; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- qTranslate's public configuration global.
	if ( ! is_array( $q_config ) || empty( $q_config['qtrad_loaded'] ) ) {
		$q_config = qtrad_load_config();
	}
	if ( $key === null ) {
		return $q_config;
	}
	return isset( $q_config[ $key ] ) ? $q_config[ $key ] : null;
}

function qtrad_setting( $key, $default = null ) {
	$value = qtrad_config( $key );
	return $value === null ? $default : $value;
}

function qtrad_enabled_languages() {
	$enabled = qtrad_config( 'enabled_languages' );
	return is_array( $enabled ) ? $enabled : array( 'en' );
}

function qtrad_default_language() {
	$default = qtrad_config( 'default_language' );
	return $default ? $default : 'en';
}

function qtrad_is_enabled( $lang ) {
	return is_string( $lang ) && in_array( strtolower( $lang ), qtrad_enabled_languages(), true );
}

function qtrad_language_name( $lang ) {
	$names = qtrad_config( 'language_name' );
	return ( is_array( $names ) && ! empty( $names[ $lang ] ) ) ? $names[ $lang ] : strtoupper( (string) $lang );
}

function qtrad_set_language( $lang ) {
	global $q_config; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- qTranslate's public configuration global.
	qtrad_config();
	if ( ! qtrad_is_enabled( $lang ) ) {
		$lang = qtrad_default_language();
	}
	$lang = strtolower( $lang );
	$q_config['language'] = $lang;
	return $lang;
}

function qtrad_current_language() {
	global $q_config; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- qTranslate's public configuration global.
	qtrad_config();
	if ( ! empty( $q_config['language'] ) && qtrad_is_enabled( $q_config['language'] ) ) {
		return $q_config['language'];
	}
	return qtrad_default_language();
}

function qtrad_admin_language() {
	if ( is_user_logged_in() ) {
		$meta = get_user_meta( get_current_user_id(), 'qtrad_edit_language', true );
		if ( qtrad_is_enabled( $meta ) ) {
			return $meta;
		}
	}
	if ( isset( $_COOKIE['qtrans_admin_language'] ) ) {
		$cookie = strtolower( sanitize_key( wp_unslash( $_COOKIE['qtrans_admin_language'] ) ) );
		if ( qtrad_is_enabled( $cookie ) ) {
			return $cookie;
		}
	}
	return qtrad_default_language();
}

function qtrad_reset_config() {
	global $q_config; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- qTranslate's public configuration global.
	$q_config = null;
}

/** Language/options belong to a site; restore the caller's context after switching. */
function qtrad_switch_blog( $new_blog_id, $previous_blog_id, $context = 'switch' ) {
	global $q_config; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- qTranslate's public configuration global.
	if ( function_exists( 'qtrad_login_admin_paths' ) ) {
		qtrad_login_admin_paths( true );
	}
	if ( $context === 'restore' && ! empty( $GLOBALS['qtrad_blog_configs'] ) ) {
		$previous = array_pop( $GLOBALS['qtrad_blog_configs'] );
		if ( $previous['blog_id'] === (int) $new_blog_id ) { $q_config = $previous['config']; return; }
	} elseif ( $context === 'switch' ) {
		$GLOBALS['qtrad_blog_configs'][] = array( 'blog_id' => (int) $previous_blog_id, 'config' => $q_config );
	}
	qtrad_reset_config();
}

function qtrad_save_settings( $input ) {
	$new = isset( $input['new_language'] ) && is_array( $input['new_language'] ) ? $input['new_language'] : array();
	if ( ! empty( $new['code'] ) || ! empty( $new['name'] ) || ! empty( $new['locale'] ) ) {
		if ( ! isset( $new['code'], $new['name'], $new['locale'] ) || ! is_string( $new['code'] ) || ! is_string( $new['name'] ) || ! is_string( $new['locale'] ) || ! preg_match( '/^[a-z]{2,3}$/iD', trim( $new['code'] ) ) || trim( $new['name'] ) === '' || ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $new['locale'] ) || isset( qtrad_available_catalog()[ strtolower( trim( $new['code'] ) ) ] ) ) {
			return new WP_Error( 'qtrad_language', __( 'Enter a new two- or three-letter language code, a native name and a valid locale. Existing codes cannot be added again.', 'qtrad' ) );
		}
	}
	if ( isset( $input['languages'] ) && is_array( $input['languages'] ) ) {
		foreach ( $input['languages'] as $meta ) {
			if ( ! is_array( $meta ) || empty( $meta['name'] ) || ! is_string( $meta['name'] ) || empty( $meta['locale'] ) || ! is_string( $meta['locale'] ) || ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $meta['locale'] ) ) {
				return new WP_Error( 'qtrad_locale', __( 'Each language needs a native name and valid WordPress locale, for example en_US.', 'qtrad' ) );
			}
		}
	}
	$catalog = qtrad_available_catalog();
	// Shared qtranslate_* arrays only receive values the admin changed, so
	// defaults that differ between qTrad and qTranslate-XT are never written.
	$effective = array( 'name' => qtrad_config( 'language_name' ), 'locale' => qtrad_config( 'locale' ), 'flag' => qtrad_config( 'flag' ) );
	$names = get_option( 'qtranslate_language_names', array() );
	$locales = get_option( 'qtranslate_locales', array() );
	$flags = get_option( 'qtranslate_flags', array() );
	$names = is_array( $names ) ? $names : array();
	$locales = is_array( $locales ) ? $locales : array();
	$flags = is_array( $flags ) ? $flags : array();
	if ( ! empty( $input['languages'] ) && is_array( $input['languages'] ) ) {
		foreach ( $input['languages'] as $code => $meta ) {
			if ( ! isset( $catalog[ $code ] ) || ! is_array( $meta ) ) {
				continue;
			}
			if ( isset( $meta['name'] ) && is_string( $meta['name'] ) && trim( $meta['name'] ) !== '' && sanitize_text_field( $meta['name'] ) !== $effective['name'][ $code ] ) {
				$names[ $code ] = sanitize_text_field( $meta['name'] );
			}
			if ( isset( $meta['locale'] ) && is_string( $meta['locale'] ) && preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $meta['locale'] ) && $meta['locale'] !== $effective['locale'][ $code ] ) {
				$locales[ $code ] = $meta['locale'];
			}
			if ( isset( $meta['flag'] ) && is_string( $meta['flag'] ) && ( $meta['flag'] === '' || preg_match( '/^[a-z0-9_.-]+\.(?:png|gif|jpe?g|svg)$/iD', $meta['flag'] ) ) && basename( $meta['flag'] ) !== $effective['flag'][ $code ] ) {
				$flags[ $code ] = basename( $meta['flag'] );
			}
		}
	}
	$new = isset( $input['new_language'] ) && is_array( $input['new_language'] ) ? $input['new_language'] : array();
	$code = isset( $new['code'] ) && is_string( $new['code'] ) ? strtolower( trim( $new['code'] ) ) : '';
	if ( preg_match( '/^[a-z]{2,3}$/D', $code ) && ! isset( $catalog[ $code ] ) && ! empty( $new['name'] ) && is_string( $new['name'] ) && ! empty( $new['locale'] ) && is_string( $new['locale'] ) && preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $new['locale'] ) ) {
		$names[ $code ] = sanitize_text_field( $new['name'] );
		$locales[ $code ] = $new['locale'];
		$flags[ $code ] = '';
		$catalog[ $code ] = array( 'name' => $names[ $code ], 'locale' => $locales[ $code ], 'flag' => '' );
	}
	$new_code = $code;
	$enabled = array();
	if ( ! empty( $input['enabled'] ) && is_array( $input['enabled'] ) ) {
		foreach ( $input['enabled'] as $code ) {
			$code = strtolower( sanitize_key( $code ) );
			if ( isset( $catalog[ $code ] ) && ! in_array( $code, $enabled, true ) ) {
				$enabled[] = $code;
			}
		}
	}
	if ( $new_code && isset( $catalog[ $new_code ] ) && ! in_array( $new_code, $enabled, true ) ) {
		$enabled[] = $new_code;
	}
	if ( ! $enabled ) {
		$enabled = array( 'en' );
	}
	$default = isset( $input['default'] ) ? strtolower( sanitize_key( $input['default'] ) ) : $enabled[0];
	if ( ! in_array( $default, $enabled, true ) ) {
		$default = $enabled[0];
	}
	$url_mode = isset( $input['url_mode'] ) ? (int) $input['url_mode'] : 2;
	if ( $url_mode < 1 || $url_mode > 4 ) {
		$url_mode = 2;
	}
	$formats = array( 'keep', 'bracket', 'comment', 'swirly' );
	$write   = isset( $input['write_format'] ) ? $input['write_format'] : 'keep';
	if ( ! in_array( $write, $formats, true ) ) {
		$write = 'keep';
	}
	$editor_mode = ( isset( $input['editor_mode'] ) && $input['editor_mode'] === 'raw' ) ? 'raw' : 'lsb';
	$extra       = isset( $input['extra_fields'] ) ? sanitize_textarea_field( $input['extra_fields'] ) : '';
	$domains = isset( $input['domains'] ) ? array() : qtrad_config( 'domains' );
	if ( ! empty( $input['domains'] ) && is_string( $input['domains'] ) ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $input['domains'] ) as $line ) {
			if ( trim( $line ) === '' ) { continue; }
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( count( $parts ) !== 2 || ! isset( $catalog[ strtolower( $parts[0] ) ] ) || ! qtrad_domain_parts( $parts[1] ) ) {
				return new WP_Error( 'qtrad_domain', __( 'Enter each domain as code = host, optionally with a port. Do not include a scheme or path.', 'qtrad' ) );
			}
			if ( in_array( strtolower( $parts[1] ), $domains, true ) ) {
				return new WP_Error( 'qtrad_domain_duplicate', __( 'Give each language a different domain.', 'qtrad' ) );
			}
			$domains[ strtolower( $parts[0] ) ] = strtolower( $parts[1] );
		}
	}

	update_option( 'qtranslate_enabled_languages', $enabled );
	update_option( 'qtranslate_language_names', $names );
	update_option( 'qtranslate_locales', $locales );
	update_option( 'qtranslate_flags', $flags );
	update_option( 'qtranslate_default_language', $default );
	update_option( 'qtranslate_url_mode', $url_mode );
	update_option( 'qtranslate_hide_default_language', empty( $input['hide_default'] ) ? '0' : '1' );
	update_option( 'qtranslate_detect_browser_language', empty( $input['detect_browser'] ) ? '0' : '1' );
	update_option( 'qtranslate_hide_untranslated', empty( $input['hide_untranslated'] ) ? '0' : '1' );
	update_option( 'qtranslate_show_displayed_language_prefix', empty( $input['show_prefix'] ) ? '0' : '1' );
	update_option( 'qtranslate_domains', $domains );
	// qTranslate-XT also has mode 2 (single language); keep any stored mode the admin did not change.
	$stored_mode = (int) get_option( 'qtranslate_editor_mode', 0 );
	if ( $editor_mode !== ( $stored_mode === 1 ? 'raw' : 'lsb' ) ) {
		update_option( 'qtranslate_editor_mode', $editor_mode === 'raw' ? 1 : 0 );
	}
	if ( isset( $input['qtrad_settings'] ) ) {
		update_option( 'qtranslate_force_markers', ! empty( $input['force_markers'] ) );
		update_option( 'qtranslate_show_alternative_content', ! empty( $input['show_alt_content'] ) );
		update_option( 'qtranslate_show_alternative_content_message', ! empty( $input['show_alt_message'] ) );
	}
	update_option(
		'qtrad_settings',
		array(
			'write_format'     => $write,
			'editor_mode'      => $editor_mode,
			'extra_fields'     => $extra,
			'show_prefix'      => ! empty( $input['show_prefix'] ),
			'show_alt_message' => ! empty( $input['show_alt_message'] ),
		)
	);
	qtrad_reset_config();
	return true;
}

function qtrad_conflicting_plugin() {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active  = array_merge( $active, array_keys( $network ) );
	}
	$candidates = array(
		'qtranslate/qtranslate.php',
		'qtranslate-x/qtranslate.php',
		'qtranslate-xt/qtranslate.php',
	);
	foreach ( $candidates as $plugin ) {
		if ( in_array( $plugin, $active, true ) ) {
			return $plugin;
		}
	}
	foreach ( $active as $plugin ) {
		if ( basename( $plugin ) === 'qtranslate.php' ) {
			return $plugin;
		}
	}
	$renamed = qtrad_renamed_predecessor( $active );
	if ( $renamed !== '' ) {
		return $renamed;
	}
	if ( defined( 'QTX_VERSION' ) || defined( 'QTRANSLATE_VERSION' ) || function_exists( 'qtranxf_init' ) || function_exists( 'qtrans_init' ) ) {
		return 'qTranslate';
	}
	return '';
}

/**
 * A predecessor installed under another folder name, found by its plugin header.
 * Headers are read only when the active plugin list changes.
 */
function qtrad_renamed_predecessor( $active ) {
	$key = md5( implode( '|', $active ) );
	$cached = get_transient( 'qtrad_conflict_scan' );
	if ( is_array( $cached ) && isset( $cached['key'], $cached['plugin'] ) && $cached['key'] === $key ) {
		return $cached['plugin'];
	}
	if ( ! function_exists( 'get_file_data' ) ) {
		return '';
	}
	$found = '';
	foreach ( $active as $plugin ) {
		$file = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! is_string( $plugin ) || ! is_file( $file ) ) {
			continue;
		}
		$header = get_file_data( $file, array( 'name' => 'Plugin Name' ) );
		if ( preg_match( '/^qTranslate(?:-X|-XT)?$/iD', trim( $header['name'] ) ) ) {
			$found = $plugin;
			break;
		}
	}
	set_transient( 'qtrad_conflict_scan', array( 'key' => $key, 'plugin' => $found ), DAY_IN_SECONDS );
	return $found;
}

/** Imported languages remain editable even when absent from the built-in list. */
function qtrad_available_catalog() {
	$catalog = qtrad_catalog();
	$names = qtrad_config( 'language_name' );
	$locales = qtrad_config( 'locale' );
	$flags = qtrad_config( 'flag' );
	foreach ( array_unique( array_merge( array_keys( $names ), qtrad_enabled_languages() ) ) as $code ) {
		$catalog[ $code ] = array(
			'name' => isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ),
			'locale' => isset( $locales[ $code ] ) ? $locales[ $code ] : $code,
			'flag' => isset( $flags[ $code ] ) ? $flags[ $code ] : '',
		);
	}
	return $catalog;
}

function qtrad_language_direction( $language ) {
	$locales = qtrad_config( 'locale' );
	$primary = isset( $locales[ $language ] ) ? strtolower( strtok( $locales[ $language ], '_-' ) ) : $language;
	return in_array( $primary, array( 'ar', 'he', 'fa', 'ur', 'ps', 'ug', 'yi' ), true ) ? 'rtl' : 'ltr';
}
