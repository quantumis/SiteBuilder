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

        if (is_dir($source_dir . '/hub')) {
            $queue[] = ['phase' => 'hub', 'kind' => 'hub_setup'];
        }

        $page_tasks = $this->collect_page_folders($source_dir);
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
     *
     * Returns an array of ['status' => ..., 'date' => 'Y-m-d H:i:s'] matching tasks index.
     */
    private function compute_schedule(array $tasks, array $settings): array {
        $now = current_time('mysql');
        $mode = $settings['schedule_mode'] ?? 'instant';
        $days = max(1, (int)($settings['days'] ?? 60));
        $immediate_count = max(0, (int)($settings['immediate_count'] ?? 10));

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

            $date = date('Y-m-d H:i:s', strtotime($now) + $delay_seconds);
            $schedule[$idx] = ['status' => 'future', 'date' => $date];
        }

        return $schedule;
    }
}
