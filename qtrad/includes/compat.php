<?php
/**
 * Public functions used by themes and plugins built for qTranslate 2.5
 * (qtrans_*) and qTranslate-X (qtranxf_*). Both names call this plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_join_with( $texts, $format ) {
	$texts = is_string( $texts ) ? qtrad_split( $texts, null, false ) : ( is_array( $texts ) ? $texts : array() );
	return qtrad_join( $texts, $format, qtrad_enabled_languages() );
}

function qtrad_active_write_format() {
	$preferred = qtrad_setting( 'write_format', 'bracket' );
	return ( $preferred && $preferred !== 'keep' ) ? $preferred : 'bracket';
}

if ( ! function_exists( 'qtranxf_getLanguage' ) ) {
	function qtranxf_getLanguage() {
		return qtrad_current_language();
	}
}

if ( ! function_exists( 'qtranxf_getLanguageDefault' ) ) {
	function qtranxf_getLanguageDefault() {
		return qtrad_default_language();
	}
}

if ( ! function_exists( 'qtranxf_getLanguageName' ) ) {
	function qtranxf_getLanguageName( $lang = '' ) {
		if ( $lang === '' || $lang === null ) {
			$lang = qtrad_current_language();
		}
		return qtrad_language_name( $lang );
	}
}

if ( ! function_exists( 'qtranxf_getLanguageNameNative' ) ) {
	function qtranxf_getLanguageNameNative( $lang = '' ) {
		return qtranxf_getLanguageName( $lang );
	}
}

if ( ! function_exists( 'qtranxf_getSortedLanguages' ) ) {
	function qtranxf_getSortedLanguages( $reverse = false ) {
		$langs = qtrad_enabled_languages();
		if ( $reverse ) {
			$langs = array_reverse( $langs );
		}
		return $langs;
	}
}

if ( ! function_exists( 'qtranxf_convertURL' ) ) {
	function qtranxf_convertURL( $url = '', $lang = '', $forceadmin = false, $showDefaultLanguage = false ) {
		$show = null;
		if ( func_num_args() >= 4 ) {
			$show = (bool) $showDefaultLanguage;
		}
		return qtrad_convert_url( (string) $url, (string) $lang, (bool) $forceadmin, $show );
	}
}

if ( ! function_exists( 'qtranxf_use' ) ) {
	function qtranxf_use( $lang, $text, $show_available = false, $show_empty = false ) {
		return qtrad_is_enabled( $lang ) ? qtrad_use_language( $text, strtolower( $lang ), (bool) $show_available, (bool) $show_empty ) : $text;
	}
}

if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
	function qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $content ) {
		return qtrad_use_current( $content, false, false );
	}
}

if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundShowAvailable' ) ) {
	function qtranxf_useCurrentLanguageIfNotFoundShowAvailable( $content ) {
		return qtrad_use_current( $content, true, false );
	}
}

if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundShowEmpty' ) ) {
	function qtranxf_useCurrentLanguageIfNotFoundShowEmpty( $content ) {
		return qtrad_use_current( $content, false, true );
	}
}

if ( ! function_exists( 'qtranxf_useDefaultLanguage' ) ) {
	function qtranxf_useDefaultLanguage( $content ) {
		return qtrad_use_language( $content, qtrad_default_language(), false, true );
	}
}

if ( ! function_exists( 'qtranxf_getAvailableLanguages' ) ) {
	function qtranxf_getAvailableLanguages( $text ) {
		if ( ! is_string( $text ) ) {
			return false;
		}
		if ( ! qtrad_has_lang_tags( $text ) ) {
			return false;
		}
		$enabled = qtrad_enabled_languages();
		$found   = array();
		qtrad_split_blocks( qtrad_language_blocks( $text ), $enabled, $found );
		$out = array();
		foreach ( $enabled as $code ) {
			if ( ! empty( $found[ $code ] ) ) {
				$out[] = $code;
			}
		}
		foreach ( array_keys( $found ) as $code ) {
			if ( ! empty( $found[ $code ] ) && ! in_array( $code, $out, true ) ) {
				$out[] = $code;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'qtranxf_split' ) ) {
	function qtranxf_split( $text ) {
		return qtrad_split( is_string( $text ) ? $text : '' );
	}
}

if ( ! function_exists( 'qtranxf_join_b' ) ) {
	function qtranxf_join_b( $texts ) {
		return qtrad_join_with( $texts, 'bracket' );
	}
}

if ( ! function_exists( 'qtranxf_join_c' ) ) {
	function qtranxf_join_c( $texts ) {
		return qtrad_join_with( $texts, 'comment' );
	}
}

if ( ! function_exists( 'qtranxf_join_s' ) ) {
	function qtranxf_join_s( $texts ) {
		return qtrad_join_with( $texts, 'swirly' );
	}
}

if ( ! function_exists( 'qtranxf_join' ) ) {
	function qtranxf_join( $texts ) {
		return qtrad_join_with( $texts, qtrad_active_write_format() );
	}
}

if ( ! function_exists( 'qtranxf_useTermLib' ) ) {
	function qtranxf_useTermLib( $obj ) {
		return qtrad_use_term_library( $obj );
	}
}

if ( ! function_exists( 'qtranxf_generateLanguageSelectCode' ) ) {
	function qtranxf_generateLanguageSelectCode( $style = '', $id = '' ) {
		echo qtrad_language_chooser( $style, $id );
	}
}

if ( ! function_exists( 'qtrans_convertURL' ) ) {
	function qtrans_convertURL( $url = '', $lang = '', $forceadmin = false, $showDefaultLanguage = false ) {
		return qtranxf_convertURL( $url, $lang, $forceadmin, $showDefaultLanguage );
	}
}

if ( ! function_exists( 'qtrans_generateLanguageSelectCode' ) ) {
	function qtrans_generateLanguageSelectCode( $style = '', $id = '' ) {
		return qtranxf_generateLanguageSelectCode( $style, $id );
	}
}

if ( ! function_exists( 'qtrans_getAvailableLanguages' ) ) {
	function qtrans_getAvailableLanguages( $text ) {
		$langs = qtranxf_getAvailableLanguages( $text );
		return $langs === false ? qtrad_enabled_languages() : $langs;
	}
}

if ( ! function_exists( 'qtrans_getLanguage' ) ) {
	function qtrans_getLanguage() {
		return qtranxf_getLanguage();
	}
}

if ( ! function_exists( 'qtrans_getLanguageName' ) ) {
	function qtrans_getLanguageName( $lang = '' ) {
		return qtranxf_getLanguageNameNative( $lang );
	}
}

if ( ! function_exists( 'qtrans_getSortedLanguages' ) ) {
	function qtrans_getSortedLanguages( $reverse = false ) {
		return qtranxf_getSortedLanguages( $reverse );
	}
}

if ( ! function_exists( 'qtrans_join' ) ) {
	/**
	 * qTranslate-X mapped this historical name onto bracket tags.
	 * Comment output is qtranxf_join_c().
	 */
	function qtrans_join( $texts ) {
		return is_string( $texts ) ? qtrad_join_with( $texts, qtrad_detect_format( $texts ) ?: 'comment' ) : qtranxf_join_b( $texts );
	}
}

if ( ! function_exists( 'qtrans_split' ) ) {
	function qtrans_split( $text, $quicktags = true ) {
		if ( $quicktags ) { return qtranxf_split( $text ); }
		$found = array();
		return qtrad_split_blocks( qtrad_language_blocks( is_string( $text ) ? $text : '', true ), qtrad_enabled_languages(), $found, true, true );
	}
}

if ( ! function_exists( 'qtrans_use' ) ) {
	function qtrans_use( $lang, $text, $show_available = false ) {
		return qtranxf_use( $lang, $text, $show_available );
	}
}

if ( ! function_exists( 'qtrans_useCurrentLanguageIfNotFoundShowAvailable' ) ) {
	function qtrans_useCurrentLanguageIfNotFoundShowAvailable( $content ) {
		return qtranxf_useCurrentLanguageIfNotFoundShowAvailable( $content );
	}
}

if ( ! function_exists( 'qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
	function qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage( $content ) {
		return qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $content );
	}
}

if ( ! function_exists( 'qtrans_useDefaultLanguage' ) ) {
	function qtrans_useDefaultLanguage( $content ) {
		return qtranxf_useDefaultLanguage( $content );
	}
}

if ( ! function_exists( 'qtrans_useTermLib' ) ) {
	function qtrans_useTermLib( $obj ) {
		return qtranxf_useTermLib( $obj );
	}
}

function qtrad_use_term_library( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'qtrad_use_term_library', $value );
	}
	if ( is_object( $value ) ) {
				$value = clone $value;
		foreach ( array( 'name', 'cat_name' ) as $key ) {
			if ( isset( $value->$key ) ) { $value->$key = qtrad_translate_term_name( $value->$key ); }
		}
		return $value;
	}
	return qtrad_translate_term_name( $value );
}

if ( ! function_exists( 'qtranxf_isEnabled' ) ) {
function qtranxf_isEnabled( $lang ) { return qtrad_is_enabled( $lang ); }
}
if ( ! function_exists( 'qtrans_isEnabled' ) ) {
function qtrans_isEnabled( $lang ) { return qtrad_is_enabled( $lang ); }
}
if ( ! function_exists( 'qtranxf_isMultilingual' ) ) {
function qtranxf_isMultilingual( $text ) { return is_string( $text ) && qtrad_has_lang_tags( $text ); }
}
if ( ! function_exists( 'qtranxf_isAvailableIn' ) ) {
function qtranxf_isAvailableIn( $post_id, $lang = '' ) {
	$post = get_post( $post_id );
	if ( ! $post ) { return false; }
	$lang = $lang === '' ? qtrad_default_language() : $lang;
	if ( $post->post_content === '' ) { return false; }
	$available = qtranxf_getAvailableLanguages( $post->post_content );
	return $available === false ? $lang === qtrad_default_language() : in_array( $lang, $available, true );
}
}
if ( ! function_exists( 'qtrans_isAvailableIn' ) ) {
function qtrans_isAvailableIn( $post_id, $lang = '' ) { return qtranxf_isAvailableIn( $post_id, $lang ); }
}
if ( ! function_exists( 'qtrans_useCurrentLanguageIfNotFoundShowEmpty' ) ) {
function qtrans_useCurrentLanguageIfNotFoundShowEmpty( $text ) { return qtrad_use_current( $text, false, true ); }
}
/** Full names remain available to screen readers in every display style. */
function qtrad_language_chooser( $style = '', $id = '' ) {
	if ( is_array( $style ) ) {
		$id = isset( $style['id'] ) ? $style['id'] : $id;
		$style = isset( $style['type'] ) ? $style['type'] : ( isset( $style['style'] ) ? $style['style'] : 'text' );
	}
	if ( is_bool( $style ) ) { $style = $style ? 'image' : 'text'; }
	if ( ! in_array( $style, array( 'text', 'image', 'both', 'dropdown', 'short' ), true ) ) { $style = 'text'; }
	$id = $id !== '' ? sanitize_html_class( $id ) : wp_unique_id( 'qtrad-language-' );
	$current = qtrad_current_language();
	$locales = qtrad_config( 'locale' );
	$html = '<nav class="qtrad-switcher" aria-label="' . esc_attr__( 'Language', 'qtrad' ) . '">';
	if ( $style === 'dropdown' ) {
		// Progressive enhancement: ordinary links remain usable without JavaScript.
		$html .= '<form class="qtrad-language-form" hidden><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Language', 'qtrad' ) . '</label> <select id="' . esc_attr( $id ) . '">';
		foreach ( qtrad_enabled_languages() as $lang ) {
			$html .= '<option value="' . esc_url( qtrad_convert_url( '', $lang, false, true ) ) . '"' . selected( $current, $lang, false ) . '>' . esc_html( qtrad_language_name( $lang ) ) . '</option>';
		}
		$html .= '</select> <button type="submit">' . esc_html__( 'Go', 'qtrad' ) . '</button></form>';
	}
	$html .= '<ul class="qtrans_language_chooser qtranxs-lang-switcher qtrad-style-' . esc_attr( $style ) . '"' . ( $style === 'dropdown' ? '' : ' id="' . esc_attr( $id ) . '"' ) . '>';
	foreach ( qtrad_enabled_languages() as $lang ) {
		$name = qtrad_language_name( $lang );
		$locale = isset( $locales[ $lang ] ) ? str_replace( '_', '-', $locales[ $lang ] ) : $lang;
		$flag = in_array( $style, array( 'image', 'both' ), true ) ? qtrad_flag_url( $lang ) : '';
		$html .= '<li class="lang-' . esc_attr( $lang ) . ( $lang === $current ? ' active' : '' ) . '"><a href="' . esc_url( qtrad_convert_url( '', $lang, false, true ) ) . '" hreflang="' . esc_attr( $locale ) . '" lang="' . esc_attr( $locale ) . '"' . ( $lang === $current ? ' aria-current="true"' : '' ) . '>';
		if ( $flag ) { $html .= '<img src="' . esc_url( $flag ) . '" width="18" height="12" alt="" />'; }
		if ( $style === 'short' ) {
			$html .= '<span aria-hidden="true">' . esc_html( strtoupper( $lang ) ) . '</span><span class="qtrad-visually-hidden">' . esc_html( $name ) . '</span>';
		} else {
			$html .= '<bdi class="' . ( $style === 'image' && $flag ? 'qtrad-flag-name' : 'qtrad-language-name' ) . '">' . esc_html( $name ) . '</bdi>';
		}
		$html .= '</a></li>';
	}
	return $html . '</ul></nav>';
}

if ( ! function_exists( 'qtranxf_convertURLs' ) ) {
function qtranxf_convertURLs( $urls, $lang = '', $forceadmin = false, $showDefaultLanguage = false ) {
	if ( is_array( $urls ) ) {
		foreach ( $urls as $key => $url ) { $urls[ $key ] = qtranxf_convertURLs( $url, $lang, $forceadmin, $showDefaultLanguage ); }
		return $urls;
	}
	return is_string( $urls ) && $urls !== '' ? qtranxf_convertURL( $urls, $lang, $forceadmin, $showDefaultLanguage ) : $urls;
}

}
