<?php
/**
 * What still needs translating: a "Missing translation" filter on the post
 * lists and a dashboard overview with counts per content type and language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtrad_register_overview_hooks() {
	if ( ! is_admin() ) {
		return;
	}
	add_action( 'restrict_manage_posts', 'qtrad_missing_filter_select', 10, 1 );
	add_action( 'pre_get_posts', 'qtrad_missing_filter_query' );
	add_filter( 'posts_where', 'qtrad_missing_filter_where', 10, 2 );
	add_action( 'wp_dashboard_setup', 'qtrad_dashboard_setup' );
}

/** Public post types the overview covers, without attachments. */
function qtrad_overview_post_types() {
	return array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
}

function qtrad_missing_filter_language() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A read-only list filter, like the core filters beside it.
	$lang = isset( $_GET['qtrad_missing'] ) && is_string( $_GET['qtrad_missing'] ) ? sanitize_key( wp_unslash( $_GET['qtrad_missing'] ) ) : '';
	return qtrad_is_enabled( $lang ) ? $lang : '';
}

function qtrad_missing_filter_select( $post_type ) {
	if ( ! in_array( $post_type, qtrad_overview_post_types(), true ) ) {
		return;
	}
	$current = qtrad_missing_filter_language();
	echo '<label for="qtrad-missing" class="screen-reader-text">' . esc_html__( 'Filter by missing translation', 'qtrad' ) . '</label>';
	echo '<select name="qtrad_missing" id="qtrad-missing"><option value="">' . esc_html__( 'All translations', 'qtrad' ) . '</option>';
	foreach ( qtrad_enabled_languages() as $lang ) {
		/* translators: %s: language name */
		echo '<option value="' . esc_attr( $lang ) . '"' . selected( $current, $lang, false ) . '>' . esc_html( sprintf( __( 'Missing in %s', 'qtrad' ), qtrad_language_name( $lang ) ) ) . '</option>';
	}
	echo '</select>';
}

function qtrad_missing_filter_query( $query ) {
	if ( ! $query instanceof WP_Query || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	$lang   = qtrad_missing_filter_language();
	if ( '' !== $lang && $screen && 'edit' === $screen->base ) {
		$query->set( 'qtrad_missing_language', $lang );
	}
}

/** Posts without content in the language, by the same rule that decides where a post is shown. */
function qtrad_missing_filter_where( $where, $query ) {
	$lang = $query instanceof WP_Query ? $query->get( 'qtrad_missing_language' ) : '';
	if ( ! is_string( $lang ) || ! qtrad_is_enabled( $lang ) ) {
		return $where;
	}
	return $where . ' AND NOT (' . preg_replace( '/^\s*AND\s*/', '', qtrad_available_language_where( $lang ) ) . ')';
}

function qtrad_dashboard_setup() {
	if ( current_user_can( 'edit_posts' ) && count( qtrad_enabled_languages() ) > 1 ) {
		wp_add_dashboard_widget( 'qtrad_translations', esc_html__( 'Translations', 'qtrad' ), 'qtrad_dashboard_widget' );
		wp_enqueue_style( 'qtrad-admin', plugins_url( 'assets/css/admin.css', QTRAD_FILE ), array(), QTRAD_VERSION );
	}
}

/**
 * Published items of a type and how many of them are missing each language.
 * Cached until posts change.
 *
 * @return array{total:int,missing:array<string,int>}
 */
function qtrad_overview_counts( $post_type ) {
	$key    = 'counts:' . $post_type . ':' . md5( implode( ',', qtrad_enabled_languages() ) ) . ':' . wp_cache_get_last_changed( 'posts' );
	$counts = wp_cache_get( $key, 'qtrad_overview' );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	$hooked = has_filter( 'posts_where', 'qtrad_missing_filter_where' );
	if ( false === $hooked ) {
		add_filter( 'posts_where', 'qtrad_missing_filter_where', 10, 2 );
	}
	$base   = array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'ignore_sticky_posts' => true, 'suppress_filters' => false );
	$total  = new WP_Query( $base );
	$counts = array( 'total' => (int) $total->found_posts, 'missing' => array() );
	foreach ( qtrad_enabled_languages() as $lang ) {
		$query                     = new WP_Query( array_merge( $base, array( 'qtrad_missing_language' => $lang ) ) );
		$counts['missing'][ $lang ] = (int) $query->found_posts;
	}
	if ( false === $hooked ) {
		remove_filter( 'posts_where', 'qtrad_missing_filter_where', 10 );
	}
	wp_cache_set( $key, $counts, 'qtrad_overview' );
	return $counts;
}

function qtrad_dashboard_widget() {
	$rows = array();
	foreach ( qtrad_overview_post_types() as $post_type ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
			continue;
		}
		$counts = qtrad_overview_counts( $post_type );
		if ( $counts['total'] > 0 ) {
			$rows[ $post_type ] = array( 'label' => $object->labels->name, 'counts' => $counts );
		}
	}
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'There is no published content yet.', 'qtrad' ) . '</p>';
		return;
	}
	$languages = qtrad_enabled_languages();
	echo '<table class="widefat striped qtrad-overview"><caption class="screen-reader-text">' . esc_html__( 'Published content missing a translation, by language', 'qtrad' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Content', 'qtrad' ) . '</th>';
	foreach ( $languages as $lang ) {
		echo '<th scope="col"><bdi>' . esc_html( qtrad_language_name( $lang ) ) . '</bdi></th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $post_type => $row ) {
		/* translators: %d: number of published items */
		echo '<tr><th scope="row">' . esc_html( $row['label'] ) . ' <span class="qtrad-overview__total">' . esc_html( sprintf( _n( '(%d published)', '(%d published)', $row['counts']['total'], 'qtrad' ), $row['counts']['total'] ) ) . '</span></th>';
		foreach ( $languages as $lang ) {
			$missing = $row['counts']['missing'][ $lang ];
			if ( 0 === $missing ) {
				echo '<td>' . esc_html__( 'Complete', 'qtrad' ) . '</td>';
				continue;
			}
			$url = add_query_arg( array( 'post_type' => $post_type, 'post_status' => 'publish', 'qtrad_missing' => $lang ), admin_url( 'edit.php' ) );
			/* translators: %d: number of items without a translation */
			echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( _n( '%d missing', '%d missing', $missing, 'qtrad' ), $missing ) ) . '</a></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Missing means the title or content has no text in that language, so visitors see the fallback.', 'qtrad' ) . '</p>';
}
