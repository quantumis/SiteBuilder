<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings tab — UI for editing values stored in Site_Builder_Settings.
 *
 * Submits to admin-post.php via the site_builder_save_settings action; the handler
 * lives in Site_Builder_Admin::handle_save_settings().
 */

$st_settings = Site_Builder_Settings::all();
$st_defaults = Site_Builder_Settings::DEFAULTS;

// Visibility flags for legacy-only sections. The plugin's primary mode is
// FSR — many older settings (Articles page, abbreviations title-casing, the
// CREATE/ADD batch sizes) are meaningful only when the corresponding legacy
// tabs are enabled. Hide them by default so the Settings page isn't cluttered
// with knobs that have no effect.
$st_show_create  = !empty($st_settings['show_create_tab']);
$st_show_add     = !empty($st_settings['show_add_tab']);
$st_any_legacy   = $st_show_create || $st_show_add || !empty($st_settings['show_md_tab']);

// Flash messages from the redirect
$st_saved    = !empty($_GET['sb_settings_saved']);
$st_reset    = !empty($_GET['sb_settings_reset']);
$st_errors   = [];
if (!empty($_GET['sb_settings_errors'])) {
    $decoded = json_decode(rawurldecode(wp_unslash($_GET['sb_settings_errors'])), true);
    if (is_array($decoded)) $st_errors = $decoded;
}
?>
<div class="sb-settings-tab">

    <div class="sb-info-box">
        <p><strong>Настройки</strong> сохраняются в базе данных и применяются ко всем последующим импортам. Чтобы вернуть всё к исходному, нажмите «Сбросить к дефолтам» внизу.</p>
        <p class="description">Значения, оставленные пустыми, заменяются дефолтами.</p>
    </div>

    <?php if ($st_saved): ?>
        <div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>
    <?php endif; ?>
    <?php if ($st_reset): ?>
        <div class="notice notice-success is-dismissible"><p>Настройки сброшены к дефолтам.</p></div>
    <?php endif; ?>
    <?php if ($st_errors): ?>
        <div class="notice notice-error">
            <p><strong>Не удалось сохранить:</strong></p>
            <ul style="margin-left: 20px; list-style: disc;">
                <?php foreach ($st_errors as $field => $msg): ?>
                    <li><?php echo esc_html($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('site_builder_save_settings'); ?>
        <input type="hidden" name="action" value="site_builder_save_settings" />

        <div class="sb-form-card">
            <h2>Размер пачки</h2>
            <p class="description">Сколько задач обрабатывается за один AJAX-запрос. Увеличение ускоряет импорт, но рискует упереться в <code>max_execution_time</code> PHP (обычно 30 сек) — тогда пачка не успеет завершиться и импорт упадёт.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sb-batch-add-flat">FSR-импорт</label></th>
                    <td>
                        <input type="number" id="sb-batch-add-flat" name="sb_settings[batch_add_flat]"
                               value="<?php echo esc_attr($st_settings['batch_add_flat']); ?>" min="1" max="500" class="small-text" />
                        <span class="description">страниц за пачку. Дефолт: <?php echo (int)$st_defaults['batch_add_flat']; ?>. Безопасный диапазон: 20–150.</span>
                    </td>
                </tr>
                <?php if ($st_show_create): ?>
                    <tr>
                        <th scope="row"><label for="sb-batch-create">Legacy CREATE</label></th>
                        <td>
                            <input type="number" id="sb-batch-create" name="sb_settings[batch_create]"
                                   value="<?php echo esc_attr($st_settings['batch_create']); ?>" min="1" max="500" class="small-text" />
                            <span class="description">страниц за пачку. Дефолт: <?php echo (int)$st_defaults['batch_create']; ?>. Всегда с картинками, безопасный диапазон 5–30.</span>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if ($st_show_add): ?>
                    <tr>
                        <th scope="row"><label for="sb-batch-add-folders">Legacy ADD (с папками картинок)</label></th>
                        <td>
                            <input type="number" id="sb-batch-add-folders" name="sb_settings[batch_add_folders]"
                                   value="<?php echo esc_attr($st_settings['batch_add_folders']); ?>" min="1" max="500" class="small-text" />
                            <span class="description">страниц за пачку. Дефолт: <?php echo (int)$st_defaults['batch_add_folders']; ?>. Применяется когда в архиве подпапки с index.html. Безопасный диапазон 5–30.</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="sb-form-card">
            <h2>Пункты меню</h2>
            <p class="description">Длинные SEO-заголовки автоматически сокращаются при попадании в главное меню или футер. Сначала обрезка идёт по разделителю (<code>:</code>, <code>—</code>, <code>–</code>, <code>-</code>, <code>|</code>, <code>,</code>, <code>(</code>) — если результат всё ещё длиннее лимита ниже, обрезается по последнему пробелу и добавляется многоточие. Полный заголовок остаётся видимым при наведении курсора (native tooltip).</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sb-menu-max-length">Максимальная длина</label></th>
                    <td>
                        <input type="number" id="sb-menu-max-length" name="sb_settings[menu_max_length]"
                               value="<?php echo esc_attr($st_settings['menu_max_length']); ?>" min="15" max="120" class="small-text" />
                        <span class="description">символов. Дефолт: <?php echo (int)$st_defaults['menu_max_length']; ?>. Диапазон 15–120. Оптимально: 30–50 для горизонтального меню, 50–100 для sidebar.</span>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ($st_show_add): ?>
        <div class="sb-form-card">
            <h2>Страница «Articles» (для legacy ADD)</h2>
            <p class="description">Страница, к которой ADD-импорт прикрепляет все новые статьи как дочерние. Используется только в legacy ADD-режиме.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sb-articles-title">Заголовок</label></th>
                    <td>
                        <input type="text" id="sb-articles-title" name="sb_settings[articles_title]"
                               value="<?php echo esc_attr($st_settings['articles_title']); ?>" class="regular-text" />
                        <p class="description">Видимое название страницы. Дефолт: <code><?php echo esc_html($st_defaults['articles_title']); ?></code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sb-articles-slug">Slug</label></th>
                    <td>
                        <input type="text" id="sb-articles-slug" name="sb_settings[articles_slug]"
                               value="<?php echo esc_attr($st_settings['articles_slug']); ?>" class="regular-text" />
                        <p class="description">URL-фрагмент. Дефолт: <code><?php echo esc_html($st_defaults['articles_slug']); ?></code>. Только латиница, цифры и дефисы.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sb-articles-template">Файл шаблона</label></th>
                    <td>
                        <input type="text" id="sb-articles-template" name="sb_settings[articles_template]"
                               value="<?php echo esc_attr($st_settings['articles_template']); ?>" class="regular-text" />
                        <p class="description">Имя файла шаблона в активной теме. Дефолт: <code><?php echo esc_html($st_defaults['articles_template']); ?></code>. Пусто — использовать дефолтный шаблон WordPress.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($st_any_legacy): ?>
        <div class="sb-form-card">
            <h2>Аббревиатуры (для legacy)</h2>
            <p class="description">Слова, которые сохраняются заглавными при формировании заголовков страниц из slug'ов. Пример: slug <code>nba-finals</code> → заголовок <code>NBA Finals</code> (только если <code>NBA</code> в списке). Применяется только в legacy CREATE/ADD-режимах. В FSR заголовок берётся из <code>title</code> во frontmatter.</p>

            <textarea name="sb_settings[abbreviations]" rows="6" class="large-text code"
                      placeholder="NBA, NFL, NHL, MLB, ..."><?php echo esc_textarea($st_settings['abbreviations']); ?></textarea>
            <p class="description">Можно разделять запятыми и переводами строк. Регистр не важен — все приводятся к верхнему.</p>
        </div>
        <?php endif; ?>

        <div class="sb-form-card">
            <h2>Исключаемые папки</h2>
            <p class="description">Папки, которые игнорируются при сканировании архива. Применяется во всех режимах. К пользовательскому списку всегда добавляются служебные: <code>IMAGES</code>, <code>PROMPTS</code>, <code>PROMTS</code>, <code>.git</code>, <code>node_modules</code>, <code>__MACOSX</code>, <code>.DS_Store</code> — они исключаются всегда независимо от настройки.</p>

            <textarea name="sb_settings[excluded_folders]" rows="4" class="large-text code"
                      placeholder="hub, my-extras, draft-pages, ..."><?php echo esc_textarea($st_settings['excluded_folders']); ?></textarea>
            <p class="description">Стандартные <code>.</code> и <code>..</code> добавляются автоматически.</p>
        </div>

        <div class="sb-form-card">
            <h2>Привязка меню к локациям темы</h2>
            <p class="description">
                Плагин создаёт два меню — <code>Main Auto Menu</code> и <code>Footer Auto Menu</code> — но они не отображаются на сайте, пока не привязаны к локациям темы.
                Здесь можно настроить автоматическую привязку: при каждом FSR-импорте плагин сам поставит меню в выбранные локации.
                Если оставить «Не привязывать» — нужно будет привязать вручную в <em>Внешний вид → Меню → Управление расположением</em>.
            </p>

            <?php
            $st_locations = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : [];
            $st_theme_name = wp_get_theme()->get('Name');
            ?>

            <?php if (empty($st_locations)): ?>
                <div class="notice notice-warning inline" style="margin:8px 0">
                    <p>Активная тема <strong><?php echo esc_html($st_theme_name); ?></strong> не объявляет ни одной локации меню. Возможно, это block-тема (FSE) — она использует блоки навигации вместо локаций. В этом случае меню придётся встраивать вручную через редактор сайта.</p>
                </div>
            <?php else: ?>
                <table class="form-table" role="presentation">
                    <?php foreach ([
                        'menu_location_main'   => ['Main Auto Menu (флаг [M])', SITE_BUILDER_MENU_NAME],
                        'menu_location_footer' => ['Footer Auto Menu (флаг [F])', SITE_BUILDER_FOOTER_MENU_NAME],
                    ] as $field => $meta):
                        $st_current = (string)($st_settings[$field] ?? '');
                        $st_orphan  = $st_current !== '' && !isset($st_locations[$st_current]);
                    ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($meta[0]); ?></label>
                            </th>
                            <td>
                                <select id="<?php echo esc_attr($field); ?>" name="sb_settings[<?php echo esc_attr($field); ?>]">
                                    <option value="">— Не привязывать (вручную) —</option>
                                    <?php foreach ($st_locations as $slug => $label): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($st_current, $slug); ?>>
                                            <?php echo esc_html($label); ?> <code>(<?php echo esc_html($slug); ?>)</code>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($st_orphan): ?>
                                        <option value="<?php echo esc_attr($st_current); ?>" selected>
                                            <?php echo esc_html($st_current); ?> (нет в текущей теме)
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($st_orphan): ?>
                                    <p class="description" style="color:#b32d2e">
                                        ⚠ Локация <code><?php echo esc_html($st_current); ?></code> не существует в активной теме <strong><?php echo esc_html($st_theme_name); ?></strong>. Возможно, тема была сменена. Выберите подходящую локацию или «Не привязывать».
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="description">Активная тема: <strong><?php echo esc_html($st_theme_name); ?></strong>. Доступно локаций: <?php echo count($st_locations); ?>.</p>
            <?php endif; ?>
        </div>

        <div class="sb-form-card">
            <h2>Маппинг SEO-полей</h2>
            <p class="description">
                Опционально для интеграции с SEO-плагинами (Yoast, Rank Math, All in One SEO и др).
                FSR-архив содержит поля <code>title</code>, <code>description</code>, <code>headline</code>, <code>headimg</code> — здесь можно указать,
                в какие <code>meta_key</code> сайта они должны записываться.
                Если ничего не выбрано, поля пишутся в стандартные <code>fsr_*</code> ключи и доступны напрямую теме.
            </p>

            <div class="sb-fsr-mapping-controls" style="margin:12px 0">
                <label>
                    <input type="checkbox" id="sb-fsr-show-private">
                    Показать приватные поля (начинающиеся с <code>_</code>)
                </label>
            </div>

            <div id="sb-fsr-mapping-grid" class="sb-fsr-mapping-grid">
                <p class="sb-fsr-mapping-loading">Загрузка списка полей из базы…</p>
            </div>

            <p>
                <button type="button" class="button button-primary" id="sb-fsr-save-mapping-btn">
                    Сохранить маппинг SEO-полей
                </button>
                <span id="sb-fsr-mapping-save-status" class="sb-fsr-save-status"></span>
            </p>
        </div>

        <div class="sb-form-card">
            <h2>Видимость legacy-режимов</h2>
            <p class="description">
                <strong>FSR Import</strong> — основной режим импорта для v1.0.0+.
                Старые режимы (<strong>Создание сайта</strong>, <strong>Добавление страниц</strong>,
                <strong>MD Restore</strong>) сохранены для совместимости со старыми архивами и аварийного восстановления,
                но по умолчанию скрыты из навигации.
                Включите чекбокс, если нужно работать с этими режимами.
            </p>
            <p>
                <label>
                    <input type="checkbox" name="sb_settings[show_create_tab]" value="1"
                        <?php checked(!empty($st_settings['show_create_tab'])); ?>>
                    Показывать вкладку <strong>«Создание сайта»</strong> (старый CREATE-режим)
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="sb_settings[show_add_tab]" value="1"
                        <?php checked(!empty($st_settings['show_add_tab'])); ?>>
                    Показывать вкладку <strong>«Добавление страниц»</strong> (старый ADD-режим)
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="sb_settings[show_md_tab]" value="1"
                        <?php checked(!empty($st_settings['show_md_tab'])); ?>>
                    Показывать вкладку <strong>«MD Restore»</strong> (аварийное восстановление из markdown-снапшотов)
                </label>
            </p>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <span class="dashicons dashicons-yes"></span> Сохранить настройки
            </button>
        </p>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sb-reset-form"
          onsubmit="return confirm('Сбросить все настройки к дефолтам?');">
        <?php wp_nonce_field('site_builder_reset_settings'); ?>
        <input type="hidden" name="action" value="site_builder_reset_settings" />
        <p>
            <button type="submit" class="button">
                <span class="dashicons dashicons-image-rotate"></span> Сбросить к дефолтам
            </button>
        </p>
    </form>
</div>
