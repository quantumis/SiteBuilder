<?php
/**
 * Site Builder — menu title synchronization.
 *
 * Long SEO titles look bad in navigation menus. FSR-imported pages typically
 * have titles like "Bet Hjemmesider i Danmark — Komplet Guide til Danske
 * Betting Sider i 2026" — great for search, terrible for a menu bar.
 *
 * This module:
 *   1. Provides a truncation helper that cuts a title at the first common
 *      separator (":", " — ", " – ", " - ", " | "). Whole-word only for the
 *      hyphen so "co-op" and "start-up" aren't split apart.
 *
 *   2. Syncs menu-item titles from the page's post_title whenever the page
 *      is saved. The editor's post_title in the admin is the source of truth —
 *      when they change it, the menu updates. Auto-truncation is re-applied.
 *
 *   3. Detects manual edits from the menu admin. When a menu item's title
 *      is changed via Appearance → Menus (not our sync path), source flips
 *      to 'manual' and future post_title changes leave it alone.
 *
 * The two-source model (_sb_menu_title_source meta on the menu-item post):
 *   'auto'   — default. Derived from post_title via truncate_for_menu().
 *              Recomputed on every save_post — the page title in the admin
 *              is the highest-priority source. If [M;label] was used in the
 *              FSR flag, it seeds the initial menu title at import but is
 *              not sticky; subsequent post_title edits will overwrite.
 *   'manual' — the editor changed the menu item title through Appearance →
 *              Menus. Sync respects the manual choice and never overwrites.
 */
if (!defined('ABSPATH')) exit;

class Site_Builder_Menu_Sync {

    const META_KEY = '_sb_menu_title_source';

    /**
     * True while we're programmatically updating a menu item title. The
     * wp_update_nav_menu_item action fires for our own writes too — this
     * flag lets the manual-edit detector distinguish "our sync" from "a
     * human editing through Appearance → Menus".
     */
    private static $suppress_detection = false;

    public static function register(): void {
        // Sync menu items when the source page's post_title changes.
        add_action('save_post_page', [__CLASS__, 'sync_from_post'], 20, 3);
        add_action('save_post_post', [__CLASS__, 'sync_from_post'], 20, 3);

        // Detect manual edits from Appearance → Menus. Fires after every
        // menu-item write, including our own — self::$suppress_detection
        // guards against that.
        add_action('wp_update_nav_menu_item', [__CLASS__, 'detect_manual_edit'], 10, 3);

        // Maintain the reverse index (page → menu items). On every menu-item
        // write, register the item in its linked page's meta. On menu-item
        // deletion, remove it. See find_menu_items_for_page() for how the
        // index accelerates sync lookups by ~5-15ms per save_post.
        add_action('wp_update_nav_menu_item', [__CLASS__, 'sync_menu_item_link_index'], 15, 3);
        add_action('before_delete_post',      [__CLASS__, 'cleanup_menu_item_link_index']);
    }

    /**
     * Fallback default for the menu-item max length. The live value is
     * pulled from Site_Builder_Settings::menu_max_length() so the admin can
     * tune it per site on the Settings tab; this constant is used only if
     * Settings isn't available (e.g. during activation before the class is
     * loaded).
     */
    const MAX_LENGTH = 40;

    /**
     * Resolve the effective max length. Prefer the Settings value when the
     * class exists (normal runtime); fall back to the constant otherwise.
     */
    private static function max_length(): int {
        if (class_exists('Site_Builder_Settings') && method_exists('Site_Builder_Settings', 'menu_max_length')) {
            return Site_Builder_Settings::menu_max_length();
        }
        return self::MAX_LENGTH;
    }

    /**
     * Cut a title at the first common SEO separator, then apply a hard
     * character limit as a safety net.
     *
     * Separators (all match earliest-wins, not priority — whichever comes
     * first in the text wins):
     *   ":" ,  " — " (em-dash),  " – " (en-dash),  " - " (hyphen w/ spaces),
     *   " | " (pipe),  ", " (comma + space),  " (" (space + paren)
     *
     * The hyphen/paren are space-bracketed so "co-op", "start-up", "$1,000"
     * and URL-like strings pass through intact. Comma requires a following
     * space so numbers stay whole.
     *
     * After separator-based cutting, if the result is still longer than
     * MAX_LENGTH characters, it's truncated at the last word boundary and
     * an ellipsis (…) is appended. Multi-byte aware — mb_* functions handle
     * Cyrillic/Greek/etc. as characters rather than bytes, so we never cut
     * mid-character.
     */
    public static function truncate_for_menu(string $title): string {
        $title = trim($title);
        if ($title === '') return '';

        // Resolve [sb_year], [sb_date], any other [sb_*] shortcodes BEFORE
        // measuring/cutting. Two reasons: (1) the visible menu title should
        // show the rendered value ("2026"), not the raw shortcode text
        // ("[sb_year]"), and (2) length calculations would be wrong if
        // measured against source markup — [sb_year] is 9 chars, "2026" is 4.
        // Guarded on '[sb_' prefix so plain text isn't gratuitously piped
        // through do_shortcode on every call.
        if (strpos($title, '[sb_') !== false && function_exists('do_shortcode')) {
            $title = do_shortcode($title);
            $title = trim($title);
        }

        $separators = [
            ':',
            ' — ',   // U+2014 em-dash
            ' – ',   // U+2013 en-dash
            ' - ',   // hyphen-minus with spaces
            ' | ',
            ', ',    // comma + space — leaves "$1,000" whole
            ' (',    // opening paren with leading space — leaves URLs whole
        ];

        // Earliest separator wins. This is byte-position based, which is
        // safe here: every separator we look for is either pure ASCII or
        // starts/ends on a UTF-8 character boundary (em-dash and en-dash
        // are single multi-byte characters flanked by ASCII spaces).
        $earliest = strlen($title);
        foreach ($separators as $sep) {
            $pos = strpos($title, $sep);
            if ($pos !== false && $pos < $earliest && $pos > 0) {
                $earliest = $pos;
            }
        }
        if ($earliest < strlen($title)) {
            $title = rtrim(substr($title, 0, $earliest));
        }

        // Hard-limit safety net. Kicks in when either no separator matched
        // ("Полный подробный обзор букмекерских контор в Германии" → no
        // separators, still 55 chars) or when the pre-separator segment is
        // itself over the limit.
        //
        // Uses mb_* when available for character-count-based limits on
        // Cyrillic/Greek/etc. Falls back to strlen (byte count) on servers
        // without mbstring — worst case the limit engages a bit earlier
        // for non-Latin text, which is still better than not engaging.
        $use_mb  = function_exists('mb_strlen');
        $strlen  = $use_mb ? 'mb_strlen'  : 'strlen';
        $substr  = $use_mb ? 'mb_substr'  : 'substr';
        $strrpos = $use_mb ? 'mb_strrpos' : 'strrpos';

        $max_length = self::max_length();
        if ($strlen($title) > $max_length) {
            $cut = $substr($title, 0, $max_length);
            $lastSpace = $strrpos($cut, ' ');
            if ($lastSpace !== false && $lastSpace >= 10) {
                $cut = $substr($cut, 0, $lastSpace);
            }
            $title = rtrim($cut, " \t\n\r,.:;-") . '…';
        }

        return $title;
    }

    /**
     * Compute what the menu-item title *should* be for a given source page.
     * Always derived from the current post_title with truncate_for_menu()
     * applied — there is no "sticky label" source anymore. Manual edits via
     * Appearance → Menus are protected via the 'manual' source and never
     * reach this function.
     */
    public static function compute_menu_title(int $menu_item_id, int $page_id): string {
        $post_title = (string)get_post_field('post_title', $page_id);
        return self::truncate_for_menu($post_title);
    }

    /**
     * Programmatically set a menu item's title, marking it with the given
     * source. Suppresses the manual-edit detector while doing so. This is
     * the single entry point for both the FSR importer and the save_post
     * sync — using it consistently keeps the meta values honest.
     *
     * $full_title is optional. When provided and different from $title, it
     * gets written to menu-item-attr-title so the browser shows it as a
     * native tooltip on hover. When empty or equal to $title, attr-title
     * is cleared — no point tooltipping the same text.
     */
    public static function set_menu_item_title(int $menu_item_id, string $title, string $source, string $full_title = ''): void {
        self::$suppress_detection = true;

        // Update the menu item — wp_update_nav_menu_item requires the menu id
        $menu_id = self::get_menu_id_for_item($menu_item_id);
        if ($menu_id > 0) {
            $existing = wp_setup_nav_menu_item(get_post($menu_item_id));

            // Resolve [sb_*] shortcodes in the tooltip text too — same
            // reasoning as in truncate_for_menu(). Browsers render the
            // title attribute as literal text, so [sb_year] would show up
            // as source markup in the tooltip if not resolved here.
            if ($full_title !== '' && strpos($full_title, '[sb_') !== false && function_exists('do_shortcode')) {
                $full_title = trim(do_shortcode($full_title));
            }

            // Only emit an attr-title when it adds information. If the menu
            // title was untouched by truncation (short enough post_title,
            // no separators, no length limit hit), tooltip would just repeat
            // the visible text — noise, not signal.
            $attr_title = ($full_title !== '' && $full_title !== $title) ? $full_title : '';

            wp_update_nav_menu_item($menu_id, $menu_item_id, [
                'menu-item-title'       => $title,
                'menu-item-object-id'   => $existing->object_id,
                'menu-item-object'      => $existing->object,
                'menu-item-type'        => $existing->type,
                'menu-item-parent-id'   => $existing->menu_item_parent,
                'menu-item-position'    => $existing->menu_order,
                'menu-item-status'      => 'publish',
                'menu-item-url'         => $existing->url,
                'menu-item-description' => $existing->description,
                'menu-item-attr-title'  => $attr_title,
                'menu-item-target'      => $existing->target,
                'menu-item-classes'     => is_array($existing->classes) ? implode(' ', $existing->classes) : (string)$existing->classes,
                'menu-item-xfn'         => $existing->xfn,
            ]);
        }

        update_post_meta($menu_item_id, self::META_KEY, $source);

        self::$suppress_detection = false;
    }

    /**
     * Runs on save_post for pages/posts. Finds all menu items pointing at
     * this page and re-syncs any that aren't manually edited. The editor's
     * post_title is the source of truth — if they changed it in the admin,
     * the menu should reflect that.
     */
    public static function sync_from_post($post_id, $post, $update): void {
        // Skip autosaves, revisions, and initial creation (there won't be
        // any menu items linked yet).
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!$update) return; // creation, not edit

        $menu_items = self::find_menu_items_for_page((int)$post_id);
        if (empty($menu_items)) return;

        foreach ($menu_items as $item_id) {
            $source = get_post_meta($item_id, self::META_KEY, true);
            // Manual edits (via Appearance → Menus) are sticky. Everything
            // else — 'auto' or unset (legacy items) — gets recomputed.
            if ($source === 'manual') continue;

            $full_title = (string)get_post_field('post_title', $post_id);
            $new_title  = self::compute_menu_title($item_id, (int)$post_id);
            if ($new_title !== '') {
                self::set_menu_item_title($item_id, $new_title, 'auto', $full_title);
            }
        }
    }

    /**
     * Run a callable with the manual-edit detector suppressed. Used by the
     * FSR importer when it creates menu items — those writes are programmatic,
     * not human edits, so detect_manual_edit shouldn't fire. Restores the
     * previous suppression state on exit (finally-block) so nested calls
     * compose correctly.
     */
    public static function without_detection(callable $fn) {
        $prev = self::$suppress_detection;
        self::$suppress_detection = true;
        try {
            return $fn();
        } finally {
            self::$suppress_detection = $prev;
        }
    }

    /**
     * Fires on every wp_update_nav_menu_item write — including our own. We
     * check the suppression flag first; if the write is programmatic (from
     * FSR import or sync_from_post), we skip. Otherwise it's a human edit
     * from Appearance → Menus and we apply the same truncate + shortcode
     * resolve + tooltip logic that the import/sync paths use. Then flag
     * the item as 'manual' so future syncs respect their choice.
     *
     * The processing applies to ALL menu items, not just FSR-managed ones —
     * the goal is consistent menu-item formatting across the whole site,
     * regardless of how a given item ended up in the menu.
     */
    public static function detect_manual_edit($menu_id, $menu_item_id, $args): void {
        if (self::$suppress_detection) return;

        // The just-saved title as the editor typed it
        $user_input = (string)get_post_field('post_title', $menu_item_id);
        if ($user_input === '') return;

        // Full text with shortcodes resolved but NOT truncated — used for
        // the tooltip. If the user typed [sb_year] we want the browser to
        // show "2026" on hover, not the raw shortcode markup.
        $full_resolved = $user_input;
        if (strpos($full_resolved, '[sb_') !== false && function_exists('do_shortcode')) {
            $full_resolved = trim(do_shortcode($full_resolved));
        }

        // Display: truncated + shortcode-resolved (truncate_for_menu handles
        // shortcode resolution internally, so we don't need to pre-process).
        $display = self::truncate_for_menu($user_input);

        if ($display !== $user_input) {
            // Truncation or shortcode resolution changed the visible title.
            // Update the item — display gets the new short form, attr-title
            // gets the full resolved text for tooltip, source flips to
            // 'manual' so future post_title changes don't overwrite the
            // editor's action.
            self::set_menu_item_title($menu_item_id, $display, 'manual', $full_resolved);
        } else {
            // Nothing to truncate or render — the editor's input is already
            // in final form. Just mark it as manual to protect from sync.
            update_post_meta($menu_item_id, self::META_KEY, 'manual');
        }
    }

    /**
     * Find all menu items pointing at a given page.
     *
     * Two-tier lookup:
     *   1. Fast path: read the reverse index from the page's own meta
     *      (_sb_linked_menu_items). This is O(1) — one meta lookup that
     *      WordPress caches in memory for the request.
     *   2. Slow path (fallback): a full WP_Query with meta_query joins
     *      through wp_postmeta. Used when the reverse index isn't there
     *      yet (legacy items, pre-1.1.9). The result is cached in the
     *      reverse index so subsequent lookups hit the fast path.
     *
     * The reverse index is maintained by:
     *   - sync_menu_item_link_index() below (hooked to wp_update_nav_menu_item)
     *   - cleanup_menu_item_link_index() below (hooked to before_delete_post)
     *
     * We validate cached IDs against the current _menu_item_object_id — an
     * item might have been repointed to a different page outside our sync
     * path (e.g. via a bulk WP-CLI update), leaving a stale entry. Cleaning
     * up lazily on read is cheaper than eagerly on every write.
     */
    private static function find_menu_items_for_page(int $page_id): array {
        $cached = get_post_meta($page_id, '_sb_linked_menu_items', true);
        if (is_array($cached) && !empty($cached)) {
            // Fast path: validate that cached items still link to this page.
            // Filter out stale entries (item deleted, or repointed elsewhere).
            $valid = [];
            $stale = false;
            foreach ($cached as $item_id) {
                $item_id = (int)$item_id;
                if ($item_id <= 0) { $stale = true; continue; }
                $linked = (int)get_post_meta($item_id, '_menu_item_object_id', true);
                if ($linked === $page_id) {
                    $valid[] = $item_id;
                } else {
                    $stale = true;
                }
            }
            if ($stale) {
                // Persist the cleaned list so next request hits pure fast path
                if (empty($valid)) {
                    delete_post_meta($page_id, '_sb_linked_menu_items');
                } else {
                    update_post_meta($page_id, '_sb_linked_menu_items', $valid);
                }
            }
            return $valid;
        }

        // Slow path: full lookup, then cache in the reverse index
        $query = new WP_Query([
            'post_type'      => 'nav_menu_item',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_menu_item_object_id', 'value' => (string)$page_id],
                ['key' => '_menu_item_type',      'value' => 'post_type'],
            ],
            'no_found_rows'  => true,
        ]);
        $ids = array_map('intval', $query->posts);
        if (!empty($ids)) {
            update_post_meta($page_id, '_sb_linked_menu_items', $ids);
        }
        return $ids;
    }

    /**
     * Return the nav_menu term id that a given menu item belongs to.
     * Wraps wp_get_post_terms because a menu item is associated with its
     * menu via the nav_menu taxonomy.
     */
    private static function get_menu_id_for_item(int $menu_item_id): int {
        $terms = wp_get_post_terms($menu_item_id, 'nav_menu', ['fields' => 'ids']);
        if (is_wp_error($terms) || empty($terms)) return 0;
        return (int)$terms[0];
    }

    /**
     * Add the menu item to the reverse index on its linked page. Fired on
     * every wp_update_nav_menu_item (create + update). Idempotent — if the
     * item is already in the index, we don't duplicate it.
     *
     * A menu item might be repointed to a different page via a UI edit. In
     * that case the OLD linked page's index gets a stale entry, which is
     * fine — find_menu_items_for_page() cleans stale entries lazily on read
     * (cheaper than doing full old-vs-new tracking on every write).
     */
    public static function sync_menu_item_link_index($menu_id, $menu_item_id, $args): void {
        $menu_item_id = (int)$menu_item_id;
        if ($menu_item_id <= 0) return;

        $type = (string)get_post_meta($menu_item_id, '_menu_item_type', true);
        if ($type !== 'post_type') return; // custom links and taxonomy items — no page to index against

        $page_id = (int)get_post_meta($menu_item_id, '_menu_item_object_id', true);
        if ($page_id <= 0) return;

        $index = get_post_meta($page_id, '_sb_linked_menu_items', true);
        if (!is_array($index)) $index = [];

        if (!in_array($menu_item_id, $index, true)) {
            $index[] = $menu_item_id;
            update_post_meta($page_id, '_sb_linked_menu_items', $index);
        }
    }

    /**
     * Remove the menu item from its linked page's reverse index. Fired on
     * before_delete_post — meta is still readable at this point (deleted_post
     * fires after WP has purged the item's meta, too late to know what it
     * linked to).
     */
    public static function cleanup_menu_item_link_index($menu_item_id): void {
        $post = get_post($menu_item_id);
        if (!$post || $post->post_type !== 'nav_menu_item') return;

        $page_id = (int)get_post_meta($menu_item_id, '_menu_item_object_id', true);
        if ($page_id <= 0) return;

        $index = get_post_meta($page_id, '_sb_linked_menu_items', true);
        if (!is_array($index)) return;

        $filtered = array_values(array_filter($index, function ($id) use ($menu_item_id) {
            return (int)$id !== (int)$menu_item_id;
        }));

        if (empty($filtered)) {
            delete_post_meta($page_id, '_sb_linked_menu_items');
        } else {
            update_post_meta($page_id, '_sb_linked_menu_items', $filtered);
        }
    }
}
