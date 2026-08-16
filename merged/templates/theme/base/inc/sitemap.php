<?php
/**
 * Site Builder — HTML sitemap page (accordion style).
 *
 * Registers the [sb_sitemap] shortcode. Rendered on the auto-created
 * "Sitemap" page, or wherever else the user inserts the shortcode.
 *
 * Layout: top-level pages become collapsible sections (native <details>/
 * <summary>, no JavaScript). Clicking the header expands the section to
 * reveal a link to the section page itself plus its children (recursively).
 * Top-level pages without any children are rendered as a simple link,
 * no collapse toggle.
 *
 * Excluded: the sitemap page itself, and pages marked both fsr_utility=1
 * AND fsr_no_index=1 (archive clearly wants them off the human sitemap).
 * Utility pages that ARE indexable stay in (users often look for
 * "privacy policy" here).
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_sitemap_shortcode')) {

    function sb_sitemap_shortcode($atts = []) {
        // Fetch all published pages once — we'll group by parent in-memory
        // to avoid an N+1 query problem walking the tree.
        $all = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
        ]);
        if (empty($all)) return '';

        $by_parent = [];
        foreach ($all as $p) {
            // Skip sitemap page itself
            if ((int)get_post_meta($p->ID, 'fsr_is_sitemap', true) === 1) continue;
            // Skip pages that are both utility AND no-index
            if ((int)get_post_meta($p->ID, 'fsr_utility',  true) === 1
             && (int)get_post_meta($p->ID, 'fsr_no_index', true) === 1) continue;
            $by_parent[(int)$p->post_parent][] = $p;
        }

        $top_level = $by_parent[0] ?? [];
        if (empty($top_level)) return '';

        $out  = '<div class="sb-sitemap">' . "\n";
        foreach ($top_level as $page) {
            $out .= sb_sitemap_render_section($page, $by_parent, 0);
        }
        $out .= "</div>\n";
        return $out;
    }
    add_shortcode('sb_sitemap', 'sb_sitemap_shortcode');

    /**
     * Render one section. Depth 0 = top-level (collapsible card), deeper =
     * nested subsections (also collapsible but visually less prominent).
     * If the page has no children, render as a simple leaf link — no
     * disclosure widget clutter for empty accordions.
     */
    function sb_sitemap_render_section($page, $by_parent, $depth = 0) {
        $children = $by_parent[$page->ID] ?? [];
        $has_children = !empty($children);
        $url    = esc_url(get_permalink($page));
        $title  = esc_html(get_the_title($page));
        $count  = count($children);
        $indent = 'depth-' . min(3, $depth); // cap CSS classes at depth-3

        if (!$has_children) {
            // Leaf — just a link, no accordion
            return '<a class="sb-sitemap-leaf ' . $indent . '" href="' . $url . '">'
                 . '<span class="sb-sitemap-leaf-icon" aria-hidden="true">•</span>'
                 . '<span class="sb-sitemap-leaf-title">' . $title . '</span>'
                 . "</a>\n";
        }

        // Section with children — collapsible
        $out  = '<details class="sb-sitemap-section ' . $indent . '">' . "\n";
        $out .= '  <summary class="sb-sitemap-section-header">' . "\n";
        $out .= '    <span class="sb-sitemap-chevron" aria-hidden="true"></span>' . "\n";
        $out .= '    <span class="sb-sitemap-section-title">' . $title . '</span>' . "\n";
        $out .= '    <span class="sb-sitemap-section-count">' . $count . '</span>' . "\n";
        $out .= "  </summary>\n";
        $out .= '  <div class="sb-sitemap-section-body">' . "\n";
        // Link to the section page itself (parent page has content too)
        $overview_label = function_exists('sb_t') ? sb_t('section_overview') : 'Section overview';
        $out .= '    <a class="sb-sitemap-parent-link" href="' . $url . '">'
              . esc_html($overview_label) . ' — ' . $title . "</a>\n";
        // Children
        foreach ($children as $child) {
            $out .= sb_sitemap_render_section($child, $by_parent, $depth + 1);
        }
        $out .= "  </div>\n";
        $out .= "</details>\n";
        return $out;
    }

    function sb_sitemap_styles() {
        if (!is_singular()) return;
        $post_id = get_the_ID();
        if (!$post_id) return;
        $is_sitemap_page = (int)get_post_meta($post_id, 'fsr_is_sitemap', true) === 1;
        $has_shortcode = has_shortcode((string)get_post_field('post_content', $post_id), 'sb_sitemap');
        if (!$is_sitemap_page && !$has_shortcode) return;

        wp_register_style('sb-sitemap', false);
        wp_enqueue_style('sb-sitemap');
        $css = '
            /* Container */
            .sb-sitemap {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin: 24px 0;
            }

            /* Top-level section card */
            .sb-sitemap-section {
                background: var(--sb-color-bg-alt, #f9fafb);
                border: 1px solid var(--sb-color-border, #e5e7eb);
                border-radius: 10px;
                overflow: hidden;
                transition: box-shadow 0.2s, border-color 0.2s;
            }
            .sb-sitemap-section:hover {
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            }
            .sb-sitemap-section[open] {
                border-color: var(--sb-color-link, #1d4ed8);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            }

            /* Header */
            .sb-sitemap-section-header {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 18px;
                cursor: pointer;
                user-select: none;
                list-style: none;
                font-weight: 600;
                color: var(--sb-color-text, #111);
                transition: background 0.15s;
            }
            .sb-sitemap-section-header::-webkit-details-marker { display: none; }
            .sb-sitemap-section-header::marker { content: ""; }
            .sb-sitemap-section-header:hover { background: rgba(0, 0, 0, 0.02); }

            /* Chevron */
            .sb-sitemap-chevron {
                width: 10px;
                height: 10px;
                border-right: 2px solid var(--sb-color-muted, #6b7280);
                border-bottom: 2px solid var(--sb-color-muted, #6b7280);
                transform: rotate(-45deg);
                transition: transform 0.2s;
                flex-shrink: 0;
                margin-left: 4px;
            }
            .sb-sitemap-section[open] > .sb-sitemap-section-header .sb-sitemap-chevron {
                transform: rotate(45deg);
            }

            /* Title & count */
            .sb-sitemap-section-title {
                flex: 1;
                font-size: 1em;
                letter-spacing: -0.01em;
            }
            .sb-sitemap-section-count {
                background: rgba(0, 0, 0, 0.06);
                color: var(--sb-color-muted, #6b7280);
                padding: 2px 10px;
                border-radius: 999px;
                font-size: 0.8em;
                font-weight: 500;
                min-width: 24px;
                text-align: center;
            }

            /* Body */
            .sb-sitemap-section-body {
                padding: 8px 18px 16px 40px;
                border-top: 1px solid var(--sb-color-border, #e5e7eb);
                background: var(--sb-color-bg, #fff);
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            /* Parent-page link ("Section overview") */
            .sb-sitemap-parent-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 10px;
                margin: 4px 0 8px -10px;
                border-radius: 6px;
                color: var(--sb-color-link, #1d4ed8);
                text-decoration: none;
                font-weight: 500;
                font-size: 0.9em;
                background: rgba(29, 78, 216, 0.05);
                transition: background 0.15s;
            }
            .sb-sitemap-parent-link:hover {
                background: rgba(29, 78, 216, 0.1);
                text-decoration: none;
            }

            /* Leaf links (no children) */
            .sb-sitemap-leaf {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 18px;
                background: var(--sb-color-bg-alt, #f9fafb);
                border: 1px solid var(--sb-color-border, #e5e7eb);
                border-radius: 10px;
                color: var(--sb-color-text, #111);
                text-decoration: none;
                font-weight: 600;
                transition: background 0.15s, border-color 0.15s, transform 0.15s;
            }
            .sb-sitemap-leaf:hover {
                background: var(--sb-color-bg, #fff);
                border-color: var(--sb-color-link, #1d4ed8);
                transform: translateX(2px);
                text-decoration: none;
            }
            .sb-sitemap-leaf-icon {
                color: var(--sb-color-muted, #6b7280);
                font-size: 1.2em;
            }

            /* Nested sections/leaves inside the body: strip the card look,
               inherit the parent card. */
            .sb-sitemap-section-body .sb-sitemap-section,
            .sb-sitemap-section-body .sb-sitemap-leaf {
                background: transparent;
                border: none;
                border-radius: 6px;
                padding: 0;
                margin: 2px 0;
            }
            .sb-sitemap-section-body .sb-sitemap-section:hover,
            .sb-sitemap-section-body .sb-sitemap-section[open] {
                box-shadow: none;
            }
            .sb-sitemap-section-body .sb-sitemap-section-header {
                padding: 8px 10px;
                font-size: 0.92em;
                font-weight: 500;
                border-radius: 6px;
            }
            .sb-sitemap-section-body .sb-sitemap-section-body {
                border-top: none;
                background: transparent;
                padding: 4px 0 4px 24px;
            }
            .sb-sitemap-section-body .sb-sitemap-leaf {
                padding: 6px 10px;
                font-weight: 400;
                color: var(--sb-color-muted, #6b7280);
            }
            .sb-sitemap-section-body .sb-sitemap-leaf:hover {
                color: var(--sb-color-link, #1d4ed8);
                background: rgba(0, 0, 0, 0.03);
            }

            /* Mobile — reduce paddings */
            @media (max-width: 600px) {
                .sb-sitemap-section-header { padding: 12px 14px; }
                .sb-sitemap-section-body { padding: 8px 14px 12px 30px; }
                .sb-sitemap-leaf { padding: 12px 14px; }
                .sb-sitemap-section-count { display: none; }
            }
        ';
        wp_add_inline_style('sb-sitemap', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_sitemap_styles');
}
