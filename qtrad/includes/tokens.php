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

function qtrad_has_lang_tags( $text ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return false;
	}
	return (bool) preg_match( '/(<!--:[a-z]{2,3}-->|\[:[a-z]{2,3}\]|\{:[a-z]{2,3}\})/i', $text );
}

function qtrad_detect_format( $text ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return null;
	}
	if ( preg_match( '/<!--:[a-z]{2,3}-->/i', $text ) ) {
		return 'comment';
	}
	if ( preg_match( '/\{:[a-z]{2,3}\}/i', $text ) ) {
		return 'swirly';
	}
	if ( preg_match( '/\[:[a-z]{2,3}\]/i', $text ) ) {
		return 'bracket';
	}
	return null;
}

function qtrad_language_blocks( $text, $comments_only = false ) {
	if ( ! is_string( $text ) || $text === '' ) {
		return array( $text );
	}
	$parts = preg_split(
		$comments_only ? '/(<!--:[a-z]{2,3}-->|<!--:-->)/i' : '/(<!--:[a-z]{2,3}-->|<!--:-->|\[:[a-z]{2,3}\]|\[:\]|\{:[a-z]{2,3}\}|\{:\})/i',
		$text,
		-1,
		PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
	);
	if ( ! is_array( $parts ) || ! $parts ) {
		return array( $text );
	}
	return $parts;
}

function qtrad_split_blocks( $blocks, $enabled, &$found = null, $trim = true, $comments_only = false ) {
	$open_pattern = $comments_only ? '/^<!--:([a-z]{2,3})-->$/i' : '/^(?:<!--:|\[:|\{:)([a-z]{2,3})(?:-->|\]|\})$/i';
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

function qtrad_split( $text, $enabled = null, $trim = true ) {
	if ( $enabled === null ) {
		$enabled = qtrad_enabled_languages();
	}
	$found = array();
	return qtrad_split_blocks( qtrad_language_blocks( $text ), $enabled, $found, $trim );
}

function qtrad_all_the_same( $texts, $force_markers ) {
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

function qtrad_ordered_keys( $texts, $order ) {
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

function qtrad_join( $texts, $format = 'bracket', $order = null, $force_markers = null ) {
	if ( $force_markers === null ) {
		$force_markers = (bool) qtrad_setting( 'force_markers', false );
	}
	$same = qtrad_all_the_same( $texts, $force_markers );
	if ( $same !== null ) {
		return $same;
	}
	$out = '';
	foreach ( qtrad_ordered_keys( $texts, $order ) as $lang ) {
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

function qtrad_join_content( $texts, $format, $order ) {
	$keys   = qtrad_ordered_keys( $texts, $order );
	$chunks = array();
	$max    = 0;
	foreach ( $keys as $lang ) {
		$chunks[ $lang ] = preg_split( '/<!--more-->/i', isset( $texts[ $lang ] ) ? $texts[ $lang ] : '' );
		$max             = max( $max, count( $chunks[ $lang ] ) );
	}
	if ( $max <= 1 ) {
		return qtrad_join( $texts, $format, $order );
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
		$out .= qtrad_join( $slice, $format, $keys );
	}
	return $out;
}

function qtrad_resolve_format( $existing, $preferred ) {
	if ( $preferred && $preferred !== 'keep' ) {
		return $preferred;
	}
	$detected = qtrad_detect_format( $existing );
	return $detected ? $detected : 'comment';
}

function qtrad_merge_field( $incoming, $existing, $lang, $kind = 'text' ) {
	$incoming = is_string( $incoming ) ? $incoming : '';
	$existing = is_string( $existing ) ? $existing : '';
	if ( qtrad_has_lang_tags( $incoming ) ) {
		return $incoming;
	}
	$enabled = qtrad_enabled_languages();
	if ( ! in_array( $lang, $enabled, true ) ) {
		$lang = qtrad_default_language();
	}
	$preferred = qtrad_setting( 'write_format', 'keep' );
	$format    = qtrad_resolve_format( $existing, $preferred );
	$order     = $enabled;
	$default   = qtrad_default_language();
	if ( ! qtrad_has_lang_tags( $existing ) ) {
		if ( $lang === $default || $incoming === $existing ) {
			return $incoming;
		}
		$texts = array();
		foreach ( $enabled as $code ) {
			$texts[ $code ] = '';
		}
		$texts[ $default ] = $existing;
		$texts[ $lang ]    = $incoming;
		return $kind === 'content' ? qtrad_join_content( $texts, $format, $order ) : qtrad_join( $texts, $format, $order );
	}
	$texts          = qtrad_split( $existing, $enabled, false );
	$texts[ $lang ] = $incoming;
	$keys           = array_keys( $texts );
	return $kind === 'content' ? qtrad_join_content( $texts, $format, $keys ) : qtrad_join( $texts, $format, $keys );
}

function qtrad_use_language( $text, $lang, $show_available = false, $show_empty = false ) {
	if ( is_array( $text ) ) {
		foreach ( $text as $key => $value ) {
			$text[ $key ] = qtrad_use_language( $value, $lang, $show_available, $show_empty );
		}
		return $text;
	}
	if ( is_object( $text ) ) {
		foreach ( get_object_vars( $text ) as $key => $value ) {
			$text->$key = qtrad_use_language( $value, $lang, $show_available, $show_empty );
		}
		return $text;
	}
	if ( ! is_string( $text ) || $text === '' || ! qtrad_has_lang_tags( $text ) ) {
		return $text;
	}
	$enabled = qtrad_enabled_languages();
	$found   = array();
	$texts   = qtrad_split_blocks( qtrad_language_blocks( $text ), $enabled, $found );
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
	$default = qtrad_default_language();
	$alt     = $available[0];
	$alt_text = isset( $texts[ $alt ] ) ? $texts[ $alt ] : '';
	$show_prefix = (bool) qtrad_setting( 'show_prefix', true );
	if ( $show_available && qtrad_setting( 'show_alt_message', false ) ) {
		$list = array();
		foreach ( $available as $code ) {
			$url    = qtrad_convert_url( '', $code, false, true );
			$name   = qtrad_language_name( $code );
			$list[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		}
		$message = sprintf(
			/* translators: %s: linked list of languages that have a translation */
			esc_html__( 'Sorry, this entry is only available in %s.', 'qtrad' ),
			implode( ', ', $list )
		);
		$prefix = $show_prefix ? '(' . esc_html( qtrad_language_name( $alt ) ) . ') ' : '';
		$templates = qtrad_config( 'not_available' );
		if ( is_array( $templates ) && ! empty( $templates[ $lang ] ) ) {
			$message = preg_replace_callback( '/%LANG:([^:]*):([^%]*)%/', function ( $match ) use ( $list ) {
				$last = array_pop( $list );
				return $list ? implode( $match[1], $list ) . $match[2] . $last : $last;
			}, wp_kses_post( $templates[ $lang ] ) );
		}
		$output = '<p class="qtrad-not-available">' . $message . '</p>';
		if ( qtrad_setting( 'show_alt_content', false ) ) {
			$locale = qtrad_config( 'locale' );
			$output .= '<div lang="' . esc_attr( str_replace( '_', '-', $locale[ $alt ] ) ) . '" dir="' . esc_attr( qtrad_language_direction( $alt ) ) . '">' . $prefix . $alt_text . '</div>';
		}
		return $output;
	}
	if ( $show_prefix ) {
		return '(' . esc_html( qtrad_language_name( $alt ) ) . ') ' . $alt_text;
	}
	return $alt_text;
}

function qtrad_use_current( $text, $show_available = false, $show_empty = false ) {
	return qtrad_use_language( $text, qtrad_current_language(), $show_available, $show_empty );
}

function qtrad_translate_term_name( $name ) {
	if ( ! is_string( $name ) || $name === '' ) {
		return $name;
	}
	if ( qtrad_has_lang_tags( $name ) ) {
		return qtrad_use_current( $name );
	}
	$library = qtrad_config( 'term_name' );
	$lang    = qtrad_current_language();
	if ( is_array( $library ) && isset( $library[ $name ][ $lang ] ) && $library[ $name ][ $lang ] !== '' ) {
		return $library[ $name ][ $lang ];
	}
	return $name;
}
