<?php
/**
 * Language-tag codec. Shared fixtures cover PHP and assets/js/codec.js.
 *
 * Reads qTranslate 2.5 comments, qTranslate-X brackets and swirly brackets.
 * A language is "available" only when it owns a tagged block. Untagged
 * strings are returned unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtu_has_lang_tags( $text ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return false;
	}
	return (bool) preg_match( '/(<!--:[a-z]{2}-->|\[:[a-z]{2}\]|\{:[a-z]{2}\})/i', $text );
}

function qtu_detect_format( $text ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return null;
	}
	if ( preg_match( '/<!--:[a-z]{2}-->/i', $text ) ) {
		return 'comment';
	}
	if ( preg_match( '/\{:[a-z]{2}\}/i', $text ) ) {
		return 'swirly';
	}
	if ( preg_match( '/\[:[a-z]{2}\]/i', $text ) ) {
		return 'bracket';
	}
	return null;
}

function qtu_language_blocks( $text, $comments_only = false ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return array( $text );
	}
	$parts = preg_split(
		$comments_only ? '/(<!--:[a-z]{2}-->|<!--:-->)/i' : '/(<!--:[a-z]{2}-->|<!--:-->|\[:[a-z]{2}\]|\[:\]|\{:[a-z]{2}\}|\{:\})/i',
		$text,
		-1,
		PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
	);
	if ( ! is_array( $parts ) || ! $parts ) {
		return array( $text );
	}
	return $parts;
}

function qtu_split_blocks( $blocks, $enabled, &$found = null, $trim = true, $comments_only = false ) {
	$open_pattern = $comments_only ? '/^<!--:([a-z]{2})-->$/i' : '/^(?:<!--:|\[:|\{:)([a-z]{2})(?:-->|\]|\})$/i';
	$found  = array();
	$texts  = array();
	$enabled = array_values( $enabled );
	foreach ( $enabled as $language ) {
		$texts[ $language ] = '';
	}
	// Shared chunks belong to disabled translations too. Discover all keys first.
	foreach ( $blocks as $block ) {
		if ( preg_match( $open_pattern, $block, $match ) ) {
			$code = strtolower( $match[1] );
			if ( ! isset( $texts[ $code ] ) ) { $texts[ $code ] = ''; }
		}
	}
	$current = false;
	foreach ( $blocks as $block ) {
		if ( preg_match( $open_pattern, $block, $matches ) ) {
			$current = strtolower( $matches[1] );
			continue;
		}
		if ( ( ! $comments_only && ( $block === '[:]' || $block === '{:}' ) ) || strcasecmp( $block, '<!--:-->' ) === 0 ) {
			$current = false;
			continue;
		}
		if ( $current ) {
			if ( ! isset( $texts[ $current ] ) ) {
				$texts[ $current ] = '';
			}
			$texts[ $current ] .= $block;
			$found[ $current ] = ! empty( $found[ $current ] ) || trim( $block ) !== '';
			$current           = false;
		} else {
			foreach ( array_keys( $texts ) as $language ) {
				$texts[ $language ] .= $block;
			}
		}
	}
	foreach ( $texts as $lang => $value ) {
		$texts[ $lang ] = $trim ? trim( $value ) : $value;
	}
	return $texts;
}

function qtu_split( $text, $enabled = null, $trim = true ) {
	if ( $enabled === null ) {
		$enabled = qtu_enabled_languages();
	}
	$found = array();
	return qtu_split_blocks( qtu_language_blocks( $text ), $enabled, $found, $trim );
}

function qtu_all_the_same( $texts, $force_markers ) {
	if ( $force_markers ) {
		return null;
	}
	$text = null;
	foreach ( $texts as $value ) {
		if ( $value === '' || $value === null ) {
			continue;
		}
		$text = $value;
		break;
	}
	if ( $text === null ) {
		return '';
	}
	foreach ( $texts as $value ) {
		if ( $value !== $text ) {
			return null;
		}
	}
	return $text;
}

function qtu_ordered_keys( $texts, $order ) {
	if ( ! $order ) {
		return array_keys( $texts );
	}
	$keys = array();
	foreach ( $order as $lang ) {
		if ( array_key_exists( $lang, $texts ) ) {
			$keys[] = $lang;
		}
	}
	foreach ( array_keys( $texts ) as $lang ) {
		if ( ! in_array( $lang, $keys, true ) ) {
			$keys[] = $lang;
		}
	}
	return $keys;
}

function qtu_join( $texts, $format = 'bracket', $order = null, $force_markers = null ) {
	if ( $force_markers === null ) {
		$force_markers = (bool) qtu_setting( 'force_markers', false );
	}
	$same = qtu_all_the_same( $texts, $force_markers );
	if ( $same !== null ) {
		return $same;
	}
	$out = '';
	foreach ( qtu_ordered_keys( $texts, $order ) as $lang ) {
		$value = isset( $texts[ $lang ] ) ? $texts[ $lang ] : '';
		if ( $value === '' ) {
			continue;
		}
		if ( $format === 'comment' ) {
			$out .= '<!--:' . $lang . '-->' . $value . '<!--:-->';
		} elseif ( $format === 'swirly' ) {
			$out .= '{:' . $lang . '}' . $value;
		} else {
			$out .= '[:' . $lang . ']' . $value;
		}
	}
	if ( $out !== '' && $format === 'swirly' ) {
		$out .= '{:}';
	}
	if ( $out !== '' && $format === 'bracket' ) {
		$out .= '[:]';
	}
	return $out;
}

function qtu_join_content( $texts, $format, $order ) {
	$keys   = qtu_ordered_keys( $texts, $order );
	$chunks = array();
	$max    = 0;
	foreach ( $keys as $lang ) {
		$chunks[ $lang ] = preg_split( '/<!--more-->/i', isset( $texts[ $lang ] ) ? $texts[ $lang ] : '' );
		$max             = max( $max, count( $chunks[ $lang ] ) );
	}
	if ( $max <= 1 ) {
		return qtu_join( $texts, $format, $order );
	}
	$out = '';
	for ( $i = 0; $i < $max; $i++ ) {
		if ( $i >= 1 ) {
			$out .= '<!--more-->';
		}
		$slice = array();
		foreach ( $keys as $lang ) {
			$slice[ $lang ] = isset( $chunks[ $lang ][ $i ] ) ? $chunks[ $lang ][ $i ] : '';
		}
		$out .= qtu_join( $slice, $format, $keys );
	}
	return $out;
}

function qtu_resolve_format( $existing, $preferred ) {
	if ( $preferred && $preferred !== 'keep' ) {
		return $preferred;
	}
	$detected = qtu_detect_format( $existing );
	return $detected ? $detected : 'comment';
}

function qtu_merge_field( $incoming, $existing, $lang, $kind = 'text' ) {
	$incoming = is_string( $incoming ) ? $incoming : '';
	$existing = is_string( $existing ) ? $existing : '';
	if ( qtu_has_lang_tags( $incoming ) ) {
		return $incoming;
	}
	$enabled = qtu_enabled_languages();
	if ( ! in_array( $lang, $enabled, true ) ) {
		$lang = qtu_default_language();
	}
	$preferred = qtu_setting( 'write_format', 'keep' );
	$format    = qtu_resolve_format( $existing, $preferred );
	$order     = $enabled;
	$default   = qtu_default_language();
	if ( ! qtu_has_lang_tags( $existing ) ) {
		if ( $lang === $default || $incoming === $existing ) {
			return $incoming;
		}
		$texts = array();
		foreach ( $enabled as $code ) {
			$texts[ $code ] = '';
		}
		$texts[ $default ] = $existing;
		$texts[ $lang ]    = $incoming;
		return $kind === 'content' ? qtu_join_content( $texts, $format, $order ) : qtu_join( $texts, $format, $order );
	}
	$texts          = qtu_split( $existing, $enabled, false );
	$texts[ $lang ] = $incoming;
	$keys           = array_keys( $texts );
	return $kind === 'content' ? qtu_join_content( $texts, $format, $keys ) : qtu_join( $texts, $format, $keys );
}

function qtu_use_language( $text, $lang, $show_available = false, $show_empty = false ) {
	if ( is_array( $text ) ) {
		foreach ( $text as $key => $value ) {
			$text[ $key ] = qtu_use_language( $value, $lang, $show_available, $show_empty );
		}
		return $text;
	}
	if ( is_object( $text ) ) {
		foreach ( get_object_vars( $text ) as $key => $value ) {
			$text->$key = qtu_use_language( $value, $lang, $show_available, $show_empty );
		}
		return $text;
	}
	if ( ! is_string( $text ) || $text === '' || ! qtu_has_lang_tags( $text ) ) {
		return $text;
	}
	$enabled = qtu_enabled_languages();
	$found   = array();
	$texts   = qtu_split_blocks( qtu_language_blocks( $text ), $enabled, $found );
	if ( ! empty( $found[ $lang ] ) ) {
		return $texts[ $lang ];
	}
	if ( $show_empty ) {
		return '';
	}
	$available = array();
	foreach ( $enabled as $code ) {
		if ( ! empty( $found[ $code ] ) ) {
			$available[] = $code;
		}
	}
	if ( ! $available ) {
		return '';
	}
	$default = qtu_default_language();
	$alt     = $available[0];
	$alt_text = isset( $texts[ $alt ] ) ? $texts[ $alt ] : '';
	$show_prefix = (bool) qtu_setting( 'show_prefix', true );
	if ( $show_available && qtu_setting( 'show_alt_message', false ) ) {
		$list = array();
		foreach ( $available as $code ) {
			$url    = qtu_convert_url( '', $code, false, true );
			$name   = qtu_language_name( $code );
			$list[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		}
		$message = sprintf(
			/* translators: %s: linked list of languages that have a translation */
			__( 'Sorry, this entry is only available in %s.', 'qtrad' ),
			implode( ', ', $list )
		);
		$prefix = $show_prefix ? '(' . esc_html( qtu_language_name( $alt ) ) . ') ' : '';
		$templates = qtu_config( 'not_available' );
		if ( is_array( $templates ) && ! empty( $templates[ $lang ] ) ) {
			$message = preg_replace_callback( '/%LANG:([^:]*):([^%]*)%/', function ( $match ) use ( $list ) {
				$last = array_pop( $list );
				return $list ? implode( $match[1], $list ) . $match[2] . $last : $last;
			}, wp_kses_post( $templates[ $lang ] ) );
		}
		$output = '<p class="qtu-not-available">' . $message . '</p>';
		if ( qtu_setting( 'show_alt_content', false ) ) {
			$locale = qtu_config( 'locale' );
			$output .= '<div lang="' . esc_attr( str_replace( '_', '-', $locale[ $alt ] ) ) . '" dir="' . esc_attr( qtu_language_direction( $alt ) ) . '">' . $prefix . $alt_text . '</div>';
		}
		return $output;
	}
	if ( $show_prefix ) {
		return '(' . qtu_language_name( $alt ) . ') ' . $alt_text;
	}
	return $alt_text;
}

function qtu_use_current( $text, $show_available = false, $show_empty = false ) {
	return qtu_use_language( $text, qtu_current_language(), $show_available, $show_empty );
}

function qtu_translate_term_name( $name ) {
	if ( ! is_string( $name ) || $name === '' ) {
		return $name;
	}
	if ( qtu_has_lang_tags( $name ) ) {
		return qtu_use_current( $name );
	}
	$library = qtu_config( 'term_name' );
	$lang    = qtu_current_language();
	if ( is_array( $library ) && isset( $library[ $name ][ $lang ] ) && $library[ $name ][ $lang ] !== '' ) {
		return $library[ $name ][ $lang ];
	}
	return $name;
}
