<?php
/**
 * Date and time formats per language, read from the qTranslate-X options
 * qtranslate_date_formats and qtranslate_time_formats. Where a page shows a
 * date in the site's format, it uses the format of the current language.
 * Formats may be PHP date formats or the strftime style qTranslate-X stored.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_date_hooks() {
	// qTranslate-XT's "use WordPress formats" choice turns this off.
	if ( '0' === (string) get_option( 'qtranslate_use_strftime', '' ) ) {
		return;
	}
	add_filter( 'option_date_format', 'qtrad_filter_date_format', 5 );
	add_filter( 'option_time_format', 'qtrad_filter_time_format', 5 );
}

/** Pages for visitors and emails show language formats; wp-admin keeps the stored site formats. */
function qtrad_shows_language_formats() {
	return qtrad_translates_values() || ! empty( $GLOBALS['qtrad_wc_email'] );
}

/** Format of a language, or '' when none is set. */
function qtrad_language_format( $kind, $lang ) {
	$formats = get_option( 'time' === $kind ? 'qtranslate_time_formats' : 'qtranslate_date_formats', array() );
	$format  = is_array( $formats ) && isset( $formats[ $lang ] ) && is_string( $formats[ $lang ] ) ? trim( $formats[ $lang ] ) : '';
	return '' === $format ? '' : qtrad_php_date_format( $format );
}

function qtrad_filter_date_format( $format ) {
	return qtrad_filter_format( $format, 'date' );
}

function qtrad_filter_time_format( $format ) {
	return qtrad_filter_format( $format, 'time' );
}

function qtrad_filter_format( $format, $kind ) {
	if ( ! qtrad_shows_language_formats() ) {
		return $format;
	}
	$language = qtrad_language_format( $kind, qtrad_current_language() );
	return '' !== $language ? $language : $format;
}

/**
 * A PHP date format from a strftime-style format, including the extra codes
 * qTranslate-X added (%E day without zero, %q English ordinal suffix and others).
 * Formats without % are PHP date formats already.
 */
function qtrad_php_date_format( $format ) {
	if ( false === strpos( $format, '%' ) ) {
		return $format;
	}
	$map = array(
		'a' => 'D', 'A' => 'l', 'd' => 'd', 'e' => 'j', 'E' => 'j', 'u' => 'N', 'w' => 'w', 'j' => 'z', 'q' => 'S', 'f' => 'w',
		'V' => 'W', 'b' => 'M', 'h' => 'M', 'B' => 'F', 'm' => 'm', 'i' => 'n', 'J' => 't', 'y' => 'y', 'Y' => 'Y', 'G' => 'o',
		'H' => 'H', 'k' => 'G', 'L' => 'G', 'I' => 'h', 'l' => 'g', 'M' => 'i', 'S' => 's', 'p' => 'A', 'P' => 'a', 'N' => 'u',
		'K' => 'B', 'Z' => 'T', 'v' => 'T', 'z' => 'O', 'O' => 'O', 'Q' => 'e', 'o' => 'I', 's' => 'U', '1' => 'Z', '2' => 'c',
		'3' => 'r', '4' => 'U', 'F' => 'Y-m-d', 'D' => 'm/d/y', 'R' => 'H:i', 'T' => 'H:i:s', 'n' => "\n", 't' => "\t", '%' => '%',
	);
	$out    = '';
	$length = strlen( $format );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $format[ $i ];
		if ( '\\' === $char && $i + 1 < $length ) {
			// Already a literal in both syntaxes.
			$out .= '\\' . $format[ ++$i ];
		} elseif ( '%' === $char && $i + 1 < $length ) {
			$code = $format[ ++$i ];
			$out .= isset( $map[ $code ] ) ? ( '%' === $code ? '\\%' : $map[ $code ] ) : '';
		} else {
			// Any other letter is text in strftime but a code in PHP date formats.
			$out .= ctype_alpha( $char ) ? '\\' . $char : $char;
		}
	}
	return $out;
}

/** Save the per-language formats from the settings form; unchanged languages keep their stored value. */
function qtrad_save_language_formats( $input ) {
	if ( empty( $input['languages'] ) || ! is_array( $input['languages'] ) ) {
		return;
	}
	foreach ( array( 'date' => 'qtranslate_date_formats', 'time' => 'qtranslate_time_formats' ) as $kind => $option ) {
		$before  = get_option( $option, array() );
		$formats = is_array( $before ) ? $before : array();
		foreach ( $input['languages'] as $code => $meta ) {
			if ( ! is_string( $code ) || ! qtrad_is_enabled( $code ) || ! is_array( $meta ) || ! isset( $meta[ $kind . '_format' ] ) || ! is_string( $meta[ $kind . '_format' ] ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( $meta[ $kind . '_format' ] ) );
			if ( '' === $value ) {
				unset( $formats[ $code ] );
			} elseif ( ! isset( $formats[ $code ] ) || $formats[ $code ] !== $value ) {
				$formats[ $code ] = $value;
			}
		}
		if ( $formats !== $before ) {
			update_option( $option, $formats );
		}
	}
}
