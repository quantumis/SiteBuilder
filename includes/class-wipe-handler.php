<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the WIPE phase — total removal of all pages and reset of front-page settings.
 * Triggered only after explicit user confirmation ("УДАЛИТЬ").
 */
class Site_Builder_Wipe_Handler {

    public function wipe_single_page(int $post_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') return false;
        $result = wp_delete_post($post_id, true);
        return (bool)$result;
    }

    /**
     * Final wipe step: reset front-page, delete site-builder menu, clear previous import records.
     */
    public function finalize_wipe(Site_Builder_Import_Tracker $tracker): void {
        // Reset front page settings
        update_option('show_on_front', 'posts');
        update_option('page_on_front', 0);

        // Delete the plugin's nav menu if present
        $menu = wp_get_nav_menu_object(SITE_BUILDER_MENU_NAME);
        if ($menu) {
            wp_delete_nav_menu($menu->term_id);
        }

        // Remove import records older than the current one (rollback history is just "last")
        global $wpdb;
        $current_id = $this->get_current_import_id($tracker);
        if ($current_id > 0) {
            $imports_table = $wpdb->prefix . 'site_builder_imports';
            $items_table = $wpdb->prefix . 'site_builder_import_items';
            $old_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $imports_table WHERE id <> %d",
                $current_id
            ));
            foreach ($old_ids ?: [] as $oid) {
                $wpdb->delete($items_table, ['import_id' => (int)$oid]);
                $wpdb->delete($imports_table, ['id' => (int)$oid]);
            }
        }
    }

    private function get_current_import_id(Site_Builder_Import_Tracker $tracker): int {
        $lock = $tracker->get_lock();
        return $lock && isset($lock['import_id']) ? (int)$lock['import_id'] : 0;
    }
}
