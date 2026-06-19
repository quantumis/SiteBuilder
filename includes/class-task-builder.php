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
     * After build_fsr_queue, holds warnings discovered during pre-scan
     * (e.g. slug containing year, unknown flags). Each entry is a string.
     */
    public array $fsr_warnings = [];

    /**
     * After build_fsr_queue, holds blocking errors (e.g. duplicate slug across
     * the whole tree, which violates the spec's global-uniqueness rule).
     */
    public array $fsr_errors = [];

    /**
     * Folders that the FSR scanner always skips. PROMTS and IMAGES are special
     * archive folders (prompts are working material, images are referenced by
     * path from md files but aren't themselves pages). The rest are common OS
     * artefacts.
     */
    private const FSR_IGNORED_FOLDERS = [
        'IMAGES', 'PROMTS', 'PROMPTS',
        '.git', 'node_modules', '__MACOSX', '.DS_Store',
    ];

    /**
     * Build an FSR-mode queue.
     *
     * Walks $source_dir recursively, treating each subfolder as a potential page:
     *   - Folder name is parsed into slug + flags.
     *   - If index.md or index.html exists in the folder, it becomes the page's
     *     content; otherwise the folder is created as a container page (title
     *     from menu label flag, or from slug).
     *   - The archive's own index.md / index.html (the file directly in
     *     $source_dir, no folder around it) is the root/front page.
     *
     * Pre-validation:
     *   - GLOBAL slug uniqueness: spec says /articles/foo and /foo are "the same".
     *     Any repeated slug at any depth is a blocking error.
     *   - Slug containing a four-digit year (~~/my-slug-2026~~) is a warning,
     *     not an error — real archives currently contain such slugs.
     *   - Unknown flags are warnings.
     *
     * Sort order: depth ascending, then folder name. Guarantees parents exist
     * before children.
     */
    public function build_fsr_queue(string $source_dir): array {
        $this->fsr_warnings = [];
        $this->fsr_errors   = [];
        $this->resolved_root = $source_dir;

        if (!is_dir($source_dir)) {
            $this->fsr_errors[] = 'Папка архива не найдена: ' . $source_dir;
            return [];
        }

        $tasks = [];

        // 0. Initialization task: load logo/icon/styles.css. Runs before any
        // pages (depth=-1 sorts first). One-shot per import.
        $tasks[] = [
            'phase' => 'fsr',
            'kind'  => 'fsr_init',
            'data'  => [
                'archive_root' => $source_dir,
                'depth'        => -1,
            ],
        ];

        // 1. Root page — index.md or index.html directly in $source_dir.
        $root_index = '';
        foreach (['index.md', 'index.html'] as $candidate) {
            $p = $source_dir . '/' . $candidate;
            if (is_file($p)) { $root_index = $p; break; }
        }
        $tasks[] = [
            'phase' => 'fsr',
            'kind'  => 'fsr_page',
            'data'  => [
                'folder_path' => '',
                'index_file'  => $root_index,
                'slug'        => 'home',
                'flags'       => Site_Builder_FSR_Importer::empty_flag_bag_public(),
                'segments'    => [],
                'is_root'     => true,
                'depth'       => 0,
            ],
        ];

        // 2. Walk subfolders.
        $this->scan_fsr_folder($source_dir, [], $tasks);

        // 3. Validate global slug uniqueness — only across actual page tasks
        // (init/footer-like tasks have no slug and don't count).
        $slug_locations = [];
        foreach ($tasks as $t) {
            if (($t['kind'] ?? '') !== 'fsr_page') continue;
            $slug = $t['data']['slug'] ?? '';
            if ($slug === '') continue;
            $loc  = '/' . implode('/', $t['data']['segments'] ?? []);
            $slug_locations[$slug][] = $loc;
        }
        foreach ($slug_locations as $slug => $locations) {
            if (count($locations) > 1) {
                $this->fsr_errors[] = "Slug '{$slug}' встречается в нескольких местах (нарушает уникальность): "
                    . implode(', ', $locations);
            }
        }

        // 4. Sort by depth (parents first), then by slug for stable ordering.
        usort($tasks, function ($a, $b) {
            $da = (int)$a['data']['depth'];
            $db = (int)$b['data']['depth'];
            if ($da !== $db) return $da <=> $db;
            return strcmp((string)($a['data']['slug'] ?? ''), (string)($b['data']['slug'] ?? ''));
        });

        // 5. Assign a sequence index to every DLY page (without a hard date).
        // The importer uses this to compute publication dates deterministically —
        // independent of which batch a page lands in.
        $dly_seq = 0;
        foreach ($tasks as &$t) {
            if (($t['kind'] ?? '') !== 'fsr_page') continue;
            $flags = $t['data']['flags'] ?? [];
            if (!empty($flags['dly']) && empty($flags['dly_date'])) {
                $t['data']['dly_seq'] = $dly_seq;
                $dly_seq++;
            }
        }
        unset($t);

        return $tasks;
    }

    /**
     * Recursive scanner for FSR mode. Pushes one task per page-folder into $tasks.
     */
    private function scan_fsr_folder(string $current_dir, array $parent_segments, array &$tasks): void {
        $items = @scandir($current_dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $current_dir . '/' . $item;
            if (!is_dir($full)) continue;

            // Skip ignored service folders. The check is case-insensitive on the
            // first segment (so "IMAGES", "images", "Images" all match).
            if (in_array($item, self::FSR_IGNORED_FOLDERS, true)
                || in_array(strtoupper($item), self::FSR_IGNORED_FOLDERS, true)) {
                continue;
            }

            $parsed = Site_Builder_FSR_Importer::parse_folder_name($item);
            $slug   = $parsed['slug'];
            $flags  = $parsed['flags'];

            if ($slug === '') {
                $this->fsr_warnings[] = "Папка без slug: '{$item}' — пропущена";
                continue;
            }

            // Warning: slug contains a 4-digit year
            if (preg_match('/(?<![0-9])(19|20)\d{2}(?![0-9])/u', $slug)) {
                $this->fsr_warnings[] = "Slug '{$slug}' содержит год (рекомендуется убрать)";
            }
            // Warning: unknown flag tags
            if (!empty($flags['raw_unknown'])) {
                $this->fsr_warnings[] = "Папка '{$item}' содержит неизвестные флаги: "
                    . implode(', ', $flags['raw_unknown']);
            }

            // Resolve index file (md or html) — empty string means container page
            $index_file = '';
            foreach (['index.md', 'index.html'] as $candidate) {
                $p = $full . '/' . $candidate;
                if (is_file($p)) { $index_file = $p; break; }
            }

            $segments = array_merge($parent_segments, [$slug]);
            $tasks[] = [
                'phase' => 'fsr',
                'kind'  => 'fsr_page',
                'data'  => [
                    'folder_path' => $full,
                    'index_file'  => $index_file,
                    'slug'        => $slug,
                    'flags'       => $flags,
                    'segments'    => $segments,
                    'is_root'     => false,
                    'depth'       => count($segments),
                ],
            ];

            // Recurse into children
            $this->scan_fsr_folder($full, $segments, $tasks);
        }
    }

    /**
     * Build a MD-RESTORE-mode queue.
     *
     * Scans $source_dir recursively for .md files, parses each one's header to extract
     * the URL, and arranges them into a queue sorted by URL depth. The root URL (path = '/')
     * is processed first; then top-level paths; then their children, and so on. This
     * ordering guarantees that parent pages exist by the time their children are processed.
     */
    public function build_md_queue(string $source_dir): array {
        $md_files = [];
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($rii as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'md') {
                $md_files[] = $f->getPathname();
            }
        }
        sort($md_files);

        $tasks = [];
        foreach ($md_files as $path) {
            $meta = $this->parse_md_header($path);
            if (!$meta || empty($meta['url'])) continue;

            $url_path = trim((string)parse_url($meta['url'], PHP_URL_PATH), '/');
            $segments = $url_path === '' ? [] : array_values(array_filter(explode('/', $url_path)));
            $is_root  = empty($segments);

            $tasks[] = [
                'phase' => 'md',
                'kind'  => 'md_page',
                'data'  => [
                    'md_path'     => $path,
                    'url'         => $meta['url'],
                    'title'       => $meta['title'] ?? '',
                    'description' => $meta['description'] ?? '',
                    'is_root'     => $is_root,
                    'segments'    => $segments,
                    'depth'       => count($segments),
                ],
            ];
        }

        // Sort: root first, then by depth, then by URL for deterministic order
        usort($tasks, function ($a, $b) {
            $da = $a['data']['depth'];
            $db = $b['data']['depth'];
            if ($da !== $db) return $da <=> $db;
            return strcmp($a['data']['url'], $b['data']['url']);
        });

        return $tasks;
    }

    /**
     * Read the first ~30 lines of a .md and extract # URL / # Title / # Description.
     * Returns null if the file can't be read or has no URL line.
     */
    private function parse_md_header(string $path): ?array {
        $fp = @fopen($path, 'r');
        if (!$fp) return null;
        $header = '';
        $lines_read = 0;
        while ($lines_read < 30 && ($line = fgets($fp)) !== false) {
            $header .= $line;
            $lines_read++;
            if (preg_match('/^-{10,}\s*$/', trim($line))) break;
        }
        fclose($fp);

        $meta = ['url' => null, 'title' => null, 'description' => null];
        if (preg_match_all('/^#\s*([^:\n]+):\s*(.*)$/mu', $header, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $key = strtolower(trim($row[1]));
                $val = trim($row[2]);
                if ($key === 'url') $meta['url'] = $val;
                elseif ($key === 'title') $meta['title'] = $val;
                elseif ($key === 'description') $meta['description'] = $val;
            }
        }
        return $meta['url'] ? $meta : null;
    }

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
