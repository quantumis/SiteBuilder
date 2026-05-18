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
        $theme_dir = get_template_directory();
        $css_path = $theme_dir . '/' . SITE_BUILDER_THEME_CSS_DIR . '/style.css';
        if (!file_exists($css_path)) return;

        wp_enqueue_style(
            'site-builder-imported',
            get_template_directory_uri() . '/' . SITE_BUILDER_THEME_CSS_DIR . '/style.css',
            [],
            (string)filemtime($css_path)
        );
    }
}
