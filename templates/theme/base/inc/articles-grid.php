<?php
/**
 * Site Builder — articles grid for pages with the [A] flag.
 *
 * Pages with the [A] flag in their FSR folder name receive fsr_articles_grid=1
 * post meta. This module hooks the_content() and appends a paginated grid of
 * the page's direct children: each card has thumbnail + title + short excerpt
 * + link. Pagination uses a "?gp=N" query parameter (g = grid) which doesn't
 * collide with WordPress's own paged/page query vars on singular pages.
 *
 * Per-page count and basic styling are baked in. To customise either, edit
 * this file post-generation (but it'll be overwritten on next theme regen).
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_articles_grid_append')) {

    /**
     * Number of cards per page. 12 fits a 3-column grid two-rows-tall on desktop
     * and 2x6 on tablet — covers most realistic child-count scenarios without
     * causing a long initial scroll.
     */
    define('SB_ARTICLES_GRID_PER_PAGE', 12);

    function sb_articles_grid_append($content) {
        if (!is_singular() || is_admin() || !is_main_query()) return $content;
        $post_id = get_the_ID();
        if (!$post_id) return $content;

        // Only on pages flagged [A]. Note: [U] does NOT skip this — the canonical
        // example is the "articles" hub page itself, which carries both [U] and [A]
        // ([U] keeps GEO shortcodes and related-posts off, [A] turns on the cards
        // grid). Treating [U] as overriding [A] would hide the cards on exactly
        // the page that's supposed to display them.
        if ((int)get_post_meta($post_id, 'fsr_articles_grid', true) !== 1) return $content;

        $per_page = SB_ARTICLES_GRID_PER_PAGE;
        $current_page = isset($_GET['gp']) ? max(1, (int)$_GET['gp']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Fetch current page's children. Order by menu_order primarily (so the
        // FSR order flag [Nm...] is respected if it sets menu_order), then title.
        // The order flag IS [<n>M...] for menus but pages also get menu_order set
        // from the same value, so this respects user intent for both surfaces.
        $children = get_posts([
            'post_type'        => 'page',
            'post_parent'      => $post_id,
            'posts_per_page'   => $per_page,
            'offset'           => $offset,
            'orderby'          => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'post_status'      => 'publish',
            'suppress_filters' => false,
        ]);

        if (empty($children)) return $content;

        // Get total count for pagination (separate query so we don't fetch every
        // post — use 'fields' => 'ids' for cheapness)
        $all_ids = get_posts([
            'post_type'        => 'page',
            'post_parent'      => $post_id,
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'post_status'      => 'publish',
            'suppress_filters' => false,
        ]);
        $total = count($all_ids);
        $total_pages = max(1, (int)ceil($total / $per_page));

        // Build the HTML
        $heading = function_exists('sb_t') ? sb_t('articles') : 'Articles';
        $out  = "\n<section class=\"sb-articles-grid\" aria-label=\"" . esc_attr($heading) . "\">\n";
        $out .= '  <h2 class="sb-articles-grid-heading">' . esc_html($heading) . "</h2>\n";
        $out .= '  <div class="sb-articles-grid-items">' . "\n";
        foreach ($children as $child) {
            $out .= sb_articles_grid_card($child);
        }
        $out .= "  </div>\n";

        // Pagination only if needed
        if ($total_pages > 1) {
            $out .= sb_articles_grid_pagination($current_page, $total_pages);
        }
        $out .= "</section>\n";

        return $content . $out;
    }

    /**
     * Render a single article card.
     */
    function sb_articles_grid_card($post) {
        $link    = get_permalink($post);
        $title   = get_the_title($post);
        $thumb   = '';
        if (has_post_thumbnail($post->ID)) {
            $thumb = get_the_post_thumbnail($post->ID, 'medium', [
                'class' => 'sb-article-card-thumb-img',
                'alt'   => esc_attr($title),
            ]);
        }
        // Manual excerpt if set, otherwise auto-trimmed from content
        $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 22, '…');

        $out  = '    <article class="sb-article-card">' . "\n";
        $out .= '      <a class="sb-article-card-link" href="' . esc_url($link) . '">' . "\n";
        if ($thumb !== '') {
            $out .= '        <div class="sb-article-card-thumb">' . $thumb . "</div>\n";
        }
        $out .= '        <div class="sb-article-card-body">' . "\n";
        $out .= '          <h3 class="sb-article-card-title">' . esc_html($title) . "</h3>\n";
        if ($excerpt !== '') {
            $out .= '          <p class="sb-article-card-excerpt">' . esc_html($excerpt) . "</p>\n";
        }
        $out .= "        </div>\n";
        $out .= "      </a>\n";
        $out .= "    </article>\n";
        return $out;
    }

    /**
     * Render pagination as Previous / Page X of Y / Next.
     * Uses the ?gp=N query parameter on the current URL.
     */
    function sb_articles_grid_pagination($current, $total) {
        $base = get_permalink();
        // Always strip an existing gp param before re-appending the new one
        $base = remove_query_arg('gp', $base);

        $prev_label = function_exists('sb_t') ? sb_t('previous') : 'Previous';
        $next_label = function_exists('sb_t') ? sb_t('next')     : 'Next';
        $pos_template = function_exists('sb_t') ? sb_t('page_of') : 'Page %1$d of %2$d';
        $position = sprintf($pos_template, $current, $total);

        $out  = '  <nav class="sb-articles-grid-pagination" aria-label="' . esc_attr($pos_template) . "\">\n";
        if ($current > 1) {
            $prev_url = ($current - 1 === 1) ? $base : add_query_arg('gp', $current - 1, $base);
            $out .= '    <a class="sb-pagination-prev" href="' . esc_url($prev_url) . '" rel="prev">‹ ' . esc_html($prev_label) . "</a>\n";
        } else {
            $out .= '    <span class="sb-pagination-prev sb-pagination-disabled" aria-hidden="true">‹ ' . esc_html($prev_label) . "</span>\n";
        }
        $out .= '    <span class="sb-pagination-position">' . esc_html($position) . "</span>\n";
        if ($current < $total) {
            $next_url = add_query_arg('gp', $current + 1, $base);
            $out .= '    <a class="sb-pagination-next" href="' . esc_url($next_url) . '" rel="next">' . esc_html($next_label) . " ›</a>\n";
        } else {
            $out .= '    <span class="sb-pagination-next sb-pagination-disabled" aria-hidden="true">' . esc_html($next_label) . " ›</span>\n";
        }
        $out .= "  </nav>\n";
        return $out;
    }

    /**
     * Inline CSS for the grid. Loaded once via wp_enqueue_scripts.
     */
    function sb_articles_grid_styles() {
        if (!is_singular()) return;
        $post_id = get_the_ID();
        if (!$post_id || (int)get_post_meta($post_id, 'fsr_articles_grid', true) !== 1) return;

        wp_register_style('sb-articles-grid', false);
        wp_enqueue_style('sb-articles-grid');

        $css = '
            .sb-articles-grid { margin: 48px 0 24px; }
            .sb-articles-grid-heading { margin: 0 0 24px; font-size: 1.6em; font-weight: 700; letter-spacing: -0.01em; }
            .sb-articles-grid-items { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
            .sb-article-card {
                background: var(--sb-color-bg-alt, #f8f9fa);
                border: 1px solid var(--sb-color-border, #e5e7eb);
                border-radius: 8px;
                overflow: hidden;
                transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            }
            .sb-article-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,0.08); border-color: var(--sb-color-link, #1d4ed8); }
            .sb-article-card-link { display: block; color: inherit; text-decoration: none; height: 100%; }
            .sb-article-card-link:hover { text-decoration: none; }
            .sb-article-card-thumb { aspect-ratio: 16 / 9; background: var(--sb-color-border, #e5e7eb); overflow: hidden; }
            .sb-article-card-thumb-img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .sb-article-card-body { padding: 16px 18px; }
            .sb-article-card-title { margin: 0 0 8px; font-size: 1.05em; font-weight: 600; line-height: 1.35; color: var(--sb-color-text); }
            .sb-article-card-excerpt { margin: 0; color: var(--sb-color-muted, #6b7280); font-size: 0.88em; line-height: 1.5; }
            .sb-articles-grid-pagination { display: flex; gap: 16px; align-items: center; justify-content: center; margin: 32px 0 0; flex-wrap: wrap; }
            .sb-articles-grid-pagination a, .sb-articles-grid-pagination span { font-size: 0.92em; padding: 8px 14px; border-radius: 6px; }
            .sb-articles-grid-pagination a { color: var(--sb-color-link, #1d4ed8); text-decoration: none; border: 1px solid var(--sb-color-border, #e5e7eb); }
            .sb-articles-grid-pagination a:hover { background: var(--sb-color-bg-alt, #f3f4f6); border-color: var(--sb-color-link, #1d4ed8); }
            .sb-articles-grid-pagination .sb-pagination-position { font-weight: 500; color: var(--sb-color-muted, #6b7280); }
            .sb-articles-grid-pagination .sb-pagination-disabled { color: var(--sb-color-muted, #9ca3af); border: 1px solid transparent; opacity: 0.5; }
            @media (max-width: 800px) { .sb-articles-grid-items { grid-template-columns: repeat(2, 1fr); gap: 16px; } }
            @media (max-width: 500px) { .sb-articles-grid-items { grid-template-columns: 1fr; } }
        ';
        wp_add_inline_style('sb-articles-grid', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_articles_grid_styles');

    // Priority 11 — runs AFTER WordPress default content filters (10) but
    // BEFORE GEO injection (12) doesn't matter here since we append to the
    // end. We want the grid to appear before similar-post if both fired,
    // but similar-post bails out on [A] pages anyway (see similar-post.php).
    add_filter('the_content', 'sb_articles_grid_append', 11);
}
