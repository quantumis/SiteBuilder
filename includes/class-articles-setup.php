<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages the "Articles" parent page used by ADD-mode imports.
 *
 * Behavior:
 *   - If an "Articles" page already exists (by slug or by the option pointer),
 *     it is reused. Reused pages are NOT tracked for rollback.
 *   - If it doesn't exist, a new one is created and tracked.
 *   - The Articles page is added to the main nav menu (if the menu exists),
 *     so all ADD-imported children render under it.
 */
class Site_Builder_Articles_Setup {

    private Site_Builder_Import_Tracker $tracker;
    private int $import_id;
    private int $menu_id;

    public function __construct(Site_Builder_Import_Tracker $tracker, int $import_id, int $menu_id) {
        $this->tracker   = $tracker;
        $this->import_id = $import_id;
        $this->menu_id   = $menu_id;
    }

    /**
     * Ensure the Articles page exists and is wired into the menu.
     * Returns ['articles_id' => int, 'created' => bool, 'menu_item_id' => int].
     */
    public function ensure(): array {
        $slug = Site_Builder_Helpers::get_articles_slug();
        $title = Site_Builder_Helpers::get_articles_title();

        // Look up existing Articles page (top-level, by slug)
        $existing = get_page_by_path($slug, OBJECT, 'page');
        $created = false;

        if ($existing) {
            $articles_id = (int)$existing->ID;
        } else {
            kses_remove_filters();
            try {
                $articles_id = wp_insert_post([
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => 0,
                    'post_content' => '',
                ], true);
            } finally {
                kses_init_filters();
            }

            if (is_wp_error($articles_id) || !$articles_id) {
                return ['articles_id' => 0, 'created' => false, 'menu_item_id' => 0];
            }
            $articles_id = (int)$articles_id;
            $this->tracker->track_item($this->import_id, 'page', $articles_id);
            $created = true;
        }

        // Save pointer for the page importer to read
        update_option('site_builder_current_articles_id', $articles_id);

        // Ensure the page is in the main nav menu
        $menu_item_id = 0;
        if ($this->menu_id) {
            $menu_item_id = $this->find_menu_item_for_post($articles_id);
            if (!$menu_item_id) {
                $new_item = wp_update_nav_menu_item($this->menu_id, 0, [
                    'menu-item-title'     => $title,
                    'menu-item-object-id' => $articles_id,
                    'menu-item-object'    => 'page',
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                    'menu-item-parent-id' => 0,
                ]);
                if (!is_wp_error($new_item) && $new_item) {
                    $menu_item_id = (int)$new_item;
                    $this->tracker->track_item($this->import_id, 'menu_item', $menu_item_id);
                }
            }
        }

        return [
            'articles_id'  => $articles_id,
            'created'      => $created,
            'menu_item_id' => (int)$menu_item_id,
        ];
    }

    private function find_menu_item_for_post(int $post_id): int {
        if (!$this->menu_id) return 0;
        $items = wp_get_nav_menu_items($this->menu_id);
        if (!$items) return 0;
        foreach ($items as $item) {
            if ((int)$item->object_id === $post_id && $item->object === 'page') {
                return (int)$item->ID;
            }
        }
        return 0;
    }
}
