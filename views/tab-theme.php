<?php
if (!defined('ABSPATH')) {
    exit;
}

$th_headers = Site_Builder_Theme_Generator::list_variants('headers');
$th_footers = Site_Builder_Theme_Generator::list_variants('footers');
$th_styles  = Site_Builder_Theme_Generator::list_variants('styles');
$th_current = Site_Builder_Theme_Generator::get_current_choices();
$th_module_options = Site_Builder_Theme_Generator::get_module_options();
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
            ['key' => 'header', 'label' => 'Шапка (header)',        'items' => $th_headers, 'current' => $th_current['header'], 'cat' => 'headers'],
            ['key' => 'footer', 'label' => 'Подвал (footer)',       'items' => $th_footers, 'current' => $th_current['footer'], 'cat' => 'footers'],
            ['key' => 'style',  'label' => 'Цветовая схема и шрифты', 'items' => $th_styles,  'current' => $th_current['style'],  'cat' => 'styles'],
        ] as $section): ?>
            <div class="sb-form-card">
                <div class="sb-theme-section-head">
                    <h2><?php echo esc_html($section['label']); ?></h2>
                    <span class="sb-theme-section-count"><?php echo count($section['items']); ?></span>
                </div>
                <?php if (empty($section['items'])): ?>
                    <p class="description">Нет доступных вариантов в этой категории.</p>
                <?php else: ?>
                    <div class="sb-theme-scroller" data-cat="<?php echo esc_attr($section['cat']); ?>">
                        <button type="button" class="sb-theme-scroll-arrow sb-theme-scroll-left" aria-label="Прокрутить влево">‹</button>
                        <div class="sb-theme-variants-row">
                            <?php foreach ($section['items'] as $variant): ?>
                                <?php
                                $is_selected = $section['current'] === $variant['slug'];
                                $preview_type = $variant['preview_type'] ?? '';
                                $palette = $variant['palette'] ?? null;
                                ?>
                                <label class="sb-theme-variant <?php echo $is_selected ? 'sb-theme-variant-selected' : ''; ?>"
                                       data-slug="<?php echo esc_attr($variant['slug']); ?>">
                                    <input type="radio"
                                           name="sb-theme-<?php echo esc_attr($section['key']); ?>"
                                           value="<?php echo esc_attr($variant['slug']); ?>"
                                           <?php checked($section['current'], $variant['slug']); ?>>
                                    <div class="sb-theme-variant-preview">
                                        <?php echo Site_Builder_Theme_Generator::render_preview_svg(
                                            $section['cat'], $preview_type, $variant['slug'], $palette
                                        ); ?>
                                    </div>
                                    <div class="sb-theme-variant-body">
                                        <strong><?php echo esc_html($variant['name']); ?></strong>
                                        <p><?php echo esc_html($variant['description']); ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="sb-theme-scroll-arrow sb-theme-scroll-right" aria-label="Прокрутить вправо">›</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="sb-form-card">
            <h2>Дополнительные элементы</h2>
            <p class="description">Переключатели работают <strong>мгновенно</strong> — не нужно повторно генерировать тему. Настройки хранятся в базе и применяются на всех страницах сайта.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sb-theme-opt-breadcrumbs">Хлебные крошки</label></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="sb-theme-opt-breadcrumbs"
                                   class="sb-theme-module-option"
                                   data-option-key="show_breadcrumbs"
                                   <?php checked(!empty($th_module_options['show_breadcrumbs'])); ?>>
                            Выводить хлебные крошки перед заголовком страницы
                        </label>
                        <p class="description">На главной странице крошки не выводятся никогда. На остальных — цепочка <em>Главная → предки → текущая страница</em>.</p>
                        <span class="sb-theme-opt-status" aria-live="polite"></span>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="sb-theme-build-btn">
                <span class="dashicons dashicons-admin-appearance"></span>
                Сгенерировать и активировать тему
            </button>
            <span id="sb-theme-build-status" class="sb-theme-build-status"></span>
        </p>
    </form>
</div>
