<?php
/**
 * Plugin Name: Divi Performance
 * Plugin URI:  https://github.com/coactive/divi-performance
 * Description: Divi-specific performance and accessibility fixes. Safe to install on any Divi site. No site-specific URLs, colors, or child-theme logic.
 * Version:     1.1.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: divi-performance
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DIVI_PERFORMANCE_VERSION', '1.1.0' );

// Skip entirely in admin, AJAX, REST, WP-CLI, and cron contexts.
if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) || defined( 'WP_CLI' ) ) {
    return;
}

require __DIR__ . '/wp-performance.php';

// ─── GUARD: Only run Divi-specific fixes when Divi is the active theme ──────
// get_template() returns the parent theme slug. If it isn't 'Divi', skip
// everything below so the plugin is safe to leave active during theme switches.
add_action( 'after_setup_theme', function () {

    if ( get_template() !== 'Divi' ) {
        return;
    }

    divi_performance_register_fixes();

}, 20 );

/**
 * Registers all Divi-specific performance and accessibility hooks.
 * Only called when Divi is confirmed as the active parent theme.
 */
function divi_performance_register_fixes() {

// ─── 1. FIX DIVI VIEWPORT META ──────────────────────────────────────────────
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


// ─── 2. PRELOAD DIVI ICON FONTS ────────────────────────────────────────────
// modules.woff (90 KB) and fa-solid-900.woff2 (79 KB) load on every Divi page
// but are discovered late — they sit 574–614 ms into the network dependency
// chain behind CSS files. Preloading moves them to the very first bytes of <head>.
// get_template_directory_uri() correctly returns the Divi parent theme URI.
add_action( 'wp_head', function () {
    $divi = get_template_directory_uri();
    echo '<link rel="preload" href="' . esc_url( $divi . '/core/admin/fonts/modules/all/modules.woff' ) . '" as="font" type="font/woff" crossorigin>' . "\n";
    echo '<link rel="preload" href="' . esc_url( $divi . '/core/admin/fonts/fontawesome/fa-solid-900.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 1 );


// ─── 3. PRECONNECT TO GOOGLE FONTS ─────────────────────────────────────────
// Divi loads Google Fonts from fonts.googleapis.com and fonts.gstatic.com.
// Preconnecting saves the DNS + TLS handshake (~100 ms) before the font CSS
// request fires. Must run at priority 1 to beat Divi's own wp_head output.
add_action( 'wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 1 );


// ─── 4. REMOVE JQUERY MIGRATE ──────────────────────────────────────────────
// jQuery Migrate is render-blocking and only patches deprecated jQuery patterns.
// Divi 4.x does not require it on the frontend. Remove and re-wire jquery to core.
//
// IMPORTANT: WP_Dependencies::add() silently refuses to overwrite an existing
// handle (returns false). Directly patching the deps array is the only reliable
// way to drop jquery-migrate without breaking the dependency chain.
//
// ROLLBACK: comment out this block if any frontend behaviour breaks.
add_action( 'wp_default_scripts', function ( $scripts ) {
    if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
        $scripts->registered['jquery']->deps = array( 'jquery-core' );
    }
} );


// ─── 5. DEFER DIVI NON-CRITICAL SCRIPTS ────────────────────────────────────
// Divi's scripts.min.js blocks the main thread after the hero image loads.
// Strategy: script_loader_tag filter only — do NOT use wp_script_add_data('strategy','defer')
// because WP 6.3 propagates defer to dependencies (causes "jQuery is not defined" errors
// when divi-custom-script's jquery dependency gets deferred along with it).
//
// Safe to defer: interaction scripts, animations, sticky, lightbox, video sizing.
// NOT deferred: jquery-core, jquery (must stay synchronous for inline scripts).

// Catch handles registered late or matched by URL substring.
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
        'dipi_hamburgers_js',              // Divi Pixel hamburger toggle
        'dipi-popup-maker-popup-effect',   // Divi Pixel popup transitions
    ];

    static $defer_url_substrings = [
        'themes/Divi/js/scripts.min.js',
        'dynamic-assets/assets/js/jquery.fitvids.js',
        'core/admin/js/common.js',
        'dynamic-assets/assets/js/sticky-elements.js',
        'dynamic-assets/assets/js/motion-effects.js',
    ];

    // Never defer jQuery itself — inline scripts depend on it being synchronous.
    if ( in_array( $handle, [ 'jquery', 'jquery-core' ], true ) ) {
        return $tag;
    }

    // Avoid double-deferring if another plugin already handled it.
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


// ─── 6. ASYNC LOAD GOOGLE FONTS ────────────────────────────────────────────
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


// ─── 6b. ASYNC LOAD DIVI CUSTOMIZER GLOBAL CSS ────────────────────────────
// et-divi-customizer-global.min.css is render-blocking but contains only
// customizer color/font overrides — not needed before first paint.
// Matched by URL substring; Divi generates the enqueue handle dynamically.
add_filter( 'style_loader_tag', function ( $tag, $handle, $href, $media ) {
    if ( strpos( $href, 'et-divi-customizer-global' ) === false ) {
        return $tag;
    }
    $url = esc_url( $href );
    return '<link rel="preload" href="' . $url . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n"
         . '<noscript><link rel="stylesheet" href="' . $url . '"></noscript>' . "\n";
}, 10, 4 );


// ─── 6c. FORCE DISPLAY=SWAP ON DIVI GOOGLE FONTS URL ──────────────────────
// Divi builds its Google Fonts URL before enqueueing. Adding display=swap here
// ensures the parameter is present even if our style_loader_tag async filter is
// bypassed (e.g. by a caching plugin that inlines the tag).
add_filter( 'et_pb_google_fonts_url', function ( $url ) {
    return add_query_arg( 'display', 'swap', $url );
} );


// ─── 7. DIVI PIXEL (DIPI) OPTIMIZATIONS ───────────────────────────────────
// Divi Pixel is a widely-used Divi addon. These handles were verified against
// the plugin source — update handle names if Divi Pixel changes them.
// Only runs if Divi Pixel is active — skips gracefully if not installed.
//
// CSS handles: async-loaded (not needed before first paint)
// JS handles:  deferred (in section 5 $defer_handles list above)

add_filter( 'style_loader_tag', function ( $tag, $handle ) {

    static $async_handles = [
        'dipi_font',                       // dipi-font.min.css — icon glyphs (frontend + admin)
        'dipi_hamburgers_css',             // hamburgers.min.css — hamburger animation CSS
        'dipi-popup-maker-popup-effect',   // popup_effect.min.css — popup transition effects
    ];

    if ( ! in_array( $handle, $async_handles, true ) ) {
        return $tag;
    }

    $tag = str_replace( "rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
    $tag = str_replace( 'rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag );
    return $tag;

}, 10, 2 );


// ─── 8. REMOVE UNUSED DIVI FONT AWESOME ICONS ─────────────────────────────
// ENABLE THIS: uncomment if you've confirmed no Divi modules use FA icons.
// KEEP COMMENTED: if any module uses a Font Awesome icon (you'll see broken icons).
//
// add_filter( 'et_global_assets_list', function ( $assets ) {
//     unset( $assets['et_icons_fa'] );
//     unset( $assets['et_post_formats'] );
//     return $assets;
// }, 99 );


// ─── 9. HTML OUTPUT BUFFER FIXES ──────────────────────────────────────────
// Post-processes the full HTML to fix Divi markup that can't be changed via hooks.
// Skips non-HTML responses (feeds, sitemaps, etc.) to avoid corrupting XML output.
add_action( 'template_redirect', function () {
    // Don't buffer feeds, sitemaps, or robots.txt — they aren't HTML.
    if ( is_feed() || is_robots() || is_trackback() ) {
        return;
    }
    ob_start( 'divi_performance_process_html' );
}, 0 );

} // end divi_performance_register_fixes()

/**
 * Output buffer callback — fixes Divi markup that can't be changed via hooks.
 *
 * @param string $html Full page HTML.
 * @return string Modified HTML.
 */
function divi_performance_process_html( $html ) {

    // Safety: only process responses that look like HTML.
    if ( strlen( $html ) < 100 || stripos( $html, '<html' ) === false ) {
        return $html;
    }

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
