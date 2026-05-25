<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static helpers shared across the plugin.
 *
 * Most values are now editable from the admin UI on the «Настройки» tab. The defaults
 * below are used only when nothing is saved in the database. Edits via this file are
 * still respected, but they only matter as long as the user hasn't saved their own
 * value on the Settings tab (the saved value takes precedence).
 *
 * For things NOT exposed in the UI (home page shortcodes, menu name, theme CSS folder),
 * the methods here are the only place to edit. Look for comments marked «EDIT» below.
 */
class Site_Builder_Helpers {

    /**
     * Abbreviations to preserve in uppercase when formatting page titles.
     * Editable from Settings tab; fallback list lives in Site_Builder_Settings::DEFAULTS.
     */
    public static function get_abbreviations(): array {
        return Site_Builder_Settings::abbreviations();
    }

    /**
     * Default shortcodes for the home page.
     * == EDIT THIS LIST IN CODE TO CHANGE DEFAULT SHORTCODES ==
     * (Not exposed in the Settings UI by design.)
     */
    public static function get_home_shortcodes(): array {
        return ['sports_predictions', 'geo_info'];
    }

    /** Title and slug of the parent page created by ADD mode. Editable in Settings. */
    public static function get_articles_title(): string {
        return Site_Builder_Settings::articles_title();
    }
    public static function get_articles_slug(): string {
        return Site_Builder_Settings::articles_slug();
    }

    /** Page template assigned to Articles. Editable in Settings. */
    public static function get_articles_template(): string {
        return Site_Builder_Settings::articles_template();
    }

    /** Folder names skipped when scanning archives. Editable in Settings. */
    public static function get_excluded_folders(): array {
        return Site_Builder_Settings::excluded_folders();
    }

    /**
     * Convert a slug "nba-finals-predictions" -> "NBA Finals Predictions",
     * keeping known abbreviations in uppercase.
     */
    public static function format_page_title(string $slug): string {
        $slug = trim($slug);
        if ($slug === '') return '';

        $words = preg_split('/[-_\s]+/', $slug);
        $abbreviations = array_map('strtoupper', self::get_abbreviations());

        foreach ($words as &$word) {
            $upper = strtoupper($word);
            if (in_array($upper, $abbreviations, true)) {
                $word = $upper;
            } else {
                $word = function_exists('mb_convert_case')
                    ? mb_convert_case($word, MB_CASE_TITLE, 'UTF-8')
                    : ucfirst(strtolower($word));
            }
        }
        return implode(' ', $words);
    }

    /** Validate folder name input from form. Returns trimmed name or null if unsafe. */
    public static function sanitize_folder_name(string $input): ?string {
        $input = trim($input);
        if ($input === '') return null;
        if (preg_match('#[/\\\\]#', $input)) return null;
        if (strpos($input, '..') !== false) return null;
        if (!preg_match('/^[A-Za-z0-9_\-\.]+$/u', $input)) return null;
        return $input;
    }

    /**
     * Find the "main" HTML file in a directory.
     *
     * Newer archives from the content team name page files like index2.html,
     * index15.html, index22.html etc. — apparently from an aggregator that
     * numbers files. Older archives use plain index.html. This helper handles
     * both, with preference order:
     *   1. exact index.html             (legacy & most common)
     *   2. any index*.html              (e.g. index2.html, index22.html)
     *   3. first *.html alphabetically  (last-resort fallback)
     *
     * Returns the full path, or null if no HTML file exists in the directory.
     */
    public static function find_index_html(string $dir): ?string {
        if (!is_dir($dir)) return null;

        $exact = $dir . '/index.html';
        if (is_file($exact)) return $exact;

        $candidates = glob($dir . '/index*.html') ?: [];
        if (!empty($candidates)) {
            sort($candidates);
            return $candidates[0];
        }

        $any = glob($dir . '/*.html') ?: [];
        if (!empty($any)) {
            sort($any);
            return $any[0];
        }

        return null;
    }

    /**
     * Check whether a directory has at least one HTML file (any name).
     */
    public static function has_html_file(string $dir): bool {
        return self::find_index_html($dir) !== null;
    }

    /** Count existing non-trashed pages on the site. */
    public static function count_existing_pages(): int {
        $counts = wp_count_posts('page');
        $total = 0;
        foreach ((array)$counts as $status => $n) {
            if (in_array($status, ['trash', 'auto-draft'], true)) continue;
            $total += (int)$n;
        }
        return $total;
    }
}
