# Spec: Разработка вариаций Header / Footer / Style

Документ описывает как создать новую вариацию `header` (шапка), `footer` (подвал) или `style` (цветовая схема) для плагина Site Builder. После создания файлы автоматически появятся в конструкторе темы в админке WordPress.

## Файловая структура

Все вариации живут в папке плагина `templates/theme/`. У каждой категории — своя подпапка:

- **Header**: `templates/theme/headers/`
- **Footer**: `templates/theme/footers/`
- **Style**: `templates/theme/styles/`

## Что за файлы нужны

Для каждой вариации нужно **3 или 4 файла** с одинаковым именем-slug (только латиница, цифры, дефис):

| Файл | Обязателен? | Что содержит |
|---|---|---|
| `slug.php` | header/footer — да | HTML+PHP шапки или подвала |
| `slug.css` | header/footer — да | CSS для вариации (изолирован уникальным классом) |
| `slug.js` | header/footer — нет | JS для интерактивности (burger, dropdown и т.д.) |
| `slug.json` | все — да | Метаданные (имя, описание, тип превью, палитра) |
| `slug.css` | style — да | CSS с переопределением CSS-переменных темы |

Slug должен совпадать между всеми файлами. Например: `sticky-slim.php` + `sticky-slim.css` + `sticky-slim.json` + `sticky-slim.js`.

## Обязательные CSS-переменные темы

Наши цветовые схемы определяют переменные, которые header/footer **должны** использовать вместо жёстких цветов — иначе тёмная тема покажет чёрный на чёрном.

| Переменная | Что это |
|---|---|
| `--sb-color-bg` | Основной фон страницы |
| `--sb-color-bg-alt` | Альтернативный фон (для карточек, футера) |
| `--sb-color-text` | Основной цвет текста |
| `--sb-color-muted` | Приглушённый текст (второстепенное) |
| `--sb-color-link` | Цвет ссылок и главный акцент |
| `--sb-color-border` | Цвет рамок и разделителей |
| `--sb-color-accent` | Второй акцент (по желанию) |
| `--sb-content-max-width` | Максимальная ширина контента (обычно 920px) |
| `--sb-font-body` | Шрифт тела |
| `--sb-font-heading` | Шрифт заголовков |

**Пример правильного использования:**

```css
.sb-header-mine {
    background: var(--sb-color-bg, #fff);      /* fallback на белый если переменная не определена */
    color: var(--sb-color-text, #111);
    border-bottom: 1px solid var(--sb-color-border, #e5e7eb);
}
```

**Всегда указывайте fallback-цвет** (второй аргумент `var()`) — на случай если пользователь использует стороннюю цветовую схему.

## Уникальный CSS-класс

Каждая вариация должна оборачивать свой HTML в **уникальный класс** с префиксом категории и slug'ом:

- Header: `sb-header-slug` (например `sb-header-sticky-slim`, `sb-header-off-canvas`)
- Footer: `sb-footer-slug` (например `sb-footer-rich-4col`)

Все внутренние правила CSS должны быть **вложены** в этот корневой класс. Иначе стили начнут конфликтовать при смене варианта.

**Хорошо:**
```css
.sb-header-mine { padding: 12px; }
.sb-header-mine .sb-menu { display: flex; gap: 8px; }
.sb-header-mine a { color: var(--sb-color-link); }
```

**Плохо:**
```css
/* эти правила заденут все ссылки на сайте */
a { color: red; }
.header { padding: 12px; }
```

## Обязательные WordPress-функции

### Header:

- `wp_nav_menu(['theme_location' => 'primary', 'depth' => 3, 'fallback_cb' => false])` — вывести главное меню
- `the_custom_logo()` — если пользователь загрузил лого в Настройках → Настройщик → Логотип сайта
- `get_bloginfo('name')` — название сайта как fallback если лого нет
- `home_url('/')` — ссылка на главную
- `is_front_page()` — проверить главная ли это (для правильной обёртки логотипа в h1)
- `sb_t('ключ')` — локализованный текст (см. ниже)

### Footer:

- `wp_nav_menu(['theme_location' => 'footer', 'depth' => 1, 'fallback_cb' => false])` — вывести футер-меню
- `get_bloginfo('description')` — описание сайта из Настройки → Общие
- `date_i18n('Y')` — текущий год
- `has_nav_menu('footer')` — проверить есть ли футер-меню (чтобы не выводить пустой блок)
- **Обязательно в конце**: `<?php wp_footer(); ?>` + `</body></html>` — иначе плагины WordPress не смогут вставить свои скрипты

### Логотип и H1

Логотип должен оборачиваться в `<h1>` **только на главной странице** (для SEO — иначе будет два h1 на странице статьи, что путает поисковики). На остальных страницах — обычный `<div>`.

```php
<?php if (is_front_page()): ?>
    <h1 class="sb-header-mine-brand"><?php the_custom_logo(); ?></h1>
<?php else: ?>
    <div class="sb-header-mine-brand"><?php the_custom_logo(); ?></div>
<?php endif; ?>
```

## Локализация через `sb_t()`

Все тексты которые видит пользователь должны идти через функцию `sb_t('ключ')` — она подставит перевод по локали сайта. Доступные ключи:

- `home` — Home / Главная
- `navigation` — Navigation / Навигация
- `site` — Site / Сайт
- `primary_menu` — Main menu / Основное меню
- `footer_menu` — Footer menu / Меню футера
- `rights_reserved` — All rights reserved / Все права защищены
- `back_to_top` — Back to top / Наверх
- `articles` — Articles / Статьи
- `previous`, `next`, `page_of` — для пагинации
- `section_overview` — Section overview / Обзор раздела

Если нужен новый ключ — открой `templates/theme/base/inc/i18n.php`, добавь ключ во все 36 локалей (или хотя бы в `default`).

## JavaScript

Если вариация интерактивная (burger, dropdown, sticky-эффект) — добавь файл `slug.js`. Он **автоматически** соберётся генератором в единый `assets/theme.js` темы и подключится через `wp_enqueue_script`.

**Важные ограничения:**
1. Все JS-файлы всех активных вариаций объединяются в один — избегай глобальных имён. Оборачивай в IIFE или используй уникальные префиксы: `slimHeader`, `ocPanel` и т.д.
2. jQuery **не гарантируется** — используй чистый JavaScript.
3. Скрипт грузится в подвале (`in_footer = true`), поэтому DOM уже готов — `DOMContentLoaded` не нужен.

## Метаданные `slug.json`

Файл JSON описывает вариацию для UI конструктора:

```json
{
  "name": "Sticky Slim",
  "description": "Тонкая липкая шапка 48px. Логотип слева, меню справа. Dropdown на hover для десктопа, burger для мобильных.",
  "preview_type": "sticky-horizontal"
}
```

Для стилей — добавь `palette` (массив 4-5 hex-цветов для превью-плашки):

```json
{
  "name": "Corporate Navy",
  "description": "Глубокий синий, золотые акценты. Serif-шрифт.",
  "palette": ["#0f172a", "#1e293b", "#f1f5f9", "#d4af37"]
}
```

**Значения `preview_type`** — определяют тип SVG-превью в UI:

| Категория | Возможные значения |
|---|---|
| headers | `sticky-horizontal`, `off-canvas`, `two-row`, `horizontal` (default) |
| footers | `columns-4`, `newsletter`, `divided-3`, `simple`, `columns-3` |
| styles | (не используется, используется `palette`) |

## Чек-лист перед сдачей

- [ ] `slug.php` синтаксически корректен, использует `wp_nav_menu` + `the_custom_logo` + `sb_t`
- [ ] Все обёртки CSS ограничены классом `sb-header-slug` / `sb-footer-slug`
- [ ] Цвета через `var(--sb-color-*)` с fallback
- [ ] Работает на всех 3 существующих цветовых схемах (Bright / Dark / Elegant) — визуальная проверка
- [ ] Responsive: работает на мобильных (≤600px)
- [ ] Для header: меню поддерживает 3 уровня вложенности
- [ ] Для header: burger на мобильных
- [ ] Логотип обёрнут в `<h1>` только на главной
- [ ] Меню в `<nav aria-label>` для доступности
- [ ] Все тексты через `sb_t()`
- [ ] JSON содержит `name`, `description`, `preview_type` (и `palette` для styles)
- [ ] JS (если есть) не создаёт глобальных конфликтов
- [ ] Валидатор HTML (например W3C) не выдаёт ошибок

## Готовые примеры

Изучи существующие вариации как образец:

- **Header** — `sticky-slim.*` (простой sticky), `off-canvas.*` (панель), `two-row.*` (двухстрочная)
- **Footer** — `rich-4col.*` (4 колонки), `newsletter-highlight.*` (форма подписки), `modern-divided.*` (соц-иконки)
- **Style** — `corporate-navy.css` (deep serif), `neon-glow.css` (gaming), `warm-earth.css` (editorial)

## Шаблон-заготовка Header

```php
<?php
// templates/theme/headers/my-header.php
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-my" role="banner">
    <div class="sb-header-my-inner">
        <?php if (is_front_page()): ?>
            <h1 class="sb-header-my-brand">
                <?php if (has_custom_logo()) { the_custom_logo(); }
                else { echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>'; } ?>
            </h1>
        <?php else: ?>
            <div class="sb-header-my-brand">
                <?php if (has_custom_logo()) { the_custom_logo(); }
                else { echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>'; } ?>
            </div>
        <?php endif; ?>
        <nav class="sb-header-my-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'sb-menu',
                'depth' => 3,
                'fallback_cb' => false,
            ]); ?>
        </nav>
    </div>
</header>
```

## Шаблон-заготовка Footer

```php
<?php
// templates/theme/footers/my-footer.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-my" role="contentinfo">
    <div class="sb-footer-my-inner">
        <div class="sb-footer-my-grid">
            <div class="sb-footer-my-col">
                <h2 class="sb-footer-my-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
                <?php if (has_nav_menu('footer')) {
                    wp_nav_menu([
                        'theme_location' => 'footer',
                        'container' => false,
                        'menu_class' => 'sb-menu',
                        'depth' => 1,
                        'fallback_cb' => false,
                    ]);
                } ?>
            </div>
            <!-- ...ещё колонки... -->
        </div>
        <p class="sb-footer-my-copyright">
            © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>.
            <?php echo esc_html(sb_t('rights_reserved')); ?>
        </p>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
```

## Шаблон-заготовка Style

```css
/* templates/theme/styles/my-style.css */
:root {
    --sb-color-bg:              #ffffff;
    --sb-color-bg-alt:          #f9fafb;
    --sb-color-text:            #111827;
    --sb-color-muted:           #6b7280;
    --sb-color-link:            #2563eb;
    --sb-color-border:          #e5e7eb;
    --sb-color-accent:          #dc2626;

    --sb-font-body:             -apple-system, BlinkMacSystemFont, sans-serif;
    --sb-font-heading:          -apple-system, BlinkMacSystemFont, sans-serif;
    --sb-font-size:             16px;
    --sb-line-height:           1.65;
    --sb-content-max-width:     920px;
}

body.sb-site {
    background: var(--sb-color-bg);
    color: var(--sb-color-text);
    font-family: var(--sb-font-body);
    font-size: var(--sb-font-size);
    line-height: var(--sb-line-height);
}

body.sb-site h1, body.sb-site h2, body.sb-site h3, body.sb-site h4 {
    font-family: var(--sb-font-heading);
    color: var(--sb-color-text);
}

body.sb-site a {
    color: var(--sb-color-link);
    text-decoration: underline;
}
```

## Что делать после создания

1. Скопировать файлы в нужную папку (`headers/`, `footers/` или `styles/`).
2. Открыть в WordPress-админке: **Site Builder → Тема** — новый вариант появится в горизонтальной прокрутке автоматически.
3. Выбрать вариант, нажать **«Сгенерировать и активировать тему»**.
4. Проверить сайт на всех 3 существующих цветовых схемах.
5. Пройти чек-лист.
