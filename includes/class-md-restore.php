<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MD Restore — восстановление страниц из набора .md-файлов с заголовком-шапкой.
 *
 * Использует точно ту же логику, что и эталонный скрипт wp_md_publisher.py:
 *   - Шапка вида "# URL: ...", "# Title: ...", "# Description: ..."
 *   - FAQ-блоки [ Question ](#) Answer  →  [faq][id="N" title="..." desc="..."][/faq]
 *   - Markdown → HTML (минимальный набор: headings, lists, bold, links, paragraphs, tables)
 *   - Slug и parent из URL: https://example.com/a/b/c → parent_chain=[a,b], slug=c
 *   - SEO-мета пишется и в Rank Math (rank_math_title/description), и в Yoast
 *     (_yoast_wpseo_title/metadesc) одновременно — на случай разных установок.
 *
 * Каждая страница после создания регистрируется через tracker->track_item('page', $post_id),
 * поэтому стандартный механизм отката плагина работает без изменений.
 */
class Site_Builder_MD_Restore {

    private Site_Builder_Import_Tracker $tracker;
    private int $import_id;

    /** Resolved post_id for the root MD page ("home"), needed as parent for top-level pages. */
    private ?int $root_post_id = null;

    public function __construct(Site_Builder_Import_Tracker $tracker, int $import_id) {
        $this->tracker = $tracker;
        $this->import_id = $import_id;
    }

    /**
     * Process a single MD-page task.
     * Task data carries: 'md_path' (full path to .md file), 'url', 'title', 'description',
     * 'is_root' (bool), and 'segments' (URL path segments, e.g. ['slots', 'pokies']).
     *
     * Returns ['ok' => bool, 'title' => string, 'message' => string, 'post_id' => int|null].
     */
    public function import_page(array $task): array {
        $data = $task['data'] ?? [];
        $md_path     = (string)($data['md_path'] ?? '');
        $url         = (string)($data['url'] ?? '');
        $title       = (string)($data['title'] ?? '');
        $description = (string)($data['description'] ?? '');
        $is_root     = !empty($data['is_root']);
        $segments    = (array)($data['segments'] ?? []);

        if ($md_path === '' || !is_readable($md_path)) {
            return ['ok' => false, 'title' => $title, 'message' => 'MD-файл недоступен'];
        }

        $raw = (string)@file_get_contents($md_path);
        if ($raw === '') {
            return ['ok' => false, 'title' => $title, 'message' => 'MD-файл пустой'];
        }

        // Split header from body
        $body = $this->extract_body_after_separator($raw);

        // Strip first H1 if present — it's redundant with the page Title
        $body = preg_replace('/^\s*#\s+[^\n]+\n/', '', $body, 1);

        // FAQ → shortcode
        $body = $this->convert_faq_blocks($body);

        // Markdown → HTML
        $html = $this->md_to_html($body);

        // Determine slug + parent_id
        $slug = $is_root
            ? 'home'
            : ($segments ? end($segments) : sanitize_title($title));

        $parent_id = 0;
        if (!$is_root) {
            $parent_segments = array_slice($segments, 0, -1);
            $parent_id = $this->resolve_parent_id($parent_segments);
            if ($parent_id === null) {
                // Some intermediate slug is missing from the .md set (the content team didn't
                // export it). Auto-create empty placeholder pages along the missing chain so
                // that the deepest page still gets imported. This is preferable to dropping
                // 124+ leaf pages whose only fault is a missing intermediate slug — operators
                // can fill the placeholders in later via the WP editor.
                $parent_id = $this->autocreate_missing_chain($parent_segments);
                if ($parent_id === null) {
                    return ['ok' => false, 'title' => $title,
                        'message' => 'Не удалось создать цепочку родителей: /' . implode('/', $parent_segments)];
                }
            }
        }

        // Collision check — skip if same slug + parent already exists
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
            return ['ok' => false, 'title' => $title,
                'message' => 'Страница с таким slug+parent уже существует (id=' . (int)$existing[0] . ')'];
        }

        // Create page
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $html,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
            'menu_order'   => 0,
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            $err = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post вернул 0';
            return ['ok' => false, 'title' => $title, 'message' => 'Не удалось создать страницу: ' . $err];
        }

        // SEO meta — both plugins at once, harmless if one isn't installed
        if ($description !== '') {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            update_post_meta($post_id, 'rank_math_description', $description);
        }
        if ($title !== '') {
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, 'rank_math_title', $title);
        }

        $this->tracker->track_item($this->import_id, 'page', (int)$post_id);

        // Remember the root post id so subsequent root-children can use it.
        if ($is_root) {
            $this->root_post_id = (int)$post_id;
            update_option('site_builder_md_root_id_' . $this->import_id, (int)$post_id);
        }

        return ['ok' => true, 'title' => $title, 'message' => 'Создана', 'post_id' => (int)$post_id];
    }

    /**
     * Walk the segment chain from the root and create any missing intermediate pages
     * as empty placeholders. Each placeholder is tracked like a real page so rollback
     * removes it cleanly.
     *
     * Returns the post_id of the deepest segment, or null if even the root MD page
     * itself is missing (in which case nothing can be done).
     */
    private function autocreate_missing_chain(array $segments): ?int {
        $parent_id = $this->root_post_id ?? (int)get_option('site_builder_md_root_id_' . $this->import_id, 0);
        if (!$parent_id) return null;

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
            if (!empty($found)) {
                $parent_id = (int)$found[0];
                continue;
            }
            // Build a readable title from the slug
            $title = ucwords(str_replace(['-', '_'], ' ', $segment));
            $new_id = wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $segment,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
                'menu_order'   => 0,
            ], true);
            if (is_wp_error($new_id) || !$new_id) return null;
            $this->tracker->track_item($this->import_id, 'page', (int)$new_id);
            $parent_id = (int)$new_id;
        }
        return $parent_id;
    }

    /**
     * Resolve the WP post_id of the deepest ancestor described by $segments.
     * E.g. for segments=['slots', 'pokies'] returns id of the "pokies" page under "slots".
     * Empty segments → returns the root MD page id (parent for top-level pages).
     *
     * Returns null if any link in the chain cannot be found (caller treats as error).
     */
    private function resolve_parent_id(array $segments): ?int {
        // Top-level children → parented to the root MD page
        if (empty($segments)) {
            if ($this->root_post_id) return $this->root_post_id;
            // Within the same batch the root may have been created and we lost the in-memory
            // reference (e.g. across batches in the same import).
            $stored = (int)get_option('site_builder_md_root_id_' . $this->import_id, 0);
            if ($stored) {
                $this->root_post_id = $stored;
                return $stored;
            }
            return null;
        }

        $parent_id = $this->root_post_id ?? (int)get_option('site_builder_md_root_id_' . $this->import_id, 0);
        if (!$parent_id) return null;

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

    /**
     * Body is whatever follows the "------" separator (10+ dashes on their own line).
     * If no separator — the whole file is the body.
     */
    private function extract_body_after_separator(string $raw): string {
        $parts = preg_split('/^-{10,}\s*$/m', $raw, 2);
        return isset($parts[1]) ? trim($parts[1]) : trim($raw);
    }

    /**
     * Convert FAQ blocks to [faq][id title desc]...[/faq] shortcode.
     *
     * Matches both observed variants:
     *   [ Q: How can I join? ](#)
     *   A: It is easy to sign up...
     *
     *   [ What is the deposit process? ](#)
     *   Just go to the cashier...
     *
     * A run of consecutive [..](#) items becomes a single [faq]…[/faq] block.
     * Blank lines and an "A:" prefix on the answer are normalised away.
     */
    private function convert_faq_blocks(string $body): string {
        $pattern = '/\[\s*([^\]]+?)\s*\]\(#\)\s*(.*?)(?=\n?\s*\[\s*[^\]]+?\s*\]\(#\)|\n#{1,6}\s|\Z)/s';
        if (!preg_match_all($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            return $body;
        }

        $out = '';
        $last = 0;
        $buffer = [];

        $flush = function () use (&$buffer) {
            if (empty($buffer)) return '';
            $parts = ['[faq]'];
            foreach ($buffer as $i => $pair) {
                $q = $this->normalise_inline($pair[0]);
                $a = $this->normalise_inline($pair[1]);
                $parts[] = sprintf('[id="%d" title="%s" desc="%s"]', $i + 1, $q, $a);
            }
            $parts[] = '[/faq]';
            $buffer = [];
            return "\n\n" . implode(' ', $parts) . "\n\n";
        };

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $full_start = $matches[0][$i][1];
            $full_text = $matches[0][$i][0];
            $q = $matches[1][$i][0];
            $a = $matches[2][$i][0];

            // Strip "Q:" / "A:" prefixes if present
            $q = preg_replace('/^\s*Q:\s*/i', '', $q);
            $a = preg_replace('/^\s*A:\s*/i', '', $a);

            $gap = substr($body, $last, $full_start - $last);
            if (!empty($buffer) && trim($gap) !== '') {
                // Non-empty gap closes the current run
                $out .= $flush();
                $out .= $gap;
            } elseif (empty($buffer)) {
                $out .= $gap;
            }
            $buffer[] = [$q, $a];
            $last = $full_start + strlen($full_text);
        }
        $out .= $flush();
        $out .= substr($body, $last);
        return $out;
    }

    /**
     * Cleanup text destined for an attribute value: strip inline markdown,
     * collapse whitespace, escape double quotes by converting them to single.
     */
    private function normalise_inline(string $text): string {
        // Strip [text](url) → text
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        // Strip markdown emphasis and backticks
        $text = preg_replace('/[*_`]+/', '', $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim(str_replace('"', "'", $text));
    }

    /**
     * Minimal Markdown → HTML converter covering exactly what the archive uses:
     * headings (## … ######), unordered lists (-), ordered lists (1.),
     * bold (**), links ([text](url)), tables (|...|), paragraphs.
     *
     * FAQ shortcodes are protected with a placeholder so the paragraph wrapper
     * doesn't touch them.
     */
    private function md_to_html(string $md): string {
        // Protect [faq]...[/faq] from line-level processing
        $placeholders = [];
        $md = preg_replace_callback('/\[faq\].*?\[\/faq\]/s', function ($m) use (&$placeholders) {
            $key = '@@SB_FAQ_' . count($placeholders) . '@@';
            $placeholders[$key] = $m[0];
            return $key;
        }, $md);

        // Normalise line endings
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $lines = explode("\n", $md);

        $html = '';
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];

            // Blank line
            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Heading: ## … ######
            if (preg_match('/^(#{2,6})\s+(.+)$/', $line, $m)) {
                $level = strlen($m[1]);
                $text = $this->inline_md($m[2]);
                $html .= "<h{$level}>{$text}</h{$level}>\n";
                $i++;
                continue;
            }

            // Table (line starts with |, and the next line is a separator |--|--|)
            if (strpos(ltrim($line), '|') === 0 && isset($lines[$i + 1]) && preg_match('/^\s*\|?[\s\-:|]+\|?\s*$/', $lines[$i + 1])) {
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

            // Placeholder (FAQ shortcode) — leave as-is, no <p> wrapping
            if (preg_match('/^@@SB_FAQ_\d+@@$/', trim($line))) {
                $html .= trim($line) . "\n";
                $i++;
                continue;
            }

            // Paragraph — gather consecutive non-blank lines
            $para_lines = [];
            while ($i < $n && trim($lines[$i]) !== ''
                && !preg_match('/^#{2,6}\s+/', $lines[$i])
                && !preg_match('/^\s*[-*]\s+/', $lines[$i])
                && !preg_match('/^\s*\d+\.\s+/', $lines[$i])
                && !preg_match('/^@@SB_FAQ_\d+@@$/', trim($lines[$i]))
                && !(strpos(ltrim($lines[$i]), '|') === 0 && isset($lines[$i + 1]) && preg_match('/^\s*\|?[\s\-:|]+\|?\s*$/', $lines[$i + 1]))) {
                $para_lines[] = $lines[$i];
                $i++;
            }
            if (!empty($para_lines)) {
                $text = $this->inline_md(implode(' ', $para_lines));
                $html .= "<p>{$text}</p>\n";
            }
        }

        // Restore FAQ shortcodes
        foreach ($placeholders as $key => $sc) {
            $html = str_replace($key, $sc, $html);
        }

        return trim($html);
    }

    /**
     * Inline markdown: **bold**, [text](url), `code`.
     * Italic (*x*) is intentionally NOT handled — too rare in this corpus
     * and conflicts with bold patterns.
     */
    private function inline_md(string $text): string {
        // **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        // [text](url) — also handles trailing relative refs gracefully
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = trim($m[2]);
            $label = $m[1];
            // Don't link if url is empty or "#" — render as plain text
            if ($url === '' || $url === '#') return $label;
            $url = esc_url($url);
            return '<a href="' . $url . '">' . $label . '</a>';
        }, $text);
        // `code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return $text;
    }

    /**
     * Parse a list block starting at $start. Returns [html, lines_consumed].
     * Does not handle nesting — flat lists only (matches the archive's usage).
     *
     * If the very first line matches the list-prefix but has no content after
     * (e.g. a stray "- " with trailing whitespace), we still consume the line
     * with an empty item — otherwise the caller's main loop would loop forever
     * since it routed us here based on the same prefix check.
     */
    private function parse_list(array $lines, int $start, string $type): array {
        $pattern = $type === 'ul' ? '/^\s*[-*]\s+(.*)$/' : '/^\s*\d+\.\s+(.*)$/';
        $items = [];
        $i = $start;
        while ($i < count($lines) && preg_match($pattern, $lines[$i], $m)) {
            $text = trim($m[1]);
            if ($text !== '') {
                $items[] = $this->inline_md($text);
            }
            $i++;
        }
        // Never return 0 — that would loop in the caller. If nothing matched at all
        // (impossible here since we entered after a successful match), skip one line.
        $consumed = max($i - $start, 1);

        if (empty($items)) {
            // We consumed lines that were "list-shaped but empty" — emit nothing.
            return ['', $consumed];
        }

        $html = "<{$type}>\n";
        foreach ($items as $item) {
            $html .= "  <li>{$item}</li>\n";
        }
        $html .= "</{$type}>\n";
        return [$html, $consumed];
    }

    /**
     * Parse a markdown table starting at $start.
     * Format:
     *   | col | col |
     *   |-----|-----|
     *   | val | val |
     */
    private function parse_table(array $lines, int $start): array {
        $i = $start;
        // Header row
        $header_cells = $this->split_table_row($lines[$i]);
        $i++; // skip separator
        $i++;
        $body_rows = [];
        while ($i < count($lines) && strpos(ltrim($lines[$i]), '|') === 0) {
            $body_rows[] = $this->split_table_row($lines[$i]);
            $i++;
        }
        $html = '<table>' . "\n  <thead><tr>";
        foreach ($header_cells as $h) {
            $html .= '<th>' . $this->inline_md(trim($h)) . '</th>';
        }
        $html .= "</tr></thead>\n  <tbody>\n";
        foreach ($body_rows as $row) {
            $html .= '    <tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $this->inline_md(trim($cell)) . '</td>';
            }
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

    /**
     * Cleanup hook called once per import when md_restore finishes —
     * removes the temporary option that tracked the root post id.
     */
    public static function cleanup_after_import(int $import_id): void {
        delete_option('site_builder_md_root_id_' . $import_id);
    }
}
