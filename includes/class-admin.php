<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the admin menu, page rendering, and tab navigation.
 */
class Site_Builder_Admin {

    private const CAPABILITY = 'manage_options';
    private const MENU_SLUG  = 'site-builder';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void {
        add_menu_page(
            'Site Builder',
            'Site Builder',
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-screenoptions',
            58
        );
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        wp_enqueue_style(
            'site-builder-admin',
            SITE_BUILDER_URL . 'assets/css/admin.css',
            [],
            SITE_BUILDER_VERSION
        );
    }

    public function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Недостаточно прав для доступа к этой странице.', 'site-builder'));
        }

        $tabs = [
            'create'   => ['label' => 'Создание сайта',     'icon' => 'plus'],
            'add'      => ['label' => 'Добавление страниц', 'icon' => 'plus-alt2'],
            'rollback' => ['label' => 'Откат',              'icon' => 'undo'],
            'report'   => ['label' => 'Отчёт',              'icon' => 'clipboard'],
        ];

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'create';
        if (!isset($tabs[$current_tab])) {
            $current_tab = 'create';
        }

        include SITE_BUILDER_PATH . 'views/admin-page.php';
    }
}
