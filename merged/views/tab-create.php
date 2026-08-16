<?php
if (!defined('ABSPATH')) {
    exit;
}
$existing_pages = Site_Builder_Helpers::count_existing_pages();
?>
<div class="sb-create-tab">

    <?php if ($existing_pages > 0): ?>
        <div class="sb-warning-box" id="sb-warning-box">
            <h2><span class="dashicons dashicons-warning"></span> Внимание: на сайте уже есть страницы</h2>
            <p>На этом сайте обнаружено <strong><?php echo (int)$existing_pages; ?></strong> страниц(ы). Режим «Создание сайта» предназначен для пустых WordPress-инсталляций и при запуске <strong>полностью удалит все существующие страницы без возможности восстановления</strong> (даже те, которые были созданы вне этого плагина).</p>
            <p>Если ты действительно хочешь пересоздать сайт с нуля — введи слово <code>УДАЛИТЬ</code> заглавными буквами в поле ниже. Кнопка «Начать импорт» активируется после правильного ввода.</p>
            <label class="sb-confirm-label">
                Подтверждение:
                <input type="text" id="sb-confirmation" class="regular-text" autocomplete="off" />
                <span class="sb-confirm-status" id="sb-confirm-status"></span>
            </label>
        </div>
    <?php endif; ?>

    <div class="sb-form-card" id="sb-create-form-card" <?php echo $existing_pages > 0 ? 'data-locked="1"' : ''; ?>>
        <h2>Параметры импорта</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sb-folder">Папка с архивом</label></th>
                <td>
                    <input type="text" id="sb-folder" class="regular-text" value="my_content" />
                    <p class="description">Имя папки, лежащей в корне сайта рядом с <code>wp-config.php</code>. Только латинские буквы, цифры, дефис, подчёркивание и точка.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sb-schedule-mode">Период публикации</label></th>
                <td>
                    <select id="sb-schedule-mode">
                        <option value="instant">Опубликовать всё сразу</option>
                        <option value="one_day">По странице в день</option>
                        <option value="period" selected>Растянуть на N дней</option>
                    </select>
                    <p class="description">Корневые страницы публикуются всегда сразу — этот параметр применяется к вложенным.</p>
                </td>
            </tr>
            <tr id="sb-days-row">
                <th scope="row"><label for="sb-days">Период в днях</label></th>
                <td>
                    <input type="number" id="sb-days" min="1" max="3650" value="60" class="small-text" />
                    <p class="description">Применяется только при выборе «Растянуть на N дней». По умолчанию — 60.</p>
                </td>
            </tr>
            <tr id="sb-immediate-count-row">
                <th scope="row"><label for="sb-immediate-count">Вложенных страниц сразу</label></th>
                <td>
                    <input type="number" id="sb-immediate-count" min="0" max="1000" value="10" class="small-text" />
                    <p class="description">Сколько вложенных страниц опубликовать мгновенно дополнительно к корневым. Не применяется в режиме «Опубликовать всё сразу».</p>
                </td>
            </tr>
            <tr id="sb-wait-week-row">
                <th scope="row">Пауза перед остальными</th>
                <td>
                    <label>
                        <input type="checkbox" id="sb-wait-week" />
                        Ждать 1 неделю перед публикацией отложенных страниц
                    </label>
                    <p class="description">Если включено, все страницы, не публикуемые мгновенно, сдвигаются на 7 дней вперёд. Полезно для SEO-«прогрева» сайта первыми страницами.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="sb-start-btn" disabled>
                <span class="dashicons dashicons-controls-play"></span>
                Начать импорт
            </button>
            <button type="button" class="button" id="sb-cancel-btn" style="display:none;">
                <span class="dashicons dashicons-no-alt"></span>
                Отменить
            </button>
        </p>
    </div>

    <div class="sb-progress-card" id="sb-progress-card" style="display:none;">
        <h2 id="sb-progress-title">Импорт идёт</h2>
        <div class="sb-progress-bar-outer">
            <div class="sb-progress-bar-inner" id="sb-progress-bar"></div>
        </div>
        <p class="sb-progress-meta">
            <span id="sb-progress-count">0</span> из <span id="sb-progress-total">0</span> задач обработано
        </p>
        <p class="sb-progress-current">
            <span class="dashicons dashicons-clock"></span>
            <span id="sb-progress-current-label">Подготовка…</span>
        </p>
    </div>

    <div class="sb-result-card" id="sb-result-card" style="display:none;">
        <h2 id="sb-result-title"></h2>
        <p id="sb-result-message"></p>
    </div>

</div>
