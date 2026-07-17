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

        // 3. Copy base files (functions.php, index.php, page.php, front-page.php, 404.php).
        // style.css is built separately below — it's a template needing substitution.
        // 404.php uses WordPress template hierarchy: it's picked up automatically
        // for any request that doesn't resolve to a post/page.
        $base = SITE_BUILDER_PATH . 'templates/theme/base/';
        foreach (['functions.php', 'index.php', 'page.php', 'front-page.php', '404.php'] as $file) {
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
     * Random-choice flags — per-category toggle that, when enabled, causes the
     * generator to pick a random variant from list_variants() instead of the
     * one the user has selected. Useful for "surprise me" workflows and for
     * batch operations across many sites where consistent look is not required.
     *
     * Defaults:
     *   header → false (users usually have a preferred site chrome)
     *   footer → false (same reason)
     *   style  → true  (color scheme is the safest thing to randomize —
     *                   it's purely visual, no layout implications)
     */
    public static function get_random_choices(): array {
        $defaults = [
            'header' => false,
            'footer' => false,
            'style'  => true,
        ];
        $saved = get_option('site_builder_theme_random_choices', null);
        // null means the option was never set — use full defaults. Empty array
        // (["}"] } false in PHP context) would also fall into this branch if
        // is_array check fails, keeping behavior safe.
        if (!is_array($saved)) return $defaults;
        return array_merge($defaults, $saved);
    }

    public static function set_random_choices(array $choices): void {
        $known = ['header', 'footer', 'style'];
        $clean = [];
        foreach ($known as $k) {
            if (array_key_exists($k, $choices)) {
                $clean[$k] = (bool)$choices[$k];
            }
        }
        $existing = get_option('site_builder_theme_random_choices', []);
        if (!is_array($existing)) $existing = [];
        update_option('site_builder_theme_random_choices', array_merge($existing, $clean));
    }

    /**
     * Pick a random variant slug from the given category. Returns empty string
     * if no variants are available (should never happen in a valid install).
     * Used by theme_build when the random-choice flag is set for a category.
     */
    public static function pick_random_variant(string $category): string {
        $variants = self::list_variants($category);
        if (empty($variants)) return '';
        $picked = $variants[array_rand($variants)];
        return (string)($picked['slug'] ?? '');
    }

    /**
     * Generate a simple SVG palette-preview for a style variant card. Headers
     * and footers no longer render previews here — their card is a text-only
     * summary of name + description (SVG schematics were removed in v1.1.4-beta2
     * because a schematic layout hint conveys less than the description itself).
     * If we later want interactive previews, they should be full iframes with
     * the actual theme rendered against sample content — not hand-drawn SVGs.
     */
    public static function render_preview_svg(string $category, string $preview_type, string $slug, ?array $palette = null): string {
        if ($category !== 'styles') return '';

        $w = 260; $h = 100;
        $bg = '#f9fafb'; $fg = '#111827'; $muted = '#9ca3af';

        if (is_array($palette) && count($palette) >= 3) {
            $bg = $palette[0] ?? $bg;
            $fg = $palette[2] ?? $fg;
        }

        $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" aria-hidden="true">';
        $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . esc_attr($bg) . '"/>';

        $chips = is_array($palette) ? array_slice($palette, 0, 5) : [$bg, $fg];
        $chip_w = 40; $chip_h = 40; $gap = 6;
        $total_w = count($chips) * ($chip_w + $gap) - $gap;
        $start_x = ($w - $total_w) / 2;
        foreach ($chips as $i => $c) {
            $svg .= '<rect x="' . ($start_x + $i * ($chip_w + $gap)) . '" y="14" width="' . $chip_w . '" height="' . $chip_h . '" rx="4" fill="' . esc_attr($c) . '" stroke="' . esc_attr($muted) . '" stroke-opacity="0.2"/>';
        }
        $svg .= '<text x="' . ($w / 2) . '" y="82" text-anchor="middle" font-family="Georgia, serif" font-size="14" font-weight="700" fill="' . esc_attr($fg) . '">Aa</text>';
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
