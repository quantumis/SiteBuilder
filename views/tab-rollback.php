<?php
if (!defined('ABSPATH')) {
    exit;
}

$rb_tracker = new Site_Builder_Import_Tracker();
$rb_target  = $rb_tracker->get_last_rollbackable_import();
?>
<div class="sb-rollback-tab">

    <div class="sb-info-box">
        <p><strong>Откат</strong> отменяет последний завершённый импорт целиком: удаляет созданные плагином страницы и картинки, восстанавливает изменённые файлы темы, удаляет добавленные пункты меню и опции главной страницы.</p>
        <p class="description">Плагин хранит только одну точку отката — самый недавний импорт. После отката повторно откатить тот же импорт нельзя.</p>
    </div>

    <?php if (!$rb_target): ?>

        <div class="sb-form-card">
            <h2>Нет данных для отката</h2>
            <p>Либо импортов ещё не было, либо последний импорт уже откатан, либо был отменён до завершения.</p>
        </div>

    <?php
    else:
        $rb_items = $rb_tracker->get_items((int)$rb_target->id);
        $rb_counts = [];
        foreach ($rb_items as $rb_item) {
            $rb_counts[$rb_item->item_type] = ($rb_counts[$rb_item->item_type] ?? 0) + 1;
        }
        $rb_type_label_map = [
            'create'     => 'Создание сайта',
            'add'        => 'Добавление страниц',
            'md_restore' => 'MD Restore',
            'fsr'        => 'FSR Import',
        ];
        $rb_type_label = $rb_type_label_map[$rb_target->type] ?? $rb_target->type;
        $rb_label_map = [
            'page'                => 'Страниц',
            'attachment'          => 'Картинок',
            'menu_item'           => 'Пунктов меню',
            'css_file'            => 'CSS-файлов темы',
            'theme_file_snapshot' => 'Файлов темы (front-page.php, footer.php)',
            'option_snapshot'     => 'Снимков опций (главная страница)',
            'nav_menu'            => 'Меню',
        ];
    ?>

        <div class="sb-form-card" id="sb-rollback-form-card">
            <h2>Последний импорт</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Тип</th>
                    <td><?php echo esc_html($rb_type_label); ?> (#<?php echo (int)$rb_target->id; ?>)</td>
                </tr>
                <tr>
                    <th scope="row">Папка</th>
                    <td><code><?php echo esc_html($rb_target->folder_name); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Завершён</th>
                    <td><?php echo esc_html($rb_target->finished_at ?: '—'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Будет отменено</th>
                    <td>
                        <?php if (empty($rb_counts)): ?>
                            <em>Нет отслеживаемых элементов</em>
                        <?php else: ?>
                            <ul style="margin: 0;">
                                <?php foreach ($rb_counts as $type => $count): ?>
                                    <li><?php echo esc_html($rb_label_map[$type] ?? $type); ?>: <strong><?php echo (int)$count; ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="button" class="button button-primary button-large" id="sb-rollback-start-btn">
                    <span class="dashicons dashicons-undo"></span>
                    Откатить
                </button>
            </p>
        </div>

        <div class="sb-progress-card" id="sb-rollback-progress-card" style="display:none;">
            <h2 id="sb-rollback-progress-title">Откат идёт</h2>
            <div class="sb-progress-bar-outer">
                <div class="sb-progress-bar-inner" id="sb-rollback-progress-bar"></div>
            </div>
            <p class="sb-progress-meta">
                <span id="sb-rollback-progress-count">0</span> из <span id="sb-rollback-progress-total">0</span> задач обработано
            </p>
            <p class="sb-progress-current">
                <span class="dashicons dashicons-clock"></span>
                <span id="sb-rollback-progress-current-label">Подготовка…</span>
            </p>
        </div>

        <div class="sb-result-card" id="sb-rollback-result-card" style="display:none;">
            <h2 id="sb-rollback-result-title"></h2>
            <p id="sb-rollback-result-message"></p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=site-builder&tab=rollback')); ?>">Обновить страницу</a>, чтобы убедиться в результате.</p>
        </div>

    <?php endif; ?>

</div>
