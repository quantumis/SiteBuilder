<?php
/**
 * Site Builder — content blocks parser.
 *
 * Handles the "container block" markdown extension used in FSR archives:
 *
 *     ::: block-name
 *     ...block content...
 *     :::
 *
 * The 17 block types shipped in v1.1.3 are registered below in $blocks_registry.
 * Third parties can add their own blocks by hooking into the
 * `site_builder_blocks_registry` filter and appending entries.
 *
 * The parser runs BEFORE the standard markdown-to-HTML conversion in
 * FSR_Importer::md_to_html(): container blocks are converted to their HTML
 * output first, so the surrounding markdown parser doesn't try to interpret
 * their inner syntax. Each block's HTML output is wrapped in an outer <div>
 * with an sb-block-{name} class; the theme's inc/blocks.php module provides
 * the CSS.
 *
 * Two categories of warnings can be collected during parsing:
 *   1. Unknown block name — the block source stays in the output verbatim so
 *      it's visually obvious something's off, and a warning is logged for QA.
 *   2. max_per_page violated — allowed occurrences are rendered as blocks,
 *      excess ones are left as text and a warning is logged.
 *
 * These warnings surface in the FSR import journal alongside other per-page
 * warnings via the tracker.
 */
if (!defined('ABSPATH')) exit;

class Site_Builder_Blocks_Parser {

    /**
     * Get the registered block definitions. Each entry is:
     *   'block-name' => [
     *       'render'       => callable(string $inner_content): string,
     *       'max_per_page' => int|null,       // null = unlimited
     *       'inline_arg'   => bool,           // true if syntax is `::: name ARG`
     *   ]
     *
     * Third parties can filter this list to add/replace/remove blocks.
     */
    public static function get_registry(): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $registry = [
            // === Simple wrapper blocks (Group 1) =============================
            'info-callout' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-callout sb-block-info-callout" role="note">'
                         . '<span class="sb-block-icon" aria-hidden="true">ⓘ</span>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'success-callout' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-callout sb-block-success-callout" role="note">'
                         . '<span class="sb-block-icon" aria-hidden="true">✓</span>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'warning-callout' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-callout sb-block-warning-callout" role="note">'
                         . '<span class="sb-block-icon" aria-hidden="true">⚠</span>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'danger-callout' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-callout sb-block-danger-callout" role="alert">'
                         . '<span class="sb-block-icon" aria-hidden="true">✕</span>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'key-takeaway' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-key-takeaway">'
                         . '<span class="sb-block-label">Key takeaway</span>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'odds-example' => [
                'render' => function ($inner) {
                    return '<aside class="sb-block sb-block-odds-example">'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</aside>';
                },
            ],
            'hero-label' => [
                'max_per_page' => 1,
                'render' => function ($inner) {
                    // Strip everything but the first non-empty line, uppercase enforced
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $inner))));
                    $text = $lines[0] ?? '';
                    return '<div class="sb-block sb-block-hero-label">' . esc_html($text) . '</div>';
                },
            ],
            'hero-subtitle' => [
                'max_per_page' => 1,
                'render' => function ($inner) {
                    return '<p class="sb-block sb-block-hero-subtitle">'
                         . esc_html(trim($inner))
                         . '</p>';
                },
            ],

            // === Blocks with special inner structure (Group 2) ================
            'key-takeaways' => [
                'render' => function ($inner) {
                    // Inner is a list of "- item" lines
                    $items = self::extract_bullet_items($inner);
                    if (empty($items)) return '';
                    $html = '<aside class="sb-block sb-block-key-takeaways">'
                          . '<span class="sb-block-label">Key takeaways</span>'
                          . '<ul class="sb-block-list">';
                    foreach ($items as $item) {
                        $html .= '<li>' . self::inline_markdown($item) . '</li>';
                    }
                    $html .= '</ul></aside>';
                    return $html;
                },
            ],
            'pre-bet-checklist' => [
                'render' => function ($inner) {
                    // Inner is a list of "- [ ] item" lines
                    $items = [];
                    foreach (explode("\n", $inner) as $line) {
                        $line = trim($line);
                        if (preg_match('/^-\s*\[[ xX]\]\s*(.+)$/', $line, $m)) {
                            $items[] = $m[1];
                        }
                    }
                    if (empty($items)) return '';
                    $html = '<div class="sb-block sb-block-pre-bet-checklist">'
                          . '<span class="sb-block-label">Pre-bet checklist</span>'
                          . '<ul class="sb-block-checklist">';
                    foreach ($items as $item) {
                        $html .= '<li><span class="sb-block-checkbox" aria-hidden="true"></span>'
                              . '<span class="sb-block-check-text">' . self::inline_markdown($item) . '</span></li>';
                    }
                    $html .= '</ul></div>';
                    return $html;
                },
            ],
            'glossary-term' => [
                'render' => function ($inner) {
                    // Inner: "### Term\nDefinition..."
                    $term = '';
                    $definition = '';
                    if (preg_match('/^\s*###\s+(.+)$/m', $inner, $m)) {
                        $term = trim($m[1]);
                        $definition = trim(preg_replace('/^\s*###\s+.+$/m', '', $inner, 1));
                    }
                    return '<dl class="sb-block sb-block-glossary-term">'
                         . '<dt>' . esc_html($term) . '</dt>'
                         . '<dd>' . self::inner_markdown($definition) . '</dd>'
                         . '</dl>';
                },
            ],
            'details' => [
                'inline_arg' => true, // syntax: ::: details Title text
                'render' => function ($inner, $arg = '') {
                    $summary = trim($arg);
                    if ($summary === '') $summary = 'Details';
                    return '<details class="sb-block sb-block-details">'
                         . '<summary class="sb-block-details-summary">' . esc_html($summary) . '</summary>'
                         . '<div class="sb-block-content">' . self::inner_markdown($inner) . '</div>'
                         . '</details>';
                },
            ],

            // === Blocks with --- separators (Group 3) =========================
            'card-grid' => [
                'render' => function ($inner) {
                    $cards = self::split_by_hr($inner);
                    if (empty($cards)) return '';
                    $html = '<div class="sb-block sb-block-card-grid">';
                    foreach ($cards as $card) {
                        // Each card: "### Title\ndescription"
                        $title = '';
                        $body = $card;
                        if (preg_match('/^\s*###\s+(.+)$/m', $card, $m)) {
                            $title = trim($m[1]);
                            $body = trim(preg_replace('/^\s*###\s+.+$/m', '', $card, 1));
                        }
                        $html .= '<div class="sb-block-card">'
                              .   '<h4 class="sb-block-card-title">' . esc_html($title) . '</h4>'
                              .   '<div class="sb-block-card-body">' . self::inner_markdown($body) . '</div>'
                              . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                },
            ],
            'faq' => [
                'render' => function ($inner) {
                    $items = self::split_by_hr($inner);
                    if (empty($items)) return '';
                    $html = '<section class="sb-block sb-block-faq" aria-label="Frequently asked questions">';
                    foreach ($items as $item) {
                        $question = '';
                        $answer = $item;
                        if (preg_match('/^\s*##\s+(.+)$/m', $item, $m)) {
                            $question = trim($m[1]);
                            $answer = trim(preg_replace('/^\s*##\s+.+$/m', '', $item, 1));
                        }
                        // Wrap question text in <h3> inside <summary> so search
                        // engines get a proper heading hierarchy (main h1 →
                        // section h2 → faq questions h3) and Rich Snippets
                        // FAQPage schema picks it up correctly. The h3 lives
                        // inside <summary> — that's a valid structure in HTML5.
                        $html .= '<details class="sb-block-faq-item">'
                              .   '<summary class="sb-block-faq-question"><h3 class="sb-block-faq-question-text">' . esc_html($question) . '</h3></summary>'
                              .   '<div class="sb-block-faq-answer">' . self::inner_markdown($answer) . '</div>'
                              . '</details>';
                    }
                    $html .= '</section>';
                    return $html;
                },
            ],
            'at-a-glance' => [
                'max_per_page' => 1,
                'render' => function ($inner) {
                    $pairs = self::split_by_hr($inner);
                    if (empty($pairs)) return '';
                    $html = '<div class="sb-block sb-block-at-a-glance">';
                    foreach ($pairs as $pair) {
                        // Two lines: label (bold), value
                        $lines = array_values(array_filter(array_map('trim', explode("\n", $pair))));
                        $label = $lines[0] ?? '';
                        $value = $lines[1] ?? '';
                        // Strip **bold** markers from label if any
                        $label = preg_replace('/^\*\*(.+)\*\*$/', '$1', $label);
                        $html .= '<div class="sb-block-glance-item">'
                              .   '<div class="sb-block-glance-label">' . esc_html($label) . '</div>'
                              .   '<div class="sb-block-glance-value">' . self::inline_markdown($value) . '</div>'
                              . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                },
            ],
            'dos-donts' => [
                'max_per_page' => 1,
                'render' => function ($inner) {
                    $parts = self::split_by_hr($inner);
                    if (count($parts) < 2) return '';
                    $render_column = function ($text, $class) {
                        $heading = '';
                        $body = $text;
                        if (preg_match('/^\s*####\s+(.+)$/m', $text, $m)) {
                            $heading = trim($m[1]);
                            $body = trim(preg_replace('/^\s*####\s+.+$/m', '', $text, 1));
                        }
                        $items = self::extract_bullet_items($body);
                        $items_html = '';
                        foreach ($items as $item) {
                            $items_html .= '<li>' . self::inline_markdown($item) . '</li>';
                        }
                        return '<div class="sb-block-dosdonts-col ' . $class . '">'
                             .   '<h4 class="sb-block-dosdonts-heading">' . esc_html($heading) . '</h4>'
                             .   '<ul class="sb-block-dosdonts-list">' . $items_html . '</ul>'
                             . '</div>';
                    };
                    return '<div class="sb-block sb-block-dos-donts">'
                         . $render_column($parts[0], 'sb-block-dosdonts-do')
                         . $render_column($parts[1], 'sb-block-dosdonts-dont')
                         . '</div>';
                },
            ],
            'worked-example' => [
                'render' => function ($inner) {
                    // Steps: ### Шаг N\ndescription... — split by "### Шаг" markers
                    if (!preg_match_all('/^\s*###\s+(?:Шаг|Step|Krok|Passo|Paso|Stap|Étape|Schritt)\s+\S+.*$/mi', $inner, $m, PREG_OFFSET_CAPTURE)) {
                        return '';
                    }
                    $positions = $m[0];
                    $steps = [];
                    $count = count($positions);
                    for ($i = 0; $i < $count; $i++) {
                        $start = $positions[$i][1] + strlen($positions[$i][0]);
                        $end = ($i + 1 < $count) ? $positions[$i + 1][1] : strlen($inner);
                        $steps[] = trim(substr($inner, $start, $end - $start));
                    }
                    if (empty($steps)) return '';
                    $html = '<ol class="sb-block sb-block-worked-example">';
                    foreach ($steps as $idx => $body) {
                        $html .= '<li class="sb-block-step">'
                              .   '<div class="sb-block-step-num">' . ($idx + 1) . '</div>'
                              .   '<div class="sb-block-step-body">' . self::inner_markdown($body) . '</div>'
                              . '</li>';
                    }
                    $html .= '</ol>';
                    return $html;
                },
            ],
        ];

        // Third parties can add/replace blocks via this filter. Docs at the top
        // of this class explain the array structure.
        $registry = apply_filters('site_builder_blocks_registry', $registry);
        $cache = $registry;
        return $cache;
    }

    /**
     * Main entry point: process container blocks in a markdown string.
     *
     * @param string $markdown  Raw markdown from index.md
     * @param array  $warnings  Passed by reference — parser appends warning strings
     * @return string  Markdown with container blocks replaced by placeholder HTML
     *                 tokens; the outer markdown parser then just leaves those alone.
     */
    public static function parse(string $markdown, array &$warnings = []): string {
        $registry = self::get_registry();
        $seen_counts = []; // block_name => count (for max_per_page enforcement)

        // Match `::: name [inline_arg]\n...content...\n:::` non-greedily. Nested
        // ::: is not supported (containers can't contain other containers of
        // the same syntax); this matches the widest simple form.
        // Note: we use [ \t] instead of \s where whitespace is on the same line
        // to prevent the pattern from eating newlines and merging adjacent blocks.
        $pattern = '/^:::[ \t]+([a-z0-9-]+)(?:[ \t]+([^\n]*))?\n(.*?)\n:::[ \t]*(?:\n|$)/ms';

        return preg_replace_callback($pattern, function ($m) use ($registry, &$warnings, &$seen_counts) {
            $name = strtolower($m[1]);
            $inline_arg = $m[2] ?? '';
            $inner = $m[3] ?? '';

            if (!isset($registry[$name])) {
                // Unknown block — leave source verbatim so it's visible, warn journal
                $warnings[] = 'Неизвестный блок «' . $name . '» — оставлен в тексте как есть';
                return $m[0]; // return the whole matched text unchanged
            }

            $def = $registry[$name];
            $max = isset($def['max_per_page']) ? (int)$def['max_per_page'] : 0;
            $seen_counts[$name] = ($seen_counts[$name] ?? 0) + 1;

            if ($max > 0 && $seen_counts[$name] > $max) {
                $warnings[] = 'Блок «' . $name . '» встречается больше ' . $max . ' раз(а) — лишний оставлен в тексте';
                return $m[0];
            }

            try {
                $render = $def['render'];
                $html = $render($inner, $inline_arg);
                // Ensure produced HTML sits on its own lines so the outer markdown
                // parser doesn't wrap it in <p>...</p>.
                return "\n\n" . trim($html) . "\n\n";
            } catch (\Throwable $e) {
                $warnings[] = 'Ошибка рендера блока «' . $name . '»: ' . $e->getMessage();
                return $m[0];
            }
        }, $markdown);
    }

    // -------------------------------------------------------------------------
    // Helpers used inside block renderers
    // -------------------------------------------------------------------------

    /**
     * Split block content on lines containing only `---` (horizontal rule).
     * Returns an array of trimmed chunks, empty ones filtered out.
     */
    private static function split_by_hr(string $text): array {
        $parts = preg_split('/^\s*---\s*$/m', $text);
        return array_values(array_filter(array_map('trim', $parts), function ($p) { return $p !== ''; }));
    }

    /**
     * Extract items from a bulleted list ("- item").
     */
    private static function extract_bullet_items(string $text): array {
        $items = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (preg_match('/^-\s+(.+)$/', $line, $m)) {
                $items[] = $m[1];
            }
        }
        return $items;
    }

    /**
     * Convert block-inner markdown to HTML. Called for content pieces inside
     * a block — supports paragraphs, links, bold/italic. Uses the same
     * lightweight rules as FSR_Importer's md_to_html but scoped to fragments.
     */
    private static function inner_markdown(string $text): string {
        $text = trim($text);
        if ($text === '') return '';

        // Split into paragraph blocks on blank lines
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $html_parts = [];
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;

            // Skip further wrapping if it's already an HTML block
            if (preg_match('/^<(p|div|ul|ol|dl|section|aside|details|h[1-6]|blockquote|pre)\b/i', $para)) {
                $html_parts[] = $para;
                continue;
            }
            $html_parts[] = '<p>' . self::inline_markdown($para) . '</p>';
        }
        return implode("\n", $html_parts);
    }

    /**
     * Inline-level markdown: bold, italic, links, code. No block-level parsing.
     */
    private static function inline_markdown(string $text): string {
        $text = esc_html($text);
        // Links: [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            return '<a href="' . esc_url(html_entity_decode($m[2])) . '">' . $m[1] . '</a>';
        }, $text);
        // Bold: **text**
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        // Italic: *text* (must not match ** — use negative lookaround)
        $text = preg_replace('/(?<![\*])\*([^*\n]+)\*(?![\*])/', '<em>$1</em>', $text);
        // Inline code: `text`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return $text;
    }
}
