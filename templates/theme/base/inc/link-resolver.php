<?php
/**
 * Site Builder — internal link resolver.
 *
 * Handles the `$$LINK slug | text$$` inline shortcode inserted at import time
 * by the plugin's FSR importer (see FSR_Importer::convert_internal_links_to_shortcodes).
 *
 * At render time, we look up "slug" in a map of currently-published pages:
 *   - If found → render as <a href="…">text</a>
 *   - If not found → render as plain text
 *
 * This solves the DLY-scheduling problem: if page A links to page B but B is
 * scheduled for future publication, the link becomes plain text until B
 * publishes. When B goes live, the map cache invalidates via WordPress hooks
 * (save_post, future_to_publish, deleted_post, trashed_post, untrashed_post),
 * and on the next request the link starts working — no manual intervention
 * needed.
 *
 * The map is cached in a transient for 24 hours; typical sites have a few
 * hundred pages, so the map lookup is a single get_option() + one array
 * access per shortcode occurrence.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_link_get_slug_map')) {
    /**
     * Return a slug → permalink map of all currently-published pages.
     *
     * Cached in a transient for 24 hours. Also memoized within a single
     * request so repeated shortcode occurrences don't hit the transient
     * more than once.
     */
    function sb_link_get_slug_map(): array {
        static $memo = null;
        if ($memo !== null) return $memo;

        $cached = get_transient('sb_link_slug_map');
        if (is_array($cached)) {
            $memo = $cached;
            return $memo;
        }

        $map = [];
        $post_ids = get_posts([
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($post_ids as $post_id) {
            $slug = get_post_field('post_name', $post_id);
            if ($slug !== '') {
                $map[$slug] = get_permalink($post_id);
            }
        }
        set_transient('sb_link_slug_map', $map, DAY_IN_SECONDS);
        $memo = $map;
        return $map;
    }
}

if (!function_exists('sb_link_invalidate_slug_map')) {
    /**
     * Drop the cached map so the next request rebuilds from the DB. Bound to
     * every WP hook that indicates the set of published pages may have changed:
     *   - save_post: any create/edit, including a scheduled post transitioning
     *     to publish (WP fires save_post inside future_to_publish too)
     *   - deleted_post / trashed_post / untrashed_post: obvious
     *   - future_to_publish: explicit belt-and-braces
     */
    function sb_link_invalidate_slug_map() {
        delete_transient('sb_link_slug_map');
    }
    add_action('save_post',         'sb_link_invalidate_slug_map');
    add_action('deleted_post',      'sb_link_invalidate_slug_map');
    add_action('trashed_post',      'sb_link_invalidate_slug_map');
    add_action('untrashed_post',    'sb_link_invalidate_slug_map');
    add_action('future_to_publish', 'sb_link_invalidate_slug_map');
}

if (!function_exists('sb_link_render_shortcodes')) {
    function sb_link_render_shortcodes($content) {
        if (!is_string($content) || $content === '') return $content;
        if (strpos($content, '$$LINK') === false) return $content;

        return preg_replace_callback(
            '/\$\$LINK\s*([^|]*?)\s*\|\s*(.*?)\$\$/',
            function ($m) {
                $slug = sanitize_title(trim($m[1]));
                $text = trim($m[2]);
                if ($slug === '' || $text === '') return esc_html($text);

                $map = sb_link_get_slug_map();
                if (isset($map[$slug])) {
                    return '<a href="' . esc_url($map[$slug]) . '">' . esc_html($text) . '</a>';
                }
                // Target not published yet → render as plain text. Once the
                // target publishes, the cache will invalidate and the next
                // request rewires this into a real link automatically.
                return esc_html($text);
            },
            $content
        );
    }
    // Priority 9 — needs to run BEFORE external-links (which sits at 20 and
    // checks link targets against the site's domain). After resolution, the
    // links we produce point at the site's own permalinks, so external-links
    // correctly leaves them alone.
    add_filter('the_content', 'sb_link_render_shortcodes', 9);
}
