# Divi Performance

A WordPress plugin that fixes Divi-specific performance and accessibility issues. Drop it into any Divi site — no configuration, no site-specific code.

## Safety features

- **Divi detection** — Divi-specific fixes only run when Divi is the active parent theme. Safe to leave active during theme switches.
- **Context-aware** — skips admin, AJAX, REST API, WP-CLI, and cron contexts entirely.
- **Output buffer guards** — skips feeds, sitemaps, and non-HTML responses.
- **No conflicts with Gutenberg** — block styles are only removed on pages built with the Divi Builder, not on Gutenberg pages.
- **Divi Pixel optional** — DIPI optimizations match on handle names and skip gracefully if the plugin isn't installed.

## Measured impact (local, Slow 4G, mobile Lighthouse)

| Test | Performance | A11y | BP | SEO |
|---|---|---|---|---|
| No plugins | 70 | 84 | 100 | 92 |
| Plugin active | 74 | 96 | 100 | 100 |

FCP: 2.3s → 2.0s · LCP: 9.9s → 8.2s · Speed Index: 3.2s → 2.0s

Production impact will be larger — local TTFB is ~530ms vs ~59ms on Cloudflare CDN.

## What it does

### Generic WordPress optimizations (`wp-performance.php`)

| # | Fix | Why |
|---|-----|-----|
| 1 | **Remove emoji scripts** | Removes ~15kb emoji detection JS/CSS loaded on every page |
| 2 | **Remove wp_head bloat** | Strips WP version, RSD, WLW manifest, shortlinks, oEmbed, feed links |
| 3 | **Remove wp-embed** | oEmbed embed script not needed on marketing sites |
| 4 | **Remove block styles (Divi pages)** | Removes Gutenberg CSS only on pages using Divi Builder |
| 5 | **Remove dashicons (guests)** | Dashicons only needed for logged-in admin bar |
| 6 | **Disable XML-RPC** | Legacy protocol, common brute-force attack surface |
| 7 | **Remove s.w.org prefetch** | Unnecessary DNS prefetch for WordPress.org assets |

### Divi-specific fixes (`divi-performance.php`)

| # | Fix | Why |
|---|-----|-----|
| 1 | **Viewport meta** | Replaces Divi's `et_add_viewport_meta` with Lighthouse-preferred format |
| 2 | **Preload Divi icon fonts** | Moves modules.woff and fa-solid-900.woff2 to early `<head>` |
| 3 | **Preconnect Google Fonts** | Saves ~100ms DNS+TLS handshake before font CSS fires |
| 4 | **Remove jQuery Migrate** | Divi 4.x doesn't need it on the frontend; it's render-blocking |
| 5 | **Defer Divi scripts** | Defers non-critical scripts via `script_loader_tag` (not WP 6.3 strategy API — avoids jQuery dependency propagation bug) |
| 6 | **Async Google Fonts** | Converts render-blocking stylesheet to `preload` + `onload` swap pattern |
| 6b | **Async Divi customizer CSS** | Same pattern for `et-divi-customizer-global.min.css` |
| 6c | **`display=swap` on font URL** | Belt-and-suspenders via `et_pb_google_fonts_url` filter |
| 7 | **Divi Pixel optimizations** | Async CSS + defer JS for DIPI handles (skipped if not installed) |
| 8 | **FA icons removal** | Commented out — uncomment after confirming no FA icons are used |
| 9 | **HTML output buffer** | Adds `role="main"`, copies `title` → `aria-label` on social links, derives `aria-label` from href slug on image-wrapped links |

## What it does NOT include

Things that look generic but are actually site-specific — handle these in your child theme:

- **Footer contrast colors** — hardcoded hex values depend on the site's color scheme
- **Mega-menu `media` attribute** — depends on the child theme's enqueue handle name
- **DNS prefetch domains** — site-specific external links
- **LCP image preloads** — image paths are site-specific

## Conflicts

**NitroPack / WP Rocket / similar caching plugins** — these plugins also defer scripts and async-load CSS. Running both will double-process the same resources. If a caching plugin is active:
- Disable its script deferral and CSS async features, OR
- Deactivate this plugin and let the caching plugin handle it instead

Check which is doing a better job with a PageSpeed test before/after.

## Required Divi admin settings

These aren't handled by the plugin — they're checkboxes in the Divi UI that must be set manually:

- **Divi → Theme Options → Performance → Generate Static CSS File** — enable this. Divi generates a static CSS file per page instead of computing it on every request.
- **Divi → Theme Options → Performance → Lazy Loading → Images** — enable this. Divi will add `loading="lazy"` to below-fold images.
- After enabling Static CSS, clear the cache: **Divi → Theme Options → Builder → Clear Static CSS**

## Child theme additions (not in this plugin — too site-specific)

**Disable Divi animations on mobile** — animations add TBT and INP cost on mobile without visual benefit. Add to your child theme's `style.css`:

```css
@media (max-width: 639px) {
    .et_pb_section, .et_pb_section * {
        animation: none !important;
        transition: none !important;
    }
}
```

Test thoroughly before shipping — this will disable all entrance animations site-wide on mobile.

## Installation

1. Upload the `divi-performance` folder to `/wp-content/plugins/`
2. Activate via WP Admin → Plugins
3. No settings page — it just works
4. Works on any Divi site — Divi-specific fixes are skipped if Divi isn't the active theme

## Rollback notes

- **jQuery Migrate removal** (section 4): if any frontend behaviour breaks after activation, comment out the `wp_default_scripts` block and test again
- **Font Awesome icons** (section 8): uncomment the `et_global_assets_list` filter only after confirming no Divi modules use FA icons on the site
- **Script defer** (section 5): if a specific Divi module breaks (e.g. sliders, forms), remove its handle from `$defer_handles`
