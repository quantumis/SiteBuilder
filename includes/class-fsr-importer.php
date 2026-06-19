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

    public function __construct(Site_Builder_Import_Tracker $tracker, int $import_id, int $menu_main_id = 0, int $menu_footer_id = 0) {
        $this->tracker = $tracker;
        $this->import_id = $import_id;
        $this->menu_main_id = $menu_main_id;
        $this->menu_footer_id = $menu_footer_id;
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
            $parsed = $ext === 'md'
                ? $this->parse_md_file($raw)
                : $this->parse_html_file($raw);
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
            return ['ok' => false, 'title' => $parsed['title'],
                'message' => 'Страница с таким slug+parent уже существует (id=' . (int)$existing[0] . ')'];
        }

        // 5. Insert the page
        $post_id = wp_insert_post([
            'post_title'   => $parsed['title'] !== '' ? $parsed['title'] : $slug,
            'post_name'    => $slug,
            'post_content' => $parsed['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
            'menu_order'   => 0,
        ], true);

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
        if (!empty($flags)) {
            update_post_meta($post_id, 'fsr_flags', $flags);
        }
        if ($parsed['headline'] !== '') {
            update_post_meta($post_id, 'fsr_headline', $parsed['headline']);
        }
        if ($parsed['headimg'] !== '') {
            update_post_meta($post_id, 'fsr_headimg', $parsed['headimg']);
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
        $menu_label = $parsed['title'] !== '' ? $parsed['title'] : $slug;
        if (!empty($flags['menu']['main']) && $this->menu_main_id > 0) {
            $this->add_to_menu(
                $this->menu_main_id,
                $post_id,
                $flags['menu']['label'] ?? $menu_label,
                $flags['menu']['order'] ?? null,
                $segments,
                'main'
            );
            if (isset($flags['menu']['depth'])) {
                update_post_meta($post_id, 'fsr_menu_depth', (int)$flags['menu']['depth']);
            }
        }
        if (!empty($flags['footer']['enabled']) && $this->menu_footer_id > 0) {
            $this->add_to_menu(
                $this->menu_footer_id,
                $post_id,
                $flags['footer']['label'] ?? $menu_label,
                $flags['footer']['order'] ?? null,
                $segments,
                'footer'
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
        if ($is_root) {
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

        return [
            'ok'       => true,
            'title'    => $parsed['title'],
            'message'  => 'Создана' . ($index_file === '' ? ' (контейнер)' : ''),
            'post_id'  => (int)$post_id,
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
    private function parse_md_file(string $raw): array {
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

        // Strip the first H1 (it's redundant with the page title)
        $body = preg_replace('/^\s*#\s+[^\n]+\n/', '', $body, 1);

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
                    $src = esc_url($payload['src']);
                    $cap = esc_html($payload['cap']);
                    $html .= "<figure><img src=\"{$src}\" alt=\"{$alt}\"><figcaption>{$cap}</figcaption></figure>\n";
                }
                $i++;
                continue;
            }

            // Standalone image (no caption)
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)\s*$/', $line, $m)) {
                $alt = esc_attr($m[1]);
                $src = esc_url($m[2]);
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
     * Replacement happens at import time (not at page render), so the substituted
     * values are baked into post_content / post_title.
     */
    private function replace_inline_shortcodes(string $text): string {
        if ($text === '' || strpos($text, '$$') === false) return $text;
        $now = current_time('timestamp');
        $replacements = [
            '$$SITE_NAME$$'         => (string)get_bloginfo('name'),
            '$$CURRENT_DATE$$'      => date_i18n(get_option('date_format', 'F j, Y'), $now),
            '$$CURRENT_DATE_ISO$$'  => gmdate('Y-m-d\TH:i:s\Z'),
            '$$CY$$'                => date('Y', $now),
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
        if (!is_file($file_path) || !is_writable($file_path)) {
            // Quietly skip — themes without front-page.php (or read-only files) just
            // fall back to the default WP page template, which the standard
            // show_on_front/page_on_front pair already pointed at.
            return;
        }
        $current = (string)@file_get_contents($file_path);
        if ($current === '') return;

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

        $marker = '<!-- Enter Code -->';
        if (strpos($original, $marker) !== false) {
            $new_content = str_replace($marker, $replacement_html, $original);
        } else {
            // No marker — append at the end so at least the content is rendered
            $new_content = $original . "\n" . $replacement_html . "\n";
        }
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
     * @param int    $menu_id   The nav-menu term id (main or footer).
     * @param int    $post_id   The page being added.
     * @param string $label     Display label (from [;label] in the flag, or page title).
     * @param int|null $order   menu_order — explicit position (lower goes first), or null = auto.
     * @param array  $segments  URL segments of the page being added.
     * @param string $menu_kind 'main' or 'footer' — used to pick the right cache.
     */
    private function add_to_menu(int $menu_id, int $post_id, string $label, ?int $order, array $segments, string $menu_kind): void {
        // Find the deepest ancestor that's already in this menu, walking upward.
        $parent_menu_item_id = $this->find_menu_parent_for_segments($menu_id, $segments);

        $args = [
            'menu-item-title'     => $label,
            'menu-item-object-id' => $post_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $parent_menu_item_id,
        ];
        if ($order !== null) {
            $args['menu-item-position'] = $order;
        }

        $menu_item_id = wp_update_nav_menu_item($menu_id, 0, $args);
        if (is_wp_error($menu_item_id) || !$menu_item_id) return;

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
