<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sb-md-tab">

    <div class="sb-info-box">
        <p><strong>MD Restore</strong> — режим быстрого восстановления сайта из набора <code>.md</code>-файлов. Используется для аварийного восстановления контента, когда сайт был удалён, а в наличии есть только markdown-снимки страниц.</p>
        <p class="description">Каждый <code>.md</code>-файл должен иметь шапку с <code># URL: …</code>, <code># Title: …</code>, <code># Description: …</code>, отделённую от тела строкой из 10+ дефисов. Иерархия страниц восстанавливается из URL: <code>https://site.com/a/b/c</code> → страница <code>c</code> создаётся как дочерняя <code>b</code>, а та — дочерняя <code>a</code>. Корневая страница (URL <code>/</code>) создаётся со slug <code>home</code>.</p>
        <p class="description">FAQ-блоки вида <code>[ Вопрос ](#)</code> + ответ конвертируются в шорткод <code>[faq][id="N" title="…" desc="…"][/faq]</code>. SEO-мета (title/description) записывается в Yoast и Rank Math одновременно. Картинки не загружаются (предполагается, что в md только текст).</p>
    </div>

    <div class="sb-form-card" id="sb-md-form-card">
        <h2>Параметры восстановления</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sb-md-folder">Папка с .md-файлами</label></th>
                <td>
                    <input type="text" id="sb-md-folder" class="regular-text" value="content" />
                    <p class="description">Имя папки, лежащей в корне сайта рядом с <code>wp-config.php</code>. Внутри будут найдены все <code>.md</code>-файлы рекурсивно. Только латинские буквы, цифры, дефис, подчёркивание и точка.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="sb-md-start-btn">
                <span class="dashicons dashicons-controls-play"></span>
                Начать восстановление
            </button>
            <button type="button" class="button" id="sb-md-cancel-btn" style="display:none;">
                <span class="dashicons dashicons-no-alt"></span>
                Отменить
            </button>
        </p>
    </div>

    <div class="sb-progress-card" id="sb-md-progress-card" style="display:none;">
        <h2 id="sb-md-progress-title">Восстановление идёт</h2>
        <div class="sb-progress-bar-outer">
            <div class="sb-progress-bar-inner" id="sb-md-progress-bar"></div>
        </div>
        <p class="sb-progress-meta">
            <span id="sb-md-progress-count">0</span> из <span id="sb-md-progress-total">0</span> страниц обработано
        </p>
        <p class="sb-progress-current">
            <span class="dashicons dashicons-clock"></span>
            <span id="sb-md-progress-current-label">Подготовка…</span>
        </p>
    </div>

    <div class="sb-result-card" id="sb-md-result-card" style="display:none;">
        <h2 id="sb-md-result-title"></h2>
        <p id="sb-md-result-message"></p>
    </div>

</div>
