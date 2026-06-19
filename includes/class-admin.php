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
        add_action('admin_post_site_builder_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_site_builder_reset_settings', [$this, 'handle_reset_settings']);
        add_filter('update_footer', [$this, 'admin_footer_version'], 11);
    }

    /**
     * Show the plugin version in the WordPress admin bottom-right corner —
     * but only on Site Builder's own pages, so we don't override the WordPress
     * version everywhere. Priority 11 ensures we run after core (priority 10).
     */
    public function admin_footer_version($content) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos((string)$screen->id, self::MENU_SLUG) === false) {
            return $content;
        }
        return 'Site Builder v' . SITE_BUILDER_VERSION;
    }

    /**
     * Handle Settings tab form submission. Validates input, saves via Site_Builder_Settings,
     * then redirects back to the tab with a success/error flag in the URL.
     */
    public function handle_save_settings(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Недостаточно прав', 'site-builder'));
        }
        check_admin_referer('site_builder_save_settings');

        $input = $_POST['sb_settings'] ?? [];
        if (!is_array($input)) $input = [];
        $input = wp_unslash($input);

        $errors = Site_Builder_Settings::save($input);

        $args = ['page' => 'site-builder', 'tab' => 'settings'];
        if ($errors) {
            $args['sb_settings_errors'] = rawurlencode(wp_json_encode($errors));
        } else {
            $args['sb_settings_saved'] = '1';
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_reset_settings(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Недостаточно прав', 'site-builder'));
        }
        check_admin_referer('site_builder_reset_settings');
        Site_Builder_Settings::reset();
        wp_safe_redirect(add_query_arg([
            'page' => 'site-builder',
            'tab' => 'settings',
            'sb_settings_reset' => '1',
        ], admin_url('admin.php')));
        exit;
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
            'batchSize'      => Site_Builder_Settings::batch_create(),
            'existingPages'  => Site_Builder_Helpers::count_existing_pages(),
            'activeImport'   => $this->get_active_import_info(),
            'reportUrl'      => admin_url('admin.php?page=site-builder&tab=report'),
            'strings'        => [
                'starting'         => 'Запуск импорта…',
                'inProgress'       => 'Импорт идёт',
                'completed'        => 'Импорт завершён',
                'cancelled'        => 'Импорт отменён',
                'failed'           => 'Импорт упал с ошибкой',
                'genericError'     => 'Произошла ошибка',
                'confirmCancel'    => 'Прервать текущий импорт?',
                'confirmNav'       => 'Импорт ещё идёт. Переключение вкладки прервёт его. Прервать и переключиться?',
                'confirmUnload'    => 'Импорт идёт. Если закрыть или перезагрузить страницу, импорт прервётся.',
                'lockBlocked'      => 'Уже выполняется другой импорт. Завершите его или нажмите «Прервать и сбросить».',
                'wipeWarningTitle' => 'Внимание: на сайте уже есть страницы',
                'wipeWarningText'  => 'CREATE-импорт полностью удалит все существующие страницы сайта. Это действие необратимо. Чтобы подтвердить, введите слово УДАЛИТЬ заглавными буквами.',
                'wipeMismatch'     => 'Введите слово УДАЛИТЬ ровно так, как показано.',
            ],
        ]);
    }

    /**
     * Information about an in-progress import, for display in the admin UI.
     * Returns null if no import is active.
     */
    public function get_active_import_info(): ?array {
        $tracker = new Site_Builder_Import_Tracker();
        $lock = $tracker->get_lock();
        if (!$lock || empty($lock['import_id'])) return null;

        $import = $tracker->get_import((int)$lock['import_id']);
        if (!$import) return null;

        $heartbeat_ts = !empty($lock['heartbeat']) ? strtotime($lock['heartbeat']) : 0;
        $seconds_since_heartbeat = $heartbeat_ts ? max(0, time() - $heartbeat_ts) : null;

        return [
            'id'                       => (int)$import->id,
            'type'                     => (string)$import->type,
            'folder'                   => (string)$import->folder_name,
            'started_at'               => (string)$import->started_at,
            'heartbeat'                => $lock['heartbeat'] ?? null,
            'seconds_since_heartbeat'  => $seconds_since_heartbeat,
            'processed'                => (int)$import->processed_count,
            'total'                    => (int)$import->total_count,
            'user_id'                  => (int)$import->user_id,
            'is_current_user'          => ((int)$import->user_id === get_current_user_id()),
        ];
    }

    public function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Недостаточно прав для доступа к этой странице.', 'site-builder'));
        }

        $tabs = [
            'fsr'      => ['label' => 'FSR Import',          'icon' => 'category'],
        ];

        // Legacy importers — visible only when the corresponding setting is on.
        // Hidden by default so the v1.0.0+ UI stays focused on FSR.
        $st = Site_Builder_Settings::all();
        if (!empty($st['show_create_tab'])) {
            $tabs['create'] = ['label' => 'Создание сайта',     'icon' => 'plus'];
        }
        if (!empty($st['show_add_tab'])) {
            $tabs['add']    = ['label' => 'Добавление страниц', 'icon' => 'plus-alt2'];
        }
        if (!empty($st['show_md_tab'])) {
            $tabs['md']     = ['label' => 'MD Restore',          'icon' => 'media-text'];
        }

        // These are always available.
        $tabs['rollback'] = ['label' => 'Откат',     'icon' => 'undo'];
        $tabs['report']   = ['label' => 'Отчёт',     'icon' => 'clipboard'];
        $tabs['settings'] = ['label' => 'Настройки', 'icon' => 'admin-settings'];

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'fsr';
        if (!isset($tabs[$current_tab])) {
            // Direct URL to a hidden legacy tab → bounce back to FSR
            $current_tab = 'fsr';
        }

        $active_import = $this->get_active_import_info();

        include SITE_BUILDER_PATH . 'views/admin-page.php';
    }
}
