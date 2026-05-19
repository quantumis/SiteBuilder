<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class. Loads dependencies and wires admin/frontend/AJAX.
 */
class Site_Builder {

    private static ?Site_Builder $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void {
        $base = SITE_BUILDER_PATH . 'includes/';
        require_once $base . 'helpers.php';
        require_once $base . 'class-installer.php';
        require_once $base . 'class-import-tracker.php';
        require_once $base . 'class-media-handler.php';
        require_once $base . 'class-content-processor.php';
        require_once $base . 'class-task-builder.php';
        require_once $base . 'class-page-importer.php';
        require_once $base . 'class-hub-importer.php';
        require_once $base . 'class-wipe-handler.php';
        require_once $base . 'class-articles-setup.php';
        require_once $base . 'class-rollback-handler.php';
        require_once $base . 'class-ajax-handler.php';
        require_once $base . 'class-frontend.php';
        require_once $base . 'class-admin.php';
    }

    private function init_hooks(): void {
        new Site_Builder_Frontend();

        if (is_admin()) {
            new Site_Builder_Admin();
            new Site_Builder_Ajax_Handler();
        }
    }

    public static function activate(): void {
        require_once SITE_BUILDER_PATH . 'includes/class-installer.php';
        Site_Builder_Installer::install();
        update_option('site_builder_version', SITE_BUILDER_VERSION);
        update_option('site_builder_activated_at', current_time('mysql'));
    }

    public static function deactivate(): void {
        delete_option('site_builder_active_import');
    }
}
