<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu, page rendering, and JS/CSS enqueueing.
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
            'dashicons-database-import',
            58
        );
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) return;

        wp_enqueue_style(
            'site-builder-admin',
            SITE_BUILDER_URL . 'assets/css/admin.css',
            [],
            SITE_BUILDER_VERSION
        );

        wp_enqueue_script(
            'site-builder-admin',
            SITE_BUILDER_URL . 'assets/js/admin.js',
            ['jquery'],
            SITE_BUILDER_VERSION,
            true
        );

        wp_localize_script('site-builder-admin', 'SiteBuilderData', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => Site_Builder_Ajax_Handler::nonce(),
            'wipeKeyword'    => 'УДАЛИТЬ',
            'batchSize'      => SITE_BUILDER_BATCH_SIZE,
            'existingPages'  => Site_Builder_Helpers::count_existing_pages(),
            'strings'        => [
                'starting'         => 'Запуск импорта…',
                'inProgress'       => 'Импорт идёт',
                'completed'        => 'Импорт завершён',
                'cancelled'        => 'Импорт отменён',
                'failed'           => 'Импорт упал с ошибкой',
                'genericError'     => 'Произошла ошибка',
                'confirmCancel'    => 'Прервать текущий импорт?',
                'wipeWarningTitle' => 'Внимание: на сайте уже есть страницы',
                'wipeWarningText'  => 'CREATE-импорт полностью удалит все существующие страницы сайта. Это действие необратимо. Чтобы подтвердить, введите слово УДАЛИТЬ заглавными буквами.',
                'wipeMismatch'     => 'Введите слово УДАЛИТЬ ровно так, как показано.',
            ],
        ]);
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
