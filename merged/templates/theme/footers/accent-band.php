<?php
/**
 * Footer variant: Accent Band
 * Bold accent intro band followed by a minimal navigation row.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-accent-band" role="contentinfo">
    <div class="sb-footer-ab-accent">
        <div class="sb-footer-ab-accent-inner">
            <div class="sb-footer-ab-brand">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-ab-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                ?>
            </div>
            <?php if (get_bloginfo('description')): ?>
                <p class="sb-footer-ab-description"><?php echo esc_html(get_bloginfo('description')); ?></p>
            <?php endif; ?>
        </div>
    </div>

<div class="sb-footer-ab-base">
    <div class="sb-footer-ab-base-inner">

        <div class="sb-footer-ab-columns">

            <div class="sb-footer-ab-col">
                <?php echo do_shortcode('[sb_regulators
                    title_class="sb-footer-ab-heading"
                    list_class="sb-footer-ab-nav"
                ]'); ?>
            </div>

            <div class="sb-footer-ab-col">
                <h3 class="sb-footer-ab-heading">
                    <?php echo esc_html(sb_t('navigation')); ?>
                </h3>

                <nav class="sb-footer-ab-nav" aria-label="<?php echo esc_attr(sb_t('footer_menu')); ?>">
                    <?php
                    if (has_nav_menu('footer')) {
                        wp_nav_menu([
                            'theme_location' => 'footer',
                            'container'      => false,
                            'menu_class'     => 'sb-menu',
                            'depth'          => 1,
                            'fallback_cb'    => false,
                        ]);
                    }
                    ?>
                </nav>
            </div>

        </div>

        <div class="sb-footer-ab-meta">
            <span>
                © <?php echo esc_html(date_i18n('Y')); ?>
                <?php echo esc_html(get_bloginfo('name')); ?>.
                <?php echo esc_html(sb_t('rights_reserved')); ?>
            </span>
        </div>

    </div>
</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
