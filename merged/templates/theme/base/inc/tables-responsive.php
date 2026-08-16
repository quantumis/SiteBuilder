<?php
/**
 * Site Builder — responsive tables.
 *
 * Wide tables (comparison charts, payment method matrices, bonus terms) don't
 * fit inside the content column on mobile viewports. Without intervention they
 * either force horizontal page scroll (breaks the entire layout) or squish
 * their columns into unreadable state.
 *
 * The classic fix — display:block + overflow-x:auto directly on <table> —
 * works but breaks table semantics: table-layout, border-collapse, and
 * caption placement all behave weirdly on a block-display table. white-space:
 * nowrap avoids column-squish but forces text cells (bonus descriptions,
 * long payment method lists) to grow arbitrarily wide, requiring long
 * horizontal scrolls.
 *
 * We take the wrapping approach instead: every <table> in post_content gets
 * wrapped in a <div class="sb-table-scroll"> that owns the overflow. The
 * table itself keeps normal table display, min-width: 100% so it doesn't
 * shrink below the viewport, and cells wrap text naturally. The wrapper
 * scrolls when the table's natural width exceeds the container.
 *
 * Filter priority is 8 — before link-resolver (9), external-links (20), etc.
 * so the wrapper lands on the outermost layer of the eventual output HTML.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_wrap_tables_for_scroll')) {
    function sb_wrap_tables_for_scroll($content) {
        if (!is_string($content) || $content === '') return $content;
        if (strpos($content, '<table') === false) return $content;

        // Wrap every <table>...</table> in a scroll container. The non-greedy
        // .*? handles multiple tables on one page correctly and prevents
        // accidentally matching across sibling tables. Nested tables are rare
        // in editorial content — if they occur, the outer table gets wrapped
        // and the inner one is left as-is (which is what you want).
        return preg_replace_callback(
            '/<table\b[^>]*>.*?<\/table>/is',
            function ($m) {
                return '<div class="sb-table-scroll">' . $m[0] . '</div>';
            },
            $content
        );
    }
    add_filter('the_content', 'sb_wrap_tables_for_scroll', 8);
}

if (!function_exists('sb_table_scroll_styles')) {
    function sb_table_scroll_styles() {
        if (is_admin()) return;
        wp_register_style('sb-table-scroll', false);
        wp_enqueue_style('sb-table-scroll');
        $css = <<<CSS
            .sb-table-scroll {
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 20px 0;
                /* Subtle right-edge shadow hints that the table is scrollable
                   horizontally. Uses two gradients: transparent overlay on the
                   left and a soft gradient on the right that fades out as the
                   user scrolls. background-attachment: local makes the right
                   gradient move away as the wrapper is scrolled to its end. */
                background:
                    linear-gradient(to right, var(--sb-color-bg, #fff), transparent 20px),
                    linear-gradient(to right, transparent, var(--sb-color-bg, #fff) calc(100% - 20px)),
                    radial-gradient(farthest-side at 0 50%, rgba(0, 0, 0, 0.08), transparent),
                    radial-gradient(farthest-side at 100% 50%, rgba(0, 0, 0, 0.08), transparent) 0 100%;
                background-repeat: no-repeat;
                background-color: var(--sb-color-bg, #fff);
                background-size: 40px 100%, 40px 100%, 14px 100%, 14px 100%;
                background-position: 0 0, 100% 0, 0 0, 100% 0;
                background-attachment: local, local, scroll, scroll;
            }
            .sb-table-scroll table {
                /* Keep the table at its natural width — the wrapper handles
                   overflow. min-width: 100% ensures narrow tables still fill
                   the container instead of hugging the left edge. */
                min-width: 100%;
                margin: 0;
            }
CSS;
        wp_add_inline_style('sb-table-scroll', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_table_scroll_styles');
}
