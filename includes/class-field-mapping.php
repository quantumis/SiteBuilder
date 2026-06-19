<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO field mapping for FSR Import.
 *
 * The FSR archive format ships content in a fixed shape: title, description,
 * headline, headimg in frontmatter. But WordPress sites store SEO data in
 * different post_meta keys depending on which plugin is active (Yoast uses
 * _yoast_wpseo_title; Rank Math uses rank_math_title; AIOSEO uses _aioseop_title;
 * many custom themes use page_title or seo_title with no underscore).
 *
 * Rather than hardcode any single target, we let the user map the FSR's fixed
 * "slots" (SEO Title, Meta Description, Social Headline, OG Description, H1)
 * onto the meta_keys that actually exist on their site. The mapping is saved
 * once and reused for every FSR import.
 *
 * Each slot can write to MULTIPLE meta_keys (e.g. both Yoast and Rank Math at
 * once), so the saved mapping is slot => array of keys.
 */
class Site_Builder_Field_Mapping {

    /** Option key in wp_options that holds the saved mapping. */
    public const OPTION_NAME = 'site_builder_fsr_field_mapping';

    /**
     * Canonical list of known meta_keys per slot, ordered by popularity. This list
     * is what we always show in the UI — independent of what's actually in the DB.
     * If a key isn't yet in wp_postmeta, the plugin will create it on import.
     *
     * Why independent of DB: on a fresh site the postmeta table is empty (or
     * contains only WordPress's own _edit_lock/_edit_last), so a DB-driven UI
     * would show nothing — leaving the user no way to configure mapping at all.
     */
    private const KNOWN_KEYS = [
        'seo_title' => [
            '_yoast_wpseo_title',          // Yoast SEO
            'rank_math_title',             // Rank Math
            '_aioseop_title',              // All in One SEO (legacy)
            '_aioseo_title',               // All in One SEO (modern)
            '_genesis_title',              // Genesis Framework
            '_seopress_titles_title',      // SEOPress
            '_su_meta_title',              // Squirrly SEO
            'meta_title',                  // generic / many themes
            'seo_title',                   // generic
        ],
        'meta_description' => [
            '_yoast_wpseo_metadesc',
            'rank_math_description',
            '_aioseop_description',
            '_aioseo_description',
            '_genesis_description',
            '_seopress_titles_desc',
            '_su_meta_description',
            'meta_description',
            'seo_description',
        ],
        'social_headline' => [
            '_yoast_wpseo_opengraph-title',
            'rank_math_facebook_title',
            '_aioseop_opengraph_settings_title',
            '_aioseo_og_title',
            '_seopress_social_fb_title',
            'og_title',
            'og_meta_title',
            'social_headline',
        ],
        'og_description' => [
            '_yoast_wpseo_opengraph-description',
            'rank_math_facebook_description',
            '_aioseop_opengraph_settings_description',
            '_aioseo_og_description',
            '_seopress_social_fb_desc',
            'og_description',
            'og_meta_description',
        ],
        'h1_title' => [
            'h1_title',
            'h1',
            'page_title',
            'display_title',
            'headline',
            '_genesis_seo_title',
        ],
    ];

    /**
     * Default selections used when the user hasn't saved a mapping yet. We
     * choose Yoast and Rank Math by default since those are the two most
     * popular WordPress SEO plugins by a wide margin — about 80% of sites
     * using SEO plugins have one of them installed. If the user has neither,
     * the keys are simply created on first import, which is harmless.
     */
    private const DEFAULT_SELECTIONS = [
        'seo_title'        => ['_yoast_wpseo_title', 'rank_math_title'],
        'meta_description' => ['_yoast_wpseo_metadesc', 'rank_math_description'],
        'social_headline'  => ['_yoast_wpseo_opengraph-title', 'rank_math_facebook_title'],
        'og_description'   => ['_yoast_wpseo_opengraph-description', 'rank_math_facebook_description'],
        'h1_title'         => [],
    ];

    /**
     * Human-readable labels for each fixed slot — shown next to the meta-key
     * checklist in the UI.
     */
    public static function slot_labels(): array {
        return [
            'seo_title'        => [
                'label'  => 'SEO Title',
                'hint'   => 'Заголовок во вкладке браузера (тег &lt;title&gt;). Источник во FSR: frontmatter <code>title</code>.',
            ],
            'meta_description' => [
                'label'  => 'Meta Description',
                'hint'   => 'Описание для поисковиков. Источник во FSR: frontmatter <code>description</code>.',
            ],
            'social_headline'  => [
                'label'  => 'Social Headline (og:title)',
                'hint'   => 'Заголовок для соцсетей при шеринге. Источник во FSR: frontmatter <code>headline</code>.',
            ],
            'og_description'   => [
                'label'  => 'OG Description',
                'hint'   => 'Описание для соцсетей при шеринге. Источник во FSR: frontmatter <code>description</code>.',
            ],
            'h1_title'         => [
                'label'  => 'H1 Title (display)',
                'hint'   => 'Заголовок, который тема выводит как &lt;h1&gt; на странице. Источник во FSR: frontmatter <code>headline</code>.',
            ],
        ];
    }

    /**
     * Reads every distinct meta_key from wp_postmeta. Sorted ascending.
     * If $include_private is false, keys starting with "_" are excluded.
     */
    public static function get_all_meta_keys(bool $include_private = false): array {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER BY meta_key ASC"
        );
        if (!is_array($rows)) return [];
        if (!$include_private) {
            $rows = array_values(array_filter($rows, function ($k) {
                return isset($k[0]) && $k[0] !== '_';
            }));
        }
        return $rows;
    }

    /**
     * Returns the saved mapping, falling back to DEFAULT_SELECTIONS when the
     * user hasn't configured a slot. Defaults are independent of the DB — they
     * just suggest Yoast + Rank Math, which works whether those plugins are
     * installed or not (the keys get created on first import either way).
     *
     * Shape: ['seo_title' => ['_yoast_wpseo_title', 'rank_math_title'], ...]
     */
    public static function get_mapping(): array {
        $saved = get_option(self::OPTION_NAME, []);
        if (!is_array($saved)) $saved = [];

        $out = [];
        foreach (array_keys(self::slot_labels()) as $slot) {
            if (isset($saved[$slot]) && is_array($saved[$slot])) {
                $out[$slot] = $saved[$slot];
            } else {
                $out[$slot] = self::DEFAULT_SELECTIONS[$slot] ?? [];
            }
        }
        return $out;
    }

    /**
     * Build the full list of meta_key options for one slot, ready to be rendered
     * in the UI. Each entry is ['key' => string, 'in_db' => bool, 'recommended' => bool].
     *
     * The list is the union of:
     *   - KNOWN_KEYS for the slot (always shown, even on a clean DB)
     *   - keys already in wp_postmeta (so custom-theme or other-plugin keys are visible)
     *   - any custom keys the user has previously saved for this slot
     *
     * This means the UI is never empty, no matter how fresh the site is.
     */
    public static function build_options_for_slot(string $slot, array $db_keys, array $saved_selected): array {
        $known = self::KNOWN_KEYS[$slot] ?? [];

        // Merge sources, deduplicating while preserving the order: KNOWN_KEYS first
        // (so popular SEO plugins are at the top), then DB-only keys, then any
        // user-saved keys we don't recognise (probably custom).
        $seen = [];
        $options = [];

        foreach ($known as $k) {
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $options[] = [
                'key'         => $k,
                'in_db'       => in_array($k, $db_keys, true),
                'recommended' => true,
            ];
        }
        foreach ($db_keys as $k) {
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $options[] = [
                'key'         => $k,
                'in_db'       => true,
                'recommended' => false,
            ];
        }
        foreach ($saved_selected as $k) {
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $options[] = [
                'key'         => $k,
                'in_db'       => in_array($k, $db_keys, true),
                'recommended' => false,
            ];
        }

        return $options;
    }

    /**
     * Returns the auto-suggested keys for one slot (those that both appear in
     * KNOWN_KEYS and actually exist in the DB).
     */
    public static function suggest_for_slot(string $slot, array $existing_keys): array {
        if (!isset(self::KNOWN_KEYS[$slot])) return [];
        return array_values(array_intersect(self::KNOWN_KEYS[$slot], $existing_keys));
    }

    /**
     * Persists the mapping. $mapping is expected to be slot => array<string>.
     * Unknown slots are dropped; each key list is normalised to unique non-empty
     * strings.
     */
    public static function save_mapping(array $mapping): void {
        $clean = [];
        $valid_slots = array_keys(self::slot_labels());
        foreach ($valid_slots as $slot) {
            $vals = $mapping[$slot] ?? [];
            if (!is_array($vals)) $vals = [];
            $vals = array_values(array_unique(array_filter(array_map(function ($v) {
                $v = trim((string)$v);
                // meta_key must be 1+ chars, allow letters/digits/underscore/hyphen/colon
                return preg_match('/^[A-Za-z0-9_:\-]{1,255}$/', $v) ? $v : '';
            }, $vals))));
            $clean[$slot] = $vals;
        }
        update_option(self::OPTION_NAME, $clean);
    }

    /**
     * Checks if the user has saved a mapping at least once. The mapping screen
     * uses this to decide whether to nudge ("save your mapping first") or just
     * proceed.
     */
    public static function has_been_configured(): bool {
        return get_option(self::OPTION_NAME, null) !== null;
    }
}
