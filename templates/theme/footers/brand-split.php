<?php
/**
 * Footer variant: Brand Split
 * Large brand statement on the left, navigation and site links on the right.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-brand-split" role="contentinfo">
    <div class="sb-footer-bs-inner">
        <div class="sb-footer-bs-main">
            <div class="sb-footer-bs-brand">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-bs-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                $description = get_bloginfo('description');
                if ($description) {
                    echo '<p class="sb-footer-bs-description">' . esc_html($description) . '</p>';
                }
                ?>
            </div>

            <div class="sb-footer-bs-links">
                <div class="sb-footer-bs-col">
                    <?php echo do_shortcode('[sb_regulators
                        title_class="sb-footer-bs-heading"
                        list_class="sb-footer-bs-list"
                    ]'); ?>
                </div>
                
                <div class="sb-footer-bs-col">
                    <h2 class="sb-footer-bs-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
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
            </div>
        </div>

        <div class="sb-footer-bs-bottom">
            <span>© <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?></span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
