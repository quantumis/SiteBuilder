<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports a single page task — creates/updates the WordPress page and adds it to the menu.
 */
class Site_Builder_Page_Importer {

    private Site_Builder_Import_Tracker $tracker;
    private Site_Builder_Content_Processor $processor;
    private int $import_id;
    private int $menu_id;
    private string $source_dir;

    public function __construct(
        Site_Builder_Import_Tracker $tracker,
        Site_Builder_Content_Processor $processor,
        int $import_id,
        int $menu_id,
        string $source_dir = ''
    ) {
        $this->tracker    = $tracker;
        $this->processor  = $processor;
        $this->import_id  = $import_id;
        $this->menu_id    = $menu_id;
        $this->source_dir = rtrim($source_dir, '/');
    }

    /**
     * Import a single page task. Returns ['ok' => bool, 'title' => string, 'message' => string].
     */
    public function import(array $task): array {
        $data = $task['data'] ?? [];
        $schedule = $task['schedule'] ?? ['status' => 'publish', 'date' => current_time('mysql')];

        $full_path   = $data['full_path']   ?? '';
        $slug        = $data['slug']        ?? '';
        $parent_path = $data['parent_path'] ?? '';
        $level       = (int)($data['level'] ?? 0);

        if (!$slug || !is_dir($full_path)) {
            return ['ok' => false, 'title' => $slug, 'message' => 'Папка не найдена'];
        }

        $page_title = Site_Builder_Helpers::format_page_title($slug);

        // Find the HTML file
        $html_file = '';
        if (file_exists($full_path . '/index.html')) {
            $html_file = $full_path . '/index.html';
        } else {
            $html_files = glob($full_path . '/*.html');
            if (!empty($html_files)) $html_file = $html_files[0];
        }

        $raw_html = '';
        $meta = ['title' => '', 'desc' => ''];
        $processed = ['content' => '', 'thumbnail_id' => null];
        $skipped_reason = '';

        if ($html_file && is_readable($html_file)) {
            $raw_html = (string)@file_get_contents($html_file);
            if ($raw_html === '') {
                $skipped_reason = 'HTML-файл пустой';
            } else {
                $meta = $this->processor->extract_meta($raw_html);
                $hub_images = $this->source_dir !== '' ? $this->source_dir . '/hub/images' : '';
                $processed = $this->processor->process_page($raw_html, $full_path, $hub_images);
            }
        } else {
            $skipped_reason = 'HTML-файл не найден';
        }

        // Resolve parent post ID from slug path
        $parent_id = 0;
        $menu_parent_id = 0;
        if ($parent_path !== '') {
            $parent_page = get_page_by_path($parent_path, OBJECT, 'page');
            if ($parent_page) {
                $parent_id = (int)$parent_page->ID;
                $menu_parent_id = $this->find_menu_item_for_post($parent_id);
            }
        }

        // Check for existing page at this slug under this parent
        global $wpdb;
        $existing_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_name = %s AND post_parent = %d AND post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft')
             LIMIT 1",
            $slug, $parent_id
        ));

        $post_data = [
            'post_title'    => $page_title,
            'post_name'     => $slug,
            'post_content'  => $processed['content'],
            'post_status'   => $schedule['status'],
            'post_type'     => 'page',
            'post_parent'   => $parent_id,
            'post_date'     => $schedule['date'],
            'post_date_gmt' => get_gmt_from_date($schedule['date']),
        ];

        // Bypass kses filtering to preserve all HTML (per project requirement)
        kses_remove_filters();
        try {
            if ($existing_id) {
                $post_data['ID'] = $existing_id;
                $result = wp_update_post($post_data, true);
                $page_id = is_wp_error($result) ? 0 : (int)$existing_id;
            } else {
                $result = wp_insert_post($post_data, true);
                $page_id = is_wp_error($result) ? 0 : (int)$result;
                if ($page_id) {
                    $this->tracker->track_item($this->import_id, 'page', $page_id);
                }
            }
        } finally {
            kses_init_filters();
        }

        if (!$page_id) {
            $msg = is_wp_error($result) ? $result->get_error_message() : 'Не удалось создать страницу';
            return ['ok' => false, 'title' => $page_title, 'message' => $msg];
        }

        update_post_meta($page_id, 'meta_title', $meta['title'] ?: $page_title);
        update_post_meta($page_id, 'meta_description', $meta['desc']);
        if (!empty($processed['thumbnail_id'])) {
            set_post_thumbnail($page_id, $processed['thumbnail_id']);
        }

        // Add to nav menu
        if ($this->menu_id) {
            $menu_item_id = wp_update_nav_menu_item($this->menu_id, 0, [
                'menu-item-title'     => $page_title,
                'menu-item-object-id' => $page_id,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $menu_parent_id,
            ]);
            if (!is_wp_error($menu_item_id) && $menu_item_id) {
                $this->tracker->track_item($this->import_id, 'menu_item', (int)$menu_item_id);
            }
        }

        if ($skipped_reason) {
            $this->tracker->append_error($this->import_id, $skipped_reason, [
                'title' => $page_title,
                'slug'  => $slug,
            ]);
            return ['ok' => true, 'title' => $page_title, 'message' => 'Создана с предупреждением: ' . $skipped_reason];
        }

        return ['ok' => true, 'title' => $page_title, 'message' => 'OK'];
    }

    private function find_menu_item_for_post(int $post_id): int {
        if (!$this->menu_id) return 0;
        $items = wp_get_nav_menu_items($this->menu_id);
        if (!$items) return 0;
        foreach ($items as $item) {
            if ((int)$item->object_id === $post_id) return (int)$item->ID;
        }
        return 0;
    }
}
