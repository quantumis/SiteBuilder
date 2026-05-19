<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap site-builder-wrap">
    <h1>
        Site Builder
        <span class="sb-version">v<?php echo esc_html(SITE_BUILDER_VERSION); ?></span>
    </h1>

    <p class="sb-intro">Инструмент массового импорта контента. Этап 3 из 5: режим ADD.</p>

    <?php if (!empty($active_import)):
        $type_label = $active_import['type'] === 'create' ? 'Создание сайта' : 'Добавление страниц';
        $progress = $active_import['total'] > 0
            ? round($active_import['processed'] / $active_import['total'] * 100)
            : 0;
        $hb = $active_import['seconds_since_heartbeat'];
        $hb_stale = ($hb !== null && $hb > 30);
    ?>
        <div class="sb-active-import <?php echo $hb_stale ? 'sb-active-import-stale' : ''; ?>">
            <h3>
                <span class="dashicons dashicons-clock"></span>
                Активный импорт: <?php echo esc_html($type_label); ?> (#<?php echo (int)$active_import['id']; ?>)
            </h3>
            <p>
                Папка: <code><?php echo esc_html($active_import['folder']); ?></code>.
                Запущен: <?php echo esc_html($active_import['started_at']); ?>.
                Прогресс: <strong><?php echo (int)$active_import['processed']; ?> / <?php echo (int)$active_import['total']; ?></strong> (<?php echo (int)$progress; ?>%).
            </p>
            <?php if ($hb_stale): ?>
                <p class="sb-active-import-warning">
                    <span class="dashicons dashicons-warning"></span>
                    Импорт не подавал признаков жизни уже <?php echo (int)$hb; ?> сек — возможно, вкладка была закрыта или прервана. Через 5 минут блокировка снимется автоматически, либо нажмите «Прервать и сбросить».
                </p>
            <?php else: ?>
                <p>Если эта вкладка не та, в которой идёт импорт — не запускайте новый, дождитесь завершения.</p>
            <?php endif; ?>
            <p>
                <button type="button" class="button" id="sb-clear-lock-btn">
                    <span class="dashicons dashicons-no-alt"></span>
                    Прервать и сбросить
                </button>
            </p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper sb-tabs">
        <?php foreach ($tabs as $slug => $tab): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-builder&tab=' . $slug)); ?>"
               class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="site-builder-content">
        <?php
        $tab_file = SITE_BUILDER_PATH . 'views/tab-' . $current_tab . '.php';
        if (file_exists($tab_file)) {
            include $tab_file;
        } else {
            echo '<p>Вкладка не найдена.</p>';
        }
        ?>
    </div>
</div>
