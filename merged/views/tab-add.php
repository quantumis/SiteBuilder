<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sb-add-tab">

    <div class="sb-info-box">
        <p><strong>Добавление страниц</strong> — расширение уже работающего сайта дополнительными страницами. Импортируемые страницы становятся дочерними страницы «<?php echo esc_html(Site_Builder_Helpers::get_articles_title()); ?>» (создаётся автоматически, либо переиспользуется существующая).</p>
        <p class="description">Формат архива определяется автоматически: подпапки с index.html — формат «папки с картинками», просто .html-файлы в корне — формат «плоский без картинок».</p>
    </div>

    <div class="sb-form-card" id="sb-add-form-card">
        <h2>Параметры импорта</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sb-add-folder">Папка с архивом</label></th>
                <td>
                    <input type="text" id="sb-add-folder" class="regular-text" value="my_add" />
                    <p class="description">Имя папки, лежащей в корне сайта рядом с <code>wp-config.php</code>. Только латинские буквы, цифры, дефис, подчёркивание и точка.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sb-add-schedule-mode">Период публикации</label></th>
                <td>
                    <select id="sb-add-schedule-mode">
                        <option value="instant">Опубликовать всё сразу</option>
                        <option value="one_day">По странице в день</option>
                        <option value="period" selected>Растянуть на N дней</option>
                    </select>
                    <p class="description">Применяется к импортируемым страницам.</p>
                </td>
            </tr>
            <tr id="sb-add-days-row">
                <th scope="row"><label for="sb-add-days">Период в днях</label></th>
                <td>
                    <input type="number" id="sb-add-days" min="1" max="3650" value="60" class="small-text" />
                    <p class="description">Применяется только при выборе «Растянуть на N дней». По умолчанию — 60.</p>
                </td>
            </tr>
            <tr id="sb-add-immediate-count-row">
                <th scope="row"><label for="sb-add-immediate-count">Страниц сразу</label></th>
                <td>
                    <input type="number" id="sb-add-immediate-count" min="0" max="1000" value="10" class="small-text" />
                    <p class="description">Сколько страниц опубликовать мгновенно. Не применяется в режиме «Опубликовать всё сразу».</p>
                </td>
            </tr>
            <tr id="sb-add-wait-week-row">
                <th scope="row">Пауза перед остальными</th>
                <td>
                    <label>
                        <input type="checkbox" id="sb-add-wait-week" />
                        Ждать 1 неделю перед публикацией отложенных страниц
                    </label>
                    <p class="description">Если включено, все страницы, не публикуемые мгновенно, сдвигаются на 7 дней вперёд.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="sb-add-start-btn">
                <span class="dashicons dashicons-controls-play"></span>
                Начать импорт
            </button>
            <button type="button" class="button" id="sb-add-cancel-btn" style="display:none;">
                <span class="dashicons dashicons-no-alt"></span>
                Отменить
            </button>
        </p>
    </div>

    <div class="sb-progress-card" id="sb-add-progress-card" style="display:none;">
        <h2 id="sb-add-progress-title">Импорт идёт</h2>
        <div class="sb-progress-bar-outer">
            <div class="sb-progress-bar-inner" id="sb-add-progress-bar"></div>
        </div>
        <p class="sb-progress-meta">
            <span id="sb-add-progress-count">0</span> из <span id="sb-add-progress-total">0</span> задач обработано
        </p>
        <p class="sb-progress-current">
            <span class="dashicons dashicons-clock"></span>
            <span id="sb-add-progress-current-label">Подготовка…</span>
        </p>
    </div>

    <div class="sb-result-card" id="sb-add-result-card" style="display:none;">
        <h2 id="sb-add-result-title"></h2>
        <p id="sb-add-result-message"></p>
    </div>

</div>
