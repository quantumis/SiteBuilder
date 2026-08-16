<?php
// templates/theme/footers/agency-bold.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-agency-bold" role="contentinfo">
    <div class="sb-footer-agency-bold-inner">
        <div class="sb-footer-agency-bold-top">
            <div class="sb-footer-agency-bold-brand">
                <div class="sb-footer-agency-bold-logo">
                    <?php if (has_custom_logo()) { 
                        the_custom_logo(); 
                    } else { 
                        echo '<span class="sb-footer-agency-bold-sitename">' . esc_html(get_bloginfo('name')) . '</span>'; 
                    } ?>
                </div>
                <p class="sb-footer-agency-bold-tagline">
                    <?php 
                    $description = get_bloginfo('description');
                    if ($description) {
                        echo esc_html($description);
                    }
                    ?>
                </p>
            </div>

            <div class="sb-footer-agency-bold-columns">
                <?php if (has_nav_menu('footer')): ?>
                <div class="sb-footer-agency-bold-col">
                    <h3 class="sb-footer-agency-bold-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
                    <?php wp_nav_menu([
                        'theme_location' => 'footer',
                        'container' => false,
                        'menu_class' => 'sb-footer-menu',
                        'depth' => 1,
                        'fallback_cb' => false,
                    ]); ?>
                </div>
                <?php endif; ?>

                <div class="sb-footer-agency-bold-col">
                    <h3 class="sb-footer-agency-bold-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                    <ul class="sb-footer-menu">
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                        <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                        <?php if ($sb_privacy_url): ?>
                            <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (has_nav_menu('footer-2')): ?>
                <div class="sb-footer-agency-bold-col">
                    <h3 class="sb-footer-agency-bold-heading"><?php echo esc_html(sb_t('links')); ?></h3>
                    <?php wp_nav_menu([
                        'theme_location' => 'footer-2',
                        'container' => false,
                        'menu_class' => 'sb-footer-menu',
                        'depth' => 1,
                        'fallback_cb' => false,
                    ]); ?>
                </div>
                <?php endif; ?>

                <div class="sb-footer-agency-bold-col">
                    <?php echo do_shortcode('[sb_regulators title_class="sb-footer-agency-bold-heading" list_class="sb-footer-menu"]'); ?>
                </div>
            </div>
        </div>

        <div class="sb-footer-agency-bold-bottom">
            <p class="sb-footer-agency-bold-copyright">
                © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
            </p>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
