<?php
// templates/theme/footers/terminal-retro.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-terminal" role="contentinfo">
    <div class="sb-footer-terminal-inner">
        <div class="sb-footer-terminal-header">
            <div class="sb-footer-terminal-dots">
                <div class="sb-footer-terminal-dot"></div>
                <div class="sb-footer-terminal-dot"></div>
                <div class="sb-footer-terminal-dot"></div>
            </div>
            <h2 class="sb-footer-terminal-title"><?php echo esc_html(get_bloginfo('name')); ?> — Terminal v1.0</h2>
        </div>

        <div class="sb-footer-terminal-box">
            <div class="sb-footer-terminal-content">
                <div class="sb-footer-terminal-left">
                    <div class="sb-footer-terminal-prompt">system.info</div>
                    
                    <div class="sb-footer-terminal-brand">
                        <?php if (has_custom_logo()) { 
                            the_custom_logo(); 
                        } else { ?>
                            <div class="sb-footer-terminal-logo-text">
                                $ <?php echo esc_html(get_bloginfo('name')); ?><span class="sb-footer-terminal-cursor"></span>
                            </div>
                        <?php } ?>
                        
                        <?php if (get_bloginfo('description')): ?>
                            <p class="sb-footer-terminal-tagline">
                                > <?php echo esc_html(get_bloginfo('description')); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sb-footer-terminal-right">
                    <div class="sb-footer-terminal-columns">
                        <?php if (has_nav_menu('footer')): ?>
                        <div class="sb-footer-terminal-col">
                            <h3 class="sb-footer-terminal-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                        <?php endif; ?>

                        <div class="sb-footer-terminal-col">
                            <h3 class="sb-footer-terminal-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                            <ul class="sb-footer-menu">
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                                <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                                <?php if ($sb_privacy_url): ?>
                                    <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <?php if (has_nav_menu('footer-2')): ?>
                        <div class="sb-footer-terminal-col">
                            <h3 class="sb-footer-terminal-heading"><?php echo esc_html(sb_t('links')); ?></h3>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer-2',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                        <?php endif; ?>

                        <div class="sb-footer-terminal-col">
                            <?php echo do_shortcode('[sb_regulators title_class="sb-footer-terminal-heading" list_class="sb-footer-menu"]'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sb-footer-terminal-bottom">
                <p class="sb-footer-terminal-copyright">
                    © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?> | Process completed successfully.
                </p>
            </div>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
