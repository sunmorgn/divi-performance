# Divi Performance

A WordPress plugin that fixes Divi-specific performance and accessibility issues. Drop it into any Divi site — no configuration, no site-specific code.

## What it does

| # | Fix | Why |
|---|-----|-----|
| 0 | **Viewport meta** | Replaces Divi's `et_add_viewport_meta` with `width=device-width, initial-scale=1.0` (Lighthouse-preferred format) |
| 1 | **Preconnect Google Fonts** | Saves ~100ms DNS+TLS handshake before font CSS fires |
| 2 | **font-display: swap** | Prevents invisible text (FOIT) on all `@font-face` declarations |
| 3 | **Remove jQuery Migrate** | Divi 4.x doesn't need it on the frontend; it's render-blocking |
| 4 | **Defer Divi scripts** | `scripts.min.js` was blocking the main thread for ~2,900ms after LCP. Uses WP 6.3 native defer API + `script_loader_tag` fallback |
| 5 | **Async Google Fonts** | Converts render-blocking `<link rel="stylesheet">` to `preload` + `onload` swap pattern |
| 5b | **`display=swap` on Divi font URL** | Adds `?display=swap` to Divi's Google Fonts URL via `et_pb_google_fonts_url` — belt-and-suspenders in case a caching plugin bypasses the tag filter |
| 6 | **Divi Pixel (DIPI) optimizations** | Async CSS: `dipi_font`, `dipi_hamburgers_css`, `dipi-popup-maker-popup-effect`. Defer JS: `dipi_hamburgers_js`, `dipi-popup-maker-popup-effect`. Handles verified against Divi Pixel source. |
| 8 | **FA icons removal** | Commented out — uncomment if you've confirmed Font Awesome icons aren't used |
| 9 | **HTML output buffer** | Fixes Divi markup post-render: adds `role="main"`, copies `title` → `aria-label` on social links, derives `aria-label` from href slug on image-wrapped links |

## What it does NOT include

Things that look generic but are actually site-specific — handle these in your child theme:

- **Footer contrast colors** — hardcoded hex values depend on the site's color scheme
- **Mega-menu `media` attribute** — depends on the child theme's enqueue handle name
- **DNS prefetch domains** — site-specific external links
- **LCP image preloads** — image paths are site-specific

## Conflicts

**NitroPack / WP Rocket / similar caching plugins** — these plugins also defer scripts and async-load CSS. Running both will double-process the same resources. If NitroPack is active:
- Disable its script deferral and CSS async features, OR
- Deactivate this plugin and let NitroPack handle it instead

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

## Rollback notes

- **jQuery Migrate removal** (section 3): if any frontend behaviour breaks after activation, comment out the `wp_default_scripts` block and test again
- **Font Awesome icons** (section 8): uncomment the `et_global_assets_list` filter only after confirming no Divi modules use FA icons on the site
- **Script defer** (section 4): if a specific Divi module breaks (e.g. sliders, forms), remove its handle from `$defer_handles`
