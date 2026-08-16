<?php
/**
 * Site Builder — built-in SEO output.
 *
 * Ported from mod-seo.php (parallel team), adapted for our ecosystem:
 *   - Auto-detects competing SEO plugins (Yoast/RankMath/AIOSEO/SEOPress/SEO Framework)
 *     and steps aside if any is active — no duplicated meta tags or canonicals.
 *   - Reads from Site Builder's own meta keys (fsr_headline, fsr_headimg) and
 *     standard WP fields (post_title, post_excerpt, featured image).
 *   - Also honors _custom_seo_title / _custom_seo_desc / _custom_seo_headline
 *     if those happen to be set — manual overrides take priority.
 *   - JSON-LD @type chosen by FSR flags:
 *       fsr_articles_grid=1 → CollectionPage  (hub of cards)
 *       fsr_utility=1       → WebPage         (privacy/legal/etc — not Article)
 *       otherwise           → Article         (with wordCount, headline, etc.)
 *
 * Picks up breadcrumbs from get_my_breadcrumbs_items() if available — that
 * function is provided by inc/breadcrumbs.php in this same theme.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_seo_should_run')) {

    /**
     * Returns false if a known third-party SEO plugin is active. We only
     * detect plugins that produce their own <title>, canonical, or schema —
     * because emitting our tags alongside theirs would duplicate them in
     * the HTML.
     */
    function sb_seo_should_run() {
        if (defined('WPSEO_VERSION'))                                    return false; // Yoast SEO
        if (function_exists('wpseo_init'))                               return false; // Yoast (legacy detection)
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath'))    return false; // RankMath SEO
        if (defined('AIOSEO_VERSION') || function_exists('aioseo'))      return false; // All in One SEO
        if (defined('SEOPRESS_VERSION'))                                 return false; // SEOPress
        if (defined('THE_SEO_FRAMEWORK_VERSION'))                        return false; // The SEO Framework
        return true;
    }

    // Wire up on init so other plugins have already declared their constants
    // by the time our should_run() check runs.
    add_action('init', function () {
        if (!sb_seo_should_run()) return;

        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', '_wp_render_title_tag', 1);

        add_action('wp_head', 'sb_seo_canonical');
        add_action('wp_head', 'sb_seo_head_output', 1);
    });

    /**
     * Custom canonical that accounts for pagination:
     *   - ?page=N (WP <!--nextpage--> splits inside a singular post)
     *   - /page/N/ (paginated archives — post_type_archive etc.)
     *   - ?gp=N (this theme's articles-grid pagination — see articles-grid.php)
     *
     * The default WP rel_canonical strips all query params, which for our
     * articles-grid would make page 2, 3, ... all canonical-point to page 1
     * (bad for SEO — Google sees them as duplicates). We include ?gp=N in
     * the canonical when we're on a paginated grid view so each pagination
     * page has its own canonical URL matching its actual URL.
     */
    function sb_seo_canonical() {
        if (!is_singular()) return;
        $link  = get_permalink();
        $page  = (int)get_query_var('page');
        $paged = (int)get_query_var('paged');
        if ($page > 1 || $paged > 1) {
            $n = max($page, $paged);
            $link = trailingslashit($link) . 'page/' . $n . '/';
        }
        // Articles-grid pagination: include ?gp=N when present and > 1.
        // We check the meta directly rather than a helper because canonical
        // runs early (before we might have loaded FSR-aware helpers).
        $post_id = get_the_ID();
        if ($post_id && isset($_GET['gp'])) {
            $gp = max(1, (int)$_GET['gp']);
            $is_grid = (int)get_post_meta($post_id, 'fsr_articles_grid', true) === 1;
            if ($is_grid && $gp >= 2) {
                $link = add_query_arg('gp', $gp, $link);
            }
        }
        echo '<link rel="canonical" href="' . esc_url($link) . "\" />\n";
    }

    /**
     * Read the first non-empty value from a list of meta keys on a given post.
     * Used to walk the fallback chain: manual custom → mapped SEO-plugin keys
     * → fsr_* frontmatter fallbacks.
     */
    function sb_seo_first_meta($post_id, array $keys) {
        foreach ($keys as $k) {
            $v = trim((string)get_post_meta($post_id, $k, true));
            if ($v !== '') return $v;
        }
        return '';
    }

    /**
     * Known meta keys that popular WordPress SEO plugins use. The importer's
     * Field_Mapping writes into these — so even when those plugins aren't
     * active on the site, the values are in the DB and we should read them.
     * Order within each slot doesn't matter (first non-empty wins), but keeps
     * Yoast/RankMath first because they're the most common.
     */
    function sb_seo_known_keys() {
        return [
            'title' => [
                '_yoast_wpseo_title', 'rank_math_title', '_aioseop_title', '_aioseo_title',
                '_genesis_title', '_seopress_titles_title', '_su_meta_title',
                'meta_title', 'seo_title',
            ],
            'desc' => [
                '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description', '_aioseo_description',
                '_genesis_description', '_seopress_titles_desc', '_su_meta_description',
                'meta_description', 'seo_description',
            ],
            'og_title' => [
                '_yoast_wpseo_opengraph-title', 'rank_math_facebook_title', '_aioseop_opengraph_settings_title',
                '_aioseo_og_title', '_seopress_social_fb_title', 'og_title', 'og_meta_title', 'social_headline',
            ],
            'og_desc' => [
                '_yoast_wpseo_opengraph-description', 'rank_math_facebook_description',
                '_aioseop_opengraph_settings_description', '_aioseo_og_description',
                '_seopress_social_fb_desc', 'og_description', 'og_meta_description',
            ],
            'og_image' => [
                '_yoast_wpseo_opengraph-image', 'rank_math_facebook_image',
                '_aioseop_opengraph_settings_customimg', '_aioseo_og_image_custom_url',
                '_seopress_social_fb_img', 'og_image', 'og_meta_image', 'featured_image_url',
            ],
        ];
    }

    /**
     * Extract FAQ items from raw post_content by scanning for the `::: faq ... :::`
     * container block. Each item is separated by a `---` line inside the block,
     * with the question on a `## ...` line and the rest being the answer.
     *
     * Runs on the raw markdown/HTML in post_content — the_content filter hasn't
     * fired yet at wp_head time, so we can't inspect the rendered DOM. This is
     * fine because block source syntax is stable and easier to parse than HTML.
     *
     * Returns [] if no faq block or no valid items — the caller uses that to
     * decide whether to emit the FAQPage entity at all (spec says: skip when
     * empty, never emit empty arrays).
     */
    function sb_seo_extract_faq($post_content) {
        if (strpos($post_content, '::: faq') === false) return [];
        if (!preg_match('/^:::\s*faq\s*$(.*?)^:::\s*$/ms', $post_content, $m)) return [];

        $body = trim($m[1]);
        // Split by `---` on its own line (fence between items)
        $chunks = preg_split('/^\s*---\s*$/m', $body);
        $items = [];
        foreach ($chunks as $chunk) {
            if (!preg_match('/^\s*##\s+(.+)$/m', $chunk, $qm)) continue;
            $question = trim($qm[1]);
            $answer_raw = trim(preg_replace('/^\s*##\s+.+$/m', '', $chunk, 1));
            // Answers may contain markdown (bold, italic, links) — strip
            // formatting characters and tags for the schema text field, which
            // must be plain readable prose. Google's FAQPage guidelines
            // explicitly allow limited HTML but plain text is safer for
            // validator.schema.org (which flags unknown markup in text nodes).
            $answer_plain = wp_strip_all_tags($answer_raw);
            // Now strip markdown syntax that wp_strip_all_tags doesn't know
            // about: bold **x**/__x__, italic *x*/_x_, inline `code`, and
            // link syntax [text](url) → text.
            $answer_plain = preg_replace('/\*\*(.+?)\*\*/s', '$1', $answer_plain); // **bold**
            $answer_plain = preg_replace('/__(.+?)__/s',     '$1', $answer_plain); // __bold__
            $answer_plain = preg_replace('/(?<![*])\*(?![*])(.+?)(?<![*])\*(?![*])/s', '$1', $answer_plain); // *italic*
            $answer_plain = preg_replace('/(?<![_])_(?![_])(.+?)(?<![_])_(?![_])/s',   '$1', $answer_plain); // _italic_
            $answer_plain = preg_replace('/`([^`]+)`/', '$1', $answer_plain);      // `code`
            $answer_plain = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $answer_plain); // [text](url) → text
            $answer_plain = preg_replace('/\s+/', ' ', $answer_plain);
            $answer_plain = trim($answer_plain);
            if ($question === '' || $answer_plain === '') continue;
            $items[] = ['q' => $question, 'a' => $answer_plain];
        }
        return $items;
    }

    /**
     * Main SEO output: <title>, meta description, OG/Twitter, JSON-LD graph.
     * Singular-only; archives/lists fall through to WordPress defaults.
     */
    function sb_seo_head_output() {
        if (!is_singular()) return;
        global $post;
        if (!$post) return;
        $post_id   = $post->ID;
        $site_name = get_bloginfo('name');
        $permalink = get_permalink($post_id);
        $locale    = get_locale();

        $known = sb_seo_known_keys();

        // === Fallback chain for each field ===
        //   1. Manual custom (edited via Site Builder SEO metabox) — highest
        //   2. Mapped SEO-plugin keys the importer wrote to (Yoast/RankMath/etc.)
        //   3. fsr_* frontmatter fallback keys (guaranteed by importer)
        //   4. Standard WP fields (post_title, post_excerpt) or auto-generated
        //
        // Values are trimmed and empty strings are skipped in sb_seo_first_meta.

        // Title
        $seo_title = sb_seo_first_meta($post_id, array_merge(
            ['_custom_seo_title'],           // manual
            $known['title'],                 // mapped
            ['fsr_title']                    // frontmatter fallback
        ));
        if ($seo_title === '') $seo_title = get_the_title($post_id);

        // Description
        $seo_desc = sb_seo_first_meta($post_id, array_merge(
            ['_custom_seo_desc'],            // manual
            $known['desc'],                  // mapped
            ['fsr_description']              // frontmatter fallback
        ));
        if ($seo_desc === '') {
            // Last resort: auto-generate from content
            $seo_desc = (string)$post->post_excerpt;
            if ($seo_desc === '') {
                $seo_desc = wp_strip_all_tags(get_the_excerpt($post_id));
            }
        }
        $seo_desc = wp_trim_words($seo_desc, 30, '');

        // Social headline (og:title)
        $social = sb_seo_first_meta($post_id, array_merge(
            ['_custom_seo_headline'],        // manual
            $known['og_title'],              // mapped
            ['fsr_headline']                 // frontmatter fallback
        ));
        if ($social === '') $social = $seo_title;

        // OG description — usually mirrors description but can be overridden
        $og_desc = sb_seo_first_meta($post_id, array_merge(
            ['_custom_seo_og_desc'],
            $known['og_desc']
        ));
        if ($og_desc === '') $og_desc = $seo_desc;

        // Classify the page — moved up here (used to be below noindex) because
        // the pagination-suffix block below needs $is_grid, and JSON-LD later
        // uses all three. Reading three meta values once is cheap.
        $is_utility = (int)get_post_meta($post_id, 'fsr_utility', true) === 1;
        $is_grid    = (int)get_post_meta($post_id, 'fsr_articles_grid', true) === 1;
        $is_article = !$is_utility && !$is_grid;

        // Pagination suffix — for articles-grid pages on page ≥ 2, append a
        // localized " – Page N" to title and description. Prevents Google
        // from treating page 2, 3, … as duplicates of page 1 (they share the
        // same permalink, only differ by ?gp=N query param), and gives users
        // clear context about which page they're viewing.
        //
        // Only articles-grid pages have pagination in this theme (via ?gp=N in
        // articles-grid.php). Regular content pages don't paginate, so no
        // suffix is needed there.
        //
        // Applied to all four surface fields (title, desc, social, og_desc)
        // so <title>, <meta description>, og:title, og:description, and the
        // JSON-LD graph all stay consistent. WebPage.name / CollectionPage.name
        // will also carry the suffix, which is correct — page 2 is a distinct
        // resource from page 1 as far as search engines are concerned.
        if ($is_grid && isset($_GET['gp'])) {
            $current_page = max(1, (int)$_GET['gp']);
            if ($current_page >= 2) {
                $page_label = function_exists('sb_t')
                    ? sprintf(sb_t('page_n'), $current_page)
                    : sprintf('Page %d', $current_page);
                // En-dash separator — works cleanly across all Latin/Cyrillic
                // locales. Not localized because typographic dashes don't vary
                // per language the way, say, quotes do.
                $suffix = ' – ' . $page_label;
                if ($seo_title !== '') $seo_title .= $suffix;
                if ($seo_desc  !== '') $seo_desc  .= $suffix;
                if ($social    !== '') $social    .= $suffix;
                if ($og_desc   !== '') $og_desc   .= $suffix;
            }
        }

        // Resolve shortcodes ([sb_year], [sb_date]) in any of the fields —
        // meta values are read raw and don't pass through the_title filters.
        // Guarded on '[sb_' prefix so plain text with brackets isn't touched.
        foreach ([&$seo_title, &$seo_desc, &$social, &$og_desc] as &$v) {
            if (is_string($v) && strpos($v, '[sb_') !== false) $v = do_shortcode($v);
        }
        unset($v);

        // Hero image — same fallback logic as title/description:
        //   1. Manual custom_seo_og_image (metabox override) — highest
        //   2. Mapped SEO-plugin OG image keys the importer writes to
        //   3. Featured thumbnail (WP standard)
        //   4. fsr_headimg from frontmatter (only if already absolute URL)
        $img_url = sb_seo_first_meta($post_id, array_merge(
            ['_custom_seo_og_image'],        // manual
            $known['og_image']               // mapped
        ));
        if (!$img_url) {
            $img_url = get_the_post_thumbnail_url($post_id, 'full');
        }
        if (!$img_url) {
            $img_url = trim((string)get_post_meta($post_id, 'fsr_headimg', true));
            // fsr_headimg is a relative path like "IMAGES/foo.webp" — useless
            // as an absolute URL. Only emit it if it's already absolute.
            if ($img_url && !preg_match('#^https?://#i', $img_url)) $img_url = '';
        }

        // noindex flag — set via metabox, forces robots meta and skips JSON-LD
        $noindex = (int)get_post_meta($post_id, '_custom_seo_noindex', true) === 1
                || (int)get_post_meta($post_id, 'fsr_no_index', true) === 1;

        // Logo for Organization schema
        $logo_url = '';
        $logo_id  = (int)get_theme_mod('custom_logo');
        if ($logo_id) $logo_url = (string)wp_get_attachment_image_url($logo_id, 'full');

        // --- HEAD output: title / description / robots ---
        echo "\n<title>" . esc_html($seo_title) . "</title>\n";
        if ($noindex) {
            echo '<meta name="robots" content="noindex,nofollow">' . "\n";
        }
        if ($seo_desc !== '') {
            echo '<meta name="description" content="' . esc_attr($seo_desc) . "\" />\n";
        }

        // --- Open Graph & Twitter Card ---
        $og_type = $is_article ? 'article' : 'website';
        echo '<meta property="og:type" content="' . esc_attr($og_type) . "\" />\n";
        echo '<meta property="og:title" content="' . esc_attr($social) . "\" />\n";
        echo '<meta property="og:url" content="' . esc_url($permalink) . "\" />\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . "\" />\n";
        echo '<meta property="og:locale" content="' . esc_attr($locale) . "\" />\n";
        if ($og_desc !== '') {
            echo '<meta property="og:description" content="' . esc_attr($og_desc) . "\" />\n";
        }
        if ($img_url) {
            echo '<meta property="og:image" content="' . esc_url($img_url) . "\" />\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($img_url) . "\" />\n";
        } else {
            echo '<meta name="twitter:card" content="summary" />' . "\n";
        }
        echo '<meta name="twitter:title" content="' . esc_attr($social) . "\" />\n";
        if ($og_desc !== '') {
            echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . "\" />\n";
        }

        // Skip JSON-LD entirely on noindex pages — no reason to feed schema
        // to a page we're telling crawlers to ignore.
        if ($noindex) return;

        // Page-type priority: [A] beats [U]. A page marked both articles-grid
        // and utility (e.g. a "list of policies" hub) still deserves a
        // CollectionPage schema — hub-of-links transit pages are exactly the
        // case where a linked entity graph helps discovery. Only pages that
        // are utility AND NOT grid get skipped entirely.
        if ($is_utility && !$is_grid) return;

        // --- JSON-LD graph ---
        //
        // Single @graph with all entities linked by @id references, per the
        // 2026-07 SEO team spec. Structure by page type (checked in priority
        // order — first match wins):
        //
        //   [A] (articles-grid)    → Organization + WebSite + CollectionPage(hasPart=children)
        //   [U] (utility, no [A])  → nothing (early return above)
        //   Front page             → Organization + WebSite + WebPage + Article + FAQPage?
        //   Regular content pages  → Organization + WebSite + WebPage + BreadcrumbList? + Article + FAQPage?
        //
        // [A] takes precedence over [U]: a page marked as both an
        // articles-grid AND utility (rare but valid — e.g. a "list of
        // policies" hub) still gets CollectionPage schema. The [A] intent to
        // present a hub-of-links overrides the [U] intent to hide from search.
        //
        // "?" means the entity only appears when the underlying data exists:
        //   - BreadcrumbList: only when get_my_breadcrumbs_items() returns >= 2 items
        //   - FAQPage: only when the post_content contains a ::: faq ::: block
        //     with at least one Q/A pair
        //
        // Empty strings and false values are never emitted — schema.org
        // validators warn on those, and spec calls them out as forbidden.

        $home_url = home_url('/');
        $graph = [];

        // --- Organization (shared identity of the publisher) ---
        $org = [
            '@type' => 'Organization',
            '@id'   => $home_url . '#organization',
            'name'  => $site_name,
            'url'   => $home_url,
        ];
        if ($logo_url) {
            $org['logo'] = [
                '@type'      => 'ImageObject',
                '@id'        => $home_url . '#logo',
                'url'        => $logo_url,
                'contentUrl' => $logo_url,
                'caption'    => $site_name,
                'inLanguage' => $locale,
            ];
        }
        $graph[] = $org;

        // --- WebSite (shared, with publisher pointing back at Organization) ---
        $graph[] = [
            '@type'      => 'WebSite',
            '@id'        => $home_url . '#website',
            'url'        => $home_url,
            'name'       => $site_name,
            'publisher'  => ['@id' => $home_url . '#organization'],
            'inLanguage' => $locale,
        ];

        if ($is_grid) {
            // --- Articles-grid page → CollectionPage with hasPart list ---
            // The children query mirrors what articles-grid.php shows on-page:
            // published child pages, ordered by menu_order then title.
            $children = get_posts([
                'post_type'        => 'page',
                'post_parent'      => $post_id,
                'posts_per_page'   => -1,
                'orderby'          => ['menu_order' => 'ASC', 'title' => 'ASC'],
                'post_status'      => 'publish',
                'suppress_filters' => false,
            ]);
            $has_part = [];
            foreach ($children as $child) {
                $has_part[] = [
                    '@type' => 'WebPage',
                    'url'   => get_permalink($child),
                    'name'  => get_the_title($child),
                ];
            }
            $collection = [
                '@type'      => 'CollectionPage',
                '@id'        => $permalink,
                'url'        => $permalink,
                'name'       => $seo_title,
                'isPartOf'   => ['@id' => $home_url . '#website'],
                'inLanguage' => $locale,
            ];
            if ($seo_desc)       $collection['description'] = $seo_desc;
            if (!empty($has_part)) $collection['hasPart']    = $has_part;
            $graph[] = $collection;

        } else {
            // --- Regular content page → WebPage + Article + (FAQPage) + (BreadcrumbList) ---

            // Breadcrumbs — only present on non-front singular pages, and only
            // when there are >= 2 items (Home + at least one deeper level).
            $breadcrumb_items = function_exists('get_my_breadcrumbs_items')
                ? get_my_breadcrumbs_items()
                : [];
            $has_breadcrumbs = count($breadcrumb_items) >= 2;

            // H1 for Article headline uses the same fallback chain as page.php:
            // _custom_seo_h1 (Site Builder SEO metabox override) → fsr_headline
            // → post title. This is distinct from $seo_title (which is <title>
            // tag text) so an editor can decouple the two.
            $h1 = trim((string)get_post_meta($post_id, '_custom_seo_h1', true));
            if ($h1 === '') $h1 = trim((string)get_post_meta($post_id, 'fsr_headline', true));
            if ($h1 === '') $h1 = get_the_title($post_id);

            // FAQ block extracted from raw post_content
            $faq_items = sb_seo_extract_faq((string)get_post_field('post_content', $post_id));

            // WebPage
            $webpage = [
                '@type'      => 'WebPage',
                '@id'        => $permalink,
                'url'        => $permalink,
                'name'       => $seo_title,
                'isPartOf'   => ['@id' => $home_url . '#website'],
                'inLanguage' => $locale,
            ];
            // Description: front page's WebPage traditionally omits it (the
            // description belongs to the Organization). Regular pages include it.
            if (!is_front_page() && $seo_desc) {
                $webpage['description'] = $seo_desc;
            }
            if ($has_breadcrumbs) {
                $webpage['breadcrumb'] = ['@id' => $permalink . '#breadcrumb'];
            }
            $graph[] = $webpage;

            // BreadcrumbList
            if ($has_breadcrumbs) {
                $breadcrumb_list = [];
                foreach ($breadcrumb_items as $i => $item) {
                    $breadcrumb_list[] = [
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => $item['name'],
                        'item'     => $item['url'],
                    ];
                }
                $graph[] = [
                    '@type'           => 'BreadcrumbList',
                    '@id'             => $permalink . '#breadcrumb',
                    'itemListElement' => $breadcrumb_list,
                ];
            }

            // Article
            $article = [
                '@type'            => 'Article',
                '@id'              => $permalink . '#article',
                'isPartOf'         => ['@id' => $permalink],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $permalink,
                ],
                'headline'         => $h1,
                'datePublished'    => get_the_date('c', $post_id),
                'dateModified'     => get_the_modified_date('c', $post_id),
                'author'           => [
                    '@type' => 'Organization',
                    'name'  => $site_name,
                ],
                'publisher'        => ['@id' => $home_url . '#organization'],
            ];
            if ($seo_desc) $article['description'] = $seo_desc;
            if ($img_url) {
                $article['image'] = [
                    '@type' => 'ImageObject',
                    'url'   => $img_url,
                ];
            }
            $graph[] = $article;

            // FAQPage — only when there's actually a FAQ block on the page
            if (!empty($faq_items)) {
                $questions = [];
                foreach ($faq_items as $faq) {
                    $questions[] = [
                        '@type' => 'Question',
                        'name'  => $faq['q'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => $faq['a'],
                        ],
                    ];
                }
                $graph[] = [
                    '@type'      => 'FAQPage',
                    '@id'        => $permalink . '#faq',
                    'isPartOf'   => ['@id' => $permalink],
                    'mainEntity' => $questions,
                ];
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
