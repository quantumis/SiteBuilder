<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates a WordPress theme from a chosen combination of header / footer /
 * style variants. The variants live in $plugin/templates/theme/{headers,footers,styles},
 * each with a .php (or .css) implementation and a sibling .json with metadata.
 *
 * Adding new variants requires no code changes: drop a `myname.php` + `myname.json`
 * pair into the right subfolder and it'll show up in the UI.
 *
 * The generated theme is always written to wp-content/themes/site-builder-generated/
 * (single folder, overwritten on each generation). The slug is the same; only the
 * displayed Theme Name in style.css can vary between generations if we wanted to
 * (currently we keep it constant for simplicity).
 */
class Site_Builder_Theme_Generator {

    public const THEME_SLUG = 'site-builder-generated';
    public const THEME_NAME = 'Site Builder Generated Theme';

    /**
     * List variants in a given category ('headers', 'footers', 'styles').
     * Returns numerically-indexed array of:
     *   ['slug' => 'classic', 'name' => '…', 'description' => '…', 'category' => '…']
     *
     * Sorted alphabetically by slug for stable ordering. Drop-in: a new file appears
     * here automatically as long as it has a matching .json.
     */
    public static function list_variants(string $category): array {
        $valid = ['headers' => 'php', 'footers' => 'php', 'styles' => 'css'];
        if (!isset($valid[$category])) return [];
        $ext = $valid[$category];

        $dir = SITE_BUILDER_PATH . 'templates/theme/' . $category . '/';
        if (!is_dir($dir)) return [];

        $variants = [];
        foreach (glob($dir . '*.' . $ext) as $impl_path) {
            $slug = pathinfo($impl_path, PATHINFO_FILENAME);
            $json_path = $dir . $slug . '.json';
            $meta = ['name' => $slug, 'description' => '', 'css' => ''];
            if (file_exists($json_path)) {
                $decoded = json_decode((string)@file_get_contents($json_path), true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                }
            }
            $variants[] = [
                'slug'         => $slug,
                'name'         => (string)$meta['name'],
                'description'  => (string)$meta['description'],
                'category'     => $category,
                'preview_type' => (string)($meta['preview_type'] ?? ''),
                'palette'      => is_array($meta['palette'] ?? null) ? $meta['palette'] : null,
            ];
        }
        usort($variants, fn($a, $b) => strcmp($a['slug'], $b['slug']));
        return $variants;
    }

    /**
     * Build the theme from chosen variants. Returns an array with:
     *   ['ok' => bool, 'message' => string, 'path' => string, 'activated' => bool]
     *
     * @param array $choices  ['header' => 'classic', 'footer' => 'classic', 'style' => 'bright']
     * @param bool  $activate If true, switch_theme() to the generated theme on success.
     */
    public function build(array $choices, bool $activate = true): array {
        // 1. Validate choices
        $header = (string)($choices['header'] ?? '');
        $footer = (string)($choices['footer'] ?? '');
        $style  = (string)($choices['style']  ?? '');
        if (!$this->variant_exists('headers', $header)) {
            return ['ok' => false, 'message' => 'Не найден вариант шапки: ' . $header];
        }
        if (!$this->variant_exists('footers', $footer)) {
            return ['ok' => false, 'message' => 'Не найден вариант футера: ' . $footer];
        }
        if (!$this->variant_exists('styles', $style)) {
            return ['ok' => false, 'message' => 'Не найден вариант стиля: ' . $style];
        }

        // 2. Prepare target directory under wp-content/themes/site-builder-generated/
        $themes_root = WP_CONTENT_DIR . '/themes';
        $target = $themes_root . '/' . self::THEME_SLUG;
        if (!is_dir($themes_root) || !is_writable($themes_root)) {
            return ['ok' => false, 'message' => 'Папка тем недоступна для записи: ' . $themes_root];
        }
        if (!is_dir($target)) {
            if (!@mkdir($target, 0755, true)) {
                return ['ok' => false, 'message' => 'Не удалось создать папку темы: ' . $target];
            }
        }

        // 3. Copy base files (functions.php, index.php, page.php, front-page.php).
        // style.css is built separately below — it's a template needing substitution.
        $base = SITE_BUILDER_PATH . 'templates/theme/base/';
        foreach (['functions.php', 'index.php', 'page.php', 'front-page.php'] as $file) {
            $src = $base . $file;
            if (!file_exists($src)) {
                return ['ok' => false, 'message' => 'Отсутствует базовый файл темы: ' . $file];
            }
            if (!@copy($src, $target . '/' . $file)) {
                return ['ok' => false, 'message' => 'Не удалось записать ' . $file . ' в папку темы'];
            }
        }

        // 3b. Copy the inc/ directory recursively — it holds add-on modules
        // (i18n, similar-post, geo-shortcodes, back-to-top) that functions.php
        // wires in. Skipping this would leave the theme without translations,
        // related posts, GEO shortcode placement, and the back-to-top button.
        $inc_src = $base . 'inc';
        if (is_dir($inc_src)) {
            $err = $this->copy_dir_recursive($inc_src, $target . '/inc');
            if ($err !== null) {
                return ['ok' => false, 'message' => 'Не удалось скопировать inc/ в папку темы: ' . $err];
            }
        }

        // 4. Compose style.css = base template + chosen style variant + variant-specific CSS
        //    from header/footer companion files (or legacy JSON `css` field).
        $style_template = (string)@file_get_contents($base . 'style.css.template');
        $variant_css    = (string)@file_get_contents(SITE_BUILDER_PATH . 'templates/theme/styles/' . $style . '.css');
        $header_meta    = $this->read_variant_meta('headers', $header);
        $footer_meta    = $this->read_variant_meta('footers', $footer);
        $header_css     = (string)($header_meta['css'] ?? '');
        $footer_css     = (string)($footer_meta['css'] ?? '');
        $header_js      = (string)($header_meta['js']  ?? '');
        $footer_js      = (string)($footer_meta['js']  ?? '');

        $combined_variant_css = $variant_css
            . "\n\n/* ===== Header variant: " . $header . " ===== */\n" . $header_css
            . "\n\n/* ===== Footer variant: " . $footer . " ===== */\n" . $footer_css;

        $style_css = strtr($style_template, [
            '{{THEME_NAME}}'        => self::THEME_NAME,
            '{{THEME_DESCRIPTION}}' => 'Сгенерирована плагином Site Builder. Шапка: ' . $header . ', футер: ' . $footer . ', стиль: ' . $style . '.',
            '{{THEME_VERSION}}'     => SITE_BUILDER_VERSION,
            '{{VARIANT_STYLES}}'    => $combined_variant_css,
        ]);
        if (@file_put_contents($target . '/style.css', $style_css) === false) {
            return ['ok' => false, 'message' => 'Не удалось записать style.css в папку темы'];
        }

        // 4.5. If either header or footer ships a companion JS file, assemble a
        // single theme.js in assets/ — functions.php picks it up via
        // wp_enqueue_scripts, but only if the file actually exists (so themes
        // built from JS-less variants stay lean).
        $combined_js = trim($header_js) . "\n" . trim($footer_js);
        if (trim($combined_js) !== '') {
            $assets_dir = $target . '/assets';
            if (!is_dir($assets_dir)) @mkdir($assets_dir, 0755, true);
            $js_body = "/* Theme JS — assembled from header:{$header} + footer:{$footer} */\n"
                     . "(function () {\n" . $combined_js . "\n})();\n";
            @file_put_contents($assets_dir . '/theme.js', $js_body);
        }

        // 5. Copy header.php and footer.php from chosen variants
        if (!@copy(SITE_BUILDER_PATH . 'templates/theme/headers/' . $header . '.php', $target . '/header.php')) {
            return ['ok' => false, 'message' => 'Не удалось записать header.php в папку темы'];
        }
        if (!@copy(SITE_BUILDER_PATH . 'templates/theme/footers/' . $footer . '.php', $target . '/footer.php')) {
            return ['ok' => false, 'message' => 'Не удалось записать footer.php в папку темы'];
        }

        // 6. Save the chosen combination for the UI to display next time
        update_option('site_builder_theme_choices', [
            'header' => $header,
            'footer' => $footer,
            'style'  => $style,
            'built_at' => time(),
        ]);

        // 7. Activate the theme (optional). switch_theme() takes the stylesheet
        //    directory name, which equals our slug.
        $activated = false;
        if ($activate && get_stylesheet() !== self::THEME_SLUG) {
            switch_theme(self::THEME_SLUG);
            $activated = true;
        } elseif ($activate && get_stylesheet() === self::THEME_SLUG) {
            // Already active — bump theme_mod so caches invalidate
            set_theme_mod('site_builder_theme_built_at', time());
            $activated = true;
        }

        // 8. If activated, sync the plugin's menu-location settings to the
        //    generated theme's locations. Our theme always declares exactly
        //    'primary' and 'footer' (functions.php → register_nav_menus), so
        //    Main Auto Menu → primary and Footer Auto Menu → footer is the
        //    canonical pairing. Previously the user had to remember to update
        //    these settings manually after switching to our theme.
        if ($activated) {
            $st = Site_Builder_Settings::all();
            $st['menu_location_main']   = 'primary';
            $st['menu_location_footer'] = 'footer';
            update_option(Site_Builder_Settings::OPTION_KEY, $st);
            if (method_exists('Site_Builder_Settings', 'clear_cache')) {
                Site_Builder_Settings::clear_cache();
            }
        }

        return [
            'ok'        => true,
            'message'   => $activated
                ? 'Тема сгенерирована и активирована (' . $header . ' / ' . $footer . ' / ' . $style . '). Меню привязаны к локациям primary / footer автоматически.'
                : 'Тема сгенерирована (' . $header . ' / ' . $footer . ' / ' . $style . '). Активируйте её в Внешний вид → Темы.',
            'path'      => $target,
            'activated' => $activated,
        ];
    }

    /**
     * Get the user's last-saved choices (or sensible defaults if none).
     */
    public static function get_current_choices(): array {
        $saved = get_option('site_builder_theme_choices', []);
        if (!is_array($saved)) $saved = [];
        return [
            'header' => (string)($saved['header'] ?? 'classic'),
            'footer' => (string)($saved['footer'] ?? 'classic'),
            'style'  => (string)($saved['style']  ?? 'bright'),
        ];
    }

    /**
     * Runtime toggle-able options for theme-side modules (inc/*.php). Stored
     * separately from theme choices because they can be changed without
     * regenerating the theme — modules read the option from DB on each request
     * and skip rendering if the flag is false.
     *
     * To add a new toggle: append the key + default value to $defaults here,
     * then have the module check `get_option('site_builder_theme_module_options')`
     * before rendering. The UI on tab-theme.php will pick up the new option
     * automatically once a corresponding checkbox is added.
     */
    public static function get_module_options(): array {
        $defaults = [
            'show_breadcrumbs' => true,
        ];
        $saved = get_option('site_builder_theme_module_options', []);
        if (!is_array($saved)) $saved = [];
        // Merge with defaults so newly-added keys automatically get their
        // default values on existing installations.
        return array_merge($defaults, $saved);
    }

    public static function set_module_options(array $options): void {
        // Only accept known keys — reject unexpected input from tampered AJAX
        $known = array_keys(self::get_module_options());
        $clean = [];
        foreach ($known as $k) {
            if (array_key_exists($k, $options)) {
                $clean[$k] = (bool)$options[$k];
            }
        }
        // Keep already-saved values for keys the caller didn't send
        $existing = get_option('site_builder_theme_module_options', []);
        if (!is_array($existing)) $existing = [];
        update_option('site_builder_theme_module_options', array_merge($existing, $clean));
    }

    /**
     * Generate a simple schematic SVG preview for a variant card. Keeps the
     * theme tab visually informative without shipping real screenshots for
     * every variant (which would have to be updated on every design tweak).
     */
    public static function render_preview_svg(string $category, string $preview_type, string $slug, ?array $palette = null): string {
        $w = 260; $h = 100;
        $bg = '#f9fafb'; $fg = '#111827'; $accent = '#2563eb'; $muted = '#9ca3af';

        // Use variant palette if provided (styles) — first 4 entries are bg/bgAlt/text/link
        if (is_array($palette) && count($palette) >= 3) {
            $bg = $palette[0] ?? $bg;
            $fg = $palette[2] ?? $fg;
            $accent = $palette[3] ?? $accent;
        }

        $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" aria-hidden="true">';
        $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . esc_attr($bg) . '"/>';

        if ($category === 'headers') {
            switch ($preview_type) {
                case 'sticky-horizontal':
                    // Thin bar, logo left, menu items right, subtle underline for sticky feel
                    $svg .= '<rect x="0" y="0" width="' . $w . '" height="18" fill="' . esc_attr($accent) . '" opacity="0.05"/>';
                    $svg .= '<rect x="0" y="17" width="' . $w . '" height="1" fill="' . esc_attr($accent) . '" opacity="0.3"/>';
                    $svg .= '<rect x="12" y="6" width="34" height="6" rx="1" fill="' . esc_attr($fg) . '"/>';
                    for ($i = 0; $i < 4; $i++) $svg .= '<rect x="' . (150 + $i * 24) . '" y="8" width="18" height="3" fill="' . esc_attr($fg) . '" opacity="0.7"/>';
                    // hint: content below
                    $svg .= '<rect x="12" y="35" width="140" height="4" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    $svg .= '<rect x="12" y="45" width="180" height="4" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    $svg .= '<rect x="12" y="55" width="120" height="4" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    break;
                case 'off-canvas':
                    // Burger + logo, then a slide-out panel hint on the left
                    $svg .= '<rect x="0" y="0" width="' . $w . '" height="24" fill="' . esc_attr($bg) . '"/>';
                    $svg .= '<rect x="0" y="23" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    // burger
                    for ($i = 0; $i < 3; $i++) $svg .= '<rect x="12" y="' . (8 + $i * 4) . '" width="14" height="2" fill="' . esc_attr($fg) . '"/>';
                    // logo
                    $svg .= '<rect x="36" y="8" width="46" height="8" rx="1" fill="' . esc_attr($fg) . '"/>';
                    // off-canvas panel (dashed hint)
                    $svg .= '<rect x="0" y="24" width="72" height="76" fill="' . esc_attr($accent) . '" opacity="0.06"/>';
                    $svg .= '<line x1="72" y1="24" x2="72" y2="100" stroke="' . esc_attr($accent) . '" stroke-dasharray="2 2" opacity="0.5"/>';
                    for ($i = 0; $i < 5; $i++) $svg .= '<rect x="10" y="' . (34 + $i * 10) . '" width="52" height="3" fill="' . esc_attr($fg) . '" opacity="0.6"/>';
                    break;
                case 'two-row':
                    // Top row: logo + utility. Bottom row: full menu bar.
                    $svg .= '<rect x="0" y="0" width="' . $w . '" height="20" fill="' . esc_attr($accent) . '" opacity="0.05"/>';
                    $svg .= '<rect x="12" y="6" width="34" height="8" rx="1" fill="' . esc_attr($fg) . '"/>';
                    $svg .= '<rect x="200" y="8" width="12" height="3" fill="' . esc_attr($fg) . '" opacity="0.6"/>';
                    $svg .= '<rect x="216" y="8" width="12" height="3" fill="' . esc_attr($fg) . '" opacity="0.6"/>';
                    $svg .= '<rect x="232" y="8" width="16" height="3" fill="' . esc_attr($fg) . '" opacity="0.6"/>';
                    $svg .= '<rect x="0" y="20" width="' . $w . '" height="1" fill="' . esc_attr($accent) . '" opacity="0.2"/>';
                    // menu row
                    $svg .= '<rect x="0" y="21" width="' . $w . '" height="20" fill="' . esc_attr($bg) . '"/>';
                    for ($i = 0; $i < 5; $i++) $svg .= '<rect x="' . (12 + $i * 40) . '" y="29" width="26" height="3" fill="' . esc_attr($fg) . '" opacity="0.7"/>';
                    $svg .= '<rect x="0" y="41" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    break;
                default:
                    // Generic horizontal menu
                    $svg .= '<rect x="12" y="8" width="34" height="8" rx="1" fill="' . esc_attr($fg) . '"/>';
                    for ($i = 0; $i < 4; $i++) $svg .= '<rect x="' . (150 + $i * 24) . '" y="10" width="18" height="3" fill="' . esc_attr($fg) . '" opacity="0.7"/>';
                    $svg .= '<rect x="0" y="24" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    break;
            }
        } elseif ($category === 'footers') {
            switch ($preview_type) {
                case 'columns-4':
                    $svg .= '<rect x="0" y="6" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    for ($c = 0; $c < 4; $c++) {
                        $x = 12 + $c * 60;
                        $svg .= '<rect x="' . $x . '" y="20" width="26" height="3" fill="' . esc_attr($fg) . '" opacity="0.8"/>';
                        for ($r = 0; $r < 4; $r++) $svg .= '<rect x="' . $x . '" y="' . (32 + $r * 8) . '" width="46" height="3" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    }
                    $svg .= '<rect x="0" y="80" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    $svg .= '<rect x="90" y="86" width="80" height="4" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    break;
                case 'newsletter':
                    // Big signup col left, 3 narrow cols right
                    $svg .= '<rect x="0" y="6" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    // signup col
                    $svg .= '<rect x="12" y="20" width="26" height="3" fill="' . esc_attr($fg) . '" opacity="0.8"/>';
                    $svg .= '<rect x="12" y="28" width="90" height="5" fill="' . esc_attr($fg) . '"/>';
                    $svg .= '<rect x="12" y="40" width="80" height="4" fill="' . esc_attr($muted) . '" opacity="0.4"/>';
                    $svg .= '<rect x="12" y="52" width="70" height="10" rx="2" fill="' . esc_attr($bg) . '" stroke="' . esc_attr($muted) . '" opacity="0.6"/>';
                    $svg .= '<rect x="86" y="52" width="26" height="10" rx="2" fill="' . esc_attr($accent) . '"/>';
                    // 3 link cols
                    for ($c = 0; $c < 3; $c++) {
                        $x = 128 + $c * 42;
                        $svg .= '<rect x="' . $x . '" y="20" width="22" height="3" fill="' . esc_attr($fg) . '" opacity="0.8"/>';
                        for ($r = 0; $r < 4; $r++) $svg .= '<rect x="' . $x . '" y="' . (32 + $r * 7) . '" width="32" height="3" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    }
                    $svg .= '<rect x="0" y="78" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    $svg .= '<rect x="90" y="85" width="80" height="4" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    break;
                case 'divided-3':
                    $svg .= '<rect x="0" y="0" width="' . $w . '" height="2" fill="' . esc_attr($accent) . '"/>';
                    for ($c = 0; $c < 3; $c++) {
                        $x = 12 + $c * 82;
                        // bullet + heading
                        $svg .= '<circle cx="' . ($x + 3) . '" cy="18" r="3" fill="' . esc_attr($accent) . '"/>';
                        $svg .= '<rect x="' . ($x + 12) . '" y="16" width="30" height="4" fill="' . esc_attr($fg) . '"/>';
                        for ($r = 0; $r < 3; $r++) $svg .= '<rect x="' . $x . '" y="' . (30 + $r * 9) . '" width="66" height="3" fill="' . esc_attr($muted) . '" opacity="0.6"/>';
                    }
                    // social icons hint
                    for ($i = 0; $i < 3; $i++) $svg .= '<circle cx="' . (180 + $i * 12) . '" cy="62" r="4" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    $svg .= '<rect x="0" y="78" width="' . $w . '" height="1" fill="' . esc_attr($muted) . '" opacity="0.3"/>';
                    $svg .= '<rect x="90" y="85" width="80" height="4" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    break;
                default:
                    // Generic columns
                    for ($c = 0; $c < 3; $c++) {
                        $x = 12 + $c * 80;
                        $svg .= '<rect x="' . $x . '" y="20" width="30" height="4" fill="' . esc_attr($fg) . '"/>';
                        for ($r = 0; $r < 3; $r++) $svg .= '<rect x="' . $x . '" y="' . (32 + $r * 9) . '" width="60" height="3" fill="' . esc_attr($muted) . '" opacity="0.5"/>';
                    }
                    break;
            }
        } elseif ($category === 'styles') {
            // Show palette as color chips + preview text
            $chips = is_array($palette) ? array_slice($palette, 0, 5) : [$bg, $fg, $accent];
            $chip_w = 40; $chip_h = 40; $gap = 6;
            $total_w = count($chips) * ($chip_w + $gap) - $gap;
            $start_x = ($w - $total_w) / 2;
            foreach ($chips as $i => $c) {
                $svg .= '<rect x="' . ($start_x + $i * ($chip_w + $gap)) . '" y="14" width="' . $chip_w . '" height="' . $chip_h . '" rx="4" fill="' . esc_attr($c) . '" stroke="' . esc_attr($muted) . '" stroke-opacity="0.2"/>';
            }
            // Sample "Aa" caption at bottom
            $svg .= '<text x="' . ($w / 2) . '" y="82" text-anchor="middle" font-family="Georgia, serif" font-size="14" font-weight="700" fill="' . esc_attr($fg) . '">Aa</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    private function variant_exists(string $category, string $slug): bool {
        $ext = $category === 'styles' ? 'css' : 'php';
        return is_file(SITE_BUILDER_PATH . 'templates/theme/' . $category . '/' . $slug . '.' . $ext);
    }

    private function read_variant_meta(string $category, string $slug): array {
        $dir = SITE_BUILDER_PATH . 'templates/theme/' . $category . '/';
        // Metadata (name/description/etc) still lives in JSON
        $meta = [];
        $json_path = $dir . $slug . '.json';
        if (is_file($json_path)) {
            $decoded = json_decode((string)@file_get_contents($json_path), true);
            if (is_array($decoded)) $meta = $decoded;
        }
        // CSS: prefer a companion .css file over the legacy `css` field inside
        // the JSON. Writing raw CSS inside a JSON string is painful (escaping,
        // no syntax highlighting, no linter), so v1.1.4 moved the CSS to a
        // separate file. The JSON `css` field is still honoured for backward
        // compatibility with any variants that haven't been migrated yet.
        $css_path = $dir . $slug . '.css';
        if (is_file($css_path)) {
            $meta['css'] = (string)@file_get_contents($css_path);
        } else {
            $meta['css'] = (string)($meta['css'] ?? '');
        }
        // JS: same pattern — optional companion .js file for interactive
        // variants like burger menus, off-canvas panels, sticky headers.
        $js_path = $dir . $slug . '.js';
        $meta['js'] = is_file($js_path) ? (string)@file_get_contents($js_path) : '';
        return $meta;
    }

    /**
     * Recursively copy a directory tree. Used to bring templates/theme/base/inc/
     * into the generated theme. Returns null on success or an error message
     * describing the first failure (we abort on first failure rather than
     * leaving the theme in an inconsistent partial-copy state).
     */
    private function copy_dir_recursive(string $src, string $dst): ?string {
        if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
            return 'не удалось создать ' . $dst;
        }
        $items = @scandir($src);
        if ($items === false) return 'не удалось прочитать ' . $src;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $src_path = $src . '/' . $item;
            $dst_path = $dst . '/' . $item;
            if (is_dir($src_path)) {
                $err = $this->copy_dir_recursive($src_path, $dst_path);
                if ($err !== null) return $err;
            } else {
                if (!@copy($src_path, $dst_path)) {
                    return 'не удалось скопировать ' . $src_path;
                }
            }
        }
        return null;
    }
}
