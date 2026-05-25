<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Report tab — detailed summary of the last completed/cancelled/rolled-back import.
 *
 * Shows a table of all pages created by that import (slug, title, status, publish date,
 * view/edit links) plus a table of errors. Designed so a developer can copy either table
 * into Excel.
 */

$rep_tracker = new Site_Builder_Import_Tracker();
$rep_import  = $rep_tracker->get_latest_import_for_report();
?>
<div class="sb-report-tab">

    <?php if (!$rep_import): ?>

        <div class="sb-form-card">
            <h2>Отчёт пуст</h2>
            <p>Импортов ещё не было. После первого CREATE или ADD здесь появится подробная сводка.</p>
        </div>

    <?php
    else:
        $rep_settings = $rep_import->settings ? (json_decode($rep_import->settings, true) ?: []) : [];
        $rep_errors   = $rep_import->errors ? (json_decode($rep_import->errors, true) ?: []) : [];
        if (!is_array($rep_errors)) $rep_errors = [];

        $rep_type_labels = [
            'create'   => 'Создание сайта',
            'add'      => 'Добавление страниц',
            'rollback' => 'Откат',
        ];
        $rep_type_label = $rep_type_labels[$rep_import->type] ?? $rep_import->type;

        $rep_status_labels = [
            'completed'   => 'Завершён успешно',
            'cancelled'   => 'Прерван пользователем',
            'failed'      => 'Прерван с ошибкой',
            'rolled_back' => 'Откатан',
            'running'     => 'В процессе',
        ];
        $rep_status_label = $rep_status_labels[$rep_import->status] ?? $rep_import->status;

        // Duration
        $rep_start_ts = $rep_import->started_at ? strtotime($rep_import->started_at) : 0;
        $rep_end_ts   = $rep_import->finished_at ? strtotime($rep_import->finished_at) : 0;
        $rep_duration = '';
        if ($rep_start_ts && $rep_end_ts) {
            $seconds = max(0, $rep_end_ts - $rep_start_ts);
            $minutes = intdiv($seconds, 60);
            $rep_duration = $minutes . ' мин ' . ($seconds % 60) . ' сек';
        }

        // Gather created pages
        $rep_pages = [];
        if ($rep_import->type !== 'rollback') {
            $rep_items = $rep_tracker->get_items((int)$rep_import->id);
            $page_ids = [];
            foreach ($rep_items as $rep_item) {
                if ($rep_item->item_type === 'page' && $rep_item->ref_id) {
                    $page_ids[] = (int)$rep_item->ref_id;
                }
            }
            if (!empty($page_ids)) {
                global $wpdb;
                $placeholders = implode(',', array_fill(0, count($page_ids), '%d'));
                $rep_pages = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name, post_status, post_date, post_parent
                     FROM {$wpdb->posts}
                     WHERE ID IN ($placeholders)
                     ORDER BY post_parent ASC, post_date ASC",
                    ...$page_ids
                ));
            }
        }

        // Count by item type for the summary
        $rep_counts = ['page' => 0, 'attachment' => 0, 'menu_item' => 0];
        $rep_items_for_count = $rep_tracker->get_items((int)$rep_import->id);
        foreach ($rep_items_for_count as $rep_item) {
            $type = $rep_item->item_type;
            $rep_counts[$type] = ($rep_counts[$type] ?? 0) + 1;
        }
    ?>

        <div class="sb-form-card sb-report-summary">
            <h2>Сводка</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Тип</th>
                    <td><?php echo esc_html($rep_type_label); ?> (#<?php echo (int)$rep_import->id; ?>)</td>
                </tr>
                <tr>
                    <th scope="row">Папка</th>
                    <td><code><?php echo esc_html($rep_import->folder_name); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Статус</th>
                    <td>
                        <span class="sb-status-badge sb-status-<?php echo esc_attr($rep_import->status); ?>">
                            <?php echo esc_html($rep_status_label); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Запущен</th>
                    <td><?php echo esc_html($rep_import->started_at ?: '—'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Завершён</th>
                    <td>
                        <?php echo esc_html($rep_import->finished_at ?: '—'); ?>
                        <?php if ($rep_duration): ?>
                            <span class="description">(длительность: <?php echo esc_html($rep_duration); ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Итог</th>
                    <td>
                        <ul style="margin: 0;">
                            <li>Страниц создано: <strong><?php echo (int)($rep_counts['page'] ?? 0); ?></strong></li>
                            <li>Картинок загружено: <strong><?php echo (int)($rep_counts['attachment'] ?? 0); ?></strong></li>
                            <li>Пунктов меню добавлено: <strong><?php echo (int)($rep_counts['menu_item'] ?? 0); ?></strong></li>
                            <li>Ошибок и предупреждений: <strong><?php echo count($rep_errors); ?></strong></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (!empty($rep_pages)): ?>
            <div class="sb-form-card sb-report-pages">
                <h2>
                    Страницы (<?php echo count($rep_pages); ?>)
                    <button type="button" class="button button-small sb-copy-table" data-target="sb-pages-table">
                        <span class="dashicons dashicons-clipboard"></span> Скопировать как таблицу
                    </button>
                </h2>
                <div class="sb-table-scroll">
                    <table class="widefat striped" id="sb-pages-table">
                        <thead>
                            <tr>
                                <th>Slug</th>
                                <th>Заголовок</th>
                                <th>Статус</th>
                                <th>Дата публикации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rep_pages as $rep_page):
                                $status_labels = [
                                    'publish' => 'Опубликована',
                                    'future'  => 'Запланирована',
                                    'draft'   => 'Черновик',
                                    'pending' => 'На утверждении',
                                ];
                                $st = $status_labels[$rep_page->post_status] ?? $rep_page->post_status;
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($rep_page->post_name); ?></code></td>
                                    <td><?php echo esc_html($rep_page->post_title); ?></td>
                                    <td>
                                        <span class="sb-page-status sb-page-status-<?php echo esc_attr($rep_page->post_status); ?>">
                                            <?php echo esc_html($st); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($rep_page->post_date); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(get_permalink($rep_page->ID)); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <a href="<?php echo esc_url(get_edit_post_link($rep_page->ID)); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($rep_errors)): ?>
            <div class="sb-form-card sb-report-errors">
                <h2>
                    Ошибки и предупреждения (<?php echo count($rep_errors); ?>)
                    <button type="button" class="button button-small sb-copy-table" data-target="sb-errors-table">
                        <span class="dashicons dashicons-clipboard"></span> Скопировать как таблицу
                    </button>
                </h2>
                <p class="description">Импорт продолжался, несмотря на эти проблемы — записи здесь для диагностики.</p>
                <div class="sb-table-scroll">
                    <table class="widefat striped" id="sb-errors-table">
                        <thead>
                            <tr>
                                <th>Время</th>
                                <th>Сообщение</th>
                                <th>Контекст</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rep_errors as $err):
                                $ctx_parts = [];
                                if (!empty($err['context']) && is_array($err['context'])) {
                                    foreach ($err['context'] as $k => $v) {
                                        if (is_scalar($v)) {
                                            $ctx_parts[] = $k . ': ' . $v;
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html($err['at'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($err['message'] ?? ''); ?></td>
                                    <td><?php echo esc_html(implode('; ', $ctx_parts)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
