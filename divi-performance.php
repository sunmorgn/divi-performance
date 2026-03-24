<?php
/**
 * Plugin Name: Divi Performance
 * Description: Divi-specific performance and accessibility fixes. Safe to install on any Divi site. No site-specific URLs, colors, or child-theme logic.
 * Version: 1.0
 */

if ( is_admin() ) return;


// ─── 0. FIX DIVI VIEWPORT META ───────────────────────────────────────────────
// Divi registers et_add_viewport_meta which outputs a non-standard viewport tag.
// Replace it with the explicit format Lighthouse expects.
add_action( 'init', function () {
    remove_action( 'wp_head', 'et_add_viewport_meta' );
    remove_action( 'wp_head', 'et_add_viewport_meta', 1 );
    remove_action( 'wp_head', 'et_add_viewport_meta', 10 );
}, 1 );

add_action( 'wp_head', function () {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
}, 0 );


// ─── 1. PRECONNECT TO GOOGLE FONTS ───────────────────────────────────────────
// Divi loads Google Fonts from fonts.googleapis.com and fonts.gstatic.com.
// Preconnecting saves the DNS + TLS handshake (~100 ms) before the font CSS
// request fires. Must run at priority 1 to beat Divi's own wp_head output.
add_action( 'wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 1 );


// ─── 2. FONT-DISPLAY: SWAP ────────────────────────────────────────────────────
// Prevents invisible text (FOIT) while web fonts load by forcing font-display:swap
// on all @font-face declarations — Google Fonts, Divi icon fonts, and child theme fonts.
add_action( 'wp_head', function () {
    echo '<style id="divi-perf-font-display">@font-face{font-display:swap!important}</style>' . "\n";
}, 2 );


// ─── 3. REMOVE JQUERY MIGRATE ────────────────────────────────────────────────
// jQuery Migrate is render-blocking and only patches deprecated jQuery patterns.
// Divi 4.x does not require it on the frontend. Remove and re-wire jquery to core.
// ROLLBACK: comment out this block if any frontend behaviour breaks.
add_action( 'wp_default_scripts', function ( $scripts ) {
    if ( ! is_admin() ) {
        $scripts->remove( 'jquery-migrate' );
        $scripts->add( 'jquery', false, [ 'jquery-core' ], null );
    }
} );


// ─── 4. DEFER DIVI NON-CRITICAL SCRIPTS ──────────────────────────────────────
// Divi's scripts.min.js blocks the main thread after the hero image loads.
// Strategy: WP 6.3 native defer API first, script_loader_tag filter as fallback
// for handles registered late or assets loaded via Divi's dynamic asset system.
//
// Safe to defer: interaction scripts, animations, sticky, lightbox, video sizing.
// NOT deferred: jquery-core (inline localize data depends on it being synchronous).

add_action( 'wp_enqueue_scripts', function () {

    $defer_handles = [
        'divi-custom-script',                  // themes/Divi/js/scripts.min.js
        'smoothscroll',                        // smooth scrolling
        'fitvids',                             // responsive video sizing
        'salvattore',                          // masonry grid layout
        'easypiechart',                        // animated circle counters
        'magnific-popup',                      // lightbox
        'et-builder-modules-script-motion',    // entrance/scroll animations
        'et-builder-modules-script-sticky',    // sticky rows/sections/columns
        'et-smooth-scroll',
        'et_shortcodes_js',
        'et_pb_custom',
    ];

    foreach ( $defer_handles as $handle ) {
        wp_script_add_data( $handle, 'strategy', 'defer' );
    }

}, 100 );

// Fallback: catch handles registered late or matched by URL substring.
add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {

    static $defer_handles = [
        'divi-custom-script',
        'smoothscroll',
        'fitvids',
        'salvattore',
        'easypiechart',
        'magnific-popup',
        'et-builder-modules-script-motion',
        'et-builder-modules-script-sticky',
        'et-smooth-scroll',
        'et_shortcodes_js',
        'et_pb_custom',
        'et-core-admin',
        'et-sticky-elements',
    ];

    static $defer_url_substrings = [
        'themes/Divi/js/scripts.min.js',
        'dynamic-assets/assets/js/jquery.fitvids.js',
        'core/admin/js/common.js',
        'dynamic-assets/assets/js/sticky-elements.js',
        'dynamic-assets/assets/js/motion-effects.js',
    ];

    if ( in_array( $handle, [ 'jquery', 'jquery-core' ], true ) ) {
        return $tag;
    }

    if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, 'strategy="defer"' ) !== false ) {
        return $tag;
    }

    $should_defer = in_array( $handle, $defer_handles, true );

    if ( ! $should_defer ) {
        foreach ( $defer_url_substrings as $substr ) {
            if ( strpos( $src, $substr ) !== false ) {
                $should_defer = true;
                break;
            }
        }
    }

    if ( $should_defer ) {
        return str_replace( '<script ', '<script defer ', $tag );
    }

    return $tag;

}, 10, 3 );


// ─── 5. ASYNC LOAD GOOGLE FONTS ──────────────────────────────────────────────
// Convert Divi's render-blocking Google Fonts <link> tags to the preload + onload
// pattern. A <noscript> fallback handles JS-disabled browsers.
add_filter( 'style_loader_tag', function ( $tag, $handle, $href, $media ) {

    if ( strpos( $href, 'fonts.googleapis.com' ) === false ) {
        return $tag;
    }

    $url = esc_url( $href );
    return '<link rel="preload" href="' . $url . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n"
         . '<noscript><link rel="stylesheet" href="' . $url . '"></noscript>' . "\n";

}, 10, 4 );


// ─── 5b. FORCE DISPLAY=SWAP ON DIVI GOOGLE FONTS URL ─────────────────────────
// Divi builds its Google Fonts URL before enqueueing. Adding display=swap here
// ensures the parameter is present even if our style_loader_tag async filter is
// bypassed (e.g. by a caching plugin that inlines the tag).
add_filter( 'et_pb_google_fonts_url', function ( $url ) {
    return add_query_arg( 'display', 'swap', $url );
} );


// ─── 6. ASYNC LOAD DIPI FONT CSS ─────────────────────────────────────────────
// DIPI (Divi Plus) registers dipi-font.min.css as render-blocking. It only
// provides supplemental icon glyphs — not needed before first paint.
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
    if ( $handle !== 'dipi-font-css' ) {
        return $tag;
    }
    $tag = str_replace( "rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
    $tag = str_replace( 'rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag );
    return $tag;
}, 10, 2 );


// ─── 8. REMOVE UNUSED DIVI FONT AWESOME ICONS ────────────────────────────────
// ENABLE THIS: uncomment if you've confirmed no Divi modules use FA icons.
// KEEP COMMENTED: if any module uses a Font Awesome icon (you'll see broken icons).
//
// add_filter( 'et_global_assets_list', function ( $assets ) {
//     unset( $assets['et_icons_fa'] );
//     unset( $assets['et_post_formats'] );
//     return $assets;
// }, 99 );


// ─── 9. HTML OUTPUT BUFFER FIXES ─────────────────────────────────────────────
// Post-processes the full HTML to fix Divi markup that can't be changed via hooks.
add_action( 'template_redirect', function () {
    ob_start( 'divi_performance_process_html' );
}, 0 );

function divi_performance_process_html( $html ) {

    // a) Add role="main" — Divi outputs <div id="main-content"> with no semantic landmark.
    $html = str_replace(
        '<div id="main-content">',
        '<div id="main-content" role="main">',
        $html
    );

    // b) Social follow link aria-labels — Divi outputs aria-hidden span text; copy title → aria-label.
    $html = preg_replace_callback(
        "/<a(\s[^>]*?class=['\"]icon et_pb_with_border['\"][^>]*?)>/is",
        function ( $m ) {
            $inner = $m[1];
            if ( stripos( $inner, 'aria-label' ) !== false ) {
                return $m[0];
            }
            if ( preg_match( "/\\btitle=['\"]([^'\"]+)['\"]/i", $inner, $tm ) ) {
                $label = htmlspecialchars( $tm[1], ENT_QUOTES, 'UTF-8' );
                return '<a' . $inner . ' aria-label="' . $label . '">';
            }
            return $m[0];
        },
        $html
    );

    // c) Image-wrapped links with empty alt — derive aria-label from the href slug.
    $html = preg_replace_callback(
        '/<a(\s+href=[\'"]([^\'"#?]+)[\'"])([^>]*)>(\s*<span[^>]*>\s*<img\b[^>]*\balt=[\'"][\'"][^>]*>)/is',
        function ( $m ) {
            if ( stripos( $m[3], 'aria-label' ) !== false ) {
                return $m[0];
            }
            $slug  = trim( parse_url( $m[2], PHP_URL_PATH ), '/' );
            $label = ucwords( str_replace( [ '-', '_' ], ' ', basename( $slug ) ) );
            if ( ! $label ) {
                return $m[0];
            }
            return '<a' . $m[1] . $m[3] . ' aria-label="' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '">' . $m[4];
        },
        $html
    );

    return $html;
}
