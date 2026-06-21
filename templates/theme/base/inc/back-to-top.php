<?php
/**
 * Site Builder — back-to-top button.
 *
 * Adds a fixed-position scroll-to-top button to every page. Pure CSS+JS,
 * no external dependencies. Renders via wp_footer so it sits at the
 * very end of the page markup.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('add_back_to_top_button')) {
    function add_back_to_top_button() {
        wp_register_style('back-to-top-style', false);
        wp_enqueue_style('back-to-top-style');

        $inline_css = '
            .back-to-top {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 50px;
                height: 50px;
                background: #000;
                color: white;
                border: none;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 9999;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                font-size: 24px;
                font-weight: bold;
            }
            .back-to-top:active {
                transform: translateY(0);
            }
            .back-to-top.show {
                opacity: 1;
                visibility: visible;
            }
            @media (max-width: 768px) {
                .back-to-top {
                    bottom: 20px;
                    right: 20px;
                    width: 45px;
                    height: 45px;
                    font-size: 20px;
                }
            }
        ';

        wp_add_inline_style('back-to-top-style', $inline_css);

        $label = function_exists('sb_t') ? sb_t('back_to_top') : 'Back to top';
        ?>
        <button type="button" id="back-to-top" class="back-to-top" aria-label="<?php echo esc_attr($label); ?>">↑</button>
        <script>
            (function() {
                var btn = document.getElementById('back-to-top');
                if (!btn) return;
                function toggleButton() {
                    if (window.scrollY > 300) {
                        btn.classList.add('show');
                    } else {
                        btn.classList.remove('show');
                    }
                }
                window.addEventListener('scroll', toggleButton);
                toggleButton();
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            })();
        </script>
        <?php
    }
    if (!has_action('wp_footer', 'add_back_to_top_button')) {
        add_action('wp_footer', 'add_back_to_top_button');
    }
}
