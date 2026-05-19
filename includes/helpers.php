<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static helpers shared across the plugin.
 *
 * == HOW TO EDIT THIS FILE ==
 *
 * 1) ABBREVIATIONS — see get_abbreviations() below.
 *    Add new entries (uppercase) to keep them uppercase in page titles.
 *    Example: "nba-finals" -> "NBA Finals" if 'NBA' is in the list.
 *
 * 2) HOME PAGE SHORTCODES — see get_home_shortcodes() below.
 *    These shortcodes are injected into the home page during HUB setup.
 *    Add new entries (without brackets) to extend.
 *
 * 3) EXCLUDED FOLDERS — see get_excluded_folders() below.
 *    Folder names skipped when scanning archives.
 */
class Site_Builder_Helpers {

    /**
     * Abbreviations to preserve in uppercase when formatting page titles.
     * == EDIT THIS LIST TO ADD NEW ABBREVIATIONS ==
     */
    public static function get_abbreviations(): array {
        return [
            'NBA', 'NFL', 'NHL', 'MLB', 'WNBA', 'NCAA', 'MMA', 'UFC',
            'EPL', 'UEFA', 'FIFA', 'MLS', 'PGA', 'LPGA', 'NASCAR',
            'USA', 'UK', 'EU', 'NYC', 'LA', 'DC',
            'CEO', 'CFO', 'CTO', 'COO',
            'GDP', 'IPO', 'ROI', 'KPI',
            'AI', 'API', 'URL', 'HTML', 'CSS', 'PDF', 'JS',
            'TV', 'DVD', 'GPS', 'USB',
            'FAQ', 'DIY',
        ];
    }

    /**
     * Default shortcodes for the home page.
     * == EDIT THIS LIST TO CHANGE DEFAULT SHORTCODES ==
     */
    public static function get_home_shortcodes(): array {
        return ['sports_predictions', 'geo_info'];
    }

    /**
     * Title and slug of the parent page created by the ADD mode.
     * All ADD-imported pages become children of this page.
     * == EDIT THESE TO CHANGE THE ARTICLES PAGE ==
     */
    public static function get_articles_title(): string {
        return 'Articles';
    }
    public static function get_articles_slug(): string {
        return 'articles';
    }

    /**
     * Folders inside the archive to skip during scanning.
     */
    public static function get_excluded_folders(): array {
        return ['.', '..', 'hub', 'images', 'prompts', '.DS_Store', '.git', 'node_modules', '__MACOSX'];
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

    /**
     * Validate folder name input from form. Returns trimmed name or null if unsafe.
     */
    public static function sanitize_folder_name(string $input): ?string {
        $input = trim($input);
        if ($input === '') return null;
        if (preg_match('#[/\\\\]#', $input)) return null;
        if (strpos($input, '..') !== false) return null;
        if (!preg_match('/^[A-Za-z0-9_\-\.]+$/u', $input)) return null;
        return $input;
    }

    /**
     * Count existing non-trashed pages on the site.
     */
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
