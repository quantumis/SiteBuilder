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
     * Custom canonical that accounts for content pagination (?page=N for
     * pages with <!--nextpage--> splits and /page/N/ for paginated archives).
     * The default WP rel_canonical strips the page parameter, which makes
     * paginated content all point at page 1 and harms SEO.
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

        // Classify the page for schema type selection
        $is_utility = (int)get_post_meta($post_id, 'fsr_utility', true) === 1;
        $is_grid    = (int)get_post_meta($post_id, 'fsr_articles_grid', true) === 1;
        $is_article = !$is_utility && !$is_grid;

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

        // --- JSON-LD schema graph ---
        $graph = [];

        // Organization
        $org = [
            '@type' => 'Organization',
            '@id'   => home_url('/#organization'),
            'name'  => $site_name,
            'url'   => home_url('/'),
        ];
        if ($logo_url) $org['logo'] = ['@type' => 'ImageObject', 'url' => $logo_url];
        $graph[] = $org;

        // WebSite
        $graph[] = [
            '@type'      => 'WebSite',
            '@id'        => home_url('/#website'),
            'url'        => home_url('/'),
            'name'       => $site_name,
            'publisher'  => ['@id' => home_url('/#organization')],
            'inLanguage' => $locale,
        ];

        // Breadcrumbs (if our breadcrumbs module is loaded)
        $breadcrumb_items = [];
        if (function_exists('get_my_breadcrumbs_items')) {
            foreach (get_my_breadcrumbs_items() as $i => $item) {
                $breadcrumb_items[] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $item['name'],
                    'item'     => $item['url'],
                ];
            }
        }

        // WebPage / CollectionPage
        $webpage_type = $is_grid ? 'CollectionPage' : 'WebPage';
        $webpage = [
            '@type'         => $webpage_type,
            '@id'           => $permalink . '#webpage',
            'url'           => $permalink,
            'name'          => $seo_title,
            'isPartOf'      => ['@id' => home_url('/#website')],
            'datePublished' => get_the_date('c', $post_id),
            'dateModified'  => get_the_modified_date('c', $post_id),
            'inLanguage'    => $locale,
        ];
        if ($img_url) {
            $webpage['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $img_url];
        }
        if ($breadcrumb_items) {
            $webpage['breadcrumb'] = ['@id' => $permalink . '#breadcrumb'];
        }
        $graph[] = $webpage;

        // BreadcrumbList
        if ($breadcrumb_items) {
            $graph[] = [
                '@type'           => 'BreadcrumbList',
                '@id'             => $permalink . '#breadcrumb',
                'itemListElement' => $breadcrumb_items,
            ];
        }

        // Article — only for actual content pages
        if ($is_article) {
            $content_plain = wp_strip_all_tags((string)get_post_field('post_content', $post_id));
            // Unicode-aware word counter: \p{L} covers Latin/Cyrillic/Greek/etc.
            $words = preg_split('~[^\p{L}\p{N}]+~u', $content_plain, -1, PREG_SPLIT_NO_EMPTY);
            $word_count = is_array($words) ? count($words) : 0;

            $article = [
                '@type'            => 'Article',
                '@id'              => $permalink . '#article',
                'headline'         => $social,
                'author'           => ['@id' => home_url('/#organization')],
                'publisher'        => ['@id' => home_url('/#organization')],
                'datePublished'    => get_the_date('c', $post_id),
                'dateModified'     => get_the_modified_date('c', $post_id),
                'mainEntityOfPage' => ['@id' => $permalink . '#webpage'],
                'inLanguage'       => $locale,
                'wordCount'        => $word_count,
            ];
            if ($img_url) {
                $article['image'] = ['@type' => 'ImageObject', 'url' => $img_url];
            }
            $graph[] = $article;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
