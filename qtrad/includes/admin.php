<?php
/**
 * Settings, the language bar on the editor, and the Languages column.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_admin_hooks() {
	if ( ! is_admin() ) {
		return;
	}
	add_action( 'admin_init', 'qtrad_capture_admin_lang' );
	add_action( 'admin_init', 'qtrad_register_taxonomy_fields' );
	add_action( 'admin_menu', 'qtrad_admin_menu' );
	add_action( 'admin_enqueue_scripts', 'qtrad_enqueue_editor' );
	add_action( 'add_meta_boxes', 'qtrad_add_meta_boxes' );
	add_filter( 'plugin_action_links_' . plugin_basename( QTRAD_FILE ), 'qtrad_plugin_links' );
	add_action( 'admin_bar_menu', 'qtrad_admin_bar_site_name', 100 );
	add_filter( 'admin_title', 'qtrad_admin_title' );
}

/**
 * The site title stays raw in wp-admin so Settings → General saves every
 * language. Places that only display it use the editing language.
 */
function qtrad_admin_site_name() {
	$raw = (string) get_option( 'blogname' );
	return qtrad_has_lang_tags( $raw ) ? qtrad_use_current( $raw ) : '';
}

function qtrad_admin_bar_site_name( $bar ) {
	$name = qtrad_admin_site_name();
	$node = ( $name !== '' && $bar instanceof WP_Admin_Bar ) ? $bar->get_node( 'site-name' ) : null;
	if ( $node ) {
		$bar->add_node( array( 'id' => 'site-name', 'title' => esc_html( wp_html_excerpt( $name, 40, '&hellip;' ) ) ) );
	}
}

function qtrad_admin_title( $title ) {
	$name = qtrad_admin_site_name();
	return $name !== '' ? str_replace( (string) get_option( 'blogname' ), $name, $title ) : $title;
}

function qtrad_plugin_links( $links ) {
	$url = admin_url( 'options-general.php?page=qtrad' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'qtrad' ) . '</a>' );
	return $links;
}

function qtrad_capture_admin_lang() {
	$id = isset( $_POST['qtrad_field_post_id'] ) ? absint( $_POST['qtrad_field_post_id'] ) : 0;
	$lang = isset( $_POST['qtrad_edit_lang'] ) && is_string( $_POST['qtrad_edit_lang'] ) ? strtolower( sanitize_key( wp_unslash( $_POST['qtrad_edit_lang'] ) ) ) : '';
	if ( $id && qtrad_is_enabled( $lang ) && current_user_can( 'edit_post', $id ) && isset( $_POST['qtrad_field_nonce'] ) && is_string( $_POST['qtrad_field_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qtrad_field_nonce'] ) ), 'qtrad_field_' . $id ) ) {
		update_user_meta( get_current_user_id(), 'qtrad_edit_language', $lang );
	}

	if ( ! isset( $_GET['qtrad_lang'] ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'qtrad_language' );
	$lang = is_string( $_GET['qtrad_lang'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['qtrad_lang'] ) ) ) : '';
	if ( ! qtrad_is_enabled( $lang ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'qtrad_edit_language', $lang );
	}
	$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	setcookie( 'qtrans_admin_language', $lang, array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => $path, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	$_COOKIE['qtrans_admin_language'] = $lang;
	qtrad_set_language( $lang );
	wp_safe_redirect( remove_query_arg( array( 'qtrad_lang', '_wpnonce' ) ) );
	exit;
}

function qtrad_admin_menu() {
	add_options_page(
		__( 'Languages', 'qtrad' ),
		__( 'Languages', 'qtrad' ),
		'manage_options',
		'qtrad',
		'qtrad_settings_page'
	);
}

function qtrad_enqueue_editor( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'settings_page_qtrad' ), true ) ) {
		return;
	}
	if ( $hook !== 'settings_page_qtrad' ) {
		$screen = get_current_screen();
		$type = $screen ? get_post_type_object( $screen->post_type ) : null;
		if ( ! $type || ! $type->public ) { return; }
	}
	wp_enqueue_script( 'qtrad-settings', plugins_url( 'assets/js/settings.js', QTRAD_FILE ), array( 'common' ), QTRAD_VERSION, true );
	wp_enqueue_style( 'qtrad-admin', plugins_url( 'assets/css/admin.css', QTRAD_FILE ), array(), QTRAD_VERSION );
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_script( 'qtrad-codec', plugins_url( 'assets/js/codec.js', QTRAD_FILE ), array(), QTRAD_VERSION, true );
	$deps = array( 'jquery', 'qtrad-codec' );
	foreach ( array( 'wp-data', 'wp-blocks', 'wp-api-fetch' ) as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}
	wp_enqueue_script( 'qtrad-editor', plugins_url( 'assets/js/editor.js', QTRAD_FILE ), $deps, QTRAD_VERSION, true );
	$enabled = qtrad_enabled_languages();
	$names   = array();
	$directions = array();
	foreach ( $enabled as $lang ) {
		$names[ $lang ] = qtrad_language_name( $lang );
		$directions[ $lang ] = qtrad_language_direction( $lang );
	}
	$screen = get_current_screen();
	$post   = null;
	if ( $screen && $screen->base === 'post' && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the post being edited; core checks edit_post.
		$post = get_post( absint( wp_unslash( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
	}
	$type = $screen ? get_post_type_object( $screen->post_type ) : null;
	wp_localize_script(
		'qtrad-editor',
		'qtradEditorConfig',
		array(
			'enabled'    => $enabled,
			'names'      => $names,
			'locales' => qtrad_config( 'locale' ),
			'directions' => $directions,
			'fieldNonce' => $post ? wp_create_nonce( 'qtrad_field_' . $post->ID ) : '',
			'forceMarkers' => (bool) qtrad_setting( 'force_markers', false ),
			'restBase' => $type && $type->rest_base ? $type->rest_base : ( $type ? $type->name : 'posts' ),
			'restRoot' => esc_url_raw( rest_url() ),
			'restNamespace' => $type && ! empty( $type->rest_namespace ) ? $type->rest_namespace : 'wp/v2',
			/* translators: %s: native language name */
			'languageMessage' => __( 'Editing %s. Other translations are preserved when you save.', 'qtrad' ),
			'editLang'   => qtrad_admin_language(),
			'editorMode' => qtrad_setting( 'editor_mode', 'lsb' ),
			'writeFormat'=> qtrad_setting( 'write_format', 'keep' ),
			'title'      => $post ? $post->post_title : '',
			'content'    => $post ? $post->post_content : '',
			'excerpt'    => $post ? $post->post_excerpt : '',
		)
	);
}

function qtrad_add_meta_boxes() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	foreach ( $types as $type ) {
		add_meta_box(
			'qtrad-languages',
			esc_html__( 'Languages', 'qtrad' ),
			'qtrad_languages_box',
			$type,
			'side',
			'high'
		);
	}
}

function qtrad_languages_box( $post ) {
	$enabled = qtrad_enabled_languages();
	$active  = qtrad_admin_language();
	$mode    = qtrad_setting( 'editor_mode', 'lsb' );
	if ( $mode === 'raw' ) {
		echo '<p>' . esc_html__( 'Raw mode: edit the language markers directly in the title, content and excerpt.', 'qtrad' ) . '</p>';
		return;
	}
	wp_nonce_field( 'qtrad_field_' . $post->ID, 'qtrad_field_nonce' );
	echo '<input type="hidden" name="qtrad_field_post_id" value="' . esc_attr( $post->ID ) . '" />';
	echo '<input type="hidden" name="qtrad_edit_lang" id="qtrad_edit_lang" value="' . esc_attr( $active ) . '" />';
	echo '<input type="hidden" name="qtrad_js" id="qtrad_js" value="" />';
	echo '<div class="qtrad-lsb" id="qtrad-lsb" role="group" aria-label="' . esc_attr__( 'Editing language', 'qtrad' ) . '" aria-describedby="qtrad-language-help">';
	foreach ( $enabled as $lang ) {
		echo '<button type="button" class="qtrad-lsb__btn" disabled data-qtrad-lang="' . esc_attr( $lang ) . '" aria-pressed="' . ( $lang === $active ? 'true' : 'false' ) . '"><bdi lang="' . esc_attr( str_replace( '_', '-', qtrad_config( 'locale' )[ $lang ] ) ) . '">' . esc_html( qtrad_language_name( $lang ) ) . '</bdi></button>';
	}
	echo '</div><p id="qtrad-language-help">' . esc_html__( 'Choose the language to edit. Tab moves between buttons; Enter or Space selects a language. Other translations are preserved when you save.', 'qtrad' ) . '</p>';
	echo '<p id="qtrad-language-status" role="status" aria-live="polite" aria-atomic="true"></p>';
	echo '<noscript><p>' . esc_html__( 'JavaScript is unavailable. Edit the full language markers directly; the language buttons require JavaScript.', 'qtrad' ) . '</p></noscript>';
	echo '<div id="qtrad-store">';
	$fields = array(
		'title'   => $post->post_title,
		'content' => $post->post_content,
		'excerpt' => $post->post_excerpt,
	);
	foreach ( $fields as $key => $raw ) {
		$parts = qtrad_split( (string) $raw, $enabled, false );
		foreach ( $enabled as $lang ) {
			$value = isset( $parts[ $lang ] ) ? $parts[ $lang ] : '';
			echo '<textarea class="qtrad-store" name="qtrad_field[' . esc_attr( $key ) . '][' . esc_attr( $lang ) . ']" data-qtrad-store="' . esc_attr( $key ) . '" data-qtrad-lang="' . esc_attr( $lang ) . '" hidden>' . esc_textarea( $value ) . '</textarea>';
		}
	}
	echo '</div>';
	$format = qtrad_detect_format( (string) $post->post_content );
	if ( ! $format ) {
		$format = qtrad_detect_format( (string) $post->post_title );
	}
	if ( $format ) {
		$labels = array(
			'comment' => __( 'qTranslate comments', 'qtrad' ),
			'bracket' => __( 'qTranslate-X brackets', 'qtrad' ),
			'swirly'  => __( 'swirly brackets', 'qtrad' ),
		);
		echo '<p class="qtrad-format">' . esc_html( isset( $labels[ $format ] ) ? $labels[ $format ] : $format ) . '</p>';
	}
}

function qtrad_language_column( $columns ) {
	$columns['qtrad_langs'] = esc_html__( 'Languages', 'qtrad' );
	return $columns;
}

function qtrad_language_column_cell( $column, $post_id ) {
	if ( $column !== 'qtrad_langs' ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$blob = $post->post_title . $post->post_content . $post->post_excerpt;
	if ( ! qtrad_has_lang_tags( $blob ) ) {
		echo '<span class="qtrad-col-plain">' . esc_html( strtoupper( qtrad_default_language() ) ) . '</span>';
		return;
	}
	$found = qtranxf_getAvailableLanguages( $post->post_title . "\n" . $post->post_content );
	if ( ! $found ) {
		$found = qtrad_enabled_languages();
	}
	echo '<span class="qtrad-col-langs">';
	foreach ( $found as $lang ) {
		echo '<span>' . esc_html( strtoupper( $lang ) ) . '</span>';
	}
	echo '</span>';
}

function qtrad_term_rows( $term = null ) {
	$enabled = qtrad_enabled_languages();
	$stored  = array();
	if ( $term && isset( $term->name ) ) {
		$term = qtrad_get_raw_term( $term->term_id, $term->taxonomy );
		$stored = get_term_meta( $term->term_id, '_qtrad_translations', true );
		$stored = is_array( $stored ) ? $stored : array();
		$library = qtrad_config( 'term_name' );
		if ( ! $stored && is_array( $library ) && isset( $library[ $term->name ] ) && is_array( $library[ $term->name ] ) ) {
			$stored = $library[ $term->name ];
		}
	}
	foreach ( $enabled as $lang ) {
		$value = isset( $stored[ $lang ] ) ? $stored[ $lang ] : '';
		if ( $value === '' && $term && $lang === qtrad_default_language() ) {
			$value = $term->name;
		}
		echo '<div class="form-field qtrad-term-field">';
		echo '<label for="qtrad-term-' . esc_attr( $lang ) . '">' . esc_html( qtrad_language_name( $lang ) ) . '</label> ';
		echo '<input name="qtrad_term[' . esc_attr( $lang ) . ']" id="qtrad-term-' . esc_attr( $lang ) . '" type="text" value="' . esc_attr( $value ) . '" class="regular-text" lang="' . esc_attr( str_replace( '_', '-', qtrad_config( 'locale' )[ $lang ] ) ) . '" dir="' . esc_attr( qtrad_language_direction( $lang ) ) . '" />';
		echo '</div>';
	}
}

function qtrad_term_add_fields( $taxonomy ) {
	echo '<input type="hidden" name="qtrad_term_taxonomy" value="' . esc_attr( $taxonomy ) . '" />';
	wp_nonce_field( 'qtrad_term', 'qtrad_term_nonce' );
	echo '<input type="hidden" name="qtrad_term_id" value="0" />';
	echo '<div class="form-field"><p><strong>' . esc_html__( 'Translations', 'qtrad' ) . '</strong></p>';
	qtrad_term_rows( null );
	echo '</div>';
}

function qtrad_term_fields( $term ) {
	echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Translations', 'qtrad' ) . '</th><td>';
	wp_nonce_field( 'qtrad_term', 'qtrad_term_nonce' );
	echo '<input type="hidden" name="qtrad_term_id" value="' . esc_attr( $term->term_id ) . '" />';
	qtrad_term_rows( $term );
	echo '<p class="description">' . esc_html__( 'Translate this term in each enabled language. The default translation is its WordPress name.', 'qtrad' ) . '</p>';
	echo '</td></tr>';
}

/** English language names, translatable so admins see them in their own language. */
function qtrad_language_admin_names() {
	return array(
		'en' => __( 'English', 'qtrad' ),
		'de' => __( 'German', 'qtrad' ),
		'fr' => __( 'French', 'qtrad' ),
		'es' => __( 'Spanish', 'qtrad' ),
		'it' => __( 'Italian', 'qtrad' ),
		'pt' => __( 'Portuguese', 'qtrad' ),
		'pb' => __( 'Portuguese (Brazil)', 'qtrad' ),
		'nl' => __( 'Dutch', 'qtrad' ),
		'sv' => __( 'Swedish', 'qtrad' ),
		'pl' => __( 'Polish', 'qtrad' ),
		'ru' => __( 'Russian', 'qtrad' ),
		'zh' => __( 'Chinese (Simplified)', 'qtrad' ),
		'ja' => __( 'Japanese', 'qtrad' ),
		'el' => __( 'Greek', 'qtrad' ),
		'tr' => __( 'Turkish', 'qtrad' ),
		'ro' => __( 'Romanian', 'qtrad' ),
		'hu' => __( 'Hungarian', 'qtrad' ),
		'fi' => __( 'Finnish', 'qtrad' ),
		'hr' => __( 'Croatian', 'qtrad' ),
		'sk' => __( 'Slovak', 'qtrad' ),
		'sr' => __( 'Serbian', 'qtrad' ),
		'vi' => __( 'Vietnamese', 'qtrad' ),
		'ar' => __( 'Arabic', 'qtrad' ),
		'et' => __( 'Estonian', 'qtrad' ),
		'cs' => __( 'Czech', 'qtrad' ),
		'da' => __( 'Danish', 'qtrad' ),
		'ca' => __( 'Catalan', 'qtrad' ),
		'gl' => __( 'Galician', 'qtrad' ),
		'eu' => __( 'Basque', 'qtrad' ),
		'cy' => __( 'Welsh', 'qtrad' ),
		'he' => __( 'Hebrew', 'qtrad' ),
		'ko' => __( 'Korean', 'qtrad' ),
		'tw' => __( 'Chinese (Traditional)', 'qtrad' ),
		'md' => __( 'Moldovan', 'qtrad' ),
		'lt' => __( 'Lithuanian', 'qtrad' ),
		'kk' => __( 'Kazakh', 'qtrad' ),
	);
}

/** Name in the admin's language, or the native name for custom languages. */
function qtrad_language_admin_name( $code ) {
	$names = qtrad_language_admin_names();
	return isset( $names[ $code ] ) ? $names[ $code ] : qtrad_language_name( $code );
}

/** "German – Deutsch (de)", with the native name isolated for right-to-left scripts. Returns escaped HTML. */
function qtrad_language_choice_label( $code, $meta ) {
	$admin  = qtrad_language_admin_name( $code );
	$native = isset( $meta['name'] ) && $meta['name'] !== '' ? $meta['name'] : $admin;
	$locale = isset( $meta['locale'] ) ? str_replace( '_', '-', $meta['locale'] ) : $code;
	$html   = esc_html( $admin );
	if ( $native !== $admin ) {
		$html .= ' – <bdi lang="' . esc_attr( $locale ) . '">' . esc_html( $native ) . '</bdi>';
	}
	return '<span class="qtrad-lang-text">' . $html . ' <span class="qtrad-lang-code">(' . esc_html( $code ) . ')</span></span>';
}

function qtrad_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['qtrad_settings'] ) && check_admin_referer( 'qtrad_settings' ) ) {
		$result = qtrad_save_settings( wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			echo '<div id="qtrad-errors" class="notice notice-error" role="alert" tabindex="-1"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			$enabled = qtrad_enabled_languages();
			foreach ( array( 'blogname', 'blogdescription' ) as $option ) {
				$parts = qtrad_split( (string) get_option( $option ), $enabled, false );
				foreach ( $enabled as $lang ) {
					if ( isset( $_POST[ $option ][ $lang ] ) && is_string( $_POST[ $option ][ $lang ] ) ) {
						$parts[ $lang ] = sanitize_text_field( wp_unslash( $_POST[ $option ][ $lang ] ) );
					}
				}
				update_option( $option, qtrad_join( $parts, 'bracket', $enabled ) );
			}
			echo '<div class="notice notice-success" role="status"><p>' . esc_html__( 'Languages saved.', 'qtrad' ) . '</p></div>';
		}
	}

	$catalog  = qtrad_available_catalog();
	$enabled  = qtrad_enabled_languages();
	$default  = qtrad_default_language();
	$url_mode = (int) qtrad_config( 'url_mode' );
	$write    = qtrad_setting( 'write_format', 'keep' );
	$editor   = qtrad_setting( 'editor_mode', 'lsb' );
	$domains  = qtrad_config( 'domains' );
	$domain_lines = '';
	$invalid = isset( $result ) && is_wp_error( $result );
	if ( is_array( $domains ) ) {
		foreach ( $domains as $code => $host ) {
			$domain_lines .= $code . ' = ' . $host . "\n";
		}
	}
	if ( $invalid && isset( $_POST['domains'] ) && is_string( $_POST['domains'] ) ) { $domain_lines = sanitize_textarea_field( wp_unslash( $_POST['domains'] ) ); }
	$names = qtrad_split( (string) get_option( 'blogname' ), $enabled );
	$descs = qtrad_split( (string) get_option( 'blogdescription' ), $enabled );

	echo '<div class="wrap qtrad-settings">';
	echo '<h1>' . esc_html__( 'Languages', 'qtrad' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'Reads qTranslate comments, qTranslate-X brackets and swirly brackets. Shared language settings are retained. For compatibility with the original qTranslate, use comment markers for posts.', 'qtrad' ) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'qtrad_settings' );
	echo '<input type="hidden" name="qtrad_settings" value="1" />';
	echo '<table class="form-table" role="presentation">';

	// Enabled languages first, in their stored order; the rest A to Z by name in the admin's language.
	$available = array_diff( array_keys( $catalog ), $enabled );
	usort( $available, function ( $a, $b ) { return strnatcasecmp( qtrad_language_admin_name( $a ), qtrad_language_admin_name( $b ) ); } );
	echo '<tr><th scope="row">' . esc_html__( 'Languages', 'qtrad' ) . '</th><td>';
	foreach ( array( 'enabled' => array( __( 'Your languages', 'qtrad' ), array_values( array_intersect( $enabled, array_keys( $catalog ) ) ) ), 'available' => array( __( 'Add languages', 'qtrad' ), $available ) ) as $group => $list ) {
		echo '<fieldset class="qtrad-lang-group qtrad-lang-group--' . esc_attr( $group ) . '"><legend>' . esc_html( $list[0] ) . '</legend><div class="qtrad-lang-grid">';
		foreach ( $list[1] as $code ) {
			echo '<label class="qtrad-lang-choice"><input type="checkbox" name="enabled[]" value="' . esc_attr( $code ) . '"' . checked( 'enabled' === $group, true, false ) . ' /> ';
			echo qtrad_language_choice_label( $code, $catalog[ $code ] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in qtrad_language_choice_label().
		}
		echo '</div></fieldset>';
	}
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="qtrad-default">' . esc_html__( 'Default language', 'qtrad' ) . '</label></th><td><select name="default" id="qtrad-default">';
	foreach ( array_keys( $catalog ) as $code ) {
		echo '<option value="' . esc_attr( $code ) . '"' . selected( $default, $code, false ) . '>' . esc_html( qtrad_language_name( $code ) ) . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'URL mode', 'qtrad' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'URL mode', 'qtrad' ) . '</legend>';
	$modes = array(
		1 => __( 'Query (?lang=en) — qTranslate mode 1', 'qtrad' ),
		2 => __( 'Path (/en/page/) — qTranslate mode 2, the usual choice', 'qtrad' ),
		3 => __( 'Subdomain (en.example.com) — mode 3', 'qtrad' ),
		4 => __( 'One domain per language — qTranslate-X mode 4', 'qtrad' ),
	);
	foreach ( $modes as $value => $label ) {
		echo '<label class="qtrad-choice"><input type="radio" name="url_mode" value="' . esc_attr( (string) $value ) . '"' . checked( $url_mode, $value, false ) . ' /> ' . esc_html( $label ) . '</label><br />';
	}
	echo '</fieldset></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'URL options', 'qtrad' ) . '</th><td>';
	echo '<label class="qtrad-choice"><input type="checkbox" name="hide_default" value="1"' . checked( (bool) qtrad_config( 'hide_default_language' ), true, false ) . ' /> ' . esc_html__( 'Hide the default language in the URL', 'qtrad' ) . '</label><br />';
	echo '<label class="qtrad-choice"><input type="checkbox" name="detect_browser" value="1"' . checked( (bool) qtrad_config( 'detect_browser_language' ), true, false ) . ' /> ' . esc_html__( 'On the first visit to the home page, use the browser language', 'qtrad' ) . '</label><br />';
	echo '<label class="qtrad-choice"><input type="checkbox" name="hide_untranslated" value="1"' . checked( (bool) qtrad_config( 'hide_untranslated' ), true, false ) . ' /> ' . esc_html__( 'Hide posts that have no translation in the current language', 'qtrad' ) . '</label>';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'When a translation is missing', 'qtrad' ) . '</th><td>';
	echo '<label class="qtrad-choice"><input type="checkbox" name="show_prefix" value="1"' . checked( (bool) qtrad_setting( 'show_prefix', true ), true, false ) . ' /> ' . esc_html__( 'Prefix the fallback with the language name', 'qtrad' ) . '</label><br />';
	echo '<label class="qtrad-choice"><input type="checkbox" name="show_alt_message" value="1"' . checked( (bool) qtrad_setting( 'show_alt_message', true ), true, false ) . ' /> ' . esc_html__( 'On content and excerpts, show “only available in …”', 'qtrad' ) . '</label>';
	echo '<br /><label class="qtrad-choice"><input type="checkbox" name="show_alt_content" value="1"' . checked( (bool) qtrad_setting( 'show_alt_content', false ), true, false ) . ' /> ' . esc_html__( 'Also show the available translation below the message', 'qtrad' ) . '</label>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="qtrad-write">' . esc_html__( 'Write format', 'qtrad' ) . '</label></th><td><select name="write_format" id="qtrad-write">';
	$formats = array(
		'keep'    => __( 'Keep the format already in the field', 'qtrad' ),
		'bracket' => __( 'qTranslate-X brackets [:en]', 'qtrad' ),
		'comment' => __( 'qTranslate comments <!--:en-->', 'qtrad' ),
		'swirly'  => __( 'Swirly brackets {:en}', 'qtrad' ),
	);
	foreach ( $formats as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $write, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select><p class="description">' . esc_html__( 'Keep preserves each existing field’s format and uses qTranslate comments for new translations. Brackets and swirly brackets require qTranslate-X or qTrad.', 'qtrad' ) . '</p></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Editor', 'qtrad' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Editor mode', 'qtrad' ) . '</legend>';
	echo '<label class="qtrad-choice"><input type="radio" name="editor_mode" value="lsb"' . checked( $editor, 'lsb', false ) . ' /> ' . esc_html__( 'Language switching buttons', 'qtrad' ) . '</label><br />';
	echo '<label class="qtrad-choice"><input type="radio" name="editor_mode" value="raw"' . checked( $editor, 'raw', false ) . ' /> ' . esc_html__( 'Raw tags in the editor', 'qtrad' ) . '</label>';
	echo '</fieldset></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Site title', 'qtrad' ) . '</th><td>';
	foreach ( $enabled as $code ) {
		echo '<p><label>' . esc_html( qtrad_language_name( $code ) ) . '<br /><input type="text" class="regular-text" lang="' . esc_attr( str_replace( '_', '-', qtrad_config( 'locale' )[ $code ] ) ) . '" dir="' . esc_attr( qtrad_language_direction( $code ) ) . '" name="blogname[' . esc_attr( $code ) . ']" value="' . esc_attr( isset( $names[ $code ] ) ? $names[ $code ] : '' ) . '" /></label></p>';
	}
	echo '<p class="description">' . esc_html__( 'Saved with bracket tags so WordPress does not strip them. The same string is what qTranslate-X would have stored in blogname.', 'qtrad' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Tagline', 'qtrad' ) . '</th><td>';
	foreach ( $enabled as $code ) {
		echo '<p><label>' . esc_html( qtrad_language_name( $code ) ) . '<br /><input type="text" class="regular-text" lang="' . esc_attr( str_replace( '_', '-', qtrad_config( 'locale' )[ $code ] ) ) . '" dir="' . esc_attr( qtrad_language_direction( $code ) ) . '" name="blogdescription[' . esc_attr( $code ) . ']" value="' . esc_attr( isset( $descs[ $code ] ) ? $descs[ $code ] : '' ) . '" /></label></p>';
	}
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="qtrad-domains">' . esc_html__( 'Domains', 'qtrad' ) . '</label></th><td>';
	echo '<textarea name="domains" id="qtrad-domains" rows="5" class="large-text code">' . esc_textarea( trim( $domain_lines ) ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Used by URL mode 4. One per line: de = de.example.com', 'qtrad' ) . '</p>';
	echo '</td></tr>';

	qtrad_slugs_settings_row();

	echo '<tr><th scope="row"><label for="qtrad-extra">' . esc_html__( 'Custom fields', 'qtrad' ) . '</label></th><td>';
	echo '<textarea name="extra_fields" id="qtrad-extra" rows="4" class="large-text code">' . esc_textarea( (string) qtrad_setting( 'extra_fields', '' ) ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Meta keys, one per line. On the public site their values are shown in the current language. A plain update merges into the language you are editing in the admin bar.', 'qtrad' ) . '</p>';
	echo '<label class="qtrad-choice"><input type="checkbox" name="translate_meta" value="1"' . checked( (bool) qtrad_setting( 'translate_meta', false ), true, false ) . ' /> ' . esc_html__( 'On the public site, also show every other custom field and user profile field that contains language markers in the current language', 'qtrad' ) . '</label>';
	echo '</td></tr>';

	$options_mode = qtrad_setting( 'translate_options', 'none' );
	echo '<tr><th scope="row">' . esc_html__( 'Options', 'qtrad' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Options', 'qtrad' ) . '</legend>';
	$option_modes = array(
		'none' => __( 'Translate only the site title and tagline', 'qtrad' ),
		'all'  => __( 'Translate every option that contains language markers, such as theme and widget settings', 'qtrad' ),
		'list' => __( 'Translate only the options listed below', 'qtrad' ),
	);
	foreach ( $option_modes as $value => $label ) {
		echo '<label class="qtrad-choice"><input type="radio" name="translate_options" value="' . esc_attr( $value ) . '"' . checked( $options_mode, $value, false ) . ' /> ' . esc_html( $label ) . '</label><br />';
	}
	echo '</fieldset><p><label for="qtrad-filter-options">' . esc_html__( 'Option names', 'qtrad' ) . '</label><br /><input type="text" class="large-text code" id="qtrad-filter-options" name="filter_options" value="' . esc_attr( implode( ' ', qtrad_option_patterns() ) ) . '" autocomplete="off" spellcheck="false" /></p>';
	echo '<p class="description">' . esc_html__( 'Separated by spaces, with % for any characters, for example widget_%. Options are translated on the public site only; wp-admin keeps showing what is stored.', 'qtrad' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="qtrad-text-filters">' . esc_html__( 'Text filters', 'qtrad' ) . '</label></th><td>';
	echo '<input type="text" class="large-text code" id="qtrad-text-filters" name="text_field_filters" value="' . esc_attr( implode( ' ', qtrad_text_filters() ) ) . '" autocomplete="off" spellcheck="false" />';
	echo '<p class="description">' . esc_html__( 'Filter hooks of your theme or other plugins whose text should be shown in the current language, separated by spaces.', 'qtrad' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Language markers', 'qtrad' ) . '</th><td><label class="qtrad-choice"><input type="checkbox" name="force_markers" value="1"' . checked( (bool) qtrad_setting( 'force_markers', false ), true, false ) . ' /> ' . esc_html__( 'Keep markers even when translations are identical', 'qtrad' ) . '</label></td></tr>';
	echo '</table>';
	echo '<details><summary>' . esc_html__( 'Language names and locales', 'qtrad' ) . '</summary>';
	foreach ( $catalog as $code => $meta ) {
		if ( ! in_array( $code, $enabled, true ) ) { continue; }
		echo '<fieldset><legend>' . esc_html( $meta['name'] . ' (' . $code . ')' ) . '</legend>';
		foreach ( array( 'name' => __( 'Native name', 'qtrad' ), 'locale' => __( 'WordPress locale', 'qtrad' ) ) as $key => $label ) {
			echo '<p><label for="qtrad-' . esc_attr( $code . '-' . $key ) . '">' . esc_html( $label ) . '</label> <input id="qtrad-' . esc_attr( $code . '-' . $key ) . '" name="languages[' . esc_attr( $code ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $invalid && isset( $_POST['languages'][ $code ][ $key ] ) && is_string( $_POST['languages'][ $code ][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['languages'][ $code ][ $key ] ) ) : $meta[ $key ] ) . '" /></p>';
		}
		echo '</fieldset>';
	}
	echo '</details><fieldset><legend><h2>' . esc_html__( 'Add a language', 'qtrad' ) . '</h2></legend><p>' . esc_html__( 'Provide a two- or three-letter code, native name and WordPress locale, for example: is, Íslenska, is_IS. The language will be enabled when you save.', 'qtrad' ) . '</p>';
	foreach ( array( 'code' => __( 'Language code', 'qtrad' ), 'name' => __( 'Native name', 'qtrad' ), 'locale' => __( 'WordPress locale', 'qtrad' ) ) as $key => $label ) {
		echo '<p><label for="qtrad-new-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <input id="qtrad-new-' . esc_attr( $key ) . '" name="new_language[' . esc_attr( $key ) . ']" value="' . esc_attr( $invalid && isset( $_POST['new_language'][ $key ] ) && is_string( $_POST['new_language'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['new_language'][ $key ] ) ) : '' ) . '" /></p>';
	}
	echo '</fieldset>';
	submit_button( __( 'Save languages', 'qtrad' ) );
	echo '</form></div>';
}

function qtrad_register_taxonomy_fields() {
	foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $taxonomy ) {
		add_action( $taxonomy . '_edit_form_fields', 'qtrad_term_fields' );
		add_action( $taxonomy . '_add_form_fields', 'qtrad_term_add_fields' );
	}
	foreach ( get_post_types( array( 'public' => true ) ) as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'qtrad_language_column' );
		add_action( "manage_{$type}_posts_custom_column", 'qtrad_language_column_cell', 10, 2 );
	}
}
