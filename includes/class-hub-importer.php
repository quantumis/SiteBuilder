<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the HUB folder during CREATE: theme file modification (with snapshots for rollback),
 * CSS copying, and home page setup.
 */
class Site_Builder_Hub_Importer {

    private Site_Builder_Import_Tracker $tracker;
    private Site_Builder_Content_Processor $processor;
    private Site_Builder_Media_Handler $media;
    private int $import_id;

    public function __construct(
        Site_Builder_Import_Tracker $tracker,
        Site_Builder_Content_Processor $processor,
        Site_Builder_Media_Handler $media,
        int $import_id
    ) {
        $this->tracker   = $tracker;
        $this->processor = $processor;
        $this->media     = $media;
        $this->import_id = $import_id;
    }

    /**
     * Run the entire HUB setup. Returns ['ok' => bool, 'message' => string].
     */
    public function setup(string $source_dir): array {
        $hub_dir = $source_dir . '/hub';
        if (!is_dir($hub_dir)) {
            return ['ok' => true, 'message' => 'HUB-папка не найдена, пропуск'];
        }

        $theme_dir = get_template_directory();
        $messages = [];

        // 1. Copy CSS to theme/imported-styles/style.css
        $css_source = $hub_dir . '/style.css';
        if (file_exists($css_source)) {
            $css_dest_dir = $theme_dir . '/' . SITE_BUILDER_THEME_CSS_DIR;
            if (!is_dir($css_dest_dir)) {
                @mkdir($css_dest_dir, 0755, true);
            }
            $css_dest = $css_dest_dir . '/style.css';
            if (@copy($css_source, $css_dest)) {
                $this->tracker->track_item($this->import_id, 'css_file', null, $css_dest);
                $messages[] = 'CSS скопирован';
            } else {
                $messages[] = 'Не удалось скопировать CSS';
            }
        }

        // 2. Process hub index.html
        $hub_html_file = $hub_dir . '/index.html';
        if (!file_exists($hub_html_file)) {
            return ['ok' => true, 'message' => implode('; ', $messages) . '; index.html не найден'];
        }

        $raw_html = (string)@file_get_contents($hub_html_file);
        if ($raw_html === '') {
            return ['ok' => false, 'message' => 'HUB index.html пустой'];
        }

        $meta = $this->processor->extract_meta($raw_html);
        $shortcodes = Site_Builder_Helpers::get_home_shortcodes();
        $main_content = $this->processor->process_hub_main($raw_html, $hub_dir, $shortcodes);
        $footer_html = $this->processor->process_hub_footer($raw_html, $hub_dir);

        // 3. Snapshot and update front-page.php
        $fp_template = $theme_dir . '/front-page.php';
        if ($main_content !== '' && file_exists($fp_template) && is_writable($fp_template)) {
            $this->snapshot_and_replace($fp_template, $main_content);
            $messages[] = 'front-page.php обновлён';
        } elseif (!file_exists($fp_template)) {
            $messages[] = 'front-page.php не найден в теме';
        }

        // 4. Snapshot and update footer.php
        $footer_template = $theme_dir . '/footer.php';
        if ($footer_html !== '' && file_exists($footer_template) && is_writable($footer_template)) {
            $this->snapshot_and_replace($footer_template, $footer_html);
            $messages[] = 'footer.php обновлён';
        }

        // 5. Set up home page
        $front_id = (int)get_option('page_on_front');
        if (!$front_id || !get_post($front_id)) {
            kses_remove_filters();
            try {
                $front_id = wp_insert_post([
                    'post_title'  => 'Home',
                    'post_name'   => 'home',
                    'post_status' => 'publish',
                    'post_type'   => 'page',
                ]);
            } finally {
                kses_init_filters();
            }
            if ($front_id && !is_wp_error($front_id)) {
                $this->tracker->track_item($this->import_id, 'page', (int)$front_id);

                // Snapshot original show_on_front + page_on_front before changing
                $this->tracker->track_item($this->import_id, 'option_snapshot', null, null, [
                    'show_on_front' => get_option('show_on_front'),
                    'page_on_front' => get_option('page_on_front'),
                ]);
                update_option('show_on_front', 'page');
                update_option('page_on_front', (int)$front_id);
            }
        }

        if ($front_id && get_post($front_id)) {
            update_post_meta((int)$front_id, 'meta_title', $meta['title']);
            update_post_meta((int)$front_id, 'meta_description', $meta['desc']);
        }

        return ['ok' => true, 'message' => implode('; ', $messages) ?: 'HUB настроен'];
    }

    /**
     * Save a snapshot of the file contents, then replace the <!-- Enter Code --> marker.
     *
     * To handle re-imports correctly, we maintain a "pristine" snapshot — the file's
     * contents as it was BEFORE any plugin modification. On subsequent imports we
     * restore from pristine first, so the marker is always present.
     */
    private function snapshot_and_replace(string $file_path, string $replacement): void {
        $current = (string)@file_get_contents($file_path);

        $pristine_snapshots = get_option('site_builder_pristine_theme_files', []);
        if (!is_array($pristine_snapshots)) $pristine_snapshots = [];

        if (isset($pristine_snapshots[$file_path])) {
            // We've modified this file before. Restore pristine before modifying again.
            $original = (string)$pristine_snapshots[$file_path];
            @file_put_contents($file_path, $original);
        } else {
            // First plugin touch. Save current state as pristine forever.
            $pristine_snapshots[$file_path] = $current;
            update_option('site_builder_pristine_theme_files', $pristine_snapshots);
            $original = $current;
        }

        // Track this import's snapshot too (used for per-import rollback in stage 4)
        $this->tracker->track_item(
            $this->import_id,
            'theme_file_snapshot',
            null,
            $file_path,
            ['original' => $original]
        );

        $marker = '<!-- Enter Code -->';
        if (strpos($original, $marker) !== false) {
            $new_content = str_replace($marker, $replacement, $original);
        } else {
            $new_content = $original . "\n" . $replacement . "\n";
        }
        @file_put_contents($file_path, $new_content);
    }
}
