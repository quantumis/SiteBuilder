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
                'slug'        => $slug,
                'name'        => (string)$meta['name'],
                'description' => (string)$meta['description'],
                'category'    => $category,
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
        //    from header.json and footer.json (they ship the styles inline in metadata).
        $style_template = (string)@file_get_contents($base . 'style.css.template');
        $variant_css    = (string)@file_get_contents(SITE_BUILDER_PATH . 'templates/theme/styles/' . $style . '.css');
        $header_meta    = $this->read_variant_meta('headers', $header);
        $footer_meta    = $this->read_variant_meta('footers', $footer);
        $header_css     = (string)($header_meta['css'] ?? '');
        $footer_css     = (string)($footer_meta['css'] ?? '');

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

    private function variant_exists(string $category, string $slug): bool {
        $ext = $category === 'styles' ? 'css' : 'php';
        return is_file(SITE_BUILDER_PATH . 'templates/theme/' . $category . '/' . $slug . '.' . $ext);
    }

    private function read_variant_meta(string $category, string $slug): array {
        $path = SITE_BUILDER_PATH . 'templates/theme/' . $category . '/' . $slug . '.json';
        if (!is_file($path)) return [];
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
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
