<?php
/**
 * Site Builder — 404 page styles.
 *
 * Registers inline CSS for the 404 page. Kept out of the 404.php template
 * because <style> tags in body are unreliable (some CSS loaders and
 * validators refuse them) and because doing it via wp_add_inline_style
 * guarantees the styles are in <head> before the page renders.
 *
 * Uses the theme's --sb-color-* variables so the page automatically matches
 * whichever color scheme is active. Loaded on every request (the CSS is
 * ~1 KB and gzips to almost nothing — no point conditional-loading).
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_404_styles')) {
    function sb_404_styles() {
        if (is_admin()) return;
        wp_register_style('sb-404', false);
        wp_enqueue_style('sb-404');
        $css = <<<CSS
            .sb-404-page {
                min-height: 60vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 60px 20px;
            }
            .sb-404-inner {
                max-width: 500px;
                text-align: center;
            }
            .sb-404-code {
                font-family: var(--sb-font-heading, inherit);
                font-size: clamp(96px, 20vw, 180px);
                font-weight: 800;
                line-height: 1;
                letter-spacing: -0.04em;
                background: linear-gradient(135deg,
                    var(--sb-color-link, #2563eb),
                    var(--sb-color-accent, var(--sb-color-link, #2563eb)));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                color: var(--sb-color-link, #2563eb); /* fallback for browsers that don't support bg-clip:text */
                margin-bottom: 8px;
                opacity: 0.95;
            }
            .sb-404-title {
                margin: 0 0 12px;
                font-size: clamp(24px, 4vw, 32px);
                font-weight: 700;
                color: var(--sb-color-text, #111);
                letter-spacing: -0.01em;
                line-height: 1.2;
            }
            .sb-404-message {
                margin: 0 0 32px;
                font-size: 16px;
                line-height: 1.6;
                color: var(--sb-color-muted, #6b7280);
            }
            .sb-404-action {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: var(--sb-color-link, #2563eb);
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 15px;
                transition: opacity 0.15s, transform 0.15s;
            }
            .sb-404-action:hover {
                opacity: 0.9;
                transform: translateX(-2px);
                text-decoration: none;
                color: #fff;
            }
            .sb-404-action-arrow {
                transition: transform 0.15s;
                display: inline-block;
            }
            .sb-404-action:hover .sb-404-action-arrow {
                transform: translateX(-3px);
            }
CSS;
        wp_add_inline_style('sb-404', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_404_styles');
}
