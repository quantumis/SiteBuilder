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
        require_once $base . 'class-settings.php';
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
        require_once $base . 'class-md-restore.php';
        require_once $base . 'class-field-mapping.php';
        require_once $base . 'class-fsr-image-resolver.php';
        require_once $base . 'class-theme-generator.php';
        require_once $base . 'class-fsr-importer.php';
        require_once $base . 'class-blocks-parser.php';
        require_once $base . 'class-seo-metabox.php';
        require_once $base . 'class-menu-sync.php';
        require_once $base . 'class-ajax-handler.php';
        require_once $base . 'class-frontend.php';
        require_once $base . 'class-admin.php';
    }

    private function init_hooks(): void {
        new Site_Builder_Frontend();

        // Menu title sync — works everywhere (front + admin) because save_post
        // fires in both contexts. Registered outside the is_admin() branch.
        Site_Builder_Menu_Sync::register();

        if (is_admin()) {
            new Site_Builder_Admin();
            new Site_Builder_Ajax_Handler();
            // The SEO metabox is registered via static hooks — no instance needed.
            // Registration is idempotent (WP dedupes actions by callable), so
            // calling it once here is enough.
            Site_Builder_SEO_Metabox::register();
        }
    }

    public static function activate(): void {
        require_once SITE_BUILDER_PATH . 'includes/class-installer.php';
        Site_Builder_Installer::install();

        // One-time migration: tag existing menu items with a title source so
        // Menu_Sync knows which ones to auto-refresh. We only touch items in
        // menus this plugin actually manages (Main Auto Menu / Footer Auto
        // Menu) — arbitrary user-defined menus are left alone.
        require_once SITE_BUILDER_PATH . 'includes/class-menu-sync.php';
        self::migrate_menu_title_sources();

        update_option('site_builder_version', SITE_BUILDER_VERSION);
        update_option('site_builder_activated_at', current_time('mysql'));
    }

    /**
     * Backfill _sb_menu_title_source on existing menu items in our two auto
     * menus. Items without the meta are assumed to be 'auto' (that's what
     * the FSR importer effectively did before this system existed). The
     * migration is idempotent — running it multiple times is a no-op for
     * items that already have the meta set.
     */
    private static function migrate_menu_title_sources(): void {
        $menu_names = ['Main Auto Menu', 'Footer Auto Menu'];
        foreach ($menu_names as $menu_name) {
            $menu_obj = wp_get_nav_menu_object($menu_name);
            if (!$menu_obj) continue;
            $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
            if (!$items) continue;
            foreach ($items as $item) {
                $existing = get_post_meta($item->ID, Site_Builder_Menu_Sync::META_KEY, true);
                if ($existing === '') {
                    update_post_meta($item->ID, Site_Builder_Menu_Sync::META_KEY, 'auto');
                }
            }
        }
    }

    public static function deactivate(): void {
        delete_option('site_builder_active_import');
    }
}
