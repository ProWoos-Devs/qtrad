<?php
/**
 * Shared language options from qTranslate and qTranslate-X are retained.
 * See COMPATIBILITY.md for the separate backward-write policies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtu_catalog() {
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
		'ua' => array( 'name' => 'Українська', 'locale' => 'uk', 'flag' => 'ua.svg' ),
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
	);
}

function qtu_bool_option( $name, $default ) {
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

function qtu_language_from_locale( $locale ) {
	$locale = (string) $locale;
	if ( stripos( $locale, 'pt_BR' ) === 0 ) {
		return 'pb';
	}
	if ( stripos( $locale, 'zh_TW' ) === 0 ) {
		return 'tw';
	}
	if ( stripos( $locale, 'uk' ) === 0 ) {
		return 'ua';
	}
	$code = strtolower( substr( $locale, 0, 2 ) );
	return isset( qtu_catalog()[ $code ] ) ? $code : 'en';
}

function qtu_load_config() {
	$catalog = qtu_catalog();
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
		$guess   = qtu_language_from_locale( get_locale() );
		$enabled = array_values( array_unique( array( 'en', $guess ) ) );
	}
	$clean = array();
	foreach ( $enabled as $code ) {
		$code = is_string( $code ) ? strtolower( sanitize_key( $code ) ) : '';
		if ( preg_match( '/^[a-z]{2}$/D', $code ) && ! in_array( $code, $clean, true ) ) {
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

	$unified = get_option( 'qtranslate_unified_settings', array() );
	if ( ! is_array( $unified ) ) {
		$unified = array();
	}
	$next = get_option( 'qtranslate_next_settings', array() );
	if ( is_array( $next ) ) {
		$unified = array_replace( $unified, $next );
	}
	$formats = array( 'keep', 'bracket', 'comment', 'swirly' );
	$write   = isset( $unified['write_format'] ) ? $unified['write_format'] : 'keep';
	if ( ! in_array( $write, $formats, true ) ) {
		$write = 'keep';
	}
	$editor_mode = isset( $unified['editor_mode'] ) ? $unified['editor_mode'] : '';
	if ( $editor_mode === '' ) {
		$legacy = get_option( 'qtranslate_editor_mode', 0 );
		$editor_mode = ( (int) $legacy === 1 ) ? 'raw' : 'lsb';
	}
	if ( ! in_array( $editor_mode, array( 'lsb', 'raw' ), true ) ) {
		$editor_mode = 'lsb';
	}

	$extra = isset( $unified['extra_fields'] ) ? $unified['extra_fields'] : '';
	$domains = get_option( 'qtranslate_domains', array() );
	if ( ! is_array( $domains ) ) {
		$domains = array();
	}
	$terms = get_option( 'qtranslate_term_name', array() );
	if ( ! is_array( $terms ) ) {
		$terms = array();
	}

	return array(
		'qtu_loaded'         => true,
		'enabled_languages'  => $clean,
		'default_language'   => $default,
		'language'           => $default,
		'language_name'      => $names,
		'locale'             => $locales,
		'flag'               => $flags,
		'url_mode'           => $url_mode,
		'hide_default_language' => qtu_bool_option( 'qtranslate_hide_default_language', true ),
		'detect_browser_language' => qtu_bool_option( 'qtranslate_detect_browser_language', true ),
		'hide_untranslated'  => qtu_bool_option( 'qtranslate_hide_untranslated', false ),
		'term_name'          => $terms,
		'domains'            => $domains,
		'write_format'       => $write,
		'editor_mode'        => $editor_mode,
		'extra_fields'       => $extra,
		'force_markers'      => qtu_bool_option( 'qtranslate_force_markers', false ),
		'not_available'      => (array) get_option( 'qtranslate_na_messages', array() ),
		'show_prefix'        => isset( $unified['show_prefix'] ) ? (bool) $unified['show_prefix'] : qtu_bool_option( 'qtranslate_show_displayed_language_prefix', true ),
		'show_alt_message'   => isset( $unified['show_alt_message'] ) ? (bool) $unified['show_alt_message'] : qtu_bool_option( 'qtranslate_show_alternative_content_message', true ),
		'show_alt_content'   => qtu_bool_option( 'qtranslate_show_alternative_content', false ),
		'url_info'           => array(),
	);
}

function qtu_config( $key = null ) {
	global $q_config;
	if ( ! is_array( $q_config ) || empty( $q_config['qtu_loaded'] ) ) {
		$q_config = qtu_load_config();
	}
	if ( $key === null ) {
		return $q_config;
	}
	return isset( $q_config[ $key ] ) ? $q_config[ $key ] : null;
}

function qtu_setting( $key, $default = null ) {
	$value = qtu_config( $key );
	return $value === null ? $default : $value;
}

function qtu_enabled_languages() {
	$enabled = qtu_config( 'enabled_languages' );
	return is_array( $enabled ) ? $enabled : array( 'en' );
}

function qtu_default_language() {
	$default = qtu_config( 'default_language' );
	return $default ? $default : 'en';
}

function qtu_is_enabled( $lang ) {
	return is_string( $lang ) && in_array( strtolower( $lang ), qtu_enabled_languages(), true );
}

function qtu_language_name( $lang ) {
	$names = qtu_config( 'language_name' );
	return ( is_array( $names ) && ! empty( $names[ $lang ] ) ) ? $names[ $lang ] : strtoupper( (string) $lang );
}

function qtu_set_language( $lang ) {
	global $q_config;
	qtu_config();
	if ( ! qtu_is_enabled( $lang ) ) {
		$lang = qtu_default_language();
	}
	$lang = strtolower( $lang );
	$q_config['language'] = $lang;
	return $lang;
}

function qtu_current_language() {
	global $q_config;
	qtu_config();
	if ( ! empty( $q_config['language'] ) && qtu_is_enabled( $q_config['language'] ) ) {
		return $q_config['language'];
	}
	return qtu_default_language();
}

function qtu_admin_language() {
	if ( is_user_logged_in() ) {
		$meta = get_user_meta( get_current_user_id(), 'qtu_edit_language', true );
		if ( qtu_is_enabled( $meta ) ) {
			return $meta;
		}
	}
	if ( isset( $_COOKIE['qtrans_admin_language'] ) ) {
		$cookie = strtolower( sanitize_key( wp_unslash( $_COOKIE['qtrans_admin_language'] ) ) );
		if ( qtu_is_enabled( $cookie ) ) {
			return $cookie;
		}
	}
	return qtu_default_language();
}

function qtu_reset_config() {
	global $q_config;
	$q_config = null;
}

/** Language/options belong to a site; restore the caller's context after switching. */
function qtu_switch_blog( $new_blog_id, $previous_blog_id, $context = 'switch' ) {
	global $q_config;
	if ( $context === 'restore' && ! empty( $GLOBALS['qtu_blog_configs'] ) ) {
		$previous = array_pop( $GLOBALS['qtu_blog_configs'] );
		if ( $previous['blog_id'] === (int) $new_blog_id ) { $q_config = $previous['config']; return; }
	} elseif ( $context === 'switch' ) {
		$GLOBALS['qtu_blog_configs'][] = array( 'blog_id' => (int) $previous_blog_id, 'config' => $q_config );
	}
	qtu_reset_config();
}

function qtu_save_settings( $input ) {
	$new = isset( $input['new_language'] ) && is_array( $input['new_language'] ) ? $input['new_language'] : array();
	if ( ! empty( $new['code'] ) || ! empty( $new['name'] ) || ! empty( $new['locale'] ) ) {
		if ( ! isset( $new['code'], $new['name'], $new['locale'] ) || ! is_string( $new['code'] ) || ! is_string( $new['name'] ) || ! is_string( $new['locale'] ) || ! preg_match( '/^[a-z]{2}$/iD', trim( $new['code'] ) ) || trim( $new['name'] ) === '' || ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $new['locale'] ) || isset( qtu_available_catalog()[ strtolower( trim( $new['code'] ) ) ] ) ) {
			return new WP_Error( 'qtn_language', __( 'Enter a new two-letter language code, a native name and a valid locale. Existing codes cannot be added again.', 'qtrad' ) );
		}
	}
	if ( isset( $input['languages'] ) && is_array( $input['languages'] ) ) {
		foreach ( $input['languages'] as $meta ) {
			if ( ! is_array( $meta ) || empty( $meta['name'] ) || ! is_string( $meta['name'] ) || empty( $meta['locale'] ) || ! is_string( $meta['locale'] ) || ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $meta['locale'] ) ) {
				return new WP_Error( 'qtn_locale', __( 'Each language needs a native name and valid WordPress locale, for example en_US.', 'qtrad' ) );
			}
		}
	}
	$catalog = qtu_available_catalog();
	$names = qtu_config( 'language_name' );
	$locales = qtu_config( 'locale' );
	$flags = qtu_config( 'flag' );
	if ( ! empty( $input['languages'] ) && is_array( $input['languages'] ) ) {
		foreach ( $input['languages'] as $code => $meta ) {
			if ( ! isset( $catalog[ $code ] ) || ! is_array( $meta ) ) {
				continue;
			}
			if ( isset( $meta['name'] ) && is_string( $meta['name'] ) && trim( $meta['name'] ) !== '' ) {
				$names[ $code ] = sanitize_text_field( $meta['name'] );
			}
			if ( isset( $meta['locale'] ) && is_string( $meta['locale'] ) && preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $meta['locale'] ) ) {
				$locales[ $code ] = $meta['locale'];
			}
			if ( isset( $meta['flag'] ) && is_string( $meta['flag'] ) && ( $meta['flag'] === '' || preg_match( '/^[a-z0-9_.-]+\.(?:png|gif|jpe?g|svg)$/iD', $meta['flag'] ) ) ) {
				$flags[ $code ] = basename( $meta['flag'] );
			}
		}
	}
	$new = isset( $input['new_language'] ) && is_array( $input['new_language'] ) ? $input['new_language'] : array();
	$code = isset( $new['code'] ) && is_string( $new['code'] ) ? strtolower( trim( $new['code'] ) ) : '';
	if ( preg_match( '/^[a-z]{2}$/D', $code ) && ! isset( $catalog[ $code ] ) && ! empty( $new['name'] ) && is_string( $new['name'] ) && ! empty( $new['locale'] ) && is_string( $new['locale'] ) && preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*$/D', $new['locale'] ) ) {
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
	$domains = isset( $input['domains'] ) ? array() : qtu_config( 'domains' );
	if ( ! empty( $input['domains'] ) && is_string( $input['domains'] ) ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $input['domains'] ) as $line ) {
			if ( trim( $line ) === '' ) { continue; }
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( count( $parts ) !== 2 || ! isset( $catalog[ strtolower( $parts[0] ) ] ) || ! qtu_domain_parts( $parts[1] ) ) {
				return new WP_Error( 'qtn_domain', __( 'Enter each domain as code = host, optionally with a port. Do not include a scheme or path.', 'qtrad' ) );
			}
			if ( in_array( strtolower( $parts[1] ), $domains, true ) ) {
				return new WP_Error( 'qtn_domain_duplicate', __( 'Give each language a different domain.', 'qtrad' ) );
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
	update_option( 'qtranslate_editor_mode', $editor_mode === 'raw' ? 1 : 0 );
	if ( isset( $input['qtu_settings'] ) ) {
		update_option( 'qtranslate_force_markers', ! empty( $input['force_markers'] ) );
		update_option( 'qtranslate_show_alternative_content', ! empty( $input['show_alt_content'] ) );
		update_option( 'qtranslate_show_alternative_content_message', ! empty( $input['show_alt_message'] ) );
	}
	update_option(
		'qtranslate_next_settings',
		array(
			'write_format'     => $write,
			'editor_mode'      => $editor_mode,
			'extra_fields'     => $extra,
			'show_prefix'      => ! empty( $input['show_prefix'] ),
			'show_alt_message' => ! empty( $input['show_alt_message'] ),
		)
	);
	qtu_reset_config();
	return true;
}

function qtu_conflicting_plugin() {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active  = array_merge( $active, array_keys( $network ) );
	}
	$candidates = array(
		'qtranslate/qtranslate.php',
		'qtranslate-x/qtranslate.php',
		'qtranslate-xt/qtranslate.php',
		'qtranslate-next/qtranslate-next.php',
		'qtranslate-unified/qtranslate-unified.php',
	);
	foreach ( $candidates as $plugin ) {
		if ( in_array( $plugin, $active, true ) ) {
			return $plugin;
		}
	}
	foreach ( $active as $plugin ) {
		$file = WP_PLUGIN_DIR . '/' . $plugin;
		if ( basename( $plugin ) !== 'qtranslate.php' && is_file( $file ) ) {
			$header = file_get_contents( $file, false, null, 0, 8192 );
			if ( is_string( $header ) && preg_match( '/^\s*\*?\s*Plugin Name:\s*qTranslate(?:-X|-XT| Next| Unified)?\s*$/mi', $header ) ) {
				return $plugin;
			}
		} elseif ( basename( $plugin ) === 'qtranslate.php' ) {
			return $plugin;
		}
	}
	if ( defined( 'QTX_VERSION' ) || defined( 'QTRANSLATE_VERSION' ) || function_exists( 'qtranxf_init' ) || function_exists( 'qtrans_init' ) ) {
		return 'qTranslate';
	}
	return '';
}

/** Imported languages remain editable even when absent from the built-in list. */
function qtu_available_catalog() {
	$catalog = qtu_catalog();
	$names = qtu_config( 'language_name' );
	$locales = qtu_config( 'locale' );
	$flags = qtu_config( 'flag' );
	foreach ( array_unique( array_merge( array_keys( $names ), qtu_enabled_languages() ) ) as $code ) {
		$catalog[ $code ] = array(
			'name' => isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ),
			'locale' => isset( $locales[ $code ] ) ? $locales[ $code ] : $code,
			'flag' => isset( $flags[ $code ] ) ? $flags[ $code ] : '',
		);
	}
	return $catalog;
}

function qtu_language_direction( $language ) {
	$locales = qtu_config( 'locale' );
	$primary = isset( $locales[ $language ] ) ? strtolower( strtok( $locales[ $language ], '_-' ) ) : $language;
	return in_array( $primary, array( 'ar', 'he', 'fa', 'ur', 'ps', 'ug', 'yi' ), true ) ? 'rtl' : 'ltr';
}
