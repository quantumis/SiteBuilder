<?php
// templates/theme/footers/hexagon-grid.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-hexagon-grid" role="contentinfo">
    <div class="sb-footer-hexagon-bg"></div>
    
    <div class="sb-footer-hexagon-inner">
        <div class="sb-footer-hexagon-top">
            <div class="sb-footer-hexagon-brand">
                <div class="sb-footer-hexagon-brand-wrapper">
                    <?php if (has_custom_logo()) { 
                        the_custom_logo(); 
                    } else { ?>
                        <div class="sb-footer-hexagon-logo-text">
                            <?php echo esc_html(get_bloginfo('name')); ?>
                        </div>
                    <?php } ?>
                    
                    <?php if (get_bloginfo('description')): ?>
                        <p class="sb-footer-hexagon-tagline">
                            <?php echo esc_html(get_bloginfo('description')); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sb-footer-hexagon-right">
                <div class="sb-footer-hexagon-columns">
                    <?php if (has_nav_menu('footer')): ?>
                    <div class="sb-footer-hexagon-col">
                        <div class="sb-footer-hexagon-shape">
                            <h3 class="sb-footer-hexagon-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="sb-footer-hexagon-col">
                        <div class="sb-footer-hexagon-shape">
                            <h3 class="sb-footer-hexagon-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                            <ul class="sb-footer-menu">
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                                <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                                <?php if ($sb_privacy_url): ?>
                                    <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <?php if (has_nav_menu('footer-2')): ?>
                    <div class="sb-footer-hexagon-col">
                        <div class="sb-footer-hexagon-shape">
                            <h3 class="sb-footer-hexagon-heading"><?php echo esc_html(sb_t('links')); ?></h3>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer-2',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="sb-footer-hexagon-col">
                        <div class="sb-footer-hexagon-shape">
                            <?php echo do_shortcode('[sb_regulators title_class="sb-footer-hexagon-heading" list_class="sb-footer-menu"]'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sb-footer-hexagon-bottom">
            <p class="sb-footer-hexagon-copyright">
                © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. 
                <?php echo esc_html(sb_t('rights_reserved')); ?>
            </p>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
