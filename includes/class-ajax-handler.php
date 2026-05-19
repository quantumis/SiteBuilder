<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoints for the import flow.
 * All endpoints require manage_options capability and a valid nonce.
 */
class Site_Builder_Ajax_Handler {

    private const CAP   = 'manage_options';
    private const NONCE = 'site_builder_ajax';

    public function __construct() {
        add_action('wp_ajax_site_builder_create_start',     [$this, 'create_start']);
        add_action('wp_ajax_site_builder_add_start',        [$this, 'add_start']);
        add_action('wp_ajax_site_builder_rollback_start',   [$this, 'rollback_start']);
        add_action('wp_ajax_site_builder_process_batch',    [$this, 'process_batch']);
        add_action('wp_ajax_site_builder_cancel',           [$this, 'cancel']);
        add_action('wp_ajax_site_builder_clear_lock',       [$this, 'clear_lock']);
        add_action('wp_ajax_site_builder_check_pages',      [$this, 'check_pages']);
    }

    public static function nonce(): string {
        return wp_create_nonce(self::NONCE);
    }

    private function authorize(): void {
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка проверки безопасности (nonce)'], 403);
        }
    }

    /**
     * Endpoint: check if the site has existing pages (for the warning UI).
     */
    public function check_pages(): void {
        $this->authorize();
        wp_send_json_success(['count' => Site_Builder_Helpers::count_existing_pages()]);
    }

    /**
     * Endpoint: start a CREATE import. Builds the task queue.
     */
    public function create_start(): void {
        $this->authorize();

        $folder_raw = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
        $folder = Site_Builder_Helpers::sanitize_folder_name($folder_raw);
        if (!$folder) {
            wp_send_json_error(['message' => 'Некорректное имя папки']);
        }

        $source_dir = ABSPATH . $folder;
        if (!is_dir($source_dir)) {
            wp_send_json_error(['message' => 'Папка "' . esc_html($folder) . '" не найдена в корне сайта']);
        }

        $schedule_mode   = isset($_POST['schedule_mode']) ? sanitize_key(wp_unslash($_POST['schedule_mode'])) : 'instant';
        if (!in_array($schedule_mode, ['instant', 'one_day', 'period'], true)) {
            $schedule_mode = 'instant';
        }
        $days            = isset($_POST['days']) ? max(1, (int)$_POST['days']) : 60;
        $immediate_count = isset($_POST['immediate_count']) ? max(0, (int)$_POST['immediate_count']) : 10;
        $wait_week       = !empty($_POST['wait_week']);
        $confirmation    = isset($_POST['confirmation']) ? sanitize_text_field(wp_unslash($_POST['confirmation'])) : '';
        $wipe_first      = ($confirmation === 'УДАЛИТЬ');

        // Safety: if site has pages but no confirmation, refuse.
        $existing = Site_Builder_Helpers::count_existing_pages();
        if ($existing > 0 && !$wipe_first) {
            wp_send_json_error([
                'message' => 'На сайте найдено страниц: ' . $existing . '. Введите "УДАЛИТЬ" для подтверждения полного сноса.',
                'requires_confirmation' => true,
            ]);
        }

        $tracker = new Site_Builder_Import_Tracker();

        if ($tracker->get_lock() !== null) {
            wp_send_json_error(['message' => 'Уже выполняется другой импорт. Дождитесь его завершения или нажмите "Отменить" в активной сессии.']);
        }

        $settings = [
            'schedule_mode'   => $schedule_mode,
            'days'            => $days,
            'immediate_count' => $immediate_count,
            'wait_week'       => $wait_week,
            'wipe_first'      => $wipe_first,
        ];

        $import_id = $tracker->create_import('create', $folder, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку импорта']);
        }

        try {
            $builder = new Site_Builder_Task_Builder();
            $queue = $builder->build_create_queue($source_dir, $settings, $wipe_first);
        } catch (Throwable $e) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'Ошибка построения очереди: ' . $e->getMessage()]);
        }

        // Create the nav menu now (so all later batches can reference it)
        $existing_menu = wp_get_nav_menu_object(SITE_BUILDER_MENU_NAME);
        if ($existing_menu && !$wipe_first) {
            // If menu exists from a prior import and we're not wiping, delete it cleanly
            wp_delete_nav_menu($existing_menu->term_id);
        }
        $menu_id = wp_create_nav_menu(SITE_BUILDER_MENU_NAME);
        if (is_wp_error($menu_id)) {
            // Maybe the wipe will handle it; fall back to existing
            $existing_menu = wp_get_nav_menu_object(SITE_BUILDER_MENU_NAME);
            $menu_id = $existing_menu ? $existing_menu->term_id : 0;
        } else {
            $tracker->track_item($import_id, 'nav_menu', (int)$menu_id);
        }

        $settings['menu_id'] = (int)$menu_id;

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'   => $import_id,
            'total'       => count($queue),
            'batch_size'  => SITE_BUILDER_BATCH_SIZE,
        ]);
    }

    /**
     * Endpoint: start an ADD import. Builds task queue for adding pages under Articles.
     */
    public function add_start(): void {
        $this->authorize();

        $folder_raw = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
        $folder = Site_Builder_Helpers::sanitize_folder_name($folder_raw);
        if (!$folder) {
            wp_send_json_error(['message' => 'Некорректное имя папки']);
        }

        $source_dir = ABSPATH . $folder;
        if (!is_dir($source_dir)) {
            wp_send_json_error(['message' => 'Папка "' . esc_html($folder) . '" не найдена в корне сайта']);
        }

        $schedule_mode = isset($_POST['schedule_mode']) ? sanitize_key(wp_unslash($_POST['schedule_mode'])) : 'instant';
        if (!in_array($schedule_mode, ['instant', 'one_day', 'period'], true)) {
            $schedule_mode = 'instant';
        }
        $days            = isset($_POST['days']) ? max(1, (int)$_POST['days']) : 60;
        $immediate_count = isset($_POST['immediate_count']) ? max(0, (int)$_POST['immediate_count']) : 10;
        $wait_week       = !empty($_POST['wait_week']);

        $tracker = new Site_Builder_Import_Tracker();
        if ($tracker->get_lock() !== null) {
            wp_send_json_error(['message' => 'Уже выполняется другой импорт. Дождитесь его завершения или нажмите "Отменить" в активной сессии.']);
        }

        $settings = [
            'schedule_mode'   => $schedule_mode,
            'days'            => $days,
            'immediate_count' => $immediate_count,
            'wait_week'       => $wait_week,
        ];

        $import_id = $tracker->create_import('add', $folder, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку импорта']);
        }

        try {
            $builder = new Site_Builder_Task_Builder();
            $queue = $builder->build_add_queue($source_dir, $settings);
        } catch (Throwable $e) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'Ошибка построения очереди: ' . $e->getMessage()]);
        }

        if (count($queue) <= 1) {
            // Only the articles_setup task — no pages found
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'В папке "' . esc_html($folder) . '" не найдены HTML-файлы или подпапки с index.html']);
        }

        // Reuse existing "Main Auto Menu" if present, otherwise create one
        $existing_menu = wp_get_nav_menu_object(SITE_BUILDER_MENU_NAME);
        $menu_id = 0;
        if ($existing_menu) {
            $menu_id = (int)$existing_menu->term_id;
        } else {
            $new_menu = wp_create_nav_menu(SITE_BUILDER_MENU_NAME);
            if (!is_wp_error($new_menu)) {
                $menu_id = (int)$new_menu;
                $tracker->track_item($import_id, 'nav_menu', $menu_id);
            }
        }
        $settings['menu_id'] = $menu_id;

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'  => $import_id,
            'total'      => count($queue),
            'batch_size' => SITE_BUILDER_BATCH_SIZE,
        ]);
    }

    /**
     * Endpoint: start a rollback for the last completed CREATE or ADD import.
     */
    public function rollback_start(): void {
        $this->authorize();

        $tracker = new Site_Builder_Import_Tracker();

        if ($tracker->get_lock() !== null) {
            wp_send_json_error(['message' => 'Уже выполняется другой импорт. Дождитесь его завершения или нажмите "Прервать и сбросить".']);
        }

        $target = $tracker->get_last_rollbackable_import();
        if (!$target) {
            wp_send_json_error(['message' => 'Нет импортов, доступных для отката']);
        }

        $settings = [
            'target_import_id'   => (int)$target->id,
            'target_import_type' => (string)$target->type,
            'target_folder'      => (string)$target->folder_name,
        ];

        // Create a new "rollback" import record. Its folder_name carries the target
        // folder for readability in logs/UI.
        $import_id = $tracker->create_import('rollback', (string)$target->folder_name, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку']);
        }

        try {
            $handler = new Site_Builder_Rollback_Handler();
            $queue = $handler->build_queue((int)$target->id, $tracker);
        } catch (Throwable $e) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'Ошибка построения плана отката: ' . $e->getMessage()]);
        }

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'  => $import_id,
            'total'      => count($queue),
            'batch_size' => SITE_BUILDER_BATCH_SIZE,
        ]);
    }

    /**
     * Endpoint: process the next batch of tasks for an in-progress import.
     */
    public function process_batch(): void {
        $this->authorize();

        $import_id = isset($_POST['import_id']) ? (int)$_POST['import_id'] : 0;
        $offset    = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

        if (!$import_id) wp_send_json_error(['message' => 'Не указан import_id']);

        $tracker = new Site_Builder_Import_Tracker();
        $import = $tracker->get_import($import_id);
        if (!$import) wp_send_json_error(['message' => 'Импорт не найден']);
        if ($import->status !== 'running') wp_send_json_error(['message' => 'Импорт не активен']);

        $queue = $tracker->get_queue($import_id);
        $total = count($queue);
        $settings = json_decode($import->settings ?: '{}', true) ?: [];
        $menu_id = (int)($settings['menu_id'] ?? 0);
        $source_dir = ABSPATH . $import->folder_name;

        $batch = array_slice($queue, $offset, SITE_BUILDER_BATCH_SIZE);
        if (empty($batch)) {
            $tracker->mark_finished($import_id, 'completed');
            $tracker->release_lock();
            wp_send_json_success([
                'done'      => true,
                'processed' => $total,
                'total'     => $total,
            ]);
        }

        $tracker->refresh_lock($import_id);

        $media = new Site_Builder_Media_Handler($tracker, $import_id);
        $processor = new Site_Builder_Content_Processor($media);
        $page_importer = new Site_Builder_Page_Importer($tracker, $processor, $import_id, $menu_id, $source_dir);
        $hub_importer = new Site_Builder_Hub_Importer($tracker, $processor, $media, $import_id);
        $wipe = new Site_Builder_Wipe_Handler();
        $articles_setup = new Site_Builder_Articles_Setup($tracker, $import_id, $menu_id);
        $rollback = new Site_Builder_Rollback_Handler();

        $current_label = '';
        $processed_in_batch = 0;

        foreach ($batch as $task) {
            $kind = $task['kind'] ?? '';
            try {
                switch ($kind) {
                    case 'wipe_page':
                        $pid = (int)($task['data']['post_id'] ?? 0);
                        $wipe->wipe_single_page($pid);
                        $current_label = 'Удаление страницы #' . $pid;
                        break;

                    case 'wipe_finalize':
                        $wipe->finalize_wipe($tracker);
                        $current_label = 'Очистка завершена';
                        break;

                    case 'hub_setup':
                        $result = $hub_importer->setup($source_dir);
                        $current_label = 'HUB: ' . ($result['message'] ?? '');
                        break;

                    case 'articles_setup':
                        $result = $articles_setup->ensure();
                        $current_label = $result['created']
                            ? 'Создана страница Articles'
                            : 'Используется существующая страница Articles';
                        break;

                    case 'page':
                        $result = $page_importer->import($task);
                        $current_label = ($result['title'] ?? '') . ' — ' . ($result['message'] ?? '');
                        break;

                    case 'add_page':
                        $result = $page_importer->import_add($task);
                        $current_label = ($result['title'] ?? '') . ' — ' . ($result['message'] ?? '');
                        break;

                    case 'rollback_menu_item':
                    case 'rollback_page':
                    case 'rollback_attachment':
                    case 'rollback_css_file':
                    case 'rollback_theme_file':
                    case 'rollback_option':
                    case 'rollback_nav_menu':
                    case 'rollback_finalize':
                        $result = $rollback->execute_task($task, $tracker);
                        $current_label = $result['message'] ?? '';
                        break;

                    default:
                        $current_label = 'Неизвестная задача: ' . $kind;
                }
            } catch (Throwable $e) {
                $tracker->append_error($import_id, 'Исключение в задаче "' . $kind . '": ' . $e->getMessage(), $task);
            }
            $processed_in_batch++;
        }

        $tracker->increment_processed($import_id, $processed_in_batch);
        $new_offset = $offset + $processed_in_batch;
        $done = $new_offset >= $total;

        if ($done) {
            $tracker->mark_finished($import_id, 'completed');
            $tracker->release_lock();
        }

        wp_send_json_success([
            'done'          => $done,
            'processed'     => $new_offset,
            'total'         => $total,
            'next_offset'   => $new_offset,
            'current_label' => $current_label,
        ]);
    }

    /**
     * Endpoint: cancel an active import (does NOT roll back changes; that's the rollback tab).
     */
    public function cancel(): void {
        $this->authorize();
        $import_id = isset($_POST['import_id']) ? (int)$_POST['import_id'] : 0;
        if (!$import_id) wp_send_json_error(['message' => 'Не указан import_id']);

        $tracker = new Site_Builder_Import_Tracker();
        $import = $tracker->get_import($import_id);
        if ($import) {
            $tracker->mark_finished($import_id, 'cancelled');
        }
        $tracker->release_lock();
        wp_send_json_success(['message' => 'Импорт отменён']);
    }

    /**
     * Endpoint: forcibly clear the active-import lock and mark whatever import is in it
     * as cancelled. Used when the previous import's tab was closed and the lock remains.
     */
    public function clear_lock(): void {
        $this->authorize();
        $tracker = new Site_Builder_Import_Tracker();
        $lock = $tracker->get_lock();
        if ($lock && !empty($lock['import_id'])) {
            $import = $tracker->get_import((int)$lock['import_id']);
            if ($import && $import->status === 'running') {
                $tracker->mark_finished((int)$lock['import_id'], 'cancelled');
            }
        }
        $tracker->release_lock();
        wp_send_json_success(['message' => 'Блокировка сброшена']);
    }
}
