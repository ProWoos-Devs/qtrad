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
		echo wp_kses( qtrad_language_chooser( $style, $id ), qtrad_chooser_allowed_html() );
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
/** Markup the language chooser may contain, for escaping it where it is echoed. */
function qtrad_chooser_allowed_html() {
	return array(
		'nav'    => array( 'class' => true, 'aria-label' => true ),
		'form'   => array( 'class' => true, 'hidden' => true ),
		'label'  => array( 'for' => true ),
		'select' => array( 'id' => true ),
		'option' => array( 'value' => true, 'selected' => true ),
		'button' => array( 'type' => true ),
		'ul'     => array( 'class' => true, 'id' => true ),
		'li'     => array( 'class' => true ),
		'a'      => array( 'href' => true, 'hreflang' => true, 'lang' => true, 'aria-current' => true ),
		'img'    => array( 'src' => true, 'width' => true, 'height' => true, 'alt' => true ),
		'span'   => array( 'class' => true, 'aria-hidden' => true ),
		'bdi'    => array( 'class' => true ),
	);
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

// qTranslate-XT helpers and its translator API, for themes and plugins that call them.
if ( ! function_exists( 'qtranxf_use_language' ) ) {
	function qtranxf_use_language( $lang, $text, $show_available = false, $show_empty = false ) {
		return qtrad_use_language( $text, (string) $lang, $show_available, $show_empty );
	}
}

if ( ! function_exists( 'qtranxf_get_language_blocks' ) ) {
	function qtranxf_get_language_blocks( $text ) {
		return is_string( $text ) ? qtrad_language_blocks( $text ) : array();
	}
}

if ( ! function_exists( 'qtranxf_split_languages' ) ) {
	function qtranxf_split_languages( $blocks ) {
		return qtrad_split_blocks( (array) $blocks, qtrad_enabled_languages() );
	}
}

if ( ! function_exists( 'qtranxf_translate_deep' ) ) {
	function qtranxf_translate_deep( $value, $lang = '' ) {
		return qtrad_use_language( $value, $lang ? (string) $lang : qtrad_current_language() );
	}
}

if ( ! function_exists( 'qtranxf_translate_post' ) ) {
	/** Translates the text fields of a post object in place, as qTranslate-XT does. */
	function qtranxf_translate_post( $post, $lang ) {
		if ( ! is_object( $post ) ) {
			return;
		}
		foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $field ) {
			if ( isset( $post->$field ) && is_string( $post->$field ) ) {
				$post->$field = qtrad_use_language( $post->$field, (string) $lang );
			}
		}
	}
}

if ( ! function_exists( 'qtranxf_get_url_for_language' ) ) {
	function qtranxf_get_url_for_language( $url, $lang, $showLanguage = true ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- qTranslate-XT signature.
		return qtrad_convert_url( (string) $url, (string) $lang, true, (bool) $showLanguage ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- As above.
	}
}

if ( ! function_exists( 'qtranxf_term_use' ) ) {
	function qtranxf_term_use( $lang, $term, $taxonomy = null ) {
		if ( is_array( $term ) ) {
			foreach ( $term as $key => $value ) {
				$term[ $key ] = qtranxf_term_use( $lang, $value, $taxonomy );
			}
			return $term;
		}
		if ( is_object( $term ) && isset( $term->name ) ) {
			$term       = clone $term;
			$term->name = qtranxf_term_use( $lang, $term->name, $taxonomy );
			return $term;
		}
		if ( ! is_string( $term ) || '' === $term ) {
			return $term;
		}
		if ( qtrad_has_lang_tags( $term ) ) {
			return qtrad_use_language( $term, (string) $lang );
		}
		$library = qtrad_config( 'term_name' );
		return is_array( $library ) && ! empty( $library[ $term ][ $lang ] ) ? $library[ $term ][ $lang ] : $term;
	}
}

if ( ! defined( 'QTX_TRANSLATOR_SHOW_DEFAULT' ) ) {
	define( 'QTX_TRANSLATOR_SHOW_DEFAULT', 1 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- qTranslate-XT constant.
	define( 'QTX_TRANSLATOR_SHOW_AVAILABLE', 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- qTranslate-XT constant.
	define( 'QTX_TRANSLATOR_SHOW_EMPTY', 4 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- qTranslate-XT constant.
}

if ( ! class_exists( 'QTX_Translator' ) ) {
	/**
	 * qTranslate-XT's translator object. Its methods also answer the filters
	 * translate_text, translate_term and translate_url, and get_language and set_language.
	 */
	class QTX_Translator { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- qTranslate-XT class name.
		private static $instance = null;

		public static function get_translator() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function get_language() {
			return qtrad_current_language();
		}

		public function set_language( $lang ) {
			$previous = qtrad_current_language();
			if ( is_string( $lang ) && qtrad_is_enabled( $lang ) ) {
				qtrad_set_language( $lang );
			}
			return $previous;
		}

		public function translate_text( $text, $lang = null, $flags = 0 ) {
			return qtrad_use_language( $text, $lang ? (string) $lang : qtrad_current_language(), (bool) ( $flags & QTX_TRANSLATOR_SHOW_AVAILABLE ), (bool) ( $flags & QTX_TRANSLATOR_SHOW_EMPTY ) );
		}

		public function translate_term( $term, $lang = null, $taxonomy = null ) {
			return qtranxf_term_use( $lang ? (string) $lang : qtrad_current_language(), $term, $taxonomy );
		}

		public function translate_url( $url, $lang = null ) {
			return $lang ? qtrad_convert_url( (string) $url, (string) $lang, false, true ) : qtrad_convert_url( (string) $url );
		}
	}
}

/** The filters qTranslate-XT answers, for integrations that call them instead of the functions. */
function qtrad_register_translator_filters() {
	if ( ! class_exists( 'QTX_Translator' ) || ! method_exists( 'QTX_Translator', 'get_translator' ) ) {
		return;
	}
	$translator = QTX_Translator::get_translator();
	add_filter( 'translate_text', array( $translator, 'translate_text' ), 10, 3 );
	add_filter( 'translate_term', array( $translator, 'translate_term' ), 10, 3 );
	add_filter( 'translate_url', array( $translator, 'translate_url' ), 10, 2 );
	add_filter( 'get_language', array( $translator, 'get_language' ) );
	add_filter( 'set_language', array( $translator, 'set_language' ) );
}
