<?php
/**
 * Site Builder — breadcrumbs.
 *
 * Two entry points:
 *   - get_my_breadcrumbs_items()  — array of ['name', 'url'] for JSON-LD
 *                                    (seo.php picks it up if defined)
 *   - sb_breadcrumbs_html()       — rendered HTML for embedding into templates
 *
 * The theme templates (page.php) call sb_breadcrumbs_html() BEFORE the_title()
 * so breadcrumbs appear above the h1 — that's the expected visual order.
 *
 * Runtime toggle: the option site_builder_theme_module_options['show_breadcrumbs']
 * (default: true) controls whether breadcrumbs render at all. When false,
 * sb_breadcrumbs_html() returns an empty string. Changed instantly via the
 * checkbox on the plugin's Theme tab — no theme regeneration needed.
 *
 * Skipped on front page (the home crumb would point at itself).
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_breadcrumbs_enabled')) {
    function sb_breadcrumbs_enabled() {
        $opts = get_option('site_builder_theme_module_options', []);
        if (!is_array($opts)) return true;
        // Default is ON — only explicit false disables
        return !array_key_exists('show_breadcrumbs', $opts) || !empty($opts['show_breadcrumbs']);
    }
}

if (!function_exists('get_my_breadcrumbs_items')) {
    /**
     * Return breadcrumb items as a list of [name, url] for the current request.
     * The expected order is: Home → ancestors (oldest first) → current page.
     * Empty array on front/home pages where breadcrumbs don't apply.
     *
     * The function name matches what mod-seo.php from the parallel team's
     * codebase expects — so if their SEO plugin is ever loaded alongside this
     * theme, it'll pick up our breadcrumbs automatically without integration.
     */
    function get_my_breadcrumbs_items() {
        $items = [];
        if (is_front_page() || is_home()) return $items;

        $home_label = function_exists('sb_t') ? sb_t('home') : 'Home';
        $items[] = ['name' => $home_label, 'url' => home_url('/')];

        if (is_singular()) {
            $post_id = get_the_ID();
            if (!$post_id) return $items;
            // Ancestors come back deepest-first; reverse for breadcrumb order.
            $ancestors = array_reverse(get_post_ancestors($post_id));
            foreach ($ancestors as $aid) {
                $items[] = ['name' => get_the_title($aid), 'url' => get_permalink($aid)];
            }
            $items[] = ['name' => get_the_title($post_id), 'url' => get_permalink($post_id)];
        }
        return $items;
    }
}

if (!function_exists('sb_breadcrumbs_html')) {
    /**
     * Return the breadcrumbs HTML (or empty string if they shouldn't render).
     * Called explicitly by templates BEFORE the h1 — that's why we don't
     * hook the_content: we want position control.
     *
     * Guaranteed to return empty on:
     *   - Front page
     *   - Non-singular views (archives, search, etc)
     *   - When show_breadcrumbs is disabled
     *   - When breadcrumb list has fewer than 2 items (would be just "Home")
     */
    function sb_breadcrumbs_html() {
        if (!is_singular() || is_admin() || is_front_page()) return '';
        if (!sb_breadcrumbs_enabled()) return '';

        $items = get_my_breadcrumbs_items();
        if (count($items) < 2) return '';

        $html  = "\n<nav class=\"sb-breadcrumbs\" aria-label=\"Breadcrumbs\">\n";
        $html .= "  <ol class=\"sb-breadcrumbs-list\">\n";
        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            if ($i === $last) {
                $html .= '    <li class="sb-breadcrumb-item sb-breadcrumb-current" aria-current="page">' . esc_html($item['name']) . "</li>\n";
            } else {
                $html .= '    <li class="sb-breadcrumb-item"><a href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . "</a></li>\n";
            }
        }
        $html .= "  </ol>\n</nav>\n";
        return $html;
    }
}

if (!function_exists('sb_breadcrumbs_styles')) {
    function sb_breadcrumbs_styles() {
        if (!is_singular() || is_front_page()) return;
        if (!sb_breadcrumbs_enabled()) return;

        wp_register_style('sb-breadcrumbs', false);
        wp_enqueue_style('sb-breadcrumbs');
        $css = '
            .sb-breadcrumbs { margin: 0 0 20px; font-size: 0.85em; }
            .sb-breadcrumbs-list { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; }
            .sb-breadcrumb-item { display: inline-flex; align-items: center; gap: 8px; color: var(--sb-color-muted, #6b7280); }
            .sb-breadcrumb-item:not(:last-child)::after { content: "›"; opacity: 0.45; }
            .sb-breadcrumb-item a { color: var(--sb-color-muted, #6b7280); text-decoration: none; }
            .sb-breadcrumb-item a:hover { color: var(--sb-color-link, #1d4ed8); text-decoration: underline; }
            .sb-breadcrumb-current { color: var(--sb-color-text); font-weight: 500; }
        ';
        wp_add_inline_style('sb-breadcrumbs', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_breadcrumbs_styles');
}
