<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sb-tab-placeholder">
    <span class="dashicons dashicons-clipboard sb-stage-icon"></span>
    <h2>Отчёт</h2>
    <p>Подробный табличный отчёт по последнему импорту.</p>
    <p class="description">Появится на этапе&nbsp;5 разработки.</p>

    <div class="sb-stage-preview">
        <p>В отчёте будут колонки:</p>
        <ul>
            <li>Название страницы</li>
            <li>Уровень вложенности</li>
            <li>Дата публикации</li>
            <li>Статус (опубликовано / запланировано / пропущено)</li>
            <li>Примечание (для пропущенных — причина)</li>
        </ul>
        <p>Формат удобен для копирования в Excel или Google Sheets.</p>
    </div>
</div>
