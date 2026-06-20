<?php
/**
 * Footer variant: Columns.
 * Three-column layout: site description (left), menu (center), info (right).
 * Bottom row holds copyright.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer sb-footer-columns">
    <div class="sb-footer-inner">
        <div class="sb-footer-grid">
            <div class="sb-footer-col sb-footer-about">
                <div class="sb-footer-brand">
                    <?php
                    if (function_exists('the_custom_logo') && has_custom_logo()) {
                        the_custom_logo();
                    } else {
                        echo '<span class="sb-footer-brand-text">' . esc_html(get_bloginfo('name')) . '</span>';
                    }
                    ?>
                </div>
                <?php $sb_tagline = get_bloginfo('description'); ?>
                <?php if ($sb_tagline) : ?>
                    <p class="sb-footer-tagline"><?php echo esc_html($sb_tagline); ?></p>
                <?php endif; ?>
            </div>

            <?php if (has_nav_menu('footer')) : ?>
                <div class="sb-footer-col sb-footer-menu-col">
                    <h4 class="sb-footer-heading"><?php esc_html_e('Навигация', 'site-builder'); ?></h4>
                    <nav aria-label="<?php esc_attr_e('Меню в подвале', 'site-builder'); ?>">
                        <?php
                        wp_nav_menu([
                            'theme_location' => 'footer',
                            'container'      => false,
                            'menu_class'     => 'sb-menu sb-menu-footer',
                            'depth'          => 1,
                        ]);
                        ?>
                    </nav>
                </div>
            <?php endif; ?>

            <div class="sb-footer-col sb-footer-info">
                <h4 class="sb-footer-heading"><?php esc_html_e('Сайт', 'site-builder'); ?></h4>
                <ul class="sb-footer-info-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Главная', 'site-builder'); ?></a></li>
                    <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                    <?php if ($sb_privacy_url) : ?>
                        <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php esc_html_e('Политика конфиденциальности', 'site-builder'); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="sb-footer-copyright">
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php esc_html_e('Все права защищены.', 'site-builder'); ?>
        </div>
    </div>
</footer>
