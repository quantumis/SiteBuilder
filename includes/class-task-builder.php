<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the import task queue from a source folder.
 * Each task is a small dict consumed by an importer.
 */
class Site_Builder_Task_Builder {

    /**
     * After build_create_queue / build_add_queue, holds the resolved content root.
     * The AJAX layer reads it to persist the real path in import settings.
     */
    public string $resolved_root = '';

    /**
     * Build a CREATE-mode queue.
     *
     * Queue structure:
     *   [
     *     ['phase' => 'wipe',    'kind' => 'wipe_page', 'data' => ['post_id' => 123]],
     *     ['phase' => 'wipe',    'kind' => 'wipe_finalize'],
     *     ['phase' => 'hub',     'kind' => 'hub_setup'],
     *     ['phase' => 'pages',   'kind' => 'page',      'data' => [...]],
     *     ...
     *   ]
     *
     * @param string $source_dir       Full path to the source folder
     * @param array  $settings         ['schedule_mode', 'days', 'immediate_count']
     * @param bool   $wipe_first       Whether to prepend wipe tasks
     */
    public function build_create_queue(string $source_dir, array $settings, bool $wipe_first = false): array {
        $queue = [];

        if ($wipe_first) {
            foreach ($this->collect_existing_pages() as $post_id) {
                $queue[] = ['phase' => 'wipe', 'kind' => 'wipe_page', 'data' => ['post_id' => $post_id]];
            }
            $queue[] = ['phase' => 'wipe', 'kind' => 'wipe_finalize'];
        }

        // Newer archives may have wrapper folders (e.g. archive/inner1/domain.com/<real content>).
        // Find the actual content root before scanning.
        $real_root = $this->find_real_root($source_dir, 'create');
        $this->resolved_root = $real_root;

        if (is_dir($real_root . '/hub')) {
            $queue[] = ['phase' => 'hub', 'kind' => 'hub_setup', 'data' => ['source_dir' => $real_root]];
        }

        $page_tasks = $this->collect_page_folders($real_root);
        // Sort by level so parents come first
        usort($page_tasks, fn($a, $b) => $a['level'] <=> $b['level']);

        $schedule = $this->compute_schedule($page_tasks, $settings);
        foreach ($page_tasks as $idx => $task) {
            $task['phase']    = 'pages';
            $task['kind']     = 'page';
            $task['schedule'] = $schedule[$idx];
            $queue[] = $task;
        }

        return $queue;
    }

    /**
     * Build an ADD-mode queue.
     *
     * Auto-detects archive format: if there are subfolders with HTML files, treats
     * them as page folders (with images). If only flat .html files in the root, treats
     * them as flat pages (no images).
     *
     * Queue structure:
     *   [
     *     ['phase' => 'articles', 'kind' => 'articles_setup'],
     *     ['phase' => 'pages',    'kind' => 'add_page', 'data' => [...], 'schedule' => [...]],
     *     ...
     *   ]
     */
    public function build_add_queue(string $source_dir, array $settings): array {
        $queue = [
            ['phase' => 'articles', 'kind' => 'articles_setup'],
        ];

        // Newer archives may have wrapper folders — descend until we find content.
        $real_root = $this->find_real_root($source_dir, 'add');
        $this->resolved_root = $real_root;

        $format = $this->detect_add_format($real_root);
        if ($format === 'folders') {
            $page_tasks = $this->collect_add_folders($real_root);
        } elseif ($format === 'flat') {
            $page_tasks = $this->collect_add_flat($real_root);
        } else {
            return $queue; // nothing to add
        }

        // All ADD pages are conceptually "level 1" (under Articles) — gives compute_schedule
        // the same treatment of immediate_count / mode that nested pages get in CREATE.
        foreach ($page_tasks as &$t) {
            $t['data']['level'] = 1;
        }
        unset($t);

        $schedule = $this->compute_schedule($page_tasks, $settings);

        foreach ($page_tasks as $idx => $task) {
            $task['phase']    = 'pages';
            $task['kind']     = 'add_page';
            $task['schedule'] = $schedule[$idx];
            $queue[] = $task;
        }

        return $queue;
    }

    /**
     * Detect ADD archive format. Subfolders win over flat files if both are present.
     */
    private function detect_add_format(string $source_dir): string {
        $exclude = Site_Builder_Helpers::get_excluded_folders();
        $has_subfolders = false;
        $has_html_files = false;

        $items = @scandir($source_dir);
        if (!$items) return 'unknown';

        foreach ($items as $item) {
            if (in_array($item, $exclude, true)) continue;
            $full = $source_dir . '/' . $item;
            if (is_dir($full)) {
                // Only count subfolders that actually contain HTML files
                if (glob($full . '/*.html')) {
                    $has_subfolders = true;
                }
            } elseif (preg_match('/\.html?$/i', $item) && is_file($full)) {
                $has_html_files = true;
            }
        }

        if ($has_subfolders) return 'folders';
        if ($has_html_files) return 'flat';
        return 'unknown';
    }

    /**
     * Collect immediate-child folders (single level deep) for ADD with images.
     */
    private function collect_add_folders(string $source_dir): array {
        $exclude = Site_Builder_Helpers::get_excluded_folders();
        $tasks = [];
        $items = @scandir($source_dir);
        if (!$items) return $tasks;

        foreach ($items as $item) {
            if (in_array($item, $exclude, true)) continue;
            $full = $source_dir . '/' . $item;
            if (!is_dir($full)) continue;

            // Add the folder even if it has no HTML — the importer will report
            // the missing file in the journal instead of silently dropping it.
            $tasks[] = [
                'data' => [
                    'full_path' => $full,
                    'slug'      => $item,
                    'mode'      => 'folder',
                ],
            ];
        }
        return $tasks;
    }

    /**
     * Collect flat .html files (no images) for ADD without images.
     */
    private function collect_add_flat(string $source_dir): array {
        $tasks = [];
        $files = glob($source_dir . '/*.html') ?: [];
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $basename = basename($file);
            $slug = preg_replace('/\.html?$/i', '', $basename);
            $slug = sanitize_title($slug);
            if (!$slug) continue;

            $tasks[] = [
                'data' => [
                    'full_path' => $file,
                    'slug'      => $slug,
                    'mode'      => 'flat',
                ],
            ];
        }
        return $tasks;
    }

    /**
     * Auto-detect the actual content root of an archive.
     *
     * Newer archives ship inside one or more wrapper folders, e.g.
     *   {given}/wrapper-A/wrapper-B/domain.com/<real content>
     * This walks down those wrappers until it finds a folder that looks like the real root.
     *
     * Definition of "real root":
     *   - CREATE: has a 'hub' subfolder, OR has ≥2 subfolders that contain HTML files.
     *   - ADD: has any HTML file in itself, OR ≥1 subfolder with HTML files.
     *
     * Wrapper detection: a folder is a wrapper if it contains exactly one significant
     * sub-item (a folder, not counting .md/.docx/loose files) and that sub-item doesn't
     * itself satisfy the real-root predicate. We descend through wrappers up to 5 levels.
     *
     * Returns the original $source_dir if no descent is needed or possible (backward
     * compatibility with archives that are already in the right shape).
     */
    private function find_real_root(string $source_dir, string $mode): string {
        $current = rtrim($source_dir, '/');
        $exclude = Site_Builder_Helpers::get_excluded_folders();

        for ($i = 0; $i < 5; $i++) {
            if ($this->looks_like_real_root($current, $mode)) {
                return $current;
            }
            // Find the single "significant" subfolder, if any
            $items = @scandir($current);
            if (!$items) return $current;

            $candidate = null;
            $candidate_count = 0;
            foreach ($items as $item) {
                if (in_array($item, $exclude, true)) continue;
                $full = $current . '/' . $item;
                if (!is_dir($full)) continue;
                $candidate = $full;
                $candidate_count++;
            }
            // Only descend if there's exactly one subfolder to descend into.
            // Multiple subfolders means we're already at the root (or no clear path).
            if ($candidate_count !== 1) {
                return $current;
            }
            $current = $candidate;
        }
        return $current;
    }

    /**
     * Whether the given folder satisfies the "real root" predicate for the given mode.
     */
    private function looks_like_real_root(string $dir, string $mode): bool {
        $exclude = Site_Builder_Helpers::get_excluded_folders();
        $items = @scandir($dir);
        if (!$items) return false;

        if ($mode === 'create') {
            // CREATE: 'hub' subfolder is the strongest signal
            if (is_dir($dir . '/hub')) return true;
            // Or ≥2 subfolders that contain HTML
            $subfolders_with_html = 0;
            foreach ($items as $item) {
                if (in_array($item, $exclude, true)) continue;
                $full = $dir . '/' . $item;
                if (!is_dir($full)) continue;
                if (Site_Builder_Helpers::has_html_file($full)) {
                    $subfolders_with_html++;
                    if ($subfolders_with_html >= 2) return true;
                }
            }
            return false;
        }

        // ADD: HTML in current dir, or ≥1 subfolder with HTML
        if (Site_Builder_Helpers::has_html_file($dir)) return true;
        foreach ($items as $item) {
            if (in_array($item, $exclude, true)) continue;
            $full = $dir . '/' . $item;
            if (!is_dir($full)) continue;
            if (Site_Builder_Helpers::has_html_file($full)) return true;
        }
        return false;
    }

    /**
     * Collect all existing pages (excluding trash/auto-draft) for the WIPE phase.
     */
    private function collect_existing_pages(): array {
        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft')"
        );
        return array_map('intval', $ids ?: []);
    }

    /**
     * Recursively scan the source folder and produce a flat list of page tasks.
     */
    private function collect_page_folders(string $base_dir): array {
        $tasks = [];
        $this->scan($base_dir, '', 0, $tasks);
        return $tasks;
    }

    private function scan(string $path, string $parent_slug_path, int $level, array &$tasks): void {
        $exclude = Site_Builder_Helpers::get_excluded_folders();
        $items = @scandir($path);
        if (!$items) return;

        foreach ($items as $item) {
            if (in_array($item, $exclude, true)) continue;
            $full = $path . '/' . $item;
            if (!is_dir($full)) continue;

            $tasks[] = [
                'data' => [
                    'full_path'   => $full,
                    'slug'        => $item,
                    'parent_path' => $parent_slug_path,
                    'level'       => $level,
                ],
            ];

            $next_parent = $parent_slug_path === '' ? $item : ($parent_slug_path . '/' . $item);
            $this->scan($full, $next_parent, $level + 1, $tasks);
        }
    }

    /**
     * Decide publish date/status for every page task.
     *
     * Rules (per project spec):
     *   - Level 0 (roots): always publish immediately
     *   - First N nested pages (immediate_count): publish immediately
     *   - Remaining nested pages: per schedule_mode
     *       'instant'  -> all immediate
     *       'one_day'  -> 1 page per day (delay_days = idx)
     *       'period'   -> stretched over $days days
     *   - If wait_week is true, every delayed page is shifted forward by 7 days.
     *     Instant publications are not affected.
     *
     * Returns an array of ['status' => ..., 'date' => 'Y-m-d H:i:s'] matching tasks index.
     */
    private function compute_schedule(array $tasks, array $settings): array {
        $now = current_time('mysql');
        $mode = $settings['schedule_mode'] ?? 'instant';
        $days = max(1, (int)($settings['days'] ?? 60));
        $immediate_count = max(0, (int)($settings['immediate_count'] ?? 10));
        $wait_week_offset = !empty($settings['wait_week']) ? 7 * 86400 : 0;

        // Count nested pages eligible for delay
        $nested_total = 0;
        foreach ($tasks as $t) {
            if (($t['data']['level'] ?? 0) > 0) $nested_total++;
        }
        $delayed_total = max(0, $nested_total - $immediate_count);
        $interval_seconds = ($mode === 'period' && $delayed_total > 0)
            ? (int)floor($days * 86400 / $delayed_total)
            : 0;

        $schedule = [];
        $nested_seen = 0;

        foreach ($tasks as $idx => $t) {
            $level = (int)($t['data']['level'] ?? 0);

            if ($level === 0) {
                $schedule[$idx] = ['status' => 'publish', 'date' => $now];
                continue;
            }

            if ($mode === 'instant' || $nested_seen < $immediate_count) {
                $schedule[$idx] = ['status' => 'publish', 'date' => $now];
                $nested_seen++;
                continue;
            }

            $delayed_index = $nested_seen - $immediate_count;
            $nested_seen++;

            if ($mode === 'one_day') {
                $delay_seconds = ($delayed_index + 1) * 86400;
            } else { // period
                $delay_seconds = ($delayed_index + 1) * $interval_seconds;
            }

            // Apply optional one-week offset for "warmup before delayed publications"
            $delay_seconds += $wait_week_offset;

            $date = date('Y-m-d H:i:s', strtotime($now) + $delay_seconds);
            $schedule[$idx] = ['status' => 'future', 'date' => $date];
        }

        return $schedule;
    }
}
