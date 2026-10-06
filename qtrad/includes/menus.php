<?php
/**
 * Navigation menus on the front end: the language switcher item that
 * qTranslate-X and qTranslate-XT store as a custom link (#qtransLangSw),
 * and custom links that point into the site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_menu_hooks() {
	if ( is_admin() ) {
		return;
	}
	add_filter( 'wp_get_nav_menu_items', 'qtrad_filter_nav_menu_items', 20 );
	add_filter( 'wp_nav_menu_objects', 'qtrad_filter_nav_menu_objects', 20 );
	add_filter( 'nav_menu_link_attributes', 'qtrad_filter_nav_menu_link_attributes', 10, 2 );
}

/** Language tag for hreflang and lang attributes, as the language chooser prints it. */
function qtrad_language_tag( $lang ) {
	$locales = qtrad_config( 'locale' );
	return is_array( $locales ) && ! empty( $locales[ $lang ] ) ? str_replace( '_', '-', $locales[ $lang ] ) : $lang;
}

/**
 * Options of a switcher item, read from its URL the way qTranslate-X does:
 * #qtransLangSw?type=[LM|AL]&title=[none|Language|Current]&flags=[none|all|items]&names=[shown|hidden]&colon=[shown|hidden]&current=[shown|hidden]
 */
function qtrad_menu_switcher_options( $url ) {
	$options = array( 'type' => 'LM', 'title' => 'Language', 'flags' => true, 'topflag' => true, 'names' => true, 'colon' => true, 'current' => true );
	$at      = strpos( $url, '?' );
	if ( false === $at ) {
		return $options;
	}
	$pars = array();
	parse_str( str_replace( '#', '', substr( $url, $at + 1 ) ), $pars );
	foreach ( $pars as $name => $value ) {
		if ( ! is_string( $value ) ) {
			unset( $pars[ $name ] );
		}
	}
	if ( isset( $pars['type'] ) && false !== stripos( $pars['type'], 'AL' ) ) {
		$options['type'] = 'AL';
	}
	if ( isset( $pars['flags'] ) ) {
		$options['flags']   = false === stripos( $pars['flags'], 'no' );
		$options['topflag'] = $options['flags'] && false === stripos( $pars['flags'], 'items' );
	}
	if ( isset( $pars['names'] ) ) {
		$options['names'] = false === stripos( $pars['names'], 'hid' );
	}
	if ( isset( $pars['title'] ) ) {
		$options['title'] = false !== stripos( $pars['title'], 'no' ) ? '' : $pars['title'];
		if ( ! $options['topflag'] && '' === $options['title'] ) {
			$options['title'] = 'Language';
		}
	}
	if ( isset( $pars['colon'] ) ) {
		$options['colon'] = false === stripos( $pars['colon'], 'hid' );
	}
	if ( isset( $pars['current'] ) ) {
		$options['current'] = false === stripos( $pars['current'], 'hid' );
	}
	if ( ! $options['names'] && ! $options['flags'] ) {
		$options['flags'] = true;
	}
	return $options;
}

/** Flag image for a menu title. The name is the text alternative only when it is not printed next to the flag. */
function qtrad_menu_flag( $lang, $alt ) {
	$flag = qtrad_flag_url( $lang );
	return $flag ? '<img class="qtranxs-flag" src="' . esc_url( $flag ) . '" width="18" height="12" alt="' . esc_attr( $alt ) . '" />' : '';
}

function qtrad_menu_language_title( $lang, $flags, $names ) {
	$name  = qtrad_language_name( $lang );
	$title = $flags ? qtrad_menu_flag( $lang, $names ? '' : $name ) : '';
	if ( $names || '' === $title ) {
		$title .= ( '' !== $title ? '&nbsp;' : '' ) . esc_html( $name );
	}
	return $title;
}

function qtrad_filter_nav_menu_items( $items ) {
	if ( ! is_array( $items ) ) {
		return $items;
	}
	$switchers = array();
	$order     = 0;
	foreach ( $items as $key => $item ) {
		if ( ! is_object( $item ) ) {
			continue;
		}
		$order = max( $order, isset( $item->menu_order ) ? (int) $item->menu_order : 0 );
		if ( isset( $item->item_lang ) ) {
			continue;
		}
		$item->item_lang = qtrad_current_language();
		foreach ( array( 'attr_title', 'description' ) as $field ) {
			if ( isset( $item->$field ) && is_string( $item->$field ) && qtrad_has_lang_tags( $item->$field ) ) {
				$item->$field = qtrad_use_current( $item->$field );
			}
		}
		if ( isset( $item->url ) && is_string( $item->url ) && false !== stripos( $item->url, 'qtransLangSw' ) ) {
			$switchers[] = $key;
		}
	}
	foreach ( $switchers as $key ) {
		foreach ( qtrad_menu_switcher_children( $items[ $key ], $order ) as $child ) {
			$items[] = $child;
		}
	}
	return $items;
}

/**
 * Turn one stored switcher item into its top item and return the language
 * items below it. Type LM lists the languages under a "Language" item, type AL
 * links the top item to the first other language and lists the rest below.
 */
function qtrad_menu_switcher_children( $item, &$order ) {
	$options  = qtrad_menu_switcher_options( $item->url );
	$current  = qtrad_current_language();
	$top_lang = $current;
	$alt_lang = '';
	if ( 'AL' === $options['type'] ) {
		foreach ( qtrad_enabled_languages() as $lang ) {
			if ( $lang !== $current ) {
				$top_lang = $lang;
				$alt_lang = $lang;
				break;
			}
		}
	}
	$name = qtrad_language_name( $top_lang );
	if ( '' !== $alt_lang ) {
		$title     = '' === $options['title'] ? '' : esc_html( $name );
		$item->url = qtrad_convert_url( '', $alt_lang, false, true );
		$item->qtrad_lang_link = array( 'lang' => $alt_lang, 'native' => true );
	} else {
		if ( '' === $options['title'] ) {
			$title = '';
		} elseif ( false !== stripos( $options['title'], 'Current' ) ) {
			$title = esc_html( $name );
		} else {
			// Only a title translated per language replaces the default, as in qTranslate-X.
			$title = esc_html( isset( $item->title ) && is_string( $item->title ) && qtrad_has_lang_tags( $item->title ) ? qtrad_use_current( $item->title ) : __( 'Language', 'qtrad' ) );
		}
		$item->url = '#';
	}
	if ( $options['topflag'] ) {
		$flag = qtrad_menu_flag( $top_lang, $title === esc_html( $name ) ? '' : $name );
		if ( '' !== $flag && '' !== $title ) {
			/* translators: %s: title of the language menu item, a flag follows the colon */
			$title = ( $options['colon'] ? sprintf( __( '%s:', 'qtrad' ), $title ) : $title ) . '&nbsp;';
		}
		$title .= $flag;
	}
	$item->title   = '' !== $title ? $title : esc_html( $name );
	$item->classes = array_merge( isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : array(), array( 'qtranxs-lang-menu', 'qtranxs-lang-menu-' . $top_lang ) );

	$parent   = isset( $item->db_id ) && $item->db_id ? (int) $item->db_id : (int) $item->ID;
	$children = array();
	foreach ( qtrad_enabled_languages() as $lang ) {
		if ( $lang === $alt_lang || ( $lang === $current && ( '' !== $alt_lang || ! $options['current'] ) ) ) {
			continue;
		}
		// Ids above any stored post, so no other post or menu item shares them.
		$id    = 1000000000 + ( $parent % 1000000 ) * 100 + count( $children ) + 1;
		$title = qtrad_menu_language_title( $lang, $options['flags'], $options['names'] );
		$child = new WP_Post(
			(object) array(
				'ID'          => $id,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => 'language-menuitem-' . $lang,
				'menu_order'  => ++$order,
			)
		);
		$child->db_id            = $id;
		$child->menu_item_parent = (string) $parent;
		$child->object_id        = (string) $id;
		$child->object           = 'custom';
		$child->type             = 'custom';
		$child->type_label       = 'Custom Link';
		$child->title            = $title;
		$child->url              = qtrad_convert_url( '', $lang, false, true );
		$child->target           = '';
		$child->attr_title       = '';
		$child->description      = '';
		$child->classes          = array( 'qtranxs-lang-menu-item', 'qtranxs-lang-menu-item-' . $lang );
		$child->xfn              = '';
		$child->item_lang        = $lang;
		$child->qtrad_lang_link  = array( 'lang' => $lang, 'native' => true );
		$children[]              = $child;
	}
	return $children;
}

/**
 * Custom links into the site follow the current language. This runs after
 * WordPress has marked the current item, which it does by comparing the stored
 * URL with the request. A link with setlang=no stays as stored, and a link to
 * #?lang=xx opens the current page in that language.
 */
function qtrad_filter_nav_menu_objects( $items ) {
	if ( ! is_array( $items ) ) {
		return $items;
	}
	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! isset( $item->type, $item->url ) || 'custom' !== $item->type || ! is_string( $item->url ) || '' === $item->url || isset( $item->qtrad_lang_link ) ) {
			continue;
		}
		if ( false !== strpos( $item->url, 'setlang=no' ) ) {
			$item->url = remove_query_arg( 'setlang', $item->url );
		} elseif ( preg_match( '/#\?lang=([a-z]{2,3})(?![a-z])/i', $item->url, $match ) ) {
			$lang = strtolower( $match[1] );
			if ( qtrad_is_enabled( $lang ) ) {
				$item->url             = qtrad_convert_url( '', $lang, false, true );
				$item->item_lang       = $lang;
				$item->qtrad_lang_link = array( 'lang' => $lang, 'native' => false );
			}
		} else {
			$url = qtrad_convert_url( $item->url );
			if ( '' !== $url ) {
				$item->url = $url;
			}
		}
	}
	return $items;
}

function qtrad_filter_nav_menu_link_attributes( $atts, $item ) {
	if ( ! is_array( $atts ) || ! is_object( $item ) || ! isset( $item->qtrad_lang_link ) || ! is_array( $item->qtrad_lang_link ) ) {
		return $atts;
	}
	$lang             = $item->qtrad_lang_link['lang'];
	$atts['hreflang'] = qtrad_language_tag( $lang );
	if ( $item->qtrad_lang_link['native'] ) {
		$atts['lang'] = $atts['hreflang'];
	}
	if ( $lang === qtrad_current_language() ) {
		$atts['aria-current'] = 'true';
	}
	return $atts;
}
