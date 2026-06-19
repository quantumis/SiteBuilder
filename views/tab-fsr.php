<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sb-fsr-tab">

    <div class="sb-info-box">
        <p><strong>FSR Import</strong> (File System Routing) — основной режим импорта для архивов нового единого формата. Файловая структура папок становится структурой страниц сайта. Имена папок несут <strong>флаги</strong> позиционирования и поведения, контент лежит в <code>index.md</code> или <code>index.html</code> внутри каждой папки.</p>
        <p class="description">Импорт идёт в два шага: <strong>(1) Маппинг полей</strong> — задаём, в какие <code>meta_key</code> сайта писать SEO-данные из архива; <strong>(2) Запуск импорта</strong> — указываем папку и стартуем.</p>
    </div>

    <div class="sb-fsr-steps">
        <div class="sb-fsr-step sb-fsr-step-active" data-step="mapping">
            <span class="sb-fsr-step-num">1</span>
            <span>Маппинг полей</span>
        </div>
        <div class="sb-fsr-step" data-step="import">
            <span class="sb-fsr-step-num">2</span>
            <span>Запуск импорта</span>
        </div>
    </div>

    <div class="sb-fsr-step-content" id="sb-fsr-mapping-step">
        <div class="sb-form-card">
            <h2>Шаг 1 — Маппинг SEO-полей</h2>
            <p class="description">FSR-архив всегда содержит одни и те же поля во frontmatter: <code>title</code>, <code>description</code>, <code>headline</code>, <code>headimg</code>. Выберите, в какие <code>meta_key</code> сайта они должны записаться. Можно выбрать несколько (если значение пишется в несколько мест одновременно — например, в Yoast и Rank Math).</p>

            <div class="sb-fsr-mapping-controls">
                <label>
                    <input type="checkbox" id="sb-fsr-show-private">
                    Показать приватные поля (начинающиеся с <code>_</code>)
                </label>
            </div>

            <div id="sb-fsr-mapping-grid" class="sb-fsr-mapping-grid">
                <p class="sb-fsr-mapping-loading">Загрузка списка полей из базы…</p>
            </div>

            <p class="submit">
                <button type="button" class="button button-primary button-large" id="sb-fsr-save-mapping-btn">
                    Сохранить маппинг и перейти к импорту →
                </button>
                <span id="sb-fsr-mapping-save-status" class="sb-fsr-save-status"></span>
            </p>
        </div>
    </div>

    <div class="sb-fsr-step-content" id="sb-fsr-import-step" style="display:none;">

        <div class="sb-form-card" id="sb-fsr-form-card">
            <h2>Шаг 2 — Запуск импорта</h2>

            <p class="description">
                <a href="#" id="sb-fsr-back-to-mapping" class="sb-fsr-back-link">← Изменить маппинг полей</a>
                <span class="sb-fsr-mapping-summary" id="sb-fsr-mapping-summary"></span>
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sb-fsr-folder">Папка архива</label></th>
                    <td>
                        <input type="text" id="sb-fsr-folder" class="regular-text" value="NEW" />
                        <p class="description">Имя папки, лежащей в корне сайта рядом с <code>wp-config.php</code>. Только латинские буквы, цифры, дефис, подчёркивание и точка.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sb-fsr-schedule-mode">Расписание для <code>[DLY]</code></label></th>
                    <td>
                        <select id="sb-fsr-schedule-mode">
                            <option value="instant">Публиковать всё сразу (игнорировать DLY)</option>
                            <option value="one_day">По одной DLY-странице в день</option>
                            <option value="period">Растянуть по N дней</option>
                        </select>
                        <p class="description">Применяется только к страницам с флагом <code>[DLY]</code> без даты. Страницы с <code>[DLY=YYYY-MM-DD]</code> публикуются на указанную дату.</p>
                    </td>
                </tr>
                <tr id="sb-fsr-days-row" style="display:none">
                    <th scope="row"><label for="sb-fsr-days">Длительность растяжки</label></th>
                    <td>
                        <input type="number" id="sb-fsr-days" value="60" min="1" max="365" style="width:80px"> дней
                    </td>
                </tr>
                <tr id="sb-fsr-wait-week-row" style="display:none">
                    <th scope="row">Прогрев</th>
                    <td>
                        <label>
                            <input type="checkbox" id="sb-fsr-wait-week">
                            Отложить DLY-страницы ещё на неделю
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="button" class="button button-primary button-large" id="sb-fsr-start-btn">
                    <span class="dashicons dashicons-controls-play"></span>
                    Начать импорт
                </button>
                <button type="button" class="button" id="sb-fsr-cancel-btn" style="display:none;">
                    <span class="dashicons dashicons-no-alt"></span>
                    Отменить
                </button>
            </p>
        </div>

        <div class="sb-progress-card" id="sb-fsr-progress-card" style="display:none;">
            <h2 id="sb-fsr-progress-title">Импорт идёт</h2>
            <div class="sb-progress-bar-outer">
                <div class="sb-progress-bar-inner" id="sb-fsr-progress-bar"></div>
            </div>
            <p class="sb-progress-meta">
                <span id="sb-fsr-progress-count">0</span> из <span id="sb-fsr-progress-total">0</span> страниц обработано
            </p>
            <p class="sb-progress-current">
                <span class="dashicons dashicons-clock"></span>
                <span id="sb-fsr-progress-current-label">Подготовка…</span>
            </p>
        </div>

        <div class="sb-result-card" id="sb-fsr-result-card" style="display:none;">
            <h2 id="sb-fsr-result-title"></h2>
            <p id="sb-fsr-result-message"></p>
        </div>
    </div>

</div>
