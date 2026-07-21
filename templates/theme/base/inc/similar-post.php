<?php 

// Получаем текущую локаль
$current_lang = get_locale();

// Массив переводов для всех текстов
$translations = [
    'default' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'pt_PT' => [
        'read_also' => 'Leia também',
        'recommend' => 'Recomendamos',
        'sections' => 'Secções',
        'news' => 'Notícia',
        'article' => 'Artigo',
        'material' => 'Material'
    ],
    'it_IT' => [
        'read_also' => 'Leggi anche',
        'recommend' => 'Consigliamo',
        'sections' => 'Sezioni',
        'news' => 'Notizia',
        'article' => 'Articolo',
        'material' => 'Materiale'
    ],
    'de_DE' => [
        'read_also' => 'Lesen Sie auch',
        'recommend' => 'Empfehlung',
        'sections' => 'Bereiche',
        'news' => 'Nachricht',
        'article' => 'Artikel',
        'material' => 'Material'
    ],
    'es_ES' => [
        'read_also' => 'Lea también',
        'recommend' => 'Recomendamos',
        'sections' => 'Secciones',
        'news' => 'Noticia',
        'article' => 'Artículo',
        'material' => 'Material'
    ],
    'fr_FR' => [
        'read_also' => 'Lire aussi',
        'recommend' => 'Recommandons',
        'sections' => 'Sections',
        'news' => 'Nouvelle',
        'article' => 'Article',
        'material' => 'Matériel'
    ],
    'pl_PL' => [
        'read_also' => 'Przeczytaj także',
        'recommend' => 'Polecamy',
        'sections' => 'Sekcje',
        'news' => 'Wiadomość',
        'article' => 'Artykuł',
        'material' => 'Materiał'
    ],
    'cs_CZ' => [
        'read_also' => 'Přečtěte si také',
        'recommend' => 'Doporučujeme',
        'sections' => 'Sekce',
        'news' => 'Zpráva',
        'article' => 'Článek',
        'material' => 'Materiál'
    ],
    'da_DK' => [
        'read_also' => 'Læs også',
        'recommend' => 'Anbefaler',
        'sections' => 'Sektioner',
        'news' => 'Nyhed',
        'article' => 'Artikel',
        'material' => 'Materiale'
    ],
    'nl_NL' => [
        'read_also' => 'Lees ook',
        'recommend' => 'Aanbevolen',
        'sections' => 'Secties',
        'news' => 'Nieuws',
        'article' => 'Artikel',
        'material' => 'Materiaal'
    ],
    'el' => [
        'read_also' => 'Διαβάστε επίσης',
        'recommend' => 'Συνιστούμε',
        'sections' => 'Ενότητες',
        'news' => 'Είδηση',
        'article' => 'Άρθρο',
        'material' => 'Υλικό'
    ],
    'ro_RO' => [
        'read_also' => 'Citiți și',
        'recommend' => 'Recomandăm',
        'sections' => 'Secțiuni',
        'news' => 'Știre',
        'article' => 'Articol',
        'material' => 'Material'
    ],
    'sv_SE' => [
        'read_also' => 'Läs också',
        'recommend' => 'Rekommenderar',
        'sections' => 'Sektioner',
        'news' => 'Nyhet',
        'article' => 'Artikel',
        'material' => 'Material'
    ],
    'fi_FI' => [
        'read_also' => 'Lue myös',
        'recommend' => 'Suosittelemme',
        'sections' => 'Osiot',
        'news' => 'Uutinen',
        'article' => 'Artikkeli',
        'material' => 'Materiaali'
    ],
    'de_CH' => [
        'read_also' => 'Lesen Sie auch',
        'recommend' => 'Empfehlung',
        'sections' => 'Bereiche',
        'news' => 'Nachricht',
        'article' => 'Artikel',
        'material' => 'Material'
    ],
    'nl_BE' => [
        'read_also' => 'Lees ook',
        'recommend' => 'Aanbevolen',
        'sections' => 'Secties',
        'news' => 'Nieuws',
        'article' => 'Artikel',
        'material' => 'Materiaal'
    ],
    'bg_BG' => [
        'read_also' => 'Прочетете също',
        'recommend' => 'Препоръчваме',
        'sections' => 'Секции',
        'news' => 'Новина',
        'article' => 'Статия',
        'material' => 'Материал'
    ],
    'en_IE' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'nb_NO' => [
        'read_also' => 'Les også',
        'recommend' => 'Anbefaler',
        'sections' => 'Seksjoner',
        'news' => 'Nyhet',
        'article' => 'Artikkel',
        'material' => 'Materiale'
    ],
    'en_CA' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'en_AU' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'en_US' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'en_GB' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'et_EE' => [
        'read_also' => 'Loe ka',
        'recommend' => 'Soovitame',
        'sections' => 'Sektsioonid',
        'news' => 'Uudis',
        'article' => 'Artikkel',
        'material' => 'Materjal'
    ],
    'sl_SI' => [
        'read_also' => 'Preberite tudi',
        'recommend' => 'Priporočamo',
        'sections' => 'Oddelki',
        'news' => 'Novica',
        'article' => 'Članek',
        'material' => 'Material'
    ],
    'sk_SK' => [
        'read_also' => 'Prečítajte si tiež',
        'recommend' => 'Odporúčame',
        'sections' => 'Sekcie',
        'news' => 'Správa',
        'article' => 'Článok',
        'material' => 'Materiál'
    ],
    'hr_HR' => [
        'read_also' => 'Pročitajte također',
        'recommend' => 'Preporučujemo',
        'sections' => 'Sekcije',
        'news' => 'Vijest',
        'article' => 'Članak',
        'material' => 'Materijal'
    ],
    'hu_HU' => [
        'read_also' => 'Olvassa el is',
        'recommend' => 'Ajánljuk',
        'sections' => 'Szakaszok',
        'news' => 'Hír',
        'article' => 'Cikk',
        'material' => 'Anyag'
    ],
    'en_NZ' => [
        'read_also' => 'Read also',
        'recommend' => 'Recommend',
        'sections' => 'Sections',
        'news' => 'News',
        'article' => 'Article',
        'material' => 'Material'
    ],
    'de_AT' => [
        'read_also' => 'Lesen Sie auch',
        'recommend' => 'Empfehlung',
        'sections' => 'Bereiche',
        'news' => 'Nachricht',
        'article' => 'Artikel',
        'material' => 'Material'
    ],
    'pt_BR' => [
        'read_also' => 'Leia também',
        'recommend' => 'Recomendamos',
        'sections' => 'Seções',
        'news' => 'Notícia',
        'article' => 'Artigo',
        'material' => 'Material'
    ],
    'is_IS' => [
        'read_also' => 'Lestu einnig',
        'recommend' => 'Mælum með',
        'sections' => 'Kaflar',
        'news' => 'Frétt',
        'article' => 'Grein',
        'material' => 'Efni'
    ],
    'lb_LU' => [
        'read_also' => 'Liest och',
        'recommend' => 'Empfeelen',
        'sections' => 'Sektiounen',
        'news' => 'Neiegkeet',
        'article' => 'Artikel',
        'material' => 'Material'
    ],
    'lv' => [
        'read_also' => 'Lasi arī',
        'recommend' => 'Iesakām',
        'sections' => 'Sadaļas',
        'news' => 'Ziņa',
        'article' => 'Raksts',
        'material' => 'Materiāls'
    ],
    'es_PE' => [
        'read_also' => 'Lea también',
        'recommend' => 'Recomendamos',
        'sections' => 'Secciones',
        'news' => 'Noticia',
        'article' => 'Artículo',
        'material' => 'Material'
    ],
    'tr_TR' => [
        'read_also' => 'Ayrıca okuyun',
        'recommend' => 'Tavsiye ederiz',
        'sections' => 'Bölümler',
        'news' => 'Haber',
        'article' => 'Makale',
        'material' => 'Materyal'
    ]
];

// Функция для получения переведенного текста
if (!function_exists('get_translated_text')) {
function get_translated_text($key, $current_lang) {
    global $translations;
    
    if (isset($translations[$current_lang][$key])) {
        return $translations[$current_lang][$key];
    }
    
    return isset($translations['default'][$key]) ? $translations['default'][$key] : $key;
}
}

// Обновленная функция auto_insert_child_pages с переводами и исключением шаблонов
if (!function_exists('auto_insert_child_pages')) {
function auto_insert_child_pages($content) {
    global $post, $current_lang;
    
    if (!is_singular() || is_admin() || !is_main_query()) return $content;
    
    if (!$post) return $content;
    if (strpos($content, 'child-pages-showcase') !== false) return $content;

    // Skip Site Builder utility pages (fsr_utility = 1) — they're for terms,
    // sitemaps, footer-only content, where related-posts blocks make no sense.
    if ((int)get_post_meta($post->ID, 'fsr_utility', true) === 1) return $content;

    // Skip [A] pages — they already display a grid of their own children via
    // articles-grid.php; a separate "related posts" block on top would feel
    // redundant and visually crowded.
    if ((int)get_post_meta($post->ID, 'fsr_articles_grid', true) === 1) return $content;

    // Исключаем страницы с определенными шаблонами
    $excluded_templates = array('page-sitemap.php', 'those-pages.php');
    $current_template = get_page_template_slug($post->ID);
    
    if (in_array($current_template, $excluded_templates)) {
        return $content;
    }

    // ─── Transient cache (P0.2, 1.1.9) ──────────────────────────────────────
    // Rebuilding the block costs 30-70ms per page: 2× get_posts + 10-20 ×
    // (get_permalink + get_the_post_thumbnail) inside render_child_pages_showcase.
    // Cache the rendered HTML per post_id and invalidate on structural changes
    // (save/delete of the post, its parent, or siblings) — see
    // sb_similar_invalidate_related() below. TTL 12h as a safety-net expiration
    // in case an invalidation is missed.
    //
    // Empty results are cached too (with '' sentinel) so we skip the expensive
    // fallback chain on pages with nothing related — with a shorter TTL so the
    // site "catches up" faster when it gains content.
    $cache_key = 'sb_similar_v1_' . $post->ID;
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $content . (string)$cached;
    }

    $extra_html = sb_similar_build_html($post, $current_lang);

    if ($extra_html !== '') {
        set_transient($cache_key, $extra_html, 12 * HOUR_IN_SECONDS);
    } else {
        set_transient($cache_key, '', HOUR_IN_SECONDS);
    }

    return $content . $extra_html;
}
}

/**
 * Build the "similar posts" HTML for a given post. Extracted from
 * auto_insert_child_pages() so the caller can cache the result via transient.
 * Behavior is intentionally identical to the pre-1.1.9 inline code —
 * mechanical extraction, not a refactor.
 */
if (!function_exists('sb_similar_build_html')) {
function sb_similar_build_html($post, $current_lang) {
    $post_type = $post->post_type;

    // Пробуем дочерние (для страниц или произвольных типов)
    if ($post_type === 'page') {
        $children = get_pages(array(
            'parent'      => $post->ID,
            'sort_column' => 'menu_order, post_title',
            'number'      => 4,
            'post_status' => 'publish',
        ));
    } else {
        // Для записей (новостей) и других типов - ищем связанные по таксономиям
        $children = get_related_posts_by_taxonomy($post, 4);
    }

    if (!empty($children)) {
        return render_child_pages_showcase($children, $post_type, $current_lang);
    }

    // Нет дочерних/связанных — берём соседей (того же родителя для страниц)
    $siblings = array();
    if ($post_type === 'page' && $post->post_parent) {
        $siblings = get_pages(array(
            'parent'      => $post->post_parent,
            'sort_column' => 'menu_order, post_title',
            'post_status' => 'publish',
            'exclude'     => array($post->ID),
        ));
    } elseif ($post_type === 'post') {
        // Для записей - посты из тех же категорий
        $categories = wp_get_post_categories($post->ID);
        if (!empty($categories)) {
            $siblings = get_posts(array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'category__in'   => $categories,
                'post__not_in'   => array($post->ID),
            ));
        }
    }

    if (!empty($siblings)) {
        shuffle($siblings);
        $pages = array_slice($siblings, 0, 4);
        return render_child_pages_showcase($pages, $post_type, $current_lang);
    }

    // Нет ничего — рандомные записи/страницы с картинками
    $random = get_posts(array(
        'post_type'      => array('post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'orderby'        => 'rand',
        'exclude'        => array($post->ID),
        'meta_query'     => array(
            array(
                'key'   => '_thumbnail_id',
                'compare' => 'EXISTS',
            ),
        ),
    ));

    // Если с картинками мало — добираем без
    if (count($random) < 4) {
        $exclude_ids = array_merge(array($post->ID), wp_list_pluck($random, 'ID'));
        $extra = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 4 - count($random),
            'orderby'        => 'rand',
            'exclude'        => $exclude_ids,
        ));
        $random = array_merge($random, $extra);
    }

    if (!empty($random)) {
        return render_child_pages_showcase($random, $post_type, $current_lang);
    }

    return '';
}
}

/**
 * Invalidate the similar-post cache for a post and its structural neighbors.
 * A save/delete of post X can change:
 *   - X's own similar block (obvious)
 *   - X's parent's similar block (X is a child)
 *   - X's siblings' similar blocks (X might appear in their fallback list)
 * We drop all three groups' transients. Sibling lookup is one indexed query —
 * cheap for an admin-side hook, not part of the front-end hot path.
 */
if (!function_exists('sb_similar_invalidate_related')) {
    function sb_similar_invalidate_related($post_id, $post = null) {
        if (!$post) $post = get_post($post_id);
        if (!$post) return;
        static $ignore = [
            'nav_menu_item' => true, 'revision' => true, 'attachment' => true,
            'customize_changeset' => true, 'oembed_cache' => true, 'wp_block' => true,
        ];
        if (isset($ignore[$post->post_type])) return;

        delete_transient('sb_similar_v1_' . $post_id);

        if ($post->post_parent > 0) {
            delete_transient('sb_similar_v1_' . $post->post_parent);
            $siblings = get_posts([
                'post_type'        => $post->post_type,
                'post_parent'      => $post->post_parent,
                'fields'           => 'ids',
                'posts_per_page'   => -1,
                'post_status'      => 'any',
                'suppress_filters' => true,
            ]);
            foreach ($siblings as $sib_id) {
                if ((int)$sib_id !== (int)$post_id) {
                    delete_transient('sb_similar_v1_' . $sib_id);
                }
            }
        }
    }
    add_action('save_post',    'sb_similar_invalidate_related', 20, 2);
    add_action('deleted_post', 'sb_similar_invalidate_related', 20, 2);
    add_action('trashed_post', 'sb_similar_invalidate_related', 20);
}
if (!has_filter('the_content', 'auto_insert_child_pages')) {
    add_filter('the_content', 'auto_insert_child_pages');
}

// Вспомогательная функция для получения связанных постов по таксономиям
if (!function_exists('get_related_posts_by_taxonomy')) {
function get_related_posts_by_taxonomy($post, $limit = 4) {
    $taxonomies = get_object_taxonomies($post->post_type, 'names');
    $terms = array();
    
    foreach ($taxonomies as $taxonomy) {
        $post_terms = wp_get_post_terms($post->ID, $taxonomy, array('fields' => 'ids'));
        if (!empty($post_terms)) {
            $terms = array_merge($terms, $post_terms);
        }
    }
    
    if (empty($terms)) {
        return array();
    }
    
    $related = get_posts(array(
        'post_type'      => $post->post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'post__not_in'   => array($post->ID),
        'tax_query'      => array(
            array(
                'taxonomy' => key($taxonomies),
                'field'    => 'term_id',
                'terms'    => array_unique($terms),
            ),
        ),
    ));
    
    return $related;
}
}

// Обновленная функция рендера с переводами
if (!function_exists('render_child_pages_showcase')) {
function render_child_pages_showcase($children, $post_type = 'page', $current_lang = 'default') {
    if (empty($children)) return '';
    
    // Определяем заголовок блока с переводом
    $first_post = $children[0];
    $parent_id = ($post_type === 'page') ? wp_get_post_parent_id($first_post->ID) : null;
    
    if ($parent_id && $post_type === 'page') {
        $parent = get_post($parent_id);
        $block_title = $parent ? $parent->post_title : get_translated_text('sections', $current_lang);
    } elseif ($post_type === 'post') {
        $block_title = get_translated_text('read_also', $current_lang);
    } else {
        $block_title = get_translated_text('recommend', $current_lang);
    }
    
    $out = '
    <style>
        .child-pages-small-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        @media (max-width: 768px) {
            .child-pages-small-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }
        @media (max-width: 480px) {
            .child-pages-small-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="child-pages-showcase" style="max-width: 900px;
        margin: 50px auto 30px;
        font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif;
    ">
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        ">
            <h2 style="
                margin: 0;
                font-size: 26px;
                font-weight: 700;
                color: var(--sb-color-text, #1a1a1a);
                letter-spacing: -0.3px;
            ">' . esc_html($block_title) . '</h2>
        </div>';
    
    // === Первая карточка — большая (hero) ===
    $first = $children[0];
    $thumb_first = get_the_post_thumbnail_url($first->ID, 'large');
    $excerpt_first = $first->post_excerpt ?: wp_trim_words(strip_shortcodes($first->post_content), 30, '…');
    
    $bg_style = $thumb_first
        ? 'background:linear-gradient(180deg,rgba(0,0,0,0) 50%,rgba(0,0,0,.7) 100%),url(' . esc_url($thumb_first) . ') center/cover no-repeat;'
        : 'background:linear-gradient(135deg,#2c3e50,#3498db);';
    
    $out .= '
        <a href="' . get_permalink($first->ID) . '" style="
            display: block;
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            min-height: 340px;
            ' . $bg_style . '
            text-decoration: none;
            color: #fff;
            margin-bottom: 16px;
            transition: transform .25s ease, box-shadow .25s ease;
        " onmouseover="this.style.transform=\'translateY(-4px)\';this.style.boxShadow=\'0 12px 40px rgba(0,0,0,.18)\'"
           onmouseout="this.style.transform=\'none\';this.style.boxShadow=\'none\'">
            <div style="
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 32px;
            ">
                <h3 style="
                    margin: 0 0 8px;
                    font-size: 24px;
                    font-weight: 700;
                    line-height: 1.3;
                    color: #fff;
                    text-shadow: 0 1px 2px rgb(0 0 0 / 80%);
                ">' . esc_html($first->post_title) . '</h3>
                <p style="
                    margin: 0;
                    font-size: 15px;
                    line-height: 1.5;
                    opacity: .9;
                    text-shadow: 0 1px 2px rgba(0,0,0,.2);
                ">' . esc_html($excerpt_first) . '</p>
            </div>
        </a>';
    
    // === Остальные 3 карточки — сетка ===
    $rest = array_slice($children, 1, 3);
    
    if (!empty($rest)) {
        $out .= '<div class="child-pages-small-grid">';
        
        $colors = array(
            'linear-gradient(135deg,#6366f1,#8b5cf6)',
            'linear-gradient(135deg,#f59e0b,#ef4444)',
            'linear-gradient(135deg,#10b981,#06b6d4)',
        );
        
        foreach ($rest as $i => $page) {
            $thumb = get_the_post_thumbnail_url($page->ID, 'medium');
            $excerpt = $page->post_excerpt ?: wp_trim_words(strip_shortcodes($page->post_content), 12, '…');
            $color = $colors[$i % count($colors)];
            
            if ($thumb) {
                $card_bg = 'background:linear-gradient(180deg,rgba(0,0,0,0) 20%,rgba(0,0,0,.65) 100%),url(' . esc_url($thumb) . ') center/cover no-repeat;';
            } else {
                $card_bg = 'background:' . $color . ';';
            }
            
            $out .= '
            <a href="' . get_permalink($page->ID) . '" style="
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                min-height: 200px;
                border-radius: 14px;
                overflow: hidden;
                padding: 20px;
                text-decoration: none;
                color: #fff;
                ' . $card_bg . '
                transition: transform .25s ease, box-shadow .25s ease;
            " onmouseover="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 8px 30px rgba(0,0,0,.15)\'"
               onmouseout="this.style.transform=\'none\';this.style.boxShadow=\'none\'">
                <h4 style="
                    margin: 0 0 6px;
                    font-size: 17px;
                    font-weight: 700;
                    line-height: 1.3;
                    color: #fff;
                    text-shadow: 0 1px 2px rgb(0 0 0 / 80%);
                ">' . esc_html($page->post_title) . '</h4>
                <p style="
                    margin: 0;
                    font-size: 13px;
                    line-height: 1.4;
                    opacity: .85;
                    text-shadow: 0 1px 2px rgba(0,0,0,.2);
                ">' . esc_html($excerpt) . '</p>
            </a>';
        }
        
        $out .= '</div>';
    };
    
    $out .= '</div>';
    return $out;
}
}

// Обновленная вспомогательная функция для метки типа поста с переводом
if (!function_exists('get_post_type_label')) {
function get_post_type_label($post_type, $current_lang = 'default') {
    $labels = array(
        'post' => get_translated_text('news', $current_lang),
        'page' => get_translated_text('article', $current_lang),
    );
    
    $post_type_obj = get_post_type_object($post_type);
    if ($post_type_obj && isset($post_type_obj->labels->singular_name)) {
        return $post_type_obj->labels->singular_name;
    }
    
    return isset($labels[$post_type]) ? $labels[$post_type] : get_translated_text('material', $current_lang);
}
}
?>