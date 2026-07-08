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

        // Title: manual override → post_title
        $seo_title = trim((string)get_post_meta($post_id, '_custom_seo_title', true));
        if ($seo_title === '') $seo_title = get_the_title($post_id);

        // Description: manual override → post_excerpt → auto-generated from content
        $seo_desc = trim((string)get_post_meta($post_id, '_custom_seo_desc', true));
        if ($seo_desc === '') {
            $seo_desc = (string)$post->post_excerpt;
            if ($seo_desc === '') {
                $seo_desc = wp_strip_all_tags(get_the_excerpt($post_id));
            }
        }
        $seo_desc = wp_trim_words($seo_desc, 30, '');

        // Social headline: manual → fsr_headline (FSR frontmatter) → seo_title
        $social = trim((string)get_post_meta($post_id, '_custom_seo_headline', true));
        if ($social === '') $social = trim((string)get_post_meta($post_id, 'fsr_headline', true));
        if ($social === '') $social = $seo_title;

        // Resolve shortcodes ([sb_year], [sb_date]) in any of the fields —
        // meta values are read raw and don't pass through the_title filters.
        // Guarded on '[sb_' prefix so plain text with brackets isn't touched.
        foreach ([&$seo_title, &$seo_desc, &$social] as &$v) {
            if (is_string($v) && strpos($v, '[sb_') !== false) $v = do_shortcode($v);
        }
        unset($v);

        // Hero image: featured thumbnail → fsr_headimg from frontmatter
        $img_url = get_the_post_thumbnail_url($post_id, 'full');
        if (!$img_url) {
            $img_url = trim((string)get_post_meta($post_id, 'fsr_headimg', true));
            // fsr_headimg is a relative path like "IMAGES/foo.webp" — useless
            // as an absolute URL. Only emit it if it's already absolute.
            if ($img_url && !preg_match('#^https?://#i', $img_url)) $img_url = '';
        }

        // Logo for Organization schema
        $logo_url = '';
        $logo_id  = (int)get_theme_mod('custom_logo');
        if ($logo_id) $logo_url = (string)wp_get_attachment_image_url($logo_id, 'full');

        // Classify the page for schema type selection
        $is_utility = (int)get_post_meta($post_id, 'fsr_utility', true) === 1;
        $is_grid    = (int)get_post_meta($post_id, 'fsr_articles_grid', true) === 1;
        $is_article = !$is_utility && !$is_grid;

        // --- HEAD output: title / description ---
        echo "\n<title>" . esc_html($seo_title) . "</title>\n";
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
        if ($seo_desc !== '') {
            echo '<meta property="og:description" content="' . esc_attr($seo_desc) . "\" />\n";
        }
        if ($img_url) {
            echo '<meta property="og:image" content="' . esc_url($img_url) . "\" />\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($img_url) . "\" />\n";
        } else {
            echo '<meta name="twitter:card" content="summary" />' . "\n";
        }
        echo '<meta name="twitter:title" content="' . esc_attr($social) . "\" />\n";
        if ($seo_desc !== '') {
            echo '<meta name="twitter:description" content="' . esc_attr($seo_desc) . "\" />\n";
        }

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
