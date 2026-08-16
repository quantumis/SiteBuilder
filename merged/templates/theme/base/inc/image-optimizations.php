<?php
/**
 * Site Builder — image optimizations for PageSpeed.
 *
 * Fixes measured on the first production site (casinoechtgeldzahlungen.com,
 * July 2026), where PageSpeed flagged the logo as saving 326 KiB:
 *   - Logo file was 931×391 (327 KiB PNG) but the actual container is ~150×60
 *   - WordPress's default `sizes="(max-width: 931px) 100vw, 931px"` told the
 *     browser to reserve viewport-width bandwidth for a tiny image
 *   - No fetchpriority="high" on the logo, but it's the desktop LCP element
 *
 * This module patches get_custom_logo's HTML output to:
 *   - Set sizes to the actual max rendered size (150px in our themes)
 *   - Give the logo fetchpriority="high" so browsers request it first
 *
 * We hook via the get_custom_logo filter — it fires for every header that
 * calls the_custom_logo() / get_custom_logo(), which is all our 8 header
 * variants. No per-header changes needed.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_optimize_custom_logo_html')) {
    function sb_optimize_custom_logo_html($html, $blog_id = 0) {
        if (!is_string($html) || $html === '') return $html;

        // Fix the sizes attribute. The logo container in all our headers is
        // capped at ~150px wide (see the CSS max-width on .custom-logo /
        // .sb-header-* .site-logo), never the viewport width. Telling the
        // browser sizes="150px" makes it pick the smallest srcset variant
        // that covers 150px instead of downloading the full 931px file.
        $html = preg_replace(
            '/\bsizes=(["\'])[^"\']*\1/i',
            'sizes="150px"',
            $html
        );

        // Add fetchpriority="high" — the logo is above the fold and is the
        // desktop LCP element on most of our sites. Only add if not already
        // present (respect any explicit value the theme sets).
        if (strpos($html, 'fetchpriority=') === false) {
            $html = preg_replace(
                '/<img\b/',
                '<img fetchpriority="high"',
                $html,
                1
            );
        }

        // Remove loading="lazy" if WordPress added it — lazy-loading the LCP
        // image delays rendering. PageSpeed explicitly flags this ("Don't use
        // loading=lazy for LCP resources"). Some WP versions add lazy to the
        // custom logo by default; we override.
        $html = preg_replace(
            '/\s+loading=(["\'])(lazy|auto)\1/i',
            ' loading="eager"',
            $html
        );

        return $html;
    }
    add_filter('get_custom_logo', 'sb_optimize_custom_logo_html', 10, 2);
}

if (!function_exists('sb_ensure_image_dimensions')) {
    /**
     * Ensure every <img> in post content has explicit width and height
     * attributes. Without them, browsers can't reserve layout space during
     * image load — that's the "Cumulative Layout Shift" (CLS) that PageSpeed
     * penalizes. WordPress usually sets these from the attachment metadata,
     * but images inserted manually or imported without going through
     * wp_get_attachment_image() can be missing them.
     *
     * We rely on getimagesize() as a last resort — it's an I/O call but only
     * happens for images that don't already have the attributes. Result is
     * cached in a transient so we don't hit the FS on every page load.
     */
    function sb_ensure_image_dimensions($content) {
        if (!is_singular() || is_admin()) return $content;
        if (strpos($content, '<img') === false) return $content;

        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function ($m) {
                $attrs = $m[1];
                // Skip if width and height are already there
                if (preg_match('/\bwidth=/i', $attrs) && preg_match('/\bheight=/i', $attrs)) {
                    return $m[0];
                }
                // Extract src
                if (!preg_match('/\bsrc=(["\'])([^"\']+)\1/i', $attrs, $srcm)) {
                    return $m[0];
                }
                $src = $srcm[2];
                // Only for local uploads — external images we can't measure
                $uploads = wp_get_upload_dir();
                if (strpos($src, $uploads['baseurl']) !== 0) return $m[0];

                $cache_key = 'sb_imgsize_' . md5($src);
                $size = get_transient($cache_key);
                if ($size === false) {
                    $rel = str_replace($uploads['baseurl'], $uploads['basedir'], $src);
                    if (file_exists($rel)) {
                        $info = @getimagesize($rel);
                        $size = ($info && isset($info[0], $info[1])) ? [$info[0], $info[1]] : null;
                    } else {
                        $size = null;
                    }
                    // Cache for a week — image dimensions don't change
                    set_transient($cache_key, $size, WEEK_IN_SECONDS);
                }
                if (!$size) return $m[0];

                $inject = ' width="' . (int)$size[0] . '" height="' . (int)$size[1] . '"';
                return '<img' . $inject . $attrs . '>';
            },
            $content
        );
    }
    // Priority 25 — after external-links (20), after all content mutations so
    // we operate on final HTML. Not part of the resource-heavy path (no DB
    // queries per image, just cached filesystem stats).
    add_filter('the_content', 'sb_ensure_image_dimensions', 25);
}
