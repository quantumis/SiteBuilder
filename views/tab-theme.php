<?php
if (!defined('ABSPATH')) {
    exit;
}

$th_headers = Site_Builder_Theme_Generator::list_variants('headers');
$th_footers = Site_Builder_Theme_Generator::list_variants('footers');
$th_styles  = Site_Builder_Theme_Generator::list_variants('styles');
$th_current = Site_Builder_Theme_Generator::get_current_choices();
$th_active  = (get_stylesheet() === Site_Builder_Theme_Generator::THEME_SLUG);
?>
<div class="sb-theme-tab">

    <div class="sb-info-box">
        <p><strong>Конструктор темы</strong> — генерирует WordPress-тему из выбранной комбинации шапки, футера и цветовой схемы. После генерации тема становится активной, и FSR-импорт можно проводить уже на ней.</p>
        <p class="description">
            Тема сохраняется в <code>wp-content/themes/<?php echo esc_html(Site_Builder_Theme_Generator::THEME_SLUG); ?>/</code> и перезаписывается при каждой повторной генерации. Если вы редактировали файлы темы вручную — изменения будут потеряны при следующей генерации.
            При активации меню Main Auto Menu и Footer Auto Menu автоматически привязываются к локациям <code>primary</code> и <code>footer</code> в Настройках плагина.
        </p>
        <?php if ($th_active): ?>
            <p class="sb-theme-active-badge">
                <span class="dashicons dashicons-yes-alt"></span>
                Сгенерированная тема сейчас активна на сайте.
            </p>
        <?php endif; ?>
    </div>

    <form id="sb-theme-form">
        <?php foreach ([
            ['key' => 'header', 'label' => 'Шапка (header)',        'items' => $th_headers, 'current' => $th_current['header']],
            ['key' => 'footer', 'label' => 'Подвал (footer)',       'items' => $th_footers, 'current' => $th_current['footer']],
            ['key' => 'style',  'label' => 'Цветовая схема и шрифты', 'items' => $th_styles,  'current' => $th_current['style']],
        ] as $section): ?>
            <div class="sb-form-card">
                <h2><?php echo esc_html($section['label']); ?></h2>
                <?php if (empty($section['items'])): ?>
                    <p class="description">Нет доступных вариантов в этой категории.</p>
                <?php else: ?>
                    <div class="sb-theme-variants">
                        <?php foreach ($section['items'] as $variant): ?>
                            <label class="sb-theme-variant <?php echo $section['current'] === $variant['slug'] ? 'sb-theme-variant-selected' : ''; ?>">
                                <input type="radio"
                                       name="sb-theme-<?php echo esc_attr($section['key']); ?>"
                                       value="<?php echo esc_attr($variant['slug']); ?>"
                                       <?php checked($section['current'], $variant['slug']); ?>>
                                <div class="sb-theme-variant-body">
                                    <strong><?php echo esc_html($variant['name']); ?></strong>
                                    <code class="sb-theme-variant-slug"><?php echo esc_html($variant['slug']); ?></code>
                                    <p><?php echo esc_html($variant['description']); ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="sb-theme-build-btn">
                <span class="dashicons dashicons-admin-appearance"></span>
                Сгенерировать и активировать тему
            </button>
            <span id="sb-theme-build-status" class="sb-theme-build-status"></span>
        </p>
    </form>
</div>
