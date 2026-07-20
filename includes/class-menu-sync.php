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
    }

    /**
     * Cut a title at the first common SEO separator. Returns the trimmed
     * portion before the separator, or the whole title if no separator.
     *
     * Separators (checked in order — first match wins):
     *   ":", " — " (em-dash), " – " (en-dash), " - " (hyphen w/ spaces), " | "
     *
     * The hyphen is space-bracketed so "co-op" and "start-up" pass through
     * intact. The colon is not because "Casino:Guide" without a space is
     * an intended SEO pattern too (though rare).
     */
    public static function truncate_for_menu(string $title): string {
        $title = trim($title);
        if ($title === '') return '';

        // Ordered by rarity — pattern that's most likely to be an intentional
        // separator (rather than incidental) goes first. Em/en dashes are
        // very deliberate SEO choices; hyphen with spaces is a fallback.
        $separators = [
            ':',
            ' — ',   // U+2014 em-dash
            ' – ',   // U+2013 en-dash
            ' - ',   // hyphen-minus with spaces
            ' | ',
        ];

        $earliest = strlen($title); // "no match found" sentinel
        foreach ($separators as $sep) {
            $pos = strpos($title, $sep);
            if ($pos !== false && $pos < $earliest && $pos > 0) {
                $earliest = $pos;
            }
        }

        if ($earliest < strlen($title)) {
            $title = rtrim(substr($title, 0, $earliest));
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
     */
    public static function set_menu_item_title(int $menu_item_id, string $title, string $source): void {
        self::$suppress_detection = true;

        // Update the menu item — wp_update_nav_menu_item requires the menu id
        $menu_id = self::get_menu_id_for_item($menu_item_id);
        if ($menu_id > 0) {
            $existing = wp_setup_nav_menu_item(get_post($menu_item_id));
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
                'menu-item-attr-title'  => $existing->attr_title,
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

            $new_title = self::compute_menu_title($item_id, (int)$post_id);
            if ($new_title !== '') {
                self::set_menu_item_title($item_id, $new_title, 'auto');
            }
        }
    }

    /**
     * Fires on every wp_update_nav_menu_item write — including our own. We
     * check the suppression flag first; if the write is programmatic (from
     * FSR import or sync_from_post), we skip. Otherwise it's a human edit
     * from Appearance → Menus and we flip the source to 'manual' so future
     * syncs respect their choice.
     */
    public static function detect_manual_edit($menu_id, $menu_item_id, $args): void {
        if (self::$suppress_detection) return;
        // Only flag existing items that we manage. First-time item creation
        // by the FSR importer will call set_menu_item_title right after,
        // which sets 'label' or 'auto' anyway.
        $existing_source = get_post_meta($menu_item_id, self::META_KEY, true);
        if ($existing_source === '') return; // not our menu item, ignore
        update_post_meta($menu_item_id, self::META_KEY, 'manual');
    }

    /**
     * Find all menu items pointing at a given page. Queries the nav_menu_item
     * CPT with _menu_item_object_id = $page_id and _menu_item_type = 'post_type'.
     */
    private static function find_menu_items_for_page(int $page_id): array {
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
        return $query->posts;
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
}
