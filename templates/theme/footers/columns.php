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
                    <h4 class="sb-footer-heading"><?php echo esc_html(sb_t('navigation')); ?></h4>
                    <nav aria-label="<?php echo esc_attr(sb_t('footer_menu')); ?>">
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
                <h4 class="sb-footer-heading"><?php echo esc_html(sb_t('site')); ?></h4>
                <ul class="sb-footer-info-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                    <?php if ($sb_privacy_url) : ?>
                        <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="sb-footer-copyright">
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
