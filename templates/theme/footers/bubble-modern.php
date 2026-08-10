<?php
// templates/theme/footers/bubble-modern.php
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-bubble-modern" role="contentinfo">
    <div class="sb-footer-bubble-bubbles">
        <?php for ($i = 0; $i < 64; $i++): 
            $size = 2 + (mt_rand(0, 400) / 100);
            $distance = 10 + (mt_rand(0, 600) / 100);
            $position = mt_rand(0, 10000) / 100;
            $time = 2 + (mt_rand(0, 200) / 100);
            $delay = -1 * (2 + (mt_rand(0, 200) / 100));
        ?>
            <div class="sb-footer-bubble-bubble" style="--size:<?php echo $size; ?>rem; --distance:<?php echo $distance; ?>rem; --position:<?php echo $position; ?>%; --time:<?php echo $time; ?>s; --delay:<?php echo $delay; ?>s;"></div>
        <?php endfor; ?>
    </div>
    
    <div class="sb-footer-bubble-content">
        <div class="sb-footer-bubble-inner">
            <div class="sb-footer-bubble-top">
                <div class="sb-footer-bubble-left">
                    <div class="sb-footer-bubble-brand">
                        <?php if (has_custom_logo()) { 
                            the_custom_logo(); 
                        } else { ?>
                            <div class="sb-footer-bubble-logo-text">
                                <?php echo esc_html(get_bloginfo('name')); ?>
                            </div>
                        <?php } ?>
                    </div>
                    
                    <?php if (get_bloginfo('description')): ?>
                        <p class="sb-footer-bubble-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
                    <?php endif; ?>
                </div>

                <div class="sb-footer-bubble-right">
                    <div class="sb-footer-bubble-columns">
                        <?php if (has_nav_menu('footer')): ?>
                        <div class="sb-footer-bubble-col">
                            <div class="sb-footer-bubble-heading"><?php echo esc_html(sb_t('navigation')); ?></div>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                        <?php endif; ?>

                        <div class="sb-footer-bubble-col">
                            <div class="sb-footer-bubble-heading"><?php echo esc_html(sb_t('site')); ?></div>
                            <ul class="sb-footer-menu">
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                                <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                                <?php if ($sb_privacy_url): ?>
                                    <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <?php if (has_nav_menu('footer-2')): ?>
                        <div class="sb-footer-bubble-col">
                            <div class="sb-footer-bubble-heading"><?php echo esc_html(sb_t('links')); ?></div>
                            <?php wp_nav_menu([
                                'theme_location' => 'footer-2',
                                'container' => false,
                                'menu_class' => 'sb-footer-menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]); ?>
                        </div>
                        <?php endif; ?>

                        <div class="sb-footer-bubble-col">
                            <?php echo do_shortcode('[sb_regulators title_class="sb-footer-bubble-heading" list_class="sb-footer-menu"]'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sb-footer-bubble-bottom">
                <p class="sb-footer-bubble-copyright">
                    © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. 
                    <?php echo esc_html(sb_t('rights_reserved')); ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<svg class="sb-footer-bubble-svg" aria-hidden="true">
    <defs>
        <filter id="sb-bubble-blob">
            <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
            <feColorMatrix in="blur" type="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="blob" />
        </filter>
    </defs>
</svg>

<?php wp_footer(); ?>
</body>
</html>
