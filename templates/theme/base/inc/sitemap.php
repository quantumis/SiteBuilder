<?php
/**
 * Site Builder — HTML sitemap page.
 *
 * Registers the [sb_sitemap] shortcode. Rendered on the auto-created
 * "Sitemap" page (which contains just [sb_sitemap] in its post_content),
 * or wherever else the user inserts it.
 *
 * What it produces: a nested <ul> of all published pages, ordered by
 * menu_order then title, grouped hierarchically by post_parent.
 *
 * What it excludes:
 *   - The sitemap page itself (self-reference is pointless)
 *   - Pages marked fsr_utility that are also fsr_no_index (rare — the archive
 *     really doesn't want them findable). Regular utility pages ARE included
 *     because users often want to find "privacy policy" via the sitemap.
 *
 * Distinct from wp-sitemap.xml — that's the XML sitemap for search engines
 * emitted by WordPress core. This is the human-readable HTML sitemap.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_sitemap_shortcode')) {

    function sb_sitemap_shortcode($atts = []) {
        // Collect all published pages once — we'll build the tree in-memory
        // to avoid N+1 get_posts() calls for children of each node.
        $all = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
        ]);
        if (empty($all)) return '';

        // Group by post_parent for O(1) child lookup
        $by_parent = [];
        foreach ($all as $p) {
            // Skip the sitemap page itself — no self-reference
            if ((int)get_post_meta($p->ID, 'fsr_is_sitemap', true) === 1) continue;
            // Skip pages marked both utility AND no-index — the archive clearly wants
            // them not discoverable. Utility pages that are indexable stay in.
            if ((int)get_post_meta($p->ID, 'fsr_utility',  true) === 1
             && (int)get_post_meta($p->ID, 'fsr_no_index', true) === 1) continue;
            $parent_id = (int)$p->post_parent;
            $by_parent[$parent_id][] = $p;
        }

        $out  = '<div class="sb-sitemap">' . "\n";
        $out .= sb_sitemap_render_level($by_parent, 0);
        $out .= "</div>\n";
        return $out;
    }
    add_shortcode('sb_sitemap', 'sb_sitemap_shortcode');

    /**
     * Recursively render one level of the tree as <ul><li>...</li></ul>.
     * Called with parent_id=0 for the top level.
     */
    function sb_sitemap_render_level($by_parent, $parent_id) {
        if (empty($by_parent[$parent_id])) return '';
        $out = '<ul class="sb-sitemap-list">' . "\n";
        foreach ($by_parent[$parent_id] as $page) {
            $out .= '  <li class="sb-sitemap-item">';
            $out .= '<a href="' . esc_url(get_permalink($page)) . '">' . esc_html(get_the_title($page)) . '</a>';
            $out .= sb_sitemap_render_level($by_parent, $page->ID);
            $out .= "</li>\n";
        }
        $out .= "</ul>\n";
        return $out;
    }

    function sb_sitemap_styles() {
        if (!is_singular()) return;
        // Only load on the sitemap page (self-hosted or wherever [sb_sitemap] is used).
        $post_id = get_the_ID();
        if (!$post_id) return;
        $is_sitemap_page = (int)get_post_meta($post_id, 'fsr_is_sitemap', true) === 1;
        // Also load if the post content contains the shortcode — user might paste it elsewhere.
        $has_shortcode = has_shortcode((string)get_post_field('post_content', $post_id), 'sb_sitemap');
        if (!$is_sitemap_page && !$has_shortcode) return;

        wp_register_style('sb-sitemap', false);
        wp_enqueue_style('sb-sitemap');
        $css = '
            .sb-sitemap { margin: 20px 0; }
            .sb-sitemap-list { list-style: none; margin: 0; padding: 0; }
            .sb-sitemap-list .sb-sitemap-list { margin-left: 24px; padding-left: 16px; border-left: 2px solid var(--sb-color-border, #e5e7eb); margin-top: 6px; }
            .sb-sitemap-item { padding: 6px 0; line-height: 1.5; }
            .sb-sitemap-item > a { color: var(--sb-color-link, #1d4ed8); text-decoration: none; font-weight: 500; }
            .sb-sitemap-item > a:hover { text-decoration: underline; }
            .sb-sitemap-list .sb-sitemap-list .sb-sitemap-item > a { font-weight: 400; color: var(--sb-color-text, #111); }
        ';
        wp_add_inline_style('sb-sitemap', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_sitemap_styles');
}
