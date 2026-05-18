<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap site-builder-wrap">
    <h1>
        Site Builder
        <span class="sb-version">v<?php echo esc_html(SITE_BUILDER_VERSION); ?></span>
    </h1>

    <p class="sb-intro">Инструмент массового импорта контента. Этап 2 из 5: режим CREATE.</p>

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
