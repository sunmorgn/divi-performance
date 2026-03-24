<?php
/**
 * wp-performance.php — Generic WordPress core optimizations
 * Loaded by divi-performance.php. Safe to reuse on any WordPress site.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// ─── 1. REMOVE EMOJI SCRIPTS & STYLES ────────────────────────────────────────
// WordPress loads emoji detection JS (~15kb) and CSS on every page by default.
// Wrapped in init hook to guarantee the default actions have been registered.
add_action( 'init', function () {
    remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles',     'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles',  'print_emoji_styles' );
    remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
    remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
} );

// Remove the inline emoji DNS prefetch that wp_head outputs.
add_filter( 'emoji_svg_url', '__return_false' );


// ─── 2. REMOVE WP HEAD BLOAT ─────────────────────────────────────────────────
// wp_generator exposes WP version. The rest are dead standards or irrelevant
// for a standard marketing site.
add_action( 'init', function () {
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'feed_links',             2 );
    remove_action( 'wp_head', 'feed_links_extra',       3 );
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
} );


// ─── 3. REMOVE WP EMBED SCRIPT ───────────────────────────────────────────────
// wp-embed.min.js enables other sites to embed your posts via oEmbed.
// Not needed for a marketing site.
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_script( 'wp-embed' );
}, 100 );


// ─── 4. REMOVE GUTENBERG BLOCK STYLES (DIVI PAGES ONLY) ──────────────────────
// wp-block-library.css loads on every page even when Divi is the builder.
// Only dequeue when the current post is actually built with Divi (has _et_pb_use_builder
// meta set to 'on'). This preserves block styles on any post/page using Gutenberg.
add_action( 'wp_enqueue_scripts', function () {
    $post_id = get_the_ID();
    if ( $post_id && get_post_meta( $post_id, '_et_pb_use_builder', true ) !== 'on' ) {
        return; // Not a Divi page — leave block styles intact
    }
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );        // WP 5.9+
    wp_dequeue_style( 'classic-theme-styles' ); // WP 6.1+
}, 100 );


// ─── 5. REMOVE DASHICONS FOR GUESTS ──────────────────────────────────────────
// Dashicons are only needed in wp-admin and for the admin bar.
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_user_logged_in() ) {
        wp_dequeue_style( 'dashicons' );
    }
}, 100 );


// ─── 6. DISABLE XML-RPC ──────────────────────────────────────────────────────
// XML-RPC is a legacy protocol and a common brute-force attack surface.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_action( 'init', function () {
    remove_action( 'wp_head', 'wp_pingback_header' );
} );


// ─── 7. REMOVE WORDPRESS DNS PREFETCH ────────────────────────────────────────
// WP outputs <link rel="dns-prefetch" href="//s.w.org"> which is unnecessary
// for sites not loading WordPress.org assets.
add_filter( 'wp_resource_hints', function ( $hints, $relation_type ) {
    if ( 'dns-prefetch' === $relation_type ) {
        return array_filter( $hints, function ( $hint ) {
            $url = is_array( $hint ) ? ( $hint['href'] ?? '' ) : $hint;
            return strpos( (string) $url, 's.w.org' ) === false;
        } );
    }
    return $hints;
}, 10, 2 );
