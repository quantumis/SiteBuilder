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
        add_action('wp_ajax_site_builder_md_start',         [$this, 'md_start']);
        add_action('wp_ajax_site_builder_fsr_start',        [$this, 'fsr_start']);
        add_action('wp_ajax_site_builder_fsr_save_mapping', [$this, 'fsr_save_mapping']);
        add_action('wp_ajax_site_builder_theme_build',      [$this, 'theme_build']);
        add_action('wp_ajax_site_builder_fsr_get_mapping',  [$this, 'fsr_get_mapping']);
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
     * Ensure a clean nav menu is ready for the FSR importer to populate. Returns
     * the term_id, or 0 if everything failed.
     *
     * Strategy:
     *   - ADD mode + menu exists       → reuse as-is
     *   - CREATE + menu exists         → delete with cache-reset, then create fresh.
     *                                    If create fails, fall back to clearing
     *                                    items on the existing menu and reusing it.
     *   - CREATE + no existing menu    → create fresh
     *
     * If absolutely everything fails (rare; usually a plugin blocking the hook),
     * we record a warning and return 0 — the importer continues but skips [M]/[F]
     * placement for that menu.
     */
    private function ensure_menu_ready(string $name, string $mode, Site_Builder_Import_Tracker $tracker, int $import_id): int {
        $existing = wp_get_nav_menu_object($name);

        if ($mode === 'add' && $existing) {
            return (int)$existing->term_id;
        }

        if ($existing) {
            $deleted = wp_delete_nav_menu($existing->term_id);
            // Force term-cache reset so the next wp_create_nav_menu doesn't see
            // the deleted term as "still there".
            clean_term_cache((int)$existing->term_id, 'nav_menu');
            wp_cache_delete('last_changed', 'terms');
            if (is_wp_error($deleted) || $deleted === false) {
                // Couldn't delete — clear items and reuse in place
                $this->clear_menu_items((int)$existing->term_id);
                $tracker->track_item($import_id, 'nav_menu', (int)$existing->term_id);
                return (int)$existing->term_id;
            }
        }

        $new_id = wp_create_nav_menu($name);
        if (!is_wp_error($new_id) && $new_id) {
            $tracker->track_item($import_id, 'nav_menu', (int)$new_id);
            return (int)$new_id;
        }

        // wp_create_nav_menu failed despite our cleanup. Try a few fallback paths.
        $retry = wp_get_nav_menu_object($name);
        if ($retry) {
            $this->clear_menu_items((int)$retry->term_id);
            $tracker->track_item($import_id, 'nav_menu', (int)$retry->term_id);
            return (int)$retry->term_id;
        }

        $by_slug = get_term_by('slug', sanitize_title($name), 'nav_menu');
        if ($by_slug) {
            $this->clear_menu_items((int)$by_slug->term_id);
            $tracker->track_item($import_id, 'nav_menu', (int)$by_slug->term_id);
            return (int)$by_slug->term_id;
        }

        // Final fallback — call wp_insert_term directly (bypasses
        // wp_create_nav_menu's name-collision pre-check).
        $inserted = wp_insert_term($name, 'nav_menu');
        if (!is_wp_error($inserted) && isset($inserted['term_id'])) {
            $tracker->track_item($import_id, 'nav_menu', (int)$inserted['term_id']);
            return (int)$inserted['term_id'];
        }
        if (is_wp_error($inserted) && $inserted->get_error_data('term_exists')) {
            $existing_id = (int)$inserted->get_error_data('term_exists');
            $term = get_term($existing_id, 'nav_menu');
            if ($term && !is_wp_error($term)) {
                $this->clear_menu_items($existing_id);
                $tracker->track_item($import_id, 'nav_menu', $existing_id);
                return $existing_id;
            }
        }

        $tracker->append_error($import_id,
            'Не удалось подготовить меню "' . $name . '". Возможно, оно блокируется другим плагином. Флаги [M]/[F] для этого меню будут проигнорированы.',
            ['kind' => 'fsr_menu']
        );
        return 0;
    }

    /**
     * Remove every item from a nav menu, leaving the menu itself intact.
     * Used when we have to reuse an existing menu instead of recreating it.
     */
    private function clear_menu_items(int $menu_id): void {
        $items = wp_get_nav_menu_items($menu_id);
        if (!is_array($items)) return;
        foreach ($items as $item) {
            wp_delete_post((int)$item->ID, true);
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
            'batch_size'      => Site_Builder_Settings::batch_create(),
        ];

        $import_id = $tracker->create_import('create', $folder, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку импорта']);
        }

        try {
            $builder = new Site_Builder_Task_Builder();
            $queue = $builder->build_create_queue($source_dir, $settings, $wipe_first);
            // Persist the resolved content root (may differ from source_dir if archive
            // had wrapper folders). All downstream batches need this real path.
            if ($builder->resolved_root !== '') {
                $settings['resolved_root'] = $builder->resolved_root;
            }
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
            'batch_size'  => (int)$settings['batch_size'],
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
            if ($builder->resolved_root !== '') {
                $settings['resolved_root'] = $builder->resolved_root;
            }
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

        // Pick batch size based on detected format (flat is light, folders may have images).
        // We inspect the first page task in the queue.
        $batch_size = Site_Builder_Settings::batch_add_folders();
        foreach ($queue as $task) {
            if (($task['kind'] ?? '') === 'add_page') {
                $mode = $task['data']['mode'] ?? 'folder';
                $batch_size = ($mode === 'flat')
                    ? Site_Builder_Settings::batch_add_flat()
                    : Site_Builder_Settings::batch_add_folders();
                break;
            }
        }
        $settings['batch_size'] = $batch_size;

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
            'batch_size' => (int)$settings['batch_size'],
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

        $settings['batch_size'] = Site_Builder_Settings::batch_add_flat();

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'  => $import_id,
            'total'      => count($queue),
            'batch_size' => (int)$settings['batch_size'],
        ]);
    }

    /**
     * Endpoint: start a MD-restore import.
     *
     * MD-restore is a separate mode for rebuilding sites from a content/ folder of
     * .md files. Unlike CREATE/ADD it doesn't touch the theme, doesn't manage a menu,
     * and doesn't deal with images — the .md files are pure text content. Page
     * hierarchy is reconstructed from each file's "# URL:" header.
     */
    public function md_start(): void {
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

        $tracker = new Site_Builder_Import_Tracker();
        if ($tracker->get_lock() !== null) {
            wp_send_json_error(['message' => 'Уже выполняется другой импорт. Дождитесь его завершения или нажмите "Прервать и сбросить".']);
        }

        $settings = [];
        $import_id = $tracker->create_import('md_restore', $folder, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку импорта']);
        }

        try {
            $builder = new Site_Builder_Task_Builder();
            $queue = $builder->build_md_queue($source_dir);
        } catch (Throwable $e) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'Ошибка построения очереди: ' . $e->getMessage()]);
        }

        if (empty($queue)) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'В папке "' . esc_html($folder) . '" не найдены .md-файлы с заголовком "# URL:"']);
        }

        // MD-restore tasks are lightweight (no image processing), so we use the larger
        // flat-ADD batch size.
        $settings['batch_size']    = Site_Builder_Settings::batch_add_flat();
        $settings['resolved_root'] = $source_dir;

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'  => $import_id,
            'total'      => count($queue),
            'batch_size' => (int)$settings['batch_size'],
        ]);
    }

    /**
     * Endpoint: start an FSR (File System Routing) import.
     *
     * FSR is the canonical archive format for v1.0.0: a Next.js-like folder tree
     * where each folder is one page (with flags in its name), and content lives
     * in index.md or index.html. Pre-validation runs first — global slug
     * uniqueness violations abort; year-in-slug and unknown flags only warn.
     */
    public function fsr_start(): void {
        $this->authorize();

        $folder_raw = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
        $folder = Site_Builder_Helpers::sanitize_folder_name($folder_raw);
        if (!$folder) {
            wp_send_json_error(['message' => 'Некорректное имя папки']);
        }

        // Mode: 'create' (full site build from scratch — wipes existing) or
        // 'add' (extend existing site, skip collisions and theme-wide assets).
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'create';
        if (!in_array($mode, ['create', 'add'], true)) {
            $mode = 'create';
        }

        // Safety gate: 'create' on a non-empty site needs the destructive
        // confirmation token. The user types "УДАЛИТЬ" into a confirmation box
        // (same pattern as the legacy CREATE mode) to acknowledge that all
        // existing pages and the front-page will be replaced.
        $existing_pages = Site_Builder_Helpers::count_existing_pages();
        if ($mode === 'create' && $existing_pages > 0) {
            $confirmation = isset($_POST['confirmation']) ? trim((string)wp_unslash($_POST['confirmation'])) : '';
            if ($confirmation !== 'УДАЛИТЬ') {
                wp_send_json_error([
                    'message' => 'На сайте уже есть страницы (' . $existing_pages . '). Чтобы создать сайт заново, нужно ввести подтверждение УДАЛИТЬ.',
                    'needs_confirmation' => true,
                    'existing_pages' => $existing_pages,
                ]);
            }
        }

        // Schedule for [DLY] pages (no-flag pages always publish instantly)
        $schedule_mode = isset($_POST['schedule_mode']) ? sanitize_key(wp_unslash($_POST['schedule_mode'])) : 'instant';
        if (!in_array($schedule_mode, ['instant', 'one_day', 'period'], true)) {
            $schedule_mode = 'instant';
        }
        $schedule_days = isset($_POST['days']) ? max(1, min(365, (int)$_POST['days'])) : 60;
        $schedule_wait_week = !empty($_POST['wait_week']);

        $source_dir = ABSPATH . $folder;
        if (!is_dir($source_dir)) {
            wp_send_json_error(['message' => 'Папка "' . esc_html($folder) . '" не найдена в корне сайта']);
        }

        $tracker = new Site_Builder_Import_Tracker();
        if ($tracker->get_lock() !== null) {
            wp_send_json_error(['message' => 'Уже выполняется другой импорт. Дождитесь его завершения или нажмите "Прервать и сбросить".']);
        }

        $settings = [];
        $import_id = $tracker->create_import('fsr', $folder, $settings, get_current_user_id());
        if (!$tracker->acquire_lock($import_id, get_current_user_id())) {
            $tracker->delete_import($import_id);
            wp_send_json_error(['message' => 'Не удалось захватить блокировку импорта']);
        }

        try {
            $builder = new Site_Builder_Task_Builder();
            $queue = $builder->build_fsr_queue($source_dir, $mode);
        } catch (Throwable $e) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'Ошибка построения очереди: ' . $e->getMessage()]);
        }

        // Blocking errors abort before any pages get created
        if (!empty($builder->fsr_errors)) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error([
                'message' => 'Архив не прошёл валидацию',
                'errors'  => $builder->fsr_errors,
            ]);
        }

        if (empty($queue)) {
            $tracker->release_lock();
            $tracker->mark_finished($import_id, 'failed');
            wp_send_json_error(['message' => 'В папке "' . esc_html($folder) . '" не найдены страницы (index.md или index.html)']);
        }

        // Pre-log warnings into the import journal so they show up in the report
        foreach ($builder->fsr_warnings as $w) {
            $tracker->append_error($import_id, $w, ['kind' => 'fsr_warning']);
        }

        // Nav menus that [M] and [F] flags populate.
        // CREATE mode: ideally delete the existing menu and create a fresh one.
        //              In practice wp_delete_nav_menu + wp_create_nav_menu can
        //              fail due to WordPress's term cache not refreshing between
        //              the two calls — wp_create_nav_menu then returns WP_Error
        //              'menu_exists' and we lose the menu_id entirely. To stay
        //              robust, fall back to clearing items on the existing menu
        //              and re-using its term_id.
        // ADD mode:    re-use any existing menu; create only if missing.
        //              Existing menu is NOT tracked for rollback in this case —
        //              we don't want rollback to delete a menu the user already had.
        $menu_ids = [];
        foreach (['main' => SITE_BUILDER_MENU_NAME, 'footer' => SITE_BUILDER_FOOTER_MENU_NAME] as $kind => $name) {
            $menu_ids[$kind] = $this->ensure_menu_ready($name, $mode, $tracker, $import_id);
        }

        // Auto-attach the menus to theme locations selected in Settings, if any.
        // We change a single theme_mod ('nav_menu_locations') that holds the
        // complete location → menu_id map. Snapshot the previous value so
        // rollback can restore the user's prior bindings.
        $loc_main   = (string)Site_Builder_Settings::get('menu_location_main');
        $loc_footer = (string)Site_Builder_Settings::get('menu_location_footer');
        if ($loc_main !== '' || $loc_footer !== '') {
            $registered = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : [];
            $current_locations = (array)get_theme_mod('nav_menu_locations', []);

            // Snapshot the previous map BEFORE we change anything — even if we
            // bail out below, the snapshot is harmless to restore (it's the same value).
            $tracker->track_item($import_id, 'option_snapshot', null, null, [
                'theme_mod:nav_menu_locations' => $current_locations,
            ]);

            $new_locations = $current_locations;
            if ($loc_main !== '' && isset($registered[$loc_main]) && $menu_ids['main'] > 0) {
                $new_locations[$loc_main] = $menu_ids['main'];
            }
            if ($loc_footer !== '' && isset($registered[$loc_footer]) && $menu_ids['footer'] > 0) {
                $new_locations[$loc_footer] = $menu_ids['footer'];
            }
            // Log mismatches so the user sees them in the report
            if ($loc_main !== '' && !isset($registered[$loc_main])) {
                $tracker->append_error($import_id,
                    'Локация "' . $loc_main . '" не существует в активной теме — Main Auto Menu не привязано. Поправьте Настройки или привяжите вручную.',
                    ['kind' => 'fsr_menu_location']
                );
            }
            if ($loc_footer !== '' && !isset($registered[$loc_footer])) {
                $tracker->append_error($import_id,
                    'Локация "' . $loc_footer . '" не существует в активной теме — Footer Auto Menu не привязано.',
                    ['kind' => 'fsr_menu_location']
                );
            }
            set_theme_mod('nav_menu_locations', $new_locations);
        }

        // Count [DLY] pages without explicit date — those are the ones subject
        // to the import-wide schedule. Pages with [DLY=date] use their own date;
        // pages without DLY publish instantly.
        $dly_total = 0;
        foreach ($queue as $t) {
            if (($t['kind'] ?? '') !== 'fsr_page') continue;
            $flags = $t['data']['flags'] ?? [];
            if (!empty($flags['dly']) && empty($flags['dly_date'])) {
                $dly_total++;
            }
        }

        $settings['batch_size']      = Site_Builder_Settings::batch_add_flat();
        $settings['resolved_root']   = $source_dir;
        $settings['menu_main_id']    = $menu_ids['main'];
        $settings['menu_footer_id']  = $menu_ids['footer'];
        $settings['mode']            = $mode;
        $settings['schedule_mode']   = $schedule_mode;
        $settings['schedule_days']   = $schedule_days;
        $settings['schedule_wait_week'] = $schedule_wait_week;
        $settings['schedule_start_ts']  = time();
        $settings['dly_total']       = $dly_total;

        $tracker->update_import($import_id, [
            'status'   => 'running',
            'settings' => wp_json_encode($settings),
        ]);
        $tracker->set_queue($import_id, $queue);

        wp_send_json_success([
            'import_id'  => $import_id,
            'total'      => count($queue),
            'batch_size' => (int)$settings['batch_size'],
            'warnings'   => $builder->fsr_warnings,
            'menus'      => [
                'main'   => $menu_ids['main'],
                'footer' => $menu_ids['footer'],
            ],
        ]);
    }

    /**
     * Endpoint: return the current SEO field mapping plus the full option list
     * per slot (KNOWN_KEYS + DB keys, deduplicated). Used to render step 1.
     *
     * The option list is independent of DB contents: even on a brand-new site
     * with empty wp_postmeta, the user sees Yoast/Rank Math/AIOSEO/etc. as
     * choices. Keys that don't yet exist in the DB are simply created when
     * the first FSR import writes to them.
     */
    public function fsr_get_mapping(): void {
        $this->authorize();

        $db_keys = Site_Builder_Field_Mapping::get_all_meta_keys(true);
        $current = Site_Builder_Field_Mapping::get_mapping();

        $slots = [];
        foreach (Site_Builder_Field_Mapping::slot_labels() as $slot_key => $info) {
            $selected = $current[$slot_key] ?? [];
            $options  = Site_Builder_Field_Mapping::build_options_for_slot($slot_key, $db_keys, $selected);
            $slots[] = [
                'slot'     => $slot_key,
                'label'    => $info['label'],
                'hint'     => $info['hint'],
                'selected' => $selected,
                'options'  => $options,
            ];
        }

        wp_send_json_success([
            'slots'      => $slots,
            'configured' => Site_Builder_Field_Mapping::has_been_configured(),
        ]);
    }

    /**
     * Endpoint: save the user's SEO field mapping. Body shape:
     *   mapping[seo_title][]=_yoast_wpseo_title&mapping[seo_title][]=rank_math_title
     *   mapping[meta_description][]=_yoast_wpseo_metadesc
     *   ...
     */
    public function fsr_save_mapping(): void {
        $this->authorize();

        $raw = $_POST['mapping'] ?? [];
        if (!is_array($raw)) $raw = [];

        // Sanitize each value before passing to the storage layer (which does
        // its own regex check, but defence in depth)
        $clean = [];
        foreach ($raw as $slot => $keys) {
            if (!is_array($keys)) continue;
            $clean[(string)$slot] = array_map(function ($v) {
                return sanitize_text_field(wp_unslash((string)$v));
            }, $keys);
        }
        Site_Builder_Field_Mapping::save_mapping($clean);

        wp_send_json_success(['mapping' => Site_Builder_Field_Mapping::get_mapping()]);
    }

    /**
     * Endpoint: build (and activate) a theme from chosen variants.
     *
     * Body shape:
     *   header = slug of header variant (e.g. 'classic')
     *   footer = slug of footer variant
     *   style  = slug of style variant
     *
     * Returns success with the path of the generated theme and whether it was
     * activated. Failure returns the textual reason (usually a permissions issue
     * with wp-content/themes/).
     */
    public function theme_build(): void {
        $this->authorize();

        $header = isset($_POST['header']) ? sanitize_key(wp_unslash($_POST['header'])) : '';
        $footer = isset($_POST['footer']) ? sanitize_key(wp_unslash($_POST['footer'])) : '';
        $style  = isset($_POST['style'])  ? sanitize_key(wp_unslash($_POST['style']))  : '';

        $generator = new Site_Builder_Theme_Generator();
        $result = $generator->build([
            'header' => $header,
            'footer' => $footer,
            'style'  => $style,
        ], true);

        if (!empty($result['ok'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? 'Не удалось сгенерировать тему']);
        }
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
        $batch_size = (int)($settings['batch_size'] ?? 15);
        if ($batch_size < 1 || $batch_size > 500) $batch_size = 15;
        // Use the resolved content root (skips wrapper folders) when available;
        // fall back to the raw folder name for backward compatibility.
        $source_dir = !empty($settings['resolved_root'])
            ? (string)$settings['resolved_root']
            : (ABSPATH . $import->folder_name);

        $batch = array_slice($queue, $offset, $batch_size);
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
        $md_restore = new Site_Builder_MD_Restore($tracker, $import_id);
        // Image resolver for FSR — re-used across all pages in this batch so
        // upload caching survives. Only constructed when we have an archive root,
        // which is the case for FSR imports (settings.resolved_root is set).
        $fsr_image_resolver = null;
        if (!empty($settings['resolved_root']) && is_dir($settings['resolved_root'])) {
            $fsr_image_resolver = new Site_Builder_FSR_Image_Resolver($media, $settings['resolved_root']);
        }

        $fsr_importer = new Site_Builder_FSR_Importer(
            $tracker,
            $import_id,
            (int)($settings['menu_main_id'] ?? 0),
            (int)($settings['menu_footer_id'] ?? 0),
            $fsr_image_resolver
        );
        // Apply the import's [DLY] schedule. Same instance is reused across the whole
        // batch so dly_index increments correctly across requests; but since each
        // process_batch invocation is a fresh request, we don't have a true per-import
        // running counter. To preserve the staggered timing we reconstruct dly_index
        // from how many DLY pages are already in the database (post_status=future
        // with our tracker entry). For now we restart the counter every batch — this
        // is acceptable because batches process pages in queue order, and 'one_day'
        // mode resolves dates from start_ts which is fixed across the import.
        // For 'period' mode this means each page gets its date based on its position
        // *within the batch*, not the whole queue. Improvement to come if needed.
        $fsr_importer->set_mode((string)($settings['mode'] ?? 'create'));
        $fsr_importer->set_schedule([
            'mode'      => (string)($settings['schedule_mode'] ?? 'instant'),
            'days'      => (int)($settings['schedule_days'] ?? 60),
            'wait_week' => !empty($settings['schedule_wait_week']),
            'start_ts'  => (int)($settings['schedule_start_ts'] ?? time()),
        ], (int)($settings['dly_total'] ?? 0));

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
                        // FSR creates its menus in fsr_start (before wipe runs),
                        // so we must NOT let finalize_wipe delete them here.
                        // Legacy CREATE creates its menu in create_start AFTER
                        // wipe runs, so it's safe for finalize_wipe to clean up.
                        // The presence of menu_main_id in settings is set only by
                        // fsr_start, so it's a clean signal of FSR vs CREATE.
                        $is_fsr = isset($settings['menu_main_id']);
                        $wipe->finalize_wipe($tracker, $is_fsr);
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
                        if (empty($result['ok'])) {
                            $tracker->append_error($import_id, $result['message'] ?? 'Импорт страницы не удался', [
                                'title' => $result['title'] ?? '',
                                'slug'  => $task['data']['slug'] ?? '',
                                'kind'  => 'page',
                            ]);
                        }
                        break;

                    case 'add_page':
                        $result = $page_importer->import_add($task);
                        $current_label = ($result['title'] ?? '') . ' — ' . ($result['message'] ?? '');
                        if (empty($result['ok'])) {
                            $tracker->append_error($import_id, $result['message'] ?? 'Импорт страницы не удался', [
                                'title' => $result['title'] ?? '',
                                'slug'  => $task['data']['slug'] ?? '',
                                'kind'  => 'add_page',
                            ]);
                        }
                        break;

                    case 'md_page':
                        $result = $md_restore->import_page($task);
                        $current_label = ($result['title'] ?? '') . ' — ' . ($result['message'] ?? '');
                        if (empty($result['ok'])) {
                            $tracker->append_error($import_id, $result['message'] ?? 'MD-импорт не удался', [
                                'title' => $result['title'] ?? '',
                                'url'   => $task['data']['url'] ?? '',
                                'kind'  => 'md_page',
                            ]);
                        }
                        break;

                    case 'fsr_init':
                        // Site-wide asset setup: logo, icon, styles.css. Runs once
                        // per import as the first task (depth=-1 sorts before page tasks).
                        $init_result = $fsr_importer->init_site_assets(
                            (string)($task['data']['archive_root'] ?? '')
                        );
                        $current_label = 'Инициализация сайта';
                        foreach (($init_result['messages'] ?? []) as $msg) {
                            if (stripos($msg, 'не найден') !== false || stripos($msg, 'Не удалось') !== false) {
                                $tracker->append_error($import_id, $msg, ['kind' => 'fsr_init']);
                            }
                        }
                        // Image-resolver warnings produced during init (e.g. failed logo upload)
                        if ($fsr_image_resolver && !empty($fsr_image_resolver->warnings)) {
                            foreach ($fsr_image_resolver->warnings as $w) {
                                $tracker->append_error($import_id, $w, ['kind' => 'fsr_init']);
                            }
                            $fsr_image_resolver->warnings = [];
                        }
                        break;

                    case 'fsr_page':
                        $result = $fsr_importer->import_page($task);
                        $current_label = ($result['title'] ?? '') . ' — ' . ($result['message'] ?? '');
                        if (empty($result['ok'])) {
                            $tracker->append_error($import_id, $result['message'] ?? 'FSR-импорт не удался', [
                                'title' => $result['title'] ?? '',
                                'slug'  => $task['data']['slug'] ?? '',
                                'path'  => '/' . implode('/', $task['data']['segments'] ?? []),
                                'kind'  => 'fsr_page',
                            ]);
                        }
                        // Drain image-resolver warnings collected during this page's processing
                        // (typically missing image files). Reset after each page so they don't
                        // pile up indefinitely across the batch.
                        if ($fsr_image_resolver && !empty($fsr_image_resolver->warnings)) {
                            foreach ($fsr_image_resolver->warnings as $w) {
                                $tracker->append_error($import_id, $w, [
                                    'kind'  => 'fsr_image',
                                    'slug'  => $task['data']['slug'] ?? '',
                                    'path'  => '/' . implode('/', $task['data']['segments'] ?? []),
                                ]);
                            }
                            $fsr_image_resolver->warnings = [];
                        }
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
