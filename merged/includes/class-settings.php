<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin settings — UI-editable values that override the helpers.php defaults.
 *
 * All settings live in a single WordPress option (`site_builder_settings`) as a JSON
 * structure. Each getter falls back to the hardcoded default in Site_Builder_Helpers
 * when the user hasn't customized it. This keeps existing call sites working unchanged.
 *
 * Reading the settings is cheap (in-process caching), so callers may invoke getters
 * freely during a request.
 */
class Site_Builder_Settings {

    const OPTION_KEY = 'site_builder_settings';

    /**
     * Hardcoded defaults — used when the user hasn't saved settings, and shown next to
     * each field on the Settings tab as a hint.
     */
    const DEFAULTS = [
        'batch_create'       => 15,
        'batch_add_folders'  => 15,
        'batch_add_flat'     => 50,
        'articles_title'     => 'Articles',
        'articles_slug'      => 'articles',
        'articles_template'  => 'articles.php',
        // Stored as comma- or newline-separated strings; getters split them.
        'abbreviations'      => "NBA, NFL, NHL, MLB, WNBA, NCAA, MMA, UFC, EPL, UEFA, FIFA, MLS, PGA, LPGA, NASCAR, USA, UK, EU, NYC, LA, DC, CEO, CFO, CTO, COO, GDP, IPO, ROI, KPI, AI, API, URL, HTML, CSS, PDF, JS, TV, DVD, GPS, USB, FAQ, DIY",
        // User additions to the always-excluded list. Service folders
        // (IMAGES, PROMPTS, .git, node_modules, __MACOSX, .DS_Store) are
        // hard-coded inside Task_Builder and excluded regardless of this value.
        'excluded_folders'   => "",
        // Visibility of legacy importers in the navigation. FSR is the canonical
        // import method for v1.0.0+; CREATE/ADD/MD Restore remain available for
        // edge cases (old archives, partial recovery) but are hidden by default
        // to keep the UI focused.
        'show_create_tab'    => 0,
        'show_add_tab'       => 0,
        'show_md_tab'        => 0,
        // Theme-menu location bindings for FSR. Empty string = don't bind (user
        // attaches the menu manually via Appearance > Menus). Otherwise the slug
        // of a registered nav menu location in the active theme.
        'menu_location_main'   => '',
        'menu_location_footer' => '',
        // Maximum character count for menu item titles after auto-truncation.
        // Menu_Sync's truncate_for_menu() reads this — when the post_title (or
        // its pre-separator portion) exceeds this, it's cut at a word boundary
        // and ellipsis-suffixed. Sensible range 15–120. Default 40 fits most
        // horizontal navigation bars comfortably.
        'menu_max_length'      => 40,
    ];

    /** @var array|null In-process cache of the merged settings. */
    private static ?array $cache = null;

    /**
     * Get all settings merged with defaults. Subsequent calls within the same request
     * return the cached array.
     */
    public static function all(): array {
        if (self::$cache !== null) return self::$cache;

        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) $stored = [];

        self::$cache = array_merge(self::DEFAULTS, $stored);
        return self::$cache;
    }

    /** Clear cache after a save so subsequent reads pick up new values. */
    public static function clear_cache(): void {
        self::$cache = null;
    }

    public static function get(string $key) {
        $all = self::all();
        return $all[$key] ?? null;
    }

    // --- Typed getters used by the rest of the plugin ---

    public static function batch_create(): int {
        return self::sanitize_batch_size((int)self::get('batch_create'), self::DEFAULTS['batch_create']);
    }

    public static function batch_add_folders(): int {
        return self::sanitize_batch_size((int)self::get('batch_add_folders'), self::DEFAULTS['batch_add_folders']);
    }

    public static function batch_add_flat(): int {
        return self::sanitize_batch_size((int)self::get('batch_add_flat'), self::DEFAULTS['batch_add_flat']);
    }

    /**
     * Max character count for auto-truncated menu-item titles. Menu_Sync's
     * truncate_for_menu() consults this when applying the hard-limit safety net.
     */
    public static function menu_max_length(): int {
        return self::sanitize_menu_max_length(
            (int)self::get('menu_max_length'),
            self::DEFAULTS['menu_max_length']
        );
    }

    public static function articles_title(): string {
        $v = trim((string)self::get('articles_title'));
        return $v !== '' ? $v : self::DEFAULTS['articles_title'];
    }

    public static function articles_slug(): string {
        $v = sanitize_title((string)self::get('articles_slug'));
        return $v !== '' ? $v : self::DEFAULTS['articles_slug'];
    }

    public static function articles_template(): string {
        // Empty is valid — it means "no custom template, use default".
        return trim((string)self::get('articles_template'));
    }

    public static function abbreviations(): array {
        return self::parse_list((string)self::get('abbreviations'));
    }

    public static function excluded_folders(): array {
        // Always include the standard "." and ".." plus whatever the user listed
        $list = self::parse_list((string)self::get('excluded_folders'));
        return array_values(array_unique(array_merge(['.', '..'], $list)));
    }

    /**
     * Save settings from a $_POST submission. Returns array of validation errors
     * (empty if everything saved).
     */
    public static function save(array $input): array {
        $errors = [];
        $new = self::all();

        foreach (['batch_create', 'batch_add_folders', 'batch_add_flat'] as $k) {
            if (isset($input[$k])) {
                $n = (int)$input[$k];
                if ($n < 1 || $n > 500) {
                    $errors[$k] = 'Размер пачки должен быть от 1 до 500';
                    continue;
                }
                $new[$k] = $n;
            }
        }

        if (isset($input['menu_max_length'])) {
            $n = (int)$input['menu_max_length'];
            if ($n < 15 || $n > 120) {
                $errors['menu_max_length'] = 'Максимальная длина названия пункта меню должна быть от 15 до 120';
            } else {
                $new['menu_max_length'] = $n;
            }
        }

        if (isset($input['articles_title'])) {
            $v = trim((string)$input['articles_title']);
            if ($v === '') {
                $errors['articles_title'] = 'Заголовок не может быть пустым';
            } else {
                $new['articles_title'] = $v;
            }
        }

        if (isset($input['articles_slug'])) {
            $v = sanitize_title((string)$input['articles_slug']);
            if ($v === '') {
                $errors['articles_slug'] = 'Slug содержит недопустимые символы';
            } else {
                $new['articles_slug'] = $v;
            }
        }

        if (isset($input['articles_template'])) {
            // Allow empty (means no custom template); otherwise enforce *.php
            $v = trim((string)$input['articles_template']);
            if ($v !== '' && !preg_match('/^[A-Za-z0-9_\-\/]+\.php$/', $v)) {
                $errors['articles_template'] = 'Должно быть имя PHP-файла или пусто';
            } else {
                $new['articles_template'] = $v;
            }
        }

        if (isset($input['abbreviations'])) {
            // Stored as-is (the parser handles whatever the user types).
            $new['abbreviations'] = (string)$input['abbreviations'];
        }

        if (isset($input['excluded_folders'])) {
            $new['excluded_folders'] = (string)$input['excluded_folders'];
        }

        // Legacy tab visibility. Unchecked checkboxes don't appear in $input at
        // all (HTML standard), so we set 0 by default for each one and only
        // flip to 1 when the key is present.
        foreach (['show_create_tab', 'show_add_tab', 'show_md_tab'] as $k) {
            $new[$k] = !empty($input[$k]) ? 1 : 0;
        }

        // Theme menu locations — accept the selected slug or empty string.
        // We don't validate against get_registered_nav_menus() at save time
        // because the theme might be switched temporarily; the UI shows a
        // warning if the saved slug doesn't exist in the current theme.
        foreach (['menu_location_main', 'menu_location_footer'] as $k) {
            if (isset($input[$k])) {
                $v = (string)$input[$k];
                if (!preg_match('/^[A-Za-z0-9_\-]{0,64}$/', $v)) $v = '';
                $new[$k] = $v;
            }
        }

        if (empty($errors)) {
            update_option(self::OPTION_KEY, $new);
            self::clear_cache();
        }

        return $errors;
    }

    /**
     * Reset everything to defaults.
     */
    public static function reset(): void {
        delete_option(self::OPTION_KEY);
        self::clear_cache();
    }

    // --- Internal helpers ---

    /**
     * Parse a textarea value into a list of items.
     * Accepts comma-separated, newline-separated, or both. Trims whitespace.
     */
    private static function parse_list(string $raw): array {
        $parts = preg_split('/[\r\n,]+/', $raw);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    private static function sanitize_batch_size(int $n, int $fallback): int {
        if ($n < 1 || $n > 500) return $fallback;
        return $n;
    }

    /**
     * Range 15..120 chosen empirically:
     *   - below 15, "…" starts consuming more space than useful text
     *   - above 120, navigation menus visibly break at typical viewport widths
     * The generous upper bound is for edge cases (sidebar menus, custom themes
     * with vertical navigation) where longer titles fit.
     */
    private static function sanitize_menu_max_length(int $n, int $fallback): int {
        if ($n < 15 || $n > 120) return $fallback;
        return $n;
    }
}
