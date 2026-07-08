<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sb-fsr-tab">

    <div class="sb-info-box">
        <p><strong>FSR Import</strong> (File System Routing) — основной режим импорта. Файловая структура папок архива становится структурой страниц сайта. Имена папок несут <strong>флаги</strong> позиционирования и поведения, контент лежит в <code>index.md</code> или <code>index.html</code> внутри каждой папки.</p>
    </div>

    <div class="sb-fsr-step-content" id="sb-fsr-import-step">

        <div class="sb-form-card" id="sb-fsr-form-card">
            <h2>Запуск импорта</h2>

            <p class="description">
                <a href="<?php echo esc_url(admin_url('admin.php?page=site-builder&tab=settings#sb-fsr-mapping-grid')); ?>" class="sb-fsr-settings-link">⚙ Настроить маппинг полей (для SEO-плагинов)</a>
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
                            <option value="instant">Публиковать <code>[DLY]</code> сразу (жёсткие даты <code>[DLY=дата]</code> работают как обычно)</option>
                            <option value="one_day">По одной DLY-странице в день</option>
                            <option value="period">Растянуть по N дней</option>
                        </select>
                        <p class="description">Применяется только к страницам с флагом <code>[DLY]</code> без даты. Страницы с <code>[DLY=YYYY-MM-DD]</code> публикуются на указанную дату независимо от режима.</p>
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

            <!-- Action buttons. Populated by JS depending on whether the site has
                 existing pages: empty site → single "Создать сайт" button;
                 non-empty → two buttons ("Создать заново" / "Добавить страницы")
                 with a confirmation prompt for the destructive option. -->
            <div id="sb-fsr-action-block">
                <p class="sb-fsr-loading-action">Анализ текущего состояния сайта…</p>
            </div>

            <!-- Hidden bridge buttons that wireImporter binds its handlers to.
                 The visible buttons (rendered by JS into #sb-fsr-action-block)
                 trigger() these on click. They must exist in the DOM at page
                 load time — jQuery .on() only attaches to existing elements. -->
            <button type="button" id="sb-fsr-start-btn" style="display:none"></button>
            <button type="button" id="sb-fsr-cancel-btn" class="button" style="display:none;">
                <span class="dashicons dashicons-no-alt"></span> Отменить
            </button>
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
