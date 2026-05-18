<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class — bootstraps everything else.
 */
class Site_Builder {

    private static ?Site_Builder $instance = null;
    private ?Site_Builder_Admin $admin = null;

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
        require_once SITE_BUILDER_PATH . 'includes/class-admin.php';
    }

    private function init_hooks(): void {
        if (is_admin()) {
            $this->admin = new Site_Builder_Admin();
        }
    }

    /**
     * Runs once when the plugin is activated.
     * Reserved for future DB-schema setup and option initialization.
     */
    public static function activate(): void {
        update_option('site_builder_version', SITE_BUILDER_VERSION);
        update_option('site_builder_activated_at', current_time('mysql'));
    }

    /**
     * Runs when the plugin is deactivated.
     * Cleans up transients and scheduled tasks (none yet).
     */
    public static function deactivate(): void {
        // Reserved for future cleanup
    }
}
