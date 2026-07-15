<?php
/**
 * Site Builder — automatic table of contents.
 *
 * Extracts h2/h3 headings from the post content and inserts a rendered
 * "Table of contents" block. Each heading gets a stable ID (slugified from
 * its text) so the TOC links become in-page anchors.
 *
 * Where the block is inserted:
 *   1. Preferred: right after the <div class="sb-geo-shortcodes"> injection
 *      block (which sb_geo_inject_shortcodes places after </h1>). That
 *      puts the TOC after the intro shortcodes [sports_predictions] and
 *      [geo_info], right before the main article content.
 *   2. Fallback: right after </h1> if the geo-shortcodes block wasn't
 *      present on this page (e.g. no shortcodes registered).
 *   3. Fallback of fallback: at the very start of content if there's no h1.
 *
 * Where the block is NOT inserted:
 *   - Utility pages (fsr_utility = 1) — privacy/legal/cookies don't need TOCs
 *   - Articles-grid pages (fsr_articles_grid = 1) — grids of cards, not articles
 *   - Front page (either is_front_page() or fsr_front_page = 1)
 *   - Pages with fewer than 3 h2/h3 headings (a TOC of 1-2 items is noise)
 *   - Any singular that hits the individual opt-out meta (_sb_hide_toc = 1)
 *
 * Filter priority is 13 — after geo-shortcodes (12) so we see its injected
 * block, before external-links (20) so link processing runs on the anchor
 * links we insert.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_toc_should_render')) {
    /**
     * Guard: return true when the current singular deserves a TOC.
     */
    function sb_toc_should_render($post_id) {
        if (is_admin() || !is_main_query()) return false;
        // Category flags mapped from FSR folder markers:
        //   [U] → fsr_utility        (privacy, cookies, legal — no TOC needed)
        //   [A] → fsr_articles_grid  (grid of cards, not a readable article)
        //
        // NOT excluded:
        //   - Front page (is_front_page()) — front page may have long content
        //     with h2/h3 that deserves a TOC
        //   - [W] / fsr_about — this is just a semantic marker for "About Us"
        //     pages (usually combined with [U]). If it's utility, the fsr_utility
        //     check above handles it; if it's not, it's a regular content page
        //     that deserves a TOC like any other.
        if ((int)get_post_meta($post_id, 'fsr_utility', true)        === 1) return false;
        if ((int)get_post_meta($post_id, 'fsr_articles_grid', true)  === 1) return false;
        // Individual per-page opt-out (an editor can hide TOC on a specific page)
        if ((int)get_post_meta($post_id, '_sb_hide_toc', true)       === 1) return false;
        return true;
    }
}

if (!function_exists('sb_toc_slugify')) {
    /**
     * Turn heading text into an anchor slug. Loose ASCII fallback for non-Latin
     * scripts (Cyrillic, Chinese, Arabic) that transliterate poorly — in those
     * cases we fall back to "section-1", "section-2"… based on the counter.
     */
    function sb_toc_slugify($text, $counter = 0) {
        $slug = sanitize_title($text);
        if ($slug === '' || $slug === '-') {
            // sanitize_title drops non-Latin — use counter fallback
            return 'section-' . $counter;
        }
        return $slug;
    }
}

if (!function_exists('sb_toc_extract_and_inject')) {
    /**
     * The_content filter callback. Extracts h2 and h3 tags, assigns anchor
     * IDs, builds the TOC HTML, and injects it into the content. Idempotent
     * (running twice on the same content produces the same output — no
     * duplicate IDs, no duplicate TOCs) because it looks for the sb-toc marker.
     */
    function sb_toc_extract_and_inject($content) {
        if (!is_singular()) return $content;
        $post_id = get_the_ID();
        if (!$post_id || !sb_toc_should_render($post_id)) return $content;
        if (strpos($content, 'class="sb-toc"') !== false) return $content;

        // Extract h2 and h3 tags (case-insensitive, tolerant of attributes)
        if (!preg_match_all('#<(h[23])(\s[^>]*)?>(.+?)</\1>#is', $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }
        if (count($matches) < 3) return $content; // too few headings — not worth a TOC

        // Assign anchor IDs, tracking which headings already had id="..." set
        $items = [];
        $used_slugs = [];
        $counter = 0;
        foreach ($matches as $m) {
            $counter++;
            $level = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            $inner = wp_strip_all_tags($m[3]);
            $inner_trim = trim($inner);
            if ($inner_trim === '') continue;

            // Extract existing id if any
            $anchor = '';
            if (preg_match('/\bid=(["\'])([^"\']+)\1/i', $attrs, $idm)) {
                $anchor = $idm[2];
            }
            if ($anchor === '') {
                $slug = sb_toc_slugify($inner_trim, $counter);
                // De-duplicate slugs: same text on the page → append -2, -3, …
                $unique = $slug;
                $n = 1;
                while (isset($used_slugs[$unique])) {
                    $n++;
                    $unique = $slug . '-' . $n;
                }
                $used_slugs[$unique] = true;
                $anchor = $unique;
            } else {
                $used_slugs[$anchor] = true;
            }

            $items[] = [
                'level'   => $level,
                'anchor'  => $anchor,
                'text'    => $inner_trim,
                'attrs'   => $attrs,
                'raw'     => $m[0],
            ];
        }
        if (count($items) < 3) return $content;

        // Inject anchor IDs into headings that didn't have them
        foreach ($items as $item) {
            $orig = $item['raw'];
            // If original tag already has id, leave it alone
            if (preg_match('/\bid=["\'][^"\']+["\']/i', $orig)) continue;
            $replacement = preg_replace(
                '#<(' . $item['level'] . ')(\s[^>]*)?>#i',
                '<$1$2 id="' . esc_attr($item['anchor']) . '">',
                $orig,
                1
            );
            // preg_replace with '$2' includes nothing if no attrs — fine
            $content = str_replace($orig, $replacement, $content);
        }

        // Build TOC HTML
        $toc_title = function_exists('sb_t') ? sb_t('toc') : 'Table of contents';
        $toc_html  = "\n<nav class=\"sb-toc\" aria-labelledby=\"sb-toc-heading\">\n";
        $toc_html .= '  <div id="sb-toc-heading" class="sb-toc-title">' . esc_html($toc_title) . "</div>\n";
        $toc_html .= "  <ol class=\"sb-toc-list\">\n";
        $prev_level = 'h2';
        $depth = 0;
        foreach ($items as $item) {
            if ($item['level'] === 'h3' && $prev_level === 'h2') {
                $toc_html .= "    <ol class=\"sb-toc-sublist\">\n";
                $depth++;
            } elseif ($item['level'] === 'h2' && $prev_level === 'h3') {
                $toc_html .= str_repeat("    </ol>\n", $depth);
                $depth = 0;
            }
            $toc_html .= '    <li class="sb-toc-item sb-toc-item-' . $item['level'] . '">'
                       . '<a href="#' . esc_attr($item['anchor']) . '">' . esc_html($item['text']) . '</a>'
                       . "</li>\n";
            $prev_level = $item['level'];
        }
        $toc_html .= str_repeat("    </ol>\n", $depth);
        $toc_html .= "  </ol>\n</nav>\n";

        // Placement: after the sb-geo-shortcodes marker comment → after </h1>
        // → at start. Using a marker rather than regex-matching </div> is
        // essential because the geo-shortcodes block contains nested <div>s
        // (prediction cards, geo info cards) — a naive regex would find one
        // of those inner </div>s and inject the TOC mid-shortcode.
        $marker = '<!--/sb-geo-shortcodes-->';
        $pos = strpos($content, $marker);
        if ($pos !== false) {
            $offset = $pos + strlen($marker);
            return substr($content, 0, $offset) . "\n" . $toc_html . substr($content, $offset);
        }
        if (preg_match('#</h1\s*>#i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $offset) . "\n" . $toc_html . substr($content, $offset);
        }
        return $toc_html . $content;
    }
    // Priority 13 — after geo-shortcodes (12) so its output is visible, and
    // after articles-grid (11) which we skip anyway via the fsr_articles_grid
    // guard. Before external-links (20) so anchor links get their target/rel
    // processing applied like any other <a>.
    add_filter('the_content', 'sb_toc_extract_and_inject', 13);
}

// Styles — inline via wp_add_inline_style attached to the theme stylesheet.
if (!function_exists('sb_toc_styles')) {
    function sb_toc_styles() {
        if (is_admin()) return;
        wp_register_style('sb-toc', false);
        wp_enqueue_style('sb-toc');
        $css = <<<CSS
            .sb-toc {
                margin: 24px 0 32px;
                padding: 20px 24px;
                background: var(--sb-color-bg-alt, #f9fafb);
                border: 1px solid var(--sb-color-border, #e5e7eb);
                border-radius: 8px;
                font-size: 0.95em;
            }
            .sb-toc-title {
                font-size: 0.8em;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--sb-color-muted, #6b7280);
                margin-bottom: 12px;
            }
            .sb-toc-list, .sb-toc-sublist {
                list-style: none;
                margin: 0;
                padding: 0;
                counter-reset: sb-toc-item;
            }
            .sb-toc-sublist {
                padding-left: 20px;
                margin-top: 4px;
                margin-bottom: 4px;
            }
            .sb-toc-item {
                margin: 6px 0;
                position: relative;
                padding-left: 22px;
            }
            .sb-toc-item::before {
                content: counter(sb-toc-item) ".";
                counter-increment: sb-toc-item;
                position: absolute;
                left: 0;
                top: 0;
                color: var(--sb-color-muted, #6b7280);
                font-variant-numeric: tabular-nums;
                font-weight: 600;
                font-size: 0.9em;
            }
            .sb-toc-item-h3 { padding-left: 22px; }
            .sb-toc-item-h3::before {
                color: var(--sb-color-muted, #6b7280);
                opacity: 0.7;
            }
            .sb-toc a {
                color: var(--sb-color-text, #111);
                text-decoration: none;
                border-bottom: 1px dotted transparent;
                transition: color 0.15s, border-color 0.15s;
            }
            .sb-toc a:hover {
                color: var(--sb-color-link, #2563eb);
                border-bottom-color: currentColor;
            }
            /* Smooth-scroll target — offset for sticky headers if any */
            html { scroll-behavior: smooth; }
            :target { scroll-margin-top: 80px; }
CSS;
        wp_add_inline_style('sb-toc', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_toc_styles');
}
