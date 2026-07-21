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
     * Drop the cached map so the next request rebuilds from the DB. The map
     * (slug → permalink for every published post) is expensive to rebuild —
     * roughly 1500 × get_permalink() on a large site, i.e. ~500ms — so we
     * invalidate only when the set of (slug, permalink) pairs can actually
     * have changed.
     *
     * Two-tier hook strategy:
     *   1. wp_insert_post_data filter — fires BEFORE the DB write, so we can
     *      compare old vs new post_name and post_status. Only invalidates
     *      when one of those actually changed. This is the common case
     *      (edits of published pages) and it now avoids invalidation for the
     *      overwhelming majority of edits (e.g. post_content changes).
     *   2. save_post for new posts (when $update === false). No prior version
     *      to compare against, and a new publish adds a slug so we do invalidate.
     *   3. delete/trash/untrash/future_to_publish — always affect the map.
     *
     * Excluded post types (never affect the front-end slug map):
     *   nav_menu_item, attachment, revision, customize_changeset, oembed_cache,
     *   user_request, wp_block, wp_navigation
     */
    function sb_link_invalidate_slug_map() {
        delete_transient('sb_link_slug_map');
    }

    /**
     * Types that never appear in the resolver's slug→permalink map. Skipping
     * their save/delete hooks avoids unnecessary invalidation — a huge win
     * since Menu_Sync (1.1.8) writes nav_menu_item posts on every page save.
     */
    function sb_link_is_ignorable_post_type($post_type) {
        static $ignore = [
            'nav_menu_item'      => true,
            'attachment'         => true,
            'revision'           => true,
            'customize_changeset'=> true,
            'oembed_cache'       => true,
            'user_request'       => true,
            'wp_block'           => true,
            'wp_navigation'      => true,
        ];
        return isset($ignore[$post_type]);
    }

    /**
     * Update path: fires before the DB write. Compares stored slug/status
     * with the incoming values. Invalidates only when one of them changed.
     */
    function sb_link_maybe_invalidate_on_edit($data, $postarr) {
        // New posts (no ID) — handled by save_post hook below
        if (empty($postarr['ID'])) return $data;
        if (sb_link_is_ignorable_post_type($data['post_type'] ?? '')) return $data;

        $old = get_post((int)$postarr['ID']);
        if (!$old) return $data;

        $slug_changed   = ($old->post_name   !== ($data['post_name']   ?? ''));
        $status_changed = ($old->post_status !== ($data['post_status'] ?? ''));

        if ($slug_changed || $status_changed) {
            sb_link_invalidate_slug_map();
        }
        return $data;
    }
    add_filter('wp_insert_post_data', 'sb_link_maybe_invalidate_on_edit', 10, 2);

    /**
     * Insert path: new post creation. $update is false only on first save.
     * Only invalidate when the new post is published (draft/pending/private
     * aren't in the resolver map either).
     */
    function sb_link_invalidate_on_new_publish($post_id, $post, $update) {
        if ($update) return;
        if (sb_link_is_ignorable_post_type($post->post_type)) return;
        if ($post->post_status !== 'publish') return;
        sb_link_invalidate_slug_map();
    }
    add_action('save_post', 'sb_link_invalidate_on_new_publish', 10, 3);

    /**
     * Deletion / status-transition paths. Same post-type filter.
     */
    function sb_link_invalidate_on_delete($post_id) {
        $post = get_post($post_id);
        if (!$post || sb_link_is_ignorable_post_type($post->post_type)) return;
        sb_link_invalidate_slug_map();
    }
    add_action('deleted_post',   'sb_link_invalidate_on_delete');
    add_action('trashed_post',   'sb_link_invalidate_on_delete');
    add_action('untrashed_post', 'sb_link_invalidate_on_delete');
    add_action('future_to_publish', function ($post) {
        if ($post && !sb_link_is_ignorable_post_type($post->post_type)) {
            sb_link_invalidate_slug_map();
        }
    });
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
