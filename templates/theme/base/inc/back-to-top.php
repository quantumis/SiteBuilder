<?php
/**
 * Site Builder — back-to-top button.
 *
 * The button hooks two callbacks:
 *
 *   1. `wp_enqueue_scripts` (fires while <head> is being built) — registers
 *      the fixed-position CSS. Doing this in wp_footer would be too late —
 *      inline styles must be attached to a stylesheet that WordPress emits
 *      before </head>, otherwise they get silently dropped and the button
 *      renders as an unstyled inline element below the footer.
 *
 *   2. `wp_footer` (fires just before </body>) — outputs the actual button
 *      markup and the scroll-listener script.
 *
 * Colors use the theme's CSS variables (--sb-color-*) so the button matches
 * whichever color scheme is active.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_back_to_top_styles')) {
    function sb_back_to_top_styles() {
        if (is_admin()) return;
        wp_register_style('sb-back-to-top', false);
        wp_enqueue_style('sb-back-to-top');
        $css = <<<CSS
            .sb-back-to-top {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 48px;
                height: 48px;
                background: var(--sb-color-text, #111);
                color: var(--sb-color-bg, #fff);
                border: none;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transform: translateY(8px);
                transition: opacity 0.25s, visibility 0.25s, transform 0.25s, background 0.15s;
                z-index: 9999;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
                font-size: 22px;
                font-weight: 700;
                line-height: 1;
                padding: 0;
            }
            .sb-back-to-top:hover {
                opacity: 1;
                filter: brightness(1.1);
            }
            .sb-back-to-top.sb-btt-show {
                opacity: 0.85;
                visibility: visible;
                transform: translateY(0);
            }
            .sb-back-to-top.sb-btt-show:hover { opacity: 1; }
            @media (max-width: 768px) {
                .sb-back-to-top {
                    bottom: 20px;
                    right: 20px;
                    width: 44px;
                    height: 44px;
                    font-size: 20px;
                }
            }
CSS;
        wp_add_inline_style('sb-back-to-top', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_back_to_top_styles');
}

if (!function_exists('sb_back_to_top_render')) {
    function sb_back_to_top_render() {
        if (is_admin()) return;
        $label = function_exists('sb_t') ? sb_t('back_to_top') : 'Back to top';
        ?>
        <button type="button" id="sb-back-to-top" class="sb-back-to-top" aria-label="<?php echo esc_attr($label); ?>">↑</button>
        <script>
            (function () {
                var btn = document.getElementById('sb-back-to-top');
                if (!btn) return;
                function toggle() {
                    if (window.scrollY > 300) btn.classList.add('sb-btt-show');
                    else btn.classList.remove('sb-btt-show');
                }
                window.addEventListener('scroll', toggle, { passive: true });
                toggle();
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            })();
        </script>
        <?php
    }
    add_action('wp_footer', 'sb_back_to_top_render');
}
