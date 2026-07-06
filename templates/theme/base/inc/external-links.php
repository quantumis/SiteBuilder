<?php
/**
 * Site Builder — external link attributes.
 *
 * On singular pages, all <a href="..."> tags pointing to external domains
 * are rewritten to include target="_blank" and rel="nofollow noopener".
 *
 * What counts as external:
 *   - http:// or https:// URLs whose host differs from the current site's host
 *
 * What is left untouched:
 *   - Same-host links (obviously)
 *   - Relative URLs (also same-site)
 *   - Anchor-only links (#section)
 *   - mailto:, tel:, javascript:, ftp: and other non-http schemes
 *
 * Rewriting rules:
 *   - Existing target and rel attributes on external links are replaced —
 *     otherwise duplicate attributes stack up over multiple content processings.
 *   - "noopener" is added along with "nofollow" — it closes the tabnabbing
 *     security hole where the popup can access window.opener of the source page.
 *
 * Hooked at priority 20 so it runs after wpautop (10), our GEO shortcodes (12),
 * and the breadcrumb/articles-grid inserts. That way even links inside those
 * inserted blocks get processed.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_external_links_process')) {

    function sb_external_links_process($content) {
        if (!is_singular() || is_admin() || !is_main_query()) return $content;
        if (empty($content)) return $content;

        $site_host = strtolower((string)parse_url(home_url(), PHP_URL_HOST));
        if ($site_host === '') return $content;

        return preg_replace_callback(
            '#<a\s+([^>]*?)href=(["\'])([^"\']+)\2([^>]*)>#i',
            function ($m) use ($site_host) {
                $before_href = $m[1];
                $quote       = $m[2];
                $url         = $m[3];
                $after_href  = $m[4];

                // Non-http links (anchors, mailto, tel, etc) — pass through untouched
                if ($url === '' || $url[0] === '#') return $m[0];
                if (preg_match('#^(mailto|tel|javascript|ftp|sms|data):#i', $url)) return $m[0];

                // Relative URL — always internal
                if (!preg_match('#^https?://#i', $url)) return $m[0];

                // Absolute — compare hosts
                $link_host = strtolower((string)parse_url($url, PHP_URL_HOST));
                if ($link_host === '' || $link_host === $site_host) return $m[0];

                // External — strip any existing target= and rel= attrs (to avoid
                // duplication), then inject our canonical pair.
                $attrs = $before_href . $after_href;
                $attrs = preg_replace('#\s*\btarget\s*=\s*("[^"]*"|\'[^\']*\'|\S+)#i', '', $attrs);
                $attrs = preg_replace('#\s*\brel\s*=\s*("[^"]*"|\'[^\']*\'|\S+)#i', '', $attrs);
                $attrs = trim($attrs);
                $attrs_prefix = $attrs !== '' ? ' ' . $attrs : '';

                return '<a' . $attrs_prefix . ' href=' . $quote . $url . $quote
                    . ' target="_blank" rel="nofollow noopener">';
            },
            $content
        );
    }
    // Priority 20 — after other content filters so external links inside injected
    // blocks (breadcrumbs, articles grid, similar posts) also get processed.
    add_filter('the_content', 'sb_external_links_process', 20);
}
