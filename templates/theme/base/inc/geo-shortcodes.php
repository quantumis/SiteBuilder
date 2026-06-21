<?php
/**
 * Site Builder — auto-insertion of [sports_predictions] and [geo_info] shortcodes.
 *
 * Both shortcodes come from the external "GEO" companion plugin. We:
 *   - Skip rendering if the page is utility-only (fsr_utility = 1)
 *   - Skip individual shortcodes that aren't registered (so the theme works
 *     even when the GEO plugin isn't installed)
 *   - Insert the rendered HTML between the first <h1> and the first <img>
 *     (i.e. immediately after the closing </h1>)
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_geo_inject_shortcodes')) {

    function sb_geo_inject_shortcodes($content) {
        // Only on singular front-end views, in the main query
        if (!is_singular() || is_admin() || !is_main_query()) return $content;

        $post_id = get_the_ID();
        if (!$post_id) return $content;

        // Utility pages (terms, sitemaps, etc) — skip
        if ((int)get_post_meta($post_id, 'fsr_utility', true) === 1) return $content;

        // Render the shortcodes (only the ones actually registered)
        $injection = '';
        if (shortcode_exists('sports_predictions')) {
            $injection .= do_shortcode('[sports_predictions]');
        }
        if (shortcode_exists('geo_info')) {
            $injection .= do_shortcode('[geo_info]');
        }
        if ($injection === '') return $content;

        // Find the first </h1> close tag (case-insensitive) — that's where we
        // insert the block. The spec guarantees every non-utility page has at
        // least one h1 and at least one image after it, so we don't bother
        // with edge-case fallbacks here.
        if (preg_match('#</h1\s*>#i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $offset)
                . "\n<div class=\"sb-geo-shortcodes\">\n" . $injection . "\n</div>\n"
                . substr($content, $offset);
        }

        // No h1 found — degrade gracefully by injecting at the very start.
        // Should not happen for valid FSR content but keeps the page from
        // losing the GEO block if a page is malformed.
        return "<div class=\"sb-geo-shortcodes\">\n" . $injection . "\n</div>\n" . $content;
    }
    // Priority 12 — after WordPress's default content filters (10), and after
    // any markdown-style processors that may have run.
    add_filter('the_content', 'sb_geo_inject_shortcodes', 12);
}
