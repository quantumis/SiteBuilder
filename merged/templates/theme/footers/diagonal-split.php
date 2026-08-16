<?php
// templates/theme/footers/diagonal-split.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-diagonal-split" role="contentinfo">
    <div class="sb-footer-diagonal-split-bg"></div>
    
    <div class="sb-footer-diagonal-split-inner">
        <div class="sb-footer-diagonal-split-left">
            <div class="sb-footer-diagonal-split-brand">
                <?php if (has_custom_logo()) { 
                    the_custom_logo(); 
                } else { ?>
                    <div class="sb-footer-diagonal-split-logo-text">
                        <?php echo esc_html(get_bloginfo('name')); ?>
                    </div>
                <?php } ?>
            </div>
            
            <?php if (get_bloginfo('description')): ?>
                <p class="sb-footer-diagonal-split-tagline">
                    <?php echo esc_html(get_bloginfo('description')); ?>
                </p>
            <?php endif; ?>
            
            <div class="sb-footer-diagonal-split-copyright">
                <p>© <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?></p>
                <p><?php echo esc_html(sb_t('rights_reserved')); ?></p>
            </div>
        </div>

        <div class="sb-footer-diagonal-split-right">
            <?php if (has_nav_menu('footer')): ?>
            <div class="sb-footer-diagonal-split-col">
                <h3 class="sb-footer-diagonal-split-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
                <?php wp_nav_menu([
                    'theme_location' => 'footer',
                    'container' => false,
                    'menu_class' => 'sb-footer-menu',
                    'depth' => 1,
                    'fallback_cb' => false,
                ]); ?>
            </div>
            <?php endif; ?>

            <div class="sb-footer-diagonal-split-col">
                <h3 class="sb-footer-diagonal-split-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-menu">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                    <?php if ($sb_privacy_url): ?>
                        <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if (has_nav_menu('footer-2')): ?>
            <div class="sb-footer-diagonal-split-col">
                <h3 class="sb-footer-diagonal-split-heading"><?php echo esc_html(sb_t('links')); ?></h3>
                <?php wp_nav_menu([
                    'theme_location' => 'footer-2',
                    'container' => false,
                    'menu_class' => 'sb-footer-menu',
                    'depth' => 1,
                    'fallback_cb' => false,
                ]); ?>
            </div>
            <?php endif; ?>

            <div class="sb-footer-diagonal-split-col">
                <?php echo do_shortcode('[sb_regulators title_class="sb-footer-diagonal-split-heading" list_class="sb-footer-menu"]'); ?>
            </div>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
