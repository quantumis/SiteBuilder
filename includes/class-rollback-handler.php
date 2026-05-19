<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds and executes a rollback for a target import.
 *
 * Rollback walks the import_items table and undoes every tracked operation, in the
 * order that's safe for WordPress:
 *
 *   1. menu_item    — remove menu entries (so menus look clean if anything else fails)
 *   2. page         — delete pages, deepest first (children before parents)
 *   3. attachment   — delete attached media (after pages, since pages may have used them as thumbnails)
 *   4. css_file     — remove the copied stylesheet from the theme
 *   5. theme_file_snapshot — restore the original front-page.php / footer.php contents
 *   6. option_snapshot     — restore show_on_front / page_on_front and similar
 *   7. nav_menu     — delete a menu created by this import as a whole
 *   8. finalize     — mark the target import as rolled_back
 *
 * The rollback itself is recorded as a separate import row (type=rollback). Each undo step
 * is a queue task processed by the same batch handler used by CREATE and ADD, so the user
 * sees a familiar progress bar.
 */
class Site_Builder_Rollback_Handler {

    /**
     * Build the rollback task queue for a target import.
     */
    public function build_queue(int $target_import_id, Site_Builder_Import_Tracker $tracker): array {
        $items = $tracker->get_items($target_import_id);
        if (empty($items)) {
            return [
                ['phase' => 'rollback', 'kind' => 'rollback_finalize',
                 'data' => ['target_import_id' => $target_import_id]],
            ];
        }

        // Group items by type
        $by_type = [];
        foreach ($items as $item) {
            $by_type[$item->item_type][] = $item;
        }

        $queue = [];

        // 1. Menu items — order doesn't matter much, WP cleans up parent-child via post deletion
        foreach ($by_type['menu_item'] ?? [] as $item) {
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_menu_item',
                        'data' => ['item_id' => (int)$item->ref_id]];
        }

        // 2. Pages — sort by ID DESC so deeply-nested ones go first
        $pages = $by_type['page'] ?? [];
        usort($pages, fn($a, $b) => (int)$b->ref_id <=> (int)$a->ref_id);
        foreach ($pages as $item) {
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_page',
                        'data' => ['post_id' => (int)$item->ref_id]];
        }

        // 3. Attachments
        foreach ($by_type['attachment'] ?? [] as $item) {
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_attachment',
                        'data' => ['post_id' => (int)$item->ref_id]];
        }

        // 4. CSS files
        foreach ($by_type['css_file'] ?? [] as $item) {
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_css_file',
                        'data' => ['path' => (string)$item->ref_path]];
        }

        // 5. Theme file snapshots
        foreach ($by_type['theme_file_snapshot'] ?? [] as $item) {
            $snap = $item->ref_data ? json_decode($item->ref_data, true) : null;
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_theme_file',
                        'data' => [
                            'path'     => (string)$item->ref_path,
                            'original' => is_array($snap) && isset($snap['original']) ? (string)$snap['original'] : '',
                        ]];
        }

        // 6. Option snapshots
        foreach ($by_type['option_snapshot'] ?? [] as $item) {
            $opts = $item->ref_data ? json_decode($item->ref_data, true) : null;
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_option',
                        'data' => ['options' => is_array($opts) ? $opts : []]];
        }

        // 7. Nav menus — delete entire menus that were created in this import
        foreach ($by_type['nav_menu'] ?? [] as $item) {
            $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_nav_menu',
                        'data' => ['term_id' => (int)$item->ref_id]];
        }

        // 8. Finalize
        $queue[] = ['phase' => 'rollback', 'kind' => 'rollback_finalize',
                    'data' => ['target_import_id' => $target_import_id]];

        return $queue;
    }

    /**
     * Execute a single rollback task. Returns ['ok' => bool, 'message' => string].
     */
    public function execute_task(array $task, Site_Builder_Import_Tracker $tracker): array {
        $kind = $task['kind'] ?? '';
        $data = $task['data'] ?? [];

        switch ($kind) {
            case 'rollback_menu_item': {
                $id = (int)($data['item_id'] ?? 0);
                if ($id) wp_delete_post($id, true);
                return ['ok' => true, 'message' => 'Удалён пункт меню #' . $id];
            }

            case 'rollback_page': {
                $id = (int)($data['post_id'] ?? 0);
                if ($id) wp_delete_post($id, true);
                return ['ok' => true, 'message' => 'Удалена страница #' . $id];
            }

            case 'rollback_attachment': {
                $id = (int)($data['post_id'] ?? 0);
                if ($id) wp_delete_attachment($id, true);
                return ['ok' => true, 'message' => 'Удалена картинка #' . $id];
            }

            case 'rollback_css_file': {
                $path = (string)($data['path'] ?? '');
                if ($path && file_exists($path)) {
                    @unlink($path);
                }
                return ['ok' => true, 'message' => 'Удалён CSS файл'];
            }

            case 'rollback_theme_file': {
                $path = (string)($data['path'] ?? '');
                $original = (string)($data['original'] ?? '');
                if ($path && is_writable($path)) {
                    @file_put_contents($path, $original);
                }
                // Also drop the pristine snapshot for this file, so the next CREATE
                // starts fresh.
                $pristine = get_option('site_builder_pristine_theme_files', []);
                if (is_array($pristine) && isset($pristine[$path])) {
                    unset($pristine[$path]);
                    if (empty($pristine)) {
                        delete_option('site_builder_pristine_theme_files');
                    } else {
                        update_option('site_builder_pristine_theme_files', $pristine);
                    }
                }
                return ['ok' => true, 'message' => 'Восстановлен файл темы ' . basename($path)];
            }

            case 'rollback_option': {
                $opts = $data['options'] ?? [];
                if (is_array($opts)) {
                    foreach ($opts as $opt_name => $opt_value) {
                        update_option($opt_name, $opt_value);
                    }
                }
                return ['ok' => true, 'message' => 'Восстановлены опции'];
            }

            case 'rollback_nav_menu': {
                $term_id = (int)($data['term_id'] ?? 0);
                if ($term_id) {
                    $menu = wp_get_nav_menu_object($term_id);
                    if ($menu) {
                        wp_delete_nav_menu($menu->term_id);
                    }
                }
                return ['ok' => true, 'message' => 'Удалено меню #' . $term_id];
            }

            case 'rollback_finalize': {
                $target_id = (int)($data['target_import_id'] ?? 0);
                if ($target_id) {
                    $tracker->update_import($target_id, [
                        'status'      => 'rolled_back',
                        'finished_at' => current_time('mysql'),
                    ]);
                }
                // Clear transient pointers so next ADD starts clean
                delete_option('site_builder_current_articles_id');
                return ['ok' => true, 'message' => 'Откат завершён'];
            }
        }

        return ['ok' => false, 'message' => 'Неизвестная задача отката: ' . $kind];
    }
}
