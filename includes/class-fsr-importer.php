<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FSR Importer (File System Routing format).
 *
 * Handles a single page-task from the FSR-mode queue. One task = one folder. The
 * folder name carries the slug plus a bag of flags ([M], [F], [U], [A], [W], [N],
 * [E], [DLY]) — see README_FLAG.md in the test archive for the full grammar.
 *
 * STAGE A SCOPE (this file's current responsibility):
 *   - Parse folder name into slug + flags (flags are parsed and forwarded into
 *     post_meta for later stages, but the heavy lifting — menu placement,
 *     scheduling, image loading — is not yet wired up).
 *   - Parse content from index.md (YAML frontmatter + body) or index.html
 *     (<head> meta tags + body).
 *   - Markdown → HTML with the new conventions (figure/figcaption from
 *     `![alt](url)\n*caption*`), reusing the proven md→html bits from MD Restore.
 *   - Replace inline shortcodes ($$CY$$, $$SITE_NAME$$, $$CURRENT_DATE$$,
 *     $$CURRENT_DATE_ISO$$).
 *   - Create the WordPress page with correct slug, parent, title, content, and
 *     SEO meta (Yoast + Rank Math, same as MD Restore).
 *
 * NOT in stage A (deferred to later stages):
 *   - Image upload from IMAGES/, rewriting of src in HTML, featured image
 *   - Menu placement (M, F flags)
 *   - post_meta flags for U/A/W/N/E (parsed and stored on the task so we can
 *     pick them up in stage B without re-parsing)
 *   - Scheduled publication (DLY flag)
 *   - logo.png / icon.png handling, styles.css copy
 *
 * Hierarchy semantics — same as MD Restore (0.7.4aus2):
 *   - Root page (the archive's top-level index.md/index.html, no folder around
 *     it) becomes the WordPress front page via show_on_front=page +
 *     page_on_front. Its URL is `/`, not `/home/`.
 *   - Top-level child folders (depth=1) have post_parent=0 — they live as
 *     siblings of the root.
 *   - Deeper folders chain via slug match.
 */
class Site_Builder_FSR_Importer {

    private Site_Builder_Import_Tracker $tracker;
    private int $import_id;
    private int $menu_main_id;
    private int $menu_footer_id;
    private ?Site_Builder_FSR_Image_Resolver $image_resolver;
    /**
     * Operating mode:
     *   - 'create' (default): full site import. Wipes existing pages BEFORE this
     *     importer runs (see Task_Builder::build_fsr_queue), inserts the front
     *     page, builds menus from scratch, sets logo/icon/styles.
     *   - 'add': extend an existing site. Skips front-page injection, skips
     *     init_site_assets entirely, treats slug+parent collisions as gentle
     *     "already exists" warnings rather than errors. Menus and the front
     *     page are left as the user configured them.
     */
    private string $mode = 'create';
    /**
     * Schedule config for [DLY] pages. Shape:
     *   ['mode' => 'instant'|'one_day'|'period', 'days' => int, 'wait_week' => bool, 'start_ts' => int]
     * 'start_ts' is the unix timestamp used as t=0 for the delay calculation —
     * passed in once at the start of the import so every page in the batch uses
     * the same reference point.
     */
    private array $schedule = ['mode' => 'instant', 'days' => 60, 'wait_week' => false, 'start_ts' => 0];
    /** Running counter of [DLY] pages encountered, used to stagger their dates. */
    private int $dly_index = 0;
    /** Total [DLY] pages in this import (set up-front so 'period' mode can divide correctly). */
    private int $dly_total = 0;

    public function __construct(
        Site_Builder_Import_Tracker $tracker,
        int $import_id,
        int $menu_main_id = 0,
        int $menu_footer_id = 0,
        ?Site_Builder_FSR_Image_Resolver $image_resolver = null
    ) {
        $this->tracker = $tracker;
        $this->import_id = $import_id;
        $this->menu_main_id = $menu_main_id;
        $this->menu_footer_id = $menu_footer_id;
        $this->image_resolver = $image_resolver;
    }

    /**
     * Set the schedule configuration for DLY pages. Called once per import.
     *
     * @param array $schedule  ['mode' => 'instant'|'one_day'|'period', 'days' => int, 'wait_week' => bool, 'start_ts' => int]
     * @param int   $dly_total Number of [DLY] pages in the entire import — used so 'period' mode can spread them evenly.
     */
    public function set_schedule(array $schedule, int $dly_total): void {
        $this->schedule = array_merge($this->schedule, $schedule);
        $this->dly_total = max(0, $dly_total);
        $this->dly_index = 0;
    }

    /**
     * Set operating mode. Accepts 'create' or 'add'; anything else is treated
     * as 'create' to fail safe.
     */
    public function set_mode(string $mode): void {
        $this->mode = ($mode === 'add') ? 'add' : 'create';
    }

    /**
     * Process one FSR-page task.
     * Task data:
     *   - 'folder_path': absolute path to the page's folder (or '' for the root page,
     *                    which lives directly in the archive root)
     *   - 'index_file':  absolute path to index.md or index.html (or '' if neither —
     *                    the folder is then a pure container with no content)
     *   - 'slug':        clean URL slug (folder name with flag tags stripped)
     *   - 'flags':       parsed flag bag (see parse_flags())
     *   - 'segments':    URL segments from archive root, e.g. ['articles', 'foo']
     *   - 'is_root':     true if this is the archive's top-level index
     */
    public function import_page(array $task): array {
        $data = $task['data'] ?? [];
        $folder_path = (string)($data['folder_path'] ?? '');
        $index_file  = (string)($data['index_file']  ?? '');
        $slug        = (string)($data['slug']        ?? '');
        $flags       = (array) ($data['flags']       ?? []);
        $segments    = (array) ($data['segments']    ?? []);
        $is_root     = !empty($data['is_root']);

        // 1. Resolve content
        if ($index_file !== '' && is_readable($index_file)) {
            $ext = strtolower(pathinfo($index_file, PATHINFO_EXTENSION));
            $raw = (string)@file_get_contents($index_file);
            if ($raw === '') {
                return ['ok' => false, 'title' => $slug, 'message' => 'index-файл пустой'];
            }
            // Container-block warnings (unknown blocks, max_per_page violations)
            // surface via reference so we can log them per-page below.
            $block_warnings = [];
            $parsed = $ext === 'md'
                ? $this->parse_md_file($raw, $block_warnings)
                : $this->parse_html_file($raw);

            // Log block warnings against this page's context — the journal
            // shows them with the file path so QA can find the offending
            // markdown quickly.
            if (!empty($block_warnings)) {
                foreach ($block_warnings as $warn) {
                    $this->tracker->append_error(
                        $this->import_id,
                        $warn . ' (' . $index_file . ')',
                        ['kind' => 'fsr_block_syntax']
                    );
                }
            }
        } else {
            // Container page — empty content, title from flag label or slug
            $parsed = [
                'title'       => $this->title_from_flags_or_slug($flags, $slug),
                'description' => '',
                'headline'    => '',
                'headimg'     => '',
                'content'     => '',
            ];
        }

        // 2. Replace inline shortcodes in all text fields
        foreach (['title', 'description', 'headline', 'content'] as $f) {
            $parsed[$f] = $this->replace_inline_shortcodes((string)$parsed[$f]);
        }

        // 2.5. Process images: resolve every <img src=""> in the page HTML to an
        // actual file on disk, upload to media library, rewrite src. Same for
        // the featured image declared in frontmatter (headimg). The resolver
        // applies a multi-level lookup strategy — see Site_Builder_FSR_Image_Resolver.
        $img_stats = ['found' => 0, 'uploaded' => 0, 'missing' => 0];
        $featured_attach_id = 0;
        if ($this->image_resolver) {
            // page_dir: the folder containing this page's index.* (or archive root for the front page)
            $page_dir = $folder_path !== '' ? $folder_path : dirname((string)$index_file);

            if ($parsed['content'] !== '') {
                $img_result = $this->image_resolver->process_content($parsed['content'], $page_dir);
                $parsed['content'] = $img_result['html'];
                $img_stats = [
                    'found'    => $img_result['found'],
                    'uploaded' => $img_result['uploaded'],
                    'missing'  => $img_result['missing'],
                ];
            }
            if ($parsed['headimg'] !== '') {
                $featured_attach_id = (int)$this->image_resolver->resolve_and_upload(
                    $parsed['headimg'], $page_dir, $parsed['headline']
                );
            }
        }

        // 2.6. Convert every relative <a href="..."> in the page HTML into a
        // $$LINK slug | text$$ inline shortcode. The theme's link-resolver
        // module resolves these at render time against the current site's
        // published pages — if the target page exists, it becomes a real
        // link; if it doesn't yet (e.g. a DLY-scheduled page still in future
        // status), it renders as plain text. Once the target page publishes,
        // the theme's cache invalidates and the link starts working
        // automatically. External URLs (http://, mailto:, tel:, etc.) are
        // left untouched — the theme's external-links module handles them
        // separately.
        if ($parsed['content'] !== '') {
            $parsed['content'] = $this->convert_internal_links_to_shortcodes($parsed['content']);
        }

        // 3. Determine page parent (chain through previous segments)
        $parent_id = $is_root ? 0 : $this->resolve_parent_id(array_slice($segments, 0, -1));
        if ($parent_id === null) {
            return ['ok' => false, 'title' => $parsed['title'],
                'message' => 'Родительская страница не найдена: /' . implode('/', array_slice($segments, 0, -1))];
        }

        // 4. Collision check
        $existing = get_posts([
            'post_type'      => 'page',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'name'           => $slug,
            'post_parent'    => $parent_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'suppress_filters' => true,
        ]);
        if (!empty($existing)) {
            // ADD mode: existing pages are expected — we're extending the site.
            // Report as a successful "skipped" outcome (ok=true so it doesn't
            // bloat the error count, with a clear message so the report shows it).
            if ($this->mode === 'add') {
                return [
                    'ok'      => true,
                    'title'   => $parsed['title'],
                    'message' => 'Уже существует, пропущена (id=' . (int)$existing[0] . ')',
                    'post_id' => (int)$existing[0],
                    'skipped' => true,
                ];
            }
            return ['ok' => false, 'title' => $parsed['title'],
                'message' => 'Страница с таким slug+parent уже существует (id=' . (int)$existing[0] . ')'];
        }

        // 5. Insert the page
        // 5. Determine publication schedule for this page.
        //
        // [DLY] without date → use the import-wide schedule (staggered across days).
        //                      The sequence index dly_seq is assigned by Task_Builder
        //                      across the whole queue, so dates are deterministic
        //                      regardless of which batch the page lands in.
        // [DLY=YYYY-MM-DD]   → hard date, exactly that day at midnight local time.
        // No DLY flag        → publish immediately (the normal case).
        $post_status = 'publish';
        $post_date   = '';
        if (!empty($flags['dly'])) {
            if (!empty($flags['dly_date'])) {
                // Explicit date — randomize time-of-day within the specified day
                // so it doesn't look robotic. Deterministic per-page (same date
                // on re-import) via crc32 seed of dly_date + slug.
                $seed = crc32('sb-dly-fixed-' . $flags['dly_date'] . '-' . $slug);
                $daytime_seconds = 9 * 3600 + ($seed % (13 * 3600)); // [9:00, 22:00)
                $day_ts = strtotime($flags['dly_date'] . ' 00:00:00');
                if ($day_ts !== false) {
                    $ts = $day_ts + $daytime_seconds;
                    if ($ts > time()) {
                        $post_status = 'future';
                        $post_date   = date('Y-m-d H:i:s', $ts);
                    }
                }
            } else {
                $dly_seq = (int)($data['dly_seq'] ?? 0);
                $sched = $this->compute_dly_schedule($dly_seq);
                if ($sched['date'] !== '' && strtotime($sched['date']) > time()) {
                    $post_status = 'future';
                    $post_date   = $sched['date'];
                }
            }
        }

        // 6. Insert the page.
        $insert_args = [
            'post_title'   => $parsed['title'] !== '' ? $parsed['title'] : $slug,
            'post_name'    => $slug,
            'post_content' => $parsed['content'],
            'post_status'  => $post_status,
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
            'menu_order'   => 0,
        ];
        if ($post_status === 'future' && $post_date !== '') {
            $insert_args['post_date']     = $post_date;
            $insert_args['post_date_gmt'] = get_gmt_from_date($post_date);
        }
        $post_id = wp_insert_post($insert_args, true);

        if (is_wp_error($post_id) || !$post_id) {
            $err = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post вернул 0';
            return ['ok' => false, 'title' => $parsed['title'], 'message' => 'Не удалось создать страницу: ' . $err];
        }

        // 6. SEO meta — apply the user-configured field mapping. Each slot
        // (seo_title, meta_description, social_headline, og_description, h1_title)
        // maps to zero or more meta_keys; we write the same value to every key
        // the user has selected for that slot.
        $mapping = Site_Builder_Field_Mapping::get_mapping();
        $slot_values = [
            'seo_title'        => $parsed['title'],
            'meta_description' => $parsed['description'],
            'social_headline'  => $parsed['headline'],
            'og_description'   => $parsed['description'],
            'h1_title'         => $parsed['headline'],
        ];
        foreach ($slot_values as $slot => $value) {
            if ($value === '') continue;
            foreach (($mapping[$slot] ?? []) as $meta_key) {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        // 7. Apply flags.
        //
        // (a) Save the raw flag bag for inspection. The keys do NOT start with '_'
        //     so they're visible in the standard Custom Fields panel (WordPress
        //     hides underscore-prefixed meta by default).
        //
        //     Note: WP Block Editor's Custom Fields panel only displays SCALAR
        //     meta values, not arrays. fsr_flags is an array (serialized in DB),
        //     so it shows up empty in the UI. To make flags visible at a glance
        //     we additionally write fsr_flags_summary as a plain string.
        if (!empty($flags)) {
            update_post_meta($post_id, 'fsr_flags', $flags);
            update_post_meta($post_id, 'fsr_flags_summary', self::flags_to_summary($flags));
        }
        // Guaranteed SEO fallback keys — always write frontmatter values into
        // our own fsr_* meta regardless of Field_Mapping. If the site later
        // switches Yoast/RankMath on/off, or the mapping is reconfigured,
        // these ensure the theme still has canonical frontmatter values to
        // fall back to. Distinct from Field_Mapping (which writes to
        // Yoast/RankMath keys the site may or may not read).
        update_post_meta($post_id, 'fsr_title',       $parsed['title']);
        update_post_meta($post_id, 'fsr_description', $parsed['description']);
        if ($parsed['headline'] !== '') {
            update_post_meta($post_id, 'fsr_headline', $parsed['headline']);
        }
        if ($parsed['headimg'] !== '') {
            update_post_meta($post_id, 'fsr_headimg', $parsed['headimg']);
        }

        // Featured image: if the resolver uploaded headimg, set as WP thumbnail
        // and also write its URL into the user-configured featured_image slot.
        if ($featured_attach_id > 0) {
            set_post_thumbnail($post_id, $featured_attach_id);
            $featured_url = (string)wp_get_attachment_url($featured_attach_id);
            if ($featured_url !== '' && !empty($mapping['featured_image'])) {
                foreach ($mapping['featured_image'] as $meta_key) {
                    update_post_meta($post_id, $meta_key, $featured_url);
                }
            }
        }

        // (b) Single-letter behaviour flags become individual post_meta entries
        //     so themes can read them with simple get_post_meta($id, 'fsr_utility', true).
        //     We always write 0/1, never delete — this way themes can rely on the key
        //     being present and don't have to check existence separately.
        update_post_meta($post_id, 'fsr_utility',        !empty($flags['utility'])  ? 1 : 0);
        update_post_meta($post_id, 'fsr_articles_grid', !empty($flags['articles']) ? 1 : 0);
        update_post_meta($post_id, 'fsr_about',          !empty($flags['about'])    ? 1 : 0);
        update_post_meta($post_id, 'fsr_news_page',      !empty($flags['news'])     ? 1 : 0);
        update_post_meta($post_id, 'fsr_events_page',    !empty($flags['events'])   ? 1 : 0);

        // (c) Menu placement.
        // If the flag included an explicit [;label], we pass that verbatim
        // and mark the menu item as 'label' (Menu_Sync won't overwrite it).
        // Otherwise we pass the page title and mark as 'auto', so title
        // edits in the admin will re-sync into the menu.
        $menu_label = $parsed['title'] !== '' ? $parsed['title'] : $slug;
        if (!empty($flags['menu']['main']) && $this->menu_main_id > 0) {
            $has_explicit_main_label = !empty($flags['menu']['label']);
            $this->add_to_menu(
                $this->menu_main_id,
                $post_id,
                $has_explicit_main_label ? $flags['menu']['label'] : $menu_label,
                $flags['menu']['order'] ?? null,
                $segments,
                'main',
                $has_explicit_main_label
            );
            if (isset($flags['menu']['depth'])) {
                update_post_meta($post_id, 'fsr_menu_depth', (int)$flags['menu']['depth']);
            }
        }
        if (!empty($flags['footer']['enabled']) && $this->menu_footer_id > 0) {
            $has_explicit_footer_label = !empty($flags['footer']['label']);
            $this->add_to_menu(
                $this->menu_footer_id,
                $post_id,
                $has_explicit_footer_label ? $flags['footer']['label'] : $menu_label,
                $flags['footer']['order'] ?? null,
                $segments,
                'footer',
                $has_explicit_footer_label
            );
        }

        $this->tracker->track_item($this->import_id, 'page', (int)$post_id);

        // 8. If this is the archive root, make it the WP front page AND inject its
        // HTML into the theme's front-page.php (where applicable).
        //
        // Why both: WordPress's standard show_on_front/page_on_front mechanism
        // tells the theme "use this page", but if the theme has its own
        // front-page.php (as the minipages theme does), that file overrides
        // page.php and may not call the_content(). The minipages theme uses a
        // <!-- Enter Code --> marker into which the homepage HTML is injected.
        // To keep things working without forcing every theme to be rewritten,
        // we do the marker replacement when the marker is present, and the
        // standard front-page assignment always.
        if ($is_root && $this->mode === 'create') {
            $this->tracker->track_item($this->import_id, 'option_snapshot', null, null, [
                'show_on_front' => get_option('show_on_front'),
                'page_on_front' => get_option('page_on_front'),
            ]);
            update_option('show_on_front', 'page');
            update_option('page_on_front', (int)$post_id);

            if ($parsed['content'] !== '') {
                $this->inject_into_theme_front_page($parsed['content']);
            }
        }
        // ADD mode: root page (if it even appears in an ADD archive — typically
        // wouldn't) is just one more page. We do NOT touch page_on_front, do NOT
        // inject into front-page.php — the user's existing home stays intact.

        // Build the result message — include image stats if any work was done
        $msg = 'Создана' . ($index_file === '' ? ' (контейнер)' : '');
        if ($img_stats['found'] > 0) {
            $msg .= ' [' . $img_stats['uploaded'] . '/' . $img_stats['found'] . ' картинок]';
        }

        return [
            'ok'         => true,
            'title'      => $parsed['title'],
            'message'    => $msg,
            'post_id'    => (int)$post_id,
            'img_stats'  => $img_stats,
            'featured'   => $featured_attach_id,
        ];
    }

    // -------------------------------------------------------------------------
    // FOLDER NAME PARSING
    // -------------------------------------------------------------------------

    /**
     * Split a raw folder name into slug + flags.
     * Example: "articles [6M;Artículos][U][A]" →
     *   ['slug' => 'articles', 'flags' => [...parsed...]]
     *
     * The slug is everything up to the first '[' (trimmed). Each [...]-bracketed
     * chunk after that is fed to parse_flags().
     */
    public static function parse_folder_name(string $name): array {
        $name = trim($name);

        // Find first '['; everything before it is the slug
        $bracket_pos = strpos($name, '[');
        if ($bracket_pos === false) {
            return ['slug' => $name, 'flags' => self::empty_flag_bag()];
        }

        $slug = trim(substr($name, 0, $bracket_pos));
        $rest = substr($name, $bracket_pos);

        // Pull out every "[...]" chunk
        if (!preg_match_all('/\[([^\]]*)\]/u', $rest, $matches)) {
            return ['slug' => $slug, 'flags' => self::empty_flag_bag()];
        }

        $flags = self::empty_flag_bag();
        foreach ($matches[1] as $tag) {
            self::merge_flag($flags, $tag);
        }
        return ['slug' => $slug, 'flags' => $flags];
    }

    /**
     * Parse a single flag (the contents between '[' and ']') and merge into the bag.
     *
     * Grammar:
     *   - Menu:    [<order>M<depth>;<label>]   e.g. "6M;Artículos", "1M3", "M2", "1M"
     *   - Footer:  [<order>F;<label>]          e.g. "1F", "F;Footer", "1F;Footer"
     *   - Single letters: U, A, W, N, E
     *   - DLY:     "DLY" or "DLY=YYYY-MM-DD"
     *
     * Unknown flags are stored in a "raw_unknown" bucket so we can warn in the
     * report rather than silently discarding.
     */
    private static function merge_flag(array &$flags, string $tag): void {
        $tag = trim($tag);
        if ($tag === '') return;

        // DLY[=date]
        if (preg_match('/^DLY(?:=(\d{4}-\d{2}-\d{2}))?$/i', $tag, $m)) {
            $flags['dly'] = true;
            if (!empty($m[1])) $flags['dly_date'] = $m[1];
            return;
        }

        // Menu: [<order>M<depth>;<label>]  e.g. "6M;Artículos", "1M3", "M"
        if (preg_match('/^(\d+)?M(\d+)?(?:;(.*))?$/u', $tag, $m)) {
            $flags['menu']['main']  = true;
            if (isset($m[1]) && $m[1] !== '') $flags['menu']['order'] = (int)$m[1];
            if (isset($m[2]) && $m[2] !== '') $flags['menu']['depth'] = (int)$m[2];
            if (isset($m[3]) && $m[3] !== '') $flags['menu']['label'] = trim($m[3]);
            return;
        }

        // Footer: [<order>F;<label>]
        if (preg_match('/^(\d+)?F(?:;(.*))?$/u', $tag, $m)) {
            $flags['footer']['enabled'] = true;
            if (isset($m[1]) && $m[1] !== '') $flags['footer']['order'] = (int)$m[1];
            if (isset($m[2]) && $m[2] !== '') $flags['footer']['label'] = trim($m[2]);
            return;
        }

        // Single-letter flags
        $upper = strtoupper($tag);
        switch ($upper) {
            case 'U': $flags['utility']  = true; return;
            case 'A': $flags['articles'] = true; return;
            case 'W': $flags['about']    = true; return;
            case 'N': $flags['news']     = true; return;
            case 'E': $flags['events']   = true; return;
        }

        // Unknown — keep verbatim for warning
        $flags['raw_unknown'][] = $tag;
    }

    private static function empty_flag_bag(): array {
        return [
            'menu'        => ['main' => false],
            'footer'      => ['enabled' => false],
            'utility'     => false,
            'articles'    => false,
            'about'       => false,
            'news'        => false,
            'events'      => false,
            'dly'         => false,
            'raw_unknown' => [],
        ];
    }

    /**
     * Public accessor for the empty flag bag — used by the task builder when
     * constructing the synthetic task for the archive root, which has no folder
     * name to parse flags from.
     */
    public static function empty_flag_bag_public(): array {
        return self::empty_flag_bag();
    }

    /**
     * Initialize site-wide assets that are configured once per import, not per page:
     *   - logo.{png,jpg,svg,ico}  → custom_logo theme_mod (and site_logo option)
     *   - icon.{png,jpg,svg,ico}  → site_icon option (browser favicon)
     *   - styles.css              → copied into theme's imported-styles/ folder
     *
     * Each modified option is snapshotted via the tracker so rollback restores
     * the previous state. logo and icon attachment uploads go through Media_Handler
     * which also tracks them for rollback.
     */
    public function init_site_assets(string $archive_root): array {
        $stats = ['logo' => 0, 'icon' => 0, 'styles' => 0, 'messages' => []];

        // ADD mode: preserve the user's existing logo, icon, and stylesheet —
        // we're extending the site, not redoing it.
        if ($this->mode === 'add') {
            $stats['messages'][] = 'Режим расширения: лого/иконка/стили оставлены без изменений';
            return $stats;
        }

        if (!$this->image_resolver) {
            $stats['messages'][] = 'image resolver не передан (init пропущен)';
            return $stats;
        }

        // --- LOGO ---
        $logo_path = $this->find_branded_file($archive_root, 'logo');
        if ($logo_path) {
            // We pipe the upload via the resolver's underlying media handler.
            // resolve_and_upload bypasses the multi-level lookup since we already
            // have the absolute path — pass an empty src and a "page_dir"
            // pointing at the file's own dir so step-1 of the strategy hits.
            $attach_id = $this->image_resolver->resolve_and_upload(basename($logo_path), dirname($logo_path), 'Site logo');
            if ($attach_id) {
                $old_logo = (int)get_theme_mod('custom_logo', 0);
                $old_site_logo = (int)get_option('site_logo', 0);
                $this->tracker->track_item($this->import_id, 'option_snapshot', null, null, [
                    'theme_mod:custom_logo' => $old_logo,
                    'site_logo'             => $old_site_logo,
                ]);
                set_theme_mod('custom_logo', $attach_id);
                update_option('site_logo', $attach_id);
                $stats['logo'] = $attach_id;
                $stats['messages'][] = "Логотип установлен (attachment_id={$attach_id})";
            }
        } else {
            $stats['messages'][] = 'logo.{png,jpg,svg,ico} в архиве не найден';
        }

        // --- ICON (favicon) ---
        $icon_path = $this->find_branded_file($archive_root, 'icon');
        if ($icon_path) {
            $attach_id = $this->image_resolver->resolve_and_upload(basename($icon_path), dirname($icon_path), 'Site icon');
            if ($attach_id) {
                $old_site_icon = (int)get_option('site_icon', 0);
                $this->tracker->track_item($this->import_id, 'option_snapshot', null, null, [
                    'site_icon' => $old_site_icon,
                ]);
                update_option('site_icon', $attach_id);
                $stats['icon'] = $attach_id;
                $stats['messages'][] = "Favicon установлен (attachment_id={$attach_id})";
            }
        } else {
            $stats['messages'][] = 'icon.{png,jpg,svg,ico} в архиве не найден';
        }

        // --- STYLES.CSS ---
        $styles_src = $archive_root . '/styles.css';
        if (is_file($styles_src)) {
            $theme_dir = get_stylesheet_directory();
            $target_dir = $theme_dir . '/' . SITE_BUILDER_THEME_CSS_DIR;
            $target_file = $target_dir . '/style.css';

            // Ensure target dir exists
            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0755, true);
            }

            if (is_dir($target_dir) && is_writable($target_dir)) {
                // Snapshot existing target file if any (so rollback restores it)
                if (is_file($target_file)) {
                    $existing = (string)@file_get_contents($target_file);
                    $this->tracker->track_item(
                        $this->import_id,
                        'theme_file_snapshot',
                        null,
                        $target_file,
                        ['original' => $existing]
                    );
                } else {
                    // Mark as "originally didn't exist" so rollback knows to delete it
                    $this->tracker->track_item(
                        $this->import_id,
                        'theme_file_snapshot',
                        null,
                        $target_file,
                        ['original' => null]
                    );
                }
                $bytes = @copy($styles_src, $target_file);
                if ($bytes) {
                    $stats['styles'] = filesize($target_file);
                    $stats['messages'][] = "styles.css скопирован в тему ({$stats['styles']} байт)";
                } else {
                    $stats['messages'][] = 'Не удалось скопировать styles.css в тему';
                }
            } else {
                $stats['messages'][] = 'Папка темы недоступна для записи: ' . $target_dir;
            }
        } else {
            $stats['messages'][] = 'styles.css в архиве не найден';
        }

        // Ensure a Sitemap page exists — an HTML sitemap page (distinct from
        // wp-sitemap.xml which WordPress emits for search engines). It hosts
        // the [sb_sitemap] shortcode; the theme's sitemap.php module renders
        // the actual hierarchy. If a page with slug 'sitemap' already exists
        // (from a prior import, from the user, or from another plugin), we
        // leave it alone and only tag it with fsr_is_sitemap so the theme
        // recognizes it.
        $sitemap_slug = 'sitemap';
        $existing = get_posts([
            'post_type'      => 'page',
            'name'           => $sitemap_slug,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        if (!empty($existing)) {
            $sitemap_id = (int)$existing[0];
            // Ensure our meta flags are set on the existing page, even if it
            // pre-dated this version of the plugin.
            if ((int)get_post_meta($sitemap_id, 'fsr_is_sitemap', true) !== 1) {
                update_post_meta($sitemap_id, 'fsr_is_sitemap', 1);
            }
            if ((int)get_post_meta($sitemap_id, 'fsr_utility', true) !== 1) {
                update_post_meta($sitemap_id, 'fsr_utility', 1);
            }
            if ((int)get_post_meta($sitemap_id, 'fsr_no_index', true) !== 1) {
                update_post_meta($sitemap_id, 'fsr_no_index', 1);
            }
            $stats['messages'][] = 'Страница Sitemap уже существует (ID=' . $sitemap_id . ') — помечена';
        } else {
            $sitemap_title = self::localize_sitemap_title();
            $sitemap_id = wp_insert_post([
                'post_type'    => 'page',
                'post_title'   => $sitemap_title,
                'post_name'    => $sitemap_slug,
                'post_status'  => 'publish',
                'post_content' => '[sb_sitemap]',
                'post_author'  => get_current_user_id() ?: 1,
            ], true);
            if (is_wp_error($sitemap_id) || !$sitemap_id) {
                $stats['messages'][] = 'Не удалось создать страницу Sitemap';
            } else {
                // Meta flags (implicit [U] + no_index): sitemap page behaves as a
                // utility page — no GEO shortcodes, no related-posts, not in the
                // search index. It IS discoverable via the footer menu ([F] flag).
                update_post_meta($sitemap_id, 'fsr_is_sitemap',    1);
                update_post_meta($sitemap_id, 'fsr_utility',       1);
                update_post_meta($sitemap_id, 'fsr_no_index',      1);
                update_post_meta($sitemap_id, 'fsr_flags_summary', 'F U');

                // [F] flag — add to the footer menu if it exists. The menu
                // label uses the same localized title so the label in the
                // footer matches the page name.
                if ($this->menu_footer_id > 0) {
                    $this->add_to_menu(
                        $this->menu_footer_id,
                        (int)$sitemap_id,
                        $sitemap_title,
                        null,     // no explicit order — footer menu order is by insertion
                        [],       // no ancestors (top-level in footer menu)
                        'footer'
                    );
                }

                $this->tracker->track_item($this->import_id, 'page', (int)$sitemap_id);
                $stats['messages'][] = 'Создана страница «' . $sitemap_title . '» (ID=' . $sitemap_id . ')';
            }
        }

        return $stats;
    }

    /**
     * Return a localized display name for the auto-generated "Sitemap" page,
     * based on the current WordPress locale (get_locale()). Used both for
     * post_title and for the label in the footer menu, so the page name in the
     * menu and its title match.
     *
     * The dictionary is deliberately kept small (single string × ~40 locales) —
     * duplicating the whole theme i18n system in the plugin would be overkill
     * for one label. Locales absent from the dictionary fall back to English.
     *
     * If the WP locale is later changed by the user, the already-created
     * post_title stays in the original locale (post rows are immutable through
     * this path). To re-localize, the user can either edit the page title
     * manually or delete the page and re-run the FSR import.
     */
    private static function localize_sitemap_title(): string {
        $t = [
            'ru_RU' => 'Карта сайта',
            'pt_PT' => 'Mapa do site',   'pt_BR' => 'Mapa do site',
            'it_IT' => 'Mappa del sito',
            'es_ES' => 'Mapa del sitio', 'es_PE' => 'Mapa del sitio',
            'fr_FR' => 'Plan du site',
            'pl_PL' => 'Mapa strony',
            'cs_CZ' => 'Mapa stránek',
            'el'    => 'Χάρτης ιστότοπου',
            'ro_RO' => 'Harta site-ului',
            'sv_SE' => 'Webbplatskarta',
            'fi_FI' => 'Sivustokartta',
            'bg_BG' => 'Карта на сайта',
            'et_EE' => 'Saidikaart',
            'sl_SI' => 'Zemljevid strani',
            'sk_SK' => 'Mapa stránok',
            'hr_HR' => 'Karta stranice',
            'hu_HU' => 'Webhelytérkép',
            'is_IS' => 'Vefkort',
            'lv'    => 'Vietnes karte',
            'nb_NO' => 'Nettstedskart',
            'tr_TR' => 'Site haritası',
            // Sitemap is understood as-is in these locales, no translation needed:
            // en_*, de_*, nl_*, da_DK, lb_LU
        ];
        $locale = function_exists('get_locale') ? get_locale() : 'en_US';
        return $t[$locale] ?? 'Sitemap';
    }

    /**
     * Look for a branded asset file (logo or icon) in the archive's IMAGES/
     * folder, with PNG/JPG/SVG/ICO extensions tried in that order.
     */
    /**
     * Compute the publication date for the *current* DLY page (dly_seq-th one
     * across the whole import) based on the import-wide schedule. Returns
     * ['date' => 'Y-m-d H:i:s'].
     *
     * dly_seq is assigned by Task_Builder when the queue is built, so the date
     * is deterministic regardless of which batch the page lands in.
     *
     * Modes:
     *   - 'instant'  → empty date (caller will treat as publish-now)
     *   - 'one_day'  → +1 day per DLY page: seq=0 → +1d, seq=1 → +2d, ...
     *   - 'period'   → spread $dly_total pages evenly over $days days
     *
     * wait_week adds an upfront 7-day delay to every DLY publication.
     */
    private function compute_dly_schedule(int $dly_seq): array {
        $mode      = (string)($this->schedule['mode']     ?? 'instant');
        $days      = max(1, (int)($this->schedule['days'] ?? 60));
        $wait_week = !empty($this->schedule['wait_week']);
        $start_ts  = (int)($this->schedule['start_ts']    ?? 0);
        if ($start_ts <= 0) $start_ts = time();

        if ($mode === 'instant') {
            return ['date' => ''];
        }

        $offset_seconds = 0;
        if ($mode === 'one_day') {
            $offset_seconds = ($dly_seq + 1) * 86400;
        } elseif ($mode === 'period') {
            $total = max(1, $this->dly_total);
            $interval = (int)floor($days * 86400 / $total);
            $offset_seconds = ($dly_seq + 1) * $interval;
        }

        if ($wait_week) $offset_seconds += 7 * 86400;

        // Randomize the time-of-day so posts don't all appear at midnight
        // (which looks robotic). Distribute across daytime hours (9:00–22:00)
        // deterministically per-page so re-running the import produces the
        // same schedule.
        $seed = crc32('sb-dly-' . $dly_seq . '-' . $start_ts);
        $daytime_seconds = 9 * 3600 + ($seed % (13 * 3600)); // [9:00, 22:00)
        // Snap to the day, then apply the daytime offset
        $day_ts = strtotime(date('Y-m-d 00:00:00', $start_ts + $offset_seconds));
        $final_ts = $day_ts + $daytime_seconds;

        return ['date' => date('Y-m-d H:i:s', $final_ts)];
    }

    private function find_branded_file(string $archive_root, string $stem): ?string {
        $dir = $archive_root . '/IMAGES';
        if (!is_dir($dir)) return null;
        foreach (['png', 'jpg', 'jpeg', 'svg', 'ico'] as $ext) {
            $p = $dir . '/' . $stem . '.' . $ext;
            if (is_file($p)) return $p;
        }
        return null;
    }

    /**
     * Return a one-line human-readable summary of a flag bag, suitable for
     * display in Custom Fields or in the import report. Stable, terse format —
     * lets a human verify at a glance which flags parsed and which didn't.
     *
     * Examples:
     *   "M[order=6;label=Artículos] U A"
     *   "F[label=Sobre Nosotros] U W"
     *   "M[order=2] DLY"
     *   "(нет флагов)"
     */
    public static function flags_to_summary(array $flags): string {
        $parts = [];

        if (!empty($flags['menu']['main'])) {
            $sub = [];
            if (isset($flags['menu']['order']))  $sub[] = 'order=' . $flags['menu']['order'];
            if (isset($flags['menu']['depth']))  $sub[] = 'depth=' . $flags['menu']['depth'];
            if (isset($flags['menu']['label']))  $sub[] = 'label=' . $flags['menu']['label'];
            $parts[] = 'M' . ($sub ? '[' . implode(';', $sub) . ']' : '');
        }
        if (!empty($flags['footer']['enabled'])) {
            $sub = [];
            if (isset($flags['footer']['order'])) $sub[] = 'order=' . $flags['footer']['order'];
            if (isset($flags['footer']['label'])) $sub[] = 'label=' . $flags['footer']['label'];
            $parts[] = 'F' . ($sub ? '[' . implode(';', $sub) . ']' : '');
        }
        if (!empty($flags['utility']))  $parts[] = 'U';
        if (!empty($flags['articles'])) $parts[] = 'A';
        if (!empty($flags['about']))    $parts[] = 'W';
        if (!empty($flags['news']))     $parts[] = 'N';
        if (!empty($flags['events']))   $parts[] = 'E';
        if (!empty($flags['dly'])) {
            $parts[] = isset($flags['dly_date']) ? 'DLY=' . $flags['dly_date'] : 'DLY';
        }
        if (!empty($flags['raw_unknown'])) {
            $parts[] = 'UNKNOWN(' . implode(',', $flags['raw_unknown']) . ')';
        }

        return empty($parts) ? '(нет флагов)' : implode(' ', $parts);
    }

    private function title_from_flags_or_slug(array $flags, string $slug): string {
        if (!empty($flags['menu']['label'])) return $flags['menu']['label'];
        if (!empty($flags['footer']['label'])) return $flags['footer']['label'];
        // Slug → "Title Case" with hyphens as spaces
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    // -------------------------------------------------------------------------
    // CONTENT PARSING
    // -------------------------------------------------------------------------

    /**
     * Parse an index.md: split YAML frontmatter from body, then run md→html.
     */
    private function parse_md_file(string $raw, array &$block_warnings = []): array {
        $title = $description = $headline = $headimg = '';
        $body  = $raw;

        // Frontmatter is bounded by --- on its own lines at the very top
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            $fm = $this->parse_yaml_frontmatter($m[1]);
            $title       = (string)($fm['title']       ?? '');
            $description = (string)($fm['description'] ?? '');
            $headline    = (string)($fm['headline']    ?? '');
            $headimg     = (string)($fm['headimg']     ?? '');
            $body = $m[2];
        }

        // If frontmatter didn't supply a title, use the first H1 of the body
        // as the title. Without this, archives without YAML frontmatter (just
        // a "# Heading" at the top) end up with an empty post_title and no h1
        // on the rendered page — the h1 gets stripped from body content below
        // because in the source theme it was redundant with the page title.
        if ($title === '' && preg_match('/^\s*#\s+([^\n]+)/m', $body, $h1m)) {
            $title = trim($h1m[1]);
        }

        // Strip the first H1 (it's redundant with the page title)
        $body = preg_replace('/^\s*#\s+[^\n]+\n/', '', $body, 1);

        // Process container blocks (::: name ... :::) BEFORE the markdown
        // parser runs — this converts them to HTML that the outer parser will
        // pass through untouched. Blocks may span multiple lines and contain
        // markdown themselves, which the renderer expands. Any warnings
        // (unknown block names, max_per_page violations) are collected via
        // reference so import_page() can log them against this specific page.
        if (class_exists('Site_Builder_Blocks_Parser')) {
            $body = Site_Builder_Blocks_Parser::parse($body, $block_warnings);
        }

        $content = $this->md_to_html($body);

        return compact('title', 'description', 'headline', 'headimg', 'content');
    }

    /**
     * Parse an index.html: extract meta from <head>, content from <body>/<main>.
     */
    private function parse_html_file(string $raw): array {
        $title = $description = $headline = $headimg = '';

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $raw, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\']/i', $raw, $m)) {
            $description = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']*)["\']/i', $raw, $m)) {
            $headline = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']*)["\']/i', $raw, $m)) {
            $headimg = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // For body content, reuse the proven extraction: prefer <main>, then <article>,
        // then <body>. Strip first H1.
        $content = '';
        if (preg_match('/<article\b[^>]*>(.*?)<\/article>/is', $raw, $m)) {
            $content = trim($m[1]);
        } elseif (preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $raw, $m)) {
            $content = trim($m[1]);
        } elseif (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $raw, $m)) {
            $content = trim($m[1]);
        } else {
            $content = $raw;
        }
        // If <title> didn't supply a title, use the first <h1> from the body
        // as a fallback before stripping it. Same rationale as parse_md_file.
        if ($title === '' && preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $content, $h1m)) {
            $title = trim(html_entity_decode(strip_tags($h1m[1]), ENT_QUOTES, 'UTF-8'));
        }
        $content = preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', '', $content, 1);

        return compact('title', 'description', 'headline', 'headimg', 'content');
    }

    /**
     * Minimal YAML parser scoped to flat "key: value" / "key: \"value\"" lines.
     * Sufficient for the FSR frontmatter spec (title, description, headline,
     * headimg). Does not support nested structures, anchors, multiline scalars.
     */
    private function parse_yaml_frontmatter(string $yaml): array {
        $out = [];
        $lines = preg_split('/\r?\n/', $yaml);
        foreach ($lines as $line) {
            if (!preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/u', $line, $m)) continue;
            $key = $m[1];
            $val = trim($m[2]);
            // Strip surrounding quotes (both ' and ")
            if ((substr($val, 0, 1) === '"' && substr($val, -1) === '"') ||
                (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
                $val = substr($val, 1, -1);
                // Unescape \" and \\
                $val = str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $val);
            }
            $out[$key] = $val;
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // MARKDOWN → HTML
    // -------------------------------------------------------------------------

    /**
     * Markdown → HTML for the FSR format. Builds on the MD Restore converter and
     * adds one new convention: `![alt](url)\n*caption*` becomes
     * <figure><img><figcaption></figure>. This makes the inline-image-with-caption
     * pattern from the new archives render properly.
     */
    private function md_to_html(string $md): string {
        // Normalise line endings
        $md = str_replace(["\r\n", "\r"], "\n", $md);

        // Pre-pass: combine ![alt](url)\n*caption* into a figure marker that
        // survives line-by-line processing. We use a placeholder so the caption
        // text isn't mangled by the paragraph wrapper.
        $md = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)]+)\)\s*\n\s*\*([^*\n][^\n]*)\*\s*(?=\n|$)/u',
            function ($m) {
                $alt = trim($m[1]);
                $src = trim($m[2]);
                $cap = trim($m[3]);
                return "\n@@SB_FIG[" . base64_encode(json_encode(compact('alt', 'src', 'cap'))) . "]@@\n";
            },
            $md
        );

        $lines = explode("\n", $md);
        $html = '';
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Figure placeholder
            if (preg_match('/^@@SB_FIG\[([A-Za-z0-9+\/=]+)\]@@$/', trim($line), $m)) {
                $payload = json_decode(base64_decode($m[1]), true);
                if (is_array($payload)) {
                    $alt = esc_attr($payload['alt']);
                    // NOT esc_url(): for relative paths like "IMAGES/foo.webp" or
                    // "../../IMAGES/foo.webp" WordPress's esc_url() prepends "http://"
                    // and turns the segment into a host — yielding the broken URL
                    // "http://IMAGES/foo.webp". The image-resolver then sees a fully-
                    // qualified URL and skips the file (its rule is "absolute = leave
                    // alone"). esc_attr() is the correct escape for an HTML attribute
                    // value and preserves the path verbatim.
                    $src = esc_attr($payload['src']);
                    $cap = esc_html($payload['cap']);
                    $html .= "<figure><img src=\"{$src}\" alt=\"{$alt}\"><figcaption>{$cap}</figcaption></figure>\n";
                }
                $i++;
                continue;
            }

            // Standalone image (no caption)
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)\s*$/', $line, $m)) {
                $alt = esc_attr($m[1]);
                // Same reason as above — preserve the relative path so image-resolver
                // can find the file on disk.
                $src = esc_attr($m[2]);
                $html .= "<p><img src=\"{$src}\" alt=\"{$alt}\"></p>\n";
                $i++;
                continue;
            }

            // Heading H2-H6 (H1 already stripped by caller)
            if (preg_match('/^(#{2,6})\s+(.+)$/', $line, $m)) {
                $level = strlen($m[1]);
                $text = $this->inline_md($m[2]);
                $html .= "<h{$level}>{$text}</h{$level}>\n";
                $i++;
                continue;
            }

            // Table
            if (strpos(ltrim($line), '|') === 0 && isset($lines[$i + 1])
                && preg_match('/^\s*\|?[\s\-:|]+\|?\s*$/', $lines[$i + 1])) {
                [$tbl, $consumed] = $this->parse_table($lines, $i);
                $html .= $tbl;
                $i += $consumed;
                continue;
            }

            // Unordered list
            if (preg_match('/^\s*[-*]\s+/', $line)) {
                [$lst, $consumed] = $this->parse_list($lines, $i, 'ul');
                $html .= $lst;
                $i += $consumed;
                continue;
            }

            // Ordered list
            if (preg_match('/^\s*\d+\.\s+/', $line)) {
                [$lst, $consumed] = $this->parse_list($lines, $i, 'ol');
                $html .= $lst;
                $i += $consumed;
                continue;
            }

            // Paragraph: gather consecutive non-blank, non-special lines
            $para = [];
            while ($i < $n && trim($lines[$i]) !== ''
                && !preg_match('/^#{2,6}\s+/', $lines[$i])
                && !preg_match('/^\s*[-*]\s+/', $lines[$i])
                && !preg_match('/^\s*\d+\.\s+/', $lines[$i])
                && !preg_match('/^@@SB_FIG\[/', trim($lines[$i]))
                && !preg_match('/^!\[/', $lines[$i])
                && !(strpos(ltrim($lines[$i]), '|') === 0 && isset($lines[$i + 1])
                     && preg_match('/^\s*\|?[\s\-:|]+\|?\s*$/', $lines[$i + 1]))) {
                $para[] = $lines[$i];
                $i++;
            }
            if (!empty($para)) {
                $text = $this->inline_md(implode(' ', $para));
                $html .= "<p>{$text}</p>\n";
            }
        }

        return trim($html);
    }

    /**
     * Inline markdown: **bold**, [text](url), `code`. Italic (*x*) is intentionally
     * not handled — it conflicts with the figure-caption pre-pass and isn't used
     * in the corpus anyway.
     */
    private function inline_md(string $text): string {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = trim($m[2]);
            $label = $m[1];
            if ($url === '' || $url === '#') return $label;
            return '<a href="' . esc_url($url) . '">' . $label . '</a>';
        }, $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return $text;
    }

    private function parse_list(array $lines, int $start, string $type): array {
        $pattern = $type === 'ul' ? '/^\s*[-*]\s+(.+)$/' : '/^\s*\d+\.\s+(.+)$/';
        $items = [];
        $i = $start;
        while ($i < count($lines) && preg_match($pattern, $lines[$i], $m)) {
            $items[] = $this->inline_md($m[1]);
            $i++;
        }
        $html = "<{$type}>\n";
        foreach ($items as $item) $html .= "  <li>{$item}</li>\n";
        $html .= "</{$type}>\n";
        return [$html, $i - $start];
    }

    private function parse_table(array $lines, int $start): array {
        $i = $start;
        $header_cells = $this->split_table_row($lines[$i]);
        $i += 2;
        $body_rows = [];
        while ($i < count($lines) && strpos(ltrim($lines[$i]), '|') === 0) {
            $body_rows[] = $this->split_table_row($lines[$i]);
            $i++;
        }
        $html = '<table>' . "\n  <thead><tr>";
        foreach ($header_cells as $h) $html .= '<th>' . $this->inline_md(trim($h)) . '</th>';
        $html .= "</tr></thead>\n  <tbody>\n";
        foreach ($body_rows as $row) {
            $html .= '    <tr>';
            foreach ($row as $cell) $html .= '<td>' . $this->inline_md(trim($cell)) . '</td>';
            $html .= "</tr>\n";
        }
        $html .= "  </tbody>\n</table>\n";
        return [$html, $i - $start];
    }

    private function split_table_row(string $line): array {
        $line = trim($line);
        if (substr($line, 0, 1) === '|') $line = substr($line, 1);
        if (substr($line, -1) === '|') $line = substr($line, 0, -1);
        return array_map('trim', explode('|', $line));
    }

    // -------------------------------------------------------------------------
    // INLINE SHORTCODES
    // -------------------------------------------------------------------------

    /**
     * Replace $$SITE_NAME$$, $$CURRENT_DATE$$, $$CURRENT_DATE_ISO$$, $$CY$$.
     *
     * $$SITE_NAME$$ and $$CURRENT_DATE_ISO$$ are substituted with literal
     * values at import time — they don't need to change afterwards.
     *
     * $$CURRENT_DATE$$ and $$CY$$ are converted to shortcodes ([sb_date] and
     * [sb_year]) that the theme resolves at render time. Reason: static
     * substitution formats dates using the WordPress admin locale, but archive
     * content is usually in a different language than the WP interface. The
     * shortcode approach lets the theme localize on-the-fly using the site's
     * front-end locale, so an ES-content site with a RU-admin WP shows dates
     * in Spanish, not Russian.
     */
    /**
     * Convert every relative <a href="..."> in the given HTML into a $$LINK
     * slug | text $$ inline shortcode that the theme's link-resolver module
     * resolves at render time.
     *
     * Why: FSR archives contain cross-links between pages that are imported
     * in the same batch. If page A links to page B but B is scheduled for
     * publication next week ([DLY] flag), a normal <a href="/b/"> would 404
     * for readers who click it before B publishes. Converting to a shortcode
     * lets the theme render the link only when the target is actually live.
     *
     * External URLs (http/https/mailto/tel/anchors/protocol-relative) are
     * left as-is — the theme's external-links module adds target="_blank"
     * and rel="nofollow noopener" to them at render time.
     *
     * The slug extraction takes the last path segment and strips any file
     * extension: /statistics/nba/ → "nba", /articles/foo.html → "foo".
     * WordPress ensures post_name (slug) uniqueness, so this is unambiguous.
     */
    private function convert_internal_links_to_shortcodes(string $html): string {
        if (strpos($html, '<a ') === false) return $html;

        return preg_replace_callback(
            '#<a\s+([^>]*?)href=(["\'])([^"\']+)\2([^>]*)>(.*?)</a>#is',
            function ($m) {
                $href = $m[3];
                $text = $m[5];

                // External / non-http-relative → leave untouched
                if ($href === '' || $href[0] === '#') return $m[0];
                if (preg_match('#^(https?://|mailto:|tel:|javascript:|ftp:|sms:|data:|//)#i', $href)) {
                    return $m[0];
                }

                // Relative → extract the trailing slug
                $path = trim((string)parse_url($href, PHP_URL_PATH), '/');
                if ($path === '') return $m[0]; // href was just "/"

                $segments = explode('/', $path);
                $last = end($segments);
                // Strip file extension (e.g. .html) if present
                $slug = pathinfo($last, PATHINFO_FILENAME);
                if ($slug === '') return $m[0];

                // The <a>...</a> body may contain inline HTML (bold, italic).
                // Strip tags for the shortcode form — the target page's title
                // isn't preserved through markup anyway.
                $link_text = trim(strip_tags($text));
                if ($link_text === '') return $m[0];

                // Guard against pipe/dollar characters inside the text that
                // would break the shortcode grammar. Replace with harmless
                // alternatives — losing a literal '|' in a link text is
                // acceptable compared to breaking the shortcode.
                $link_text = str_replace(['|', '$$'], ['/', '$'], $link_text);

                return '$$LINK ' . $slug . ' | ' . $link_text . '$$';
            },
            $html
        );
    }

    /**
     * Replace $$SITE_NAME$$, $$CURRENT_DATE$$, $$CURRENT_DATE_ISO$$, $$CY$$.
     *
     * $$SITE_NAME$$ and $$CURRENT_DATE_ISO$$ are substituted with literal
     * values at import time. $$CURRENT_DATE$$ and $$CY$$ are converted to
     * shortcodes ([sb_date] and [sb_year]) that the theme resolves at render
     * time — see the docstring on the theme's shortcodes.php module for the
     * rationale (localization mismatches between WP admin and content).
     */
    private function replace_inline_shortcodes(string $text): string {
        if ($text === '' || strpos($text, '$$') === false) return $text;
        $now = current_time('timestamp');
        $replacements = [
            '$$SITE_NAME$$'         => (string)get_bloginfo('name'),
            '$$CURRENT_DATE_ISO$$'  => gmdate('Y-m-d\TH:i:s\Z'),
            // These become shortcodes — resolved by the theme at render time.
            '$$CURRENT_DATE$$'      => '[sb_date]',
            '$$CY$$'                => '[sb_year]',
        ];
        return strtr($text, $replacements);
    }

    // -------------------------------------------------------------------------
    // PARENT RESOLUTION
    // -------------------------------------------------------------------------

    /**
     * Same as MD Restore's resolve_parent_id: walk the segment chain starting at
     * the site root. Top-level pages live at post_parent=0; the root page is
     * their sibling, distinguished only by being the WP front page.
     */
    private function resolve_parent_id(array $segments): ?int {
        if (empty($segments)) return 0;
        $parent_id = 0;
        foreach ($segments as $segment) {
            $found = get_posts([
                'post_type'      => 'page',
                'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
                'name'           => $segment,
                'post_parent'    => $parent_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'suppress_filters' => true,
            ]);
            if (empty($found)) return null;
            $parent_id = (int)$found[0];
        }
        return $parent_id;
    }

    // -------------------------------------------------------------------------
    // FRONT-PAGE INJECTION
    // -------------------------------------------------------------------------

    /**
     * If the active theme has a front-page.php that contains a "<!-- Enter Code -->"
     * marker, replace the marker with the root page's HTML. Saves a pristine
     * snapshot the first time the file is touched, plus a per-import snapshot so
     * rollback can restore the file to its previous state.
     *
     * This mirrors Site_Builder_Hub_Importer::snapshot_and_replace() — the two
     * importers solve different problems but the on-disk file handling is identical.
     * Kept duplicated rather than extracted to a helper because the tracker
     * instance is per-importer and the cross-cutting class would need to carry
     * tracker plumbing that hurts readability more than the duplication.
     */
    private function inject_into_theme_front_page(string $replacement_html): void {
        $theme_dir = get_stylesheet_directory();
        $file_path = $theme_dir . '/front-page.php';

        // No front-page.php at all → WordPress will fall back to page.php (which
        // calls the_content() in all sane themes), and our show_on_front +
        // page_on_front pair routes it to the imported root page. Nothing to do.
        if (!is_file($file_path)) {
            return;
        }

        $current = (string)@file_get_contents($file_path);
        if ($current === '') return;

        $marker      = '<!-- Enter Code -->';
        $has_marker  = (strpos($current, $marker) !== false);
        // Best-effort heuristic: look for any of the common content-emitting
        // calls. False positives are fine; we only avoid the warning when at
        // least one is present.
        $has_content = (bool)preg_match('/\b(the_content|do_blocks|render_block_data|wp_render_layout_support_flag)\s*\(/', $current);

        if (!$has_marker && !$has_content) {
            // Theme has its own front-page.php but neither calls the_content() nor
            // exposes our marker. The root page won't render through it. Warn
            // the user — they can add the marker to the theme template, switch
            // themes, or accept a blank homepage.
            $this->tracker->append_error(
                $this->import_id,
                'Тема имеет front-page.php без the_content() и без маркера <!-- Enter Code --> — главная страница может отображаться пустой. Добавьте маркер в шаблон темы, либо смените тему.',
                ['kind' => 'fsr_front_page']
            );
            return;
        }

        if (!$has_marker) {
            // Theme already renders the content properly via the_content() — our
            // page_on_front assignment is enough. Don't modify the template.
            return;
        }

        // Marker present → inject into it. Requires the file to be writable.
        if (!is_writable($file_path)) {
            $this->tracker->append_error(
                $this->import_id,
                'Файл темы front-page.php не доступен для записи — инжекция контента главной пропущена. Главная может отображаться пустой.',
                ['kind' => 'fsr_front_page']
            );
            return;
        }

        $pristine_snapshots = get_option('site_builder_pristine_theme_files', []);
        if (!is_array($pristine_snapshots)) $pristine_snapshots = [];

        if (isset($pristine_snapshots[$file_path])) {
            // We've touched this file before — restore pristine before re-injecting,
            // so the marker is always present in the source we work against.
            $original = (string)$pristine_snapshots[$file_path];
            @file_put_contents($file_path, $original);
        } else {
            $pristine_snapshots[$file_path] = $current;
            update_option('site_builder_pristine_theme_files', $pristine_snapshots);
            $original = $current;
        }

        $this->tracker->track_item(
            $this->import_id,
            'theme_file_snapshot',
            null,
            $file_path,
            ['original' => $original]
        );

        $new_content = str_replace($marker, $replacement_html, $original);
        @file_put_contents($file_path, $new_content);
    }

    // -------------------------------------------------------------------------
    // MENU PLACEMENT
    // -------------------------------------------------------------------------

    /**
     * Add a page to one of the auto-created nav menus.
     *
     * Hierarchy: if a parent page (by URL segments) is already in the SAME menu,
     * the new menu item is created as that parent's child. This means a Main menu
     * tree like /articles → /articles/foo will be reproduced as a submenu IF both
     * pages carry the [M] flag. Pages whose ancestors don't have [M] become
     * top-level menu items.
     *
     * @param int    $menu_id        The nav-menu term id (main or footer).
     * @param int    $post_id        The page being added.
     * @param string $label          Display label — if $has_explicit_label is
     *                               true, used as-is; otherwise truncated by
     *                               Menu_Sync::truncate_for_menu().
     * @param int|null $order        menu_order — explicit position (lower goes
     *                               first), or null = auto.
     * @param array  $segments       URL segments of the page being added.
     * @param string $menu_kind      'main' or 'footer' — used to pick the right cache.
     * @param bool   $has_explicit_label True when the label came from a
     *                               [M;label] / [F;label] flag; false when it
     *                               was derived from post_title. Controls
     *                               whether Menu_Sync marks the item as
     *                               'label' (never overwrite) or 'auto'
     *                               (recompute on post_title changes).
     */
    private function add_to_menu(int $menu_id, int $post_id, string $label, ?int $order, array $segments, string $menu_kind, bool $has_explicit_label = false): void {
        // Find the deepest ancestor that's already in this menu, walking upward.
        $parent_menu_item_id = $this->find_menu_parent_for_segments($menu_id, $segments);

        // If no explicit [;label] was given, truncate the long SEO title for
        // menu display. Keeps items like "Bet Hjemmesider i Danmark — Komplet
        // Guide til Danske Betting Sider i 2026" from breaking the layout.
        $display_title = $has_explicit_label
            ? $label
            : Site_Builder_Menu_Sync::truncate_for_menu($label);

        // Full page title — used as native browser tooltip via attr-title
        // when it differs from the visible menu title. Redundant tooltips
        // are silenced (see the equality check below). Also resolve any
        // [sb_year]/[sb_date] shortcodes in the raw title — get_post_field
        // returns unfiltered content, so shortcodes stay as source markup
        // unless we run do_shortcode explicitly. truncate_for_menu already
        // does this for $display_title internally; we do it here for the
        // parallel $full_title path.
        $full_title = (string)get_post_field('post_title', $post_id);
        if ($full_title === '') $full_title = $label; // fallback for exotic cases
        if (strpos($full_title, '[sb_') !== false && function_exists('do_shortcode')) {
            $full_title = trim(do_shortcode($full_title));
        }
        $attr_title = ($display_title !== $full_title) ? $full_title : '';

        $args = [
            'menu-item-title'      => $display_title,
            'menu-item-attr-title' => $attr_title,
            'menu-item-object-id'  => $post_id,
            'menu-item-object'     => 'page',
            'menu-item-type'       => 'post_type',
            'menu-item-status'     => 'publish',
            'menu-item-parent-id'  => $parent_menu_item_id,
        ];
        if ($order !== null) {
            $args['menu-item-position'] = $order;
        }

        // Suppress the manual-edit detector while we create the item — this
        // is a programmatic write, not a human edit, and detect_manual_edit
        // would otherwise flag it as 'manual' before we set 'auto' below.
        $menu_item_id = Site_Builder_Menu_Sync::without_detection(function() use ($menu_id, $args) {
            return wp_update_nav_menu_item($menu_id, 0, $args);
        });
        if (is_wp_error($menu_item_id) || !$menu_item_id) return;

        // Tag as 'auto' regardless of whether an explicit label was used.
        // The label seeds the initial menu title (so first render looks right)
        // but is NOT sticky — Menu_Sync will overwrite on the next save_post
        // of the source page. The page's post_title is the source of truth,
        // per user request: "manual edit of the page title in the admin has
        // the highest priority".
        update_post_meta($menu_item_id, Site_Builder_Menu_Sync::META_KEY, 'auto');

        $this->tracker->track_item($this->import_id, 'menu_item', (int)$menu_item_id);
    }

    /**
     * Walk up the URL segment chain. For each ancestor, check whether a menu
     * item for that page exists in $menu_id. Return the deepest match's
     * menu_item_id, or 0 if no ancestor is in the menu (top-level placement).
     */
    private function find_menu_parent_for_segments(int $menu_id, array $segments): int {
        // The page being added is at $segments; its potential parents are
        // segments[0..n-2], segments[0..n-3], ..., segments[0..0].
        // We want the DEEPEST match (longest prefix).
        for ($len = count($segments) - 1; $len >= 1; $len--) {
            $ancestor_segments = array_slice($segments, 0, $len);
            $ancestor_id = $this->resolve_parent_id($ancestor_segments);
            if (!$ancestor_id) continue;

            // Find this page's menu item in the given menu
            $menu_item_id = $this->find_menu_item_for_page($menu_id, $ancestor_id);
            if ($menu_item_id) return $menu_item_id;
        }
        return 0;
    }

    /**
     * Find the menu_item id (post_id of nav_menu_item) corresponding to a page,
     * scoped to a specific menu. Returns 0 if no such item exists.
     */
    private function find_menu_item_for_page(int $menu_id, int $page_id): int {
        $items = wp_get_nav_menu_items($menu_id);
        if (!is_array($items)) return 0;
        foreach ($items as $item) {
            // For page-type menu items, object_id points to the page's post_id
            if ((int)$item->object_id === $page_id && $item->object === 'page') {
                return (int)$item->ID;
            }
        }
        return 0;
    }
}
