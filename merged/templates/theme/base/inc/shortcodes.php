<?php
/**
 * Site Builder — dynamic date/year shortcodes.
 *
 * The plugin's FSR importer converts $$CURRENT_DATE$$ and $$CY$$ placeholders
 * in archive markdown into [sb_date] and [sb_year] shortcodes stored inside
 * post_content. This module resolves them at render time.
 *
 * Why not substitute at import time? A static substitution uses the WordPress
 * admin locale, but archive content is in a different language than the WP
 * interface (typical scenario: RU-admin WP hosting ES/IT/NL content). Resolving
 * at render time lets us pick the correct locale based on where the page
 * actually lives.
 *
 * Localization priority:
 *   1. WP core locale (usually matches the front-end language) — this covers
 *      the common case where the sitewide locale is set correctly for the
 *      audience the content targets.
 *
 * [sb_date]  — renders the post's last-modified date (if the article was
 *              edited, the date reflects the latest edit; otherwise it stays
 *              at the publish date). Uses WP's configured date format.
 *
 *   [sb_date format="F j, Y"]
 *   [sb_date basis="published"]   (or "modified", default "modified")
 *
 * [sb_year]  — renders the current year (four digits). No arguments.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_shortcode_date')) {
    function sb_shortcode_date($atts = []) {
        $atts = shortcode_atts([
            'format' => (string)get_option('date_format', 'F j, Y'),
            'basis'  => 'modified', // "modified" (default) or "published"
        ], (array)$atts, 'sb_date');

        $timestamp = 0;
        if (is_singular()) {
            $post_id = get_the_ID();
            if ($post_id) {
                $field = $atts['basis'] === 'published' ? 'post_date' : 'post_modified';
                $raw = get_post_field($field, $post_id);
                if ($raw) $timestamp = strtotime($raw);
            }
        }
        if ($timestamp <= 0) {
            // Outside singular context (rare — shortcode inside a widget?),
            // fall back to today's date.
            $timestamp = current_time('timestamp');
        }
        return esc_html(date_i18n($atts['format'], $timestamp));
    }
    add_shortcode('sb_date', 'sb_shortcode_date');
}

if (!function_exists('sb_shortcode_year')) {
    function sb_shortcode_year($atts = []) {
        return (string)date_i18n('Y', current_time('timestamp'));
    }
    add_shortcode('sb_year', 'sb_shortcode_year');
}

/**
 * Apply do_shortcode() to titles across WordPress surfaces where WP doesn't
 * do it automatically. WP core only runs shortcode processing on the_content;
 * post_title, menu item labels, and the document <title> are passed as-is, so
 * any [sb_year] or [sb_date] in them stays as literal text.
 *
 * We gate on strpos($str, '[sb_') so this filter is a no-op for the 99% of
 * titles that don't contain our shortcodes — avoids any surprise interaction
 * with plain-text titles that happen to have square brackets.
 */
if (!function_exists('sb_apply_shortcodes_to_title')) {
    function sb_apply_shortcodes_to_title($text) {
        if (!is_string($text) || strpos($text, '[sb_') === false) return $text;
        return do_shortcode($text);
    }
    // Post title as rendered by the_title() / get_the_title() — covers page
    // headings, breadcrumbs, sitemap, similar-posts, articles-grid cards, and
    // the wp-admin post list.
    add_filter('the_title', 'sb_apply_shortcodes_to_title', 10);
    // Menu item labels (nav menus assembled by wp_nav_menu()).
    add_filter('nav_menu_item_title', 'sb_apply_shortcodes_to_title', 10);
    // The document <title> tag emitted by wp_head().
    add_filter('document_title_parts', function ($parts) {
        foreach ($parts as $k => $v) {
            $parts[$k] = sb_apply_shortcodes_to_title($v);
        }
        return $parts;
    }, 10);
    add_filter('wp_title', 'sb_apply_shortcodes_to_title', 10);
    // Post excerpt (used in cards / OG description if no custom SEO desc).
    add_filter('the_excerpt', 'sb_apply_shortcodes_to_title', 10);
    add_filter('get_the_excerpt', 'sb_apply_shortcodes_to_title', 10);
    // Widget titles (in case admins put a shortcode in a widget heading).
    add_filter('widget_title', 'sb_apply_shortcodes_to_title', 10);
}
