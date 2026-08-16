<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend hooks. Enqueues the imported CSS from the active theme's imported-styles/ folder
 * — no functions.php modification required.
 */
class Site_Builder_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_imported_styles']);
    }

    public function enqueue_imported_styles(): void {
        // Use get_stylesheet_directory() (not template_directory) so child themes
        // work correctly — that's also where init_site_assets() writes the file.
        $theme_dir = get_stylesheet_directory();
        $theme_url = get_stylesheet_directory_uri();
        $css_path  = $theme_dir . '/' . SITE_BUILDER_THEME_CSS_DIR . '/style.css';
        if (!file_exists($css_path)) return;

        wp_enqueue_style(
            'site-builder-imported',
            $theme_url . '/' . SITE_BUILDER_THEME_CSS_DIR . '/style.css',
            [],
            (string)filemtime($css_path)
        );
    }
}
