<?php
/**
 * Site Builder — content authorship signature.
 *
 * Appends a small "Content created by the {site name} team" line at the end
 * of each page's content. The line is:
 *   - Localized via sb_t('content_by_team') across all 36 supported locales
 *   - Uses the site's real name from get_bloginfo('name')
 *   - Wrapped in a <p class="sb-content-signature"> with muted styling and
 *     a subtle top border so it clearly reads as ancillary, not part of the
 *     article body
 *
 * Where it is NOT appended:
 *   - Articles-grid pages (fsr_articles_grid = 1) — those are card grids
 *     without editorial content, a "created by team" line makes no sense
 *   - Non-singular views (archives, search, 404) — nothing to sign there
 *   - Admin screens
 *   - When the site name is empty (rare, but bloginfo can be empty during
 *     early setup — better to skip than emit "Content by the team")
 *
 * Filter priority is 100 — after all other content filters (external-links=20,
 * geo-shortcodes=12, toc=13, articles-grid=11, link-resolver=9) so nothing
 * touches the signature markup after it's inserted. Nothing in the block
 * needs shortcode processing, external link rewriting, or heading extraction
 * — it's plain-text authorship metadata.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_content_signature_append')) {
    function sb_content_signature_append($content) {
        if (is_admin() || !is_singular() || !is_main_query()) return $content;

        $post_id = get_the_ID();
        if (!$post_id) return $content;

        // Skip articles-grid pages — they're not editorial content
        if ((int)get_post_meta($post_id, 'fsr_articles_grid', true) === 1) return $content;

        $site_name = trim((string)get_bloginfo('name'));
        if ($site_name === '') return $content;

        // Idempotency: don't double-append if the filter somehow runs twice
        // on the same content (e.g. do_shortcode processed content re-entering
        // the filter chain).
        if (strpos($content, 'class="sb-content-signature"') !== false) return $content;

        // Wrap the site name in a link to the About-Us page (any page tagged
        // with [W], i.e. fsr_about=1). Fall back to plain text if no such
        // page exists. sb_content_signature_get_about_url() caches the
        // lookup so this doesn't cost a query on every request.
        $about_url = sb_content_signature_get_about_url();
        if ($about_url !== '') {
            $quoted_site = '«<a href="' . esc_url($about_url) . '">' . esc_html($site_name) . '</a>»';
        } else {
            $quoted_site = '«' . esc_html($site_name) . '»';
        }
        $template = function_exists('sb_t') ? sb_t('content_by_team') : 'Content created by the %s team';
        $line     = sprintf($template, $quoted_site);

        $signature = "\n<p class=\"sb-content-signature\">" . $line . "</p>\n";
        return $content . $signature;
    }
    add_filter('the_content', 'sb_content_signature_append', 100);
}

if (!function_exists('sb_content_signature_get_about_url')) {
    /**
     * Return the permalink of the "About Us" page (the one tagged with [W] /
     * fsr_about=1) or empty string if there is none. Result is cached in a
     * transient for 24 hours and in a static var for the request; the
     * transient is invalidated whenever any page is saved or deleted so a
     * newly-created or repurposed About page shows up right away.
     */
    function sb_content_signature_get_about_url() {
        static $cached = null;
        if ($cached !== null) return $cached;

        $key = 'sb_about_url_v1';
        $url = get_transient($key);
        if ($url !== false) {
            return $cached = (string)$url;
        }

        $about = get_posts([
            'post_type'        => 'page',
            'meta_key'         => 'fsr_about',
            'meta_value'       => '1',
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'post_status'      => 'publish',
            'suppress_filters' => true,
            'no_found_rows'    => true,
        ]);
        $url = !empty($about) ? (string)get_permalink($about[0]) : '';

        set_transient($key, $url, DAY_IN_SECONDS);
        return $cached = $url;
    }

    // Invalidate the About-URL transient when any page changes state — a
    // save/delete could add, remove, or change the About page. Filtered to
    // 'page' post type so nav_menu_item, revisions, etc. don't invalidate.
    add_action('save_post_page', function () {
        delete_transient('sb_about_url_v1');
    });
    add_action('deleted_post', function ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'page') {
            delete_transient('sb_about_url_v1');
        }
    });
    add_action('trashed_post', function ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'page') {
            delete_transient('sb_about_url_v1');
        }
    });
}

if (!function_exists('sb_content_signature_styles')) {
    function sb_content_signature_styles() {
        if (is_admin()) return;
        wp_register_style('sb-content-signature', false);
        wp_enqueue_style('sb-content-signature');
        $css = <<<CSS
            .sb-content-signature {
                margin-top: 40px !important;
                padding-top: 20px;
                border-top: 1px solid var(--sb-color-border, #e5e7eb);
                font-size: 0.88em;
                color: var(--sb-color-muted, #6b7280);
                font-style: italic;
                text-align: center;
                letter-spacing: 0.01em;
            }
            /* The site name inside the signature is wrapped in <a href="…">
               pointing at the About page (fsr_about=1). Style it to blend
               with the italic muted line — a subtle underline signals it's
               clickable without breaking the ancillary tone of the block. */
            .sb-content-signature a {
                color: inherit;
                font-style: inherit;
                text-decoration: underline;
                text-decoration-color: var(--sb-color-border, #d1d5db);
                text-decoration-thickness: 1px;
                text-underline-offset: 3px;
                transition: color 0.15s, text-decoration-color 0.15s;
            }
            .sb-content-signature a:hover {
                color: var(--sb-color-text, #111);
                text-decoration-color: currentColor;
            }
CSS;
        wp_add_inline_style('sb-content-signature', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_content_signature_styles');
}
