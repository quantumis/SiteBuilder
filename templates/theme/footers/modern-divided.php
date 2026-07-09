<?php
/**
 * Footer variant: Modern Divided
 * Three equal columns with clear visual separation. Each column has a heading
 * with a bullet accent. Bottom row: social icons + copyright.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-md" role="contentinfo">
    <div class="sb-footer-md-inner">
        <div class="sb-footer-md-grid">
            <div class="sb-footer-md-col">
                <h2 class="sb-footer-md-heading">
                    <span class="sb-footer-md-bullet"></span>
                    <?php echo esc_html(sb_t('site')); ?>
                </h2>
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                }
                ?>
                <p class="sb-footer-md-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="sb-footer-md-col">
                <h2 class="sb-footer-md-heading">
                    <span class="sb-footer-md-bullet"></span>
                    <?php echo esc_html(sb_t('navigation')); ?>
                </h2>
                <?php
                if (has_nav_menu('footer')) {
                    wp_nav_menu([
                        'theme_location'  => 'footer',
                        'container'       => false,
                        'menu_class'      => 'sb-menu',
                        'depth'           => 1,
                        'fallback_cb'     => false,
                    ]);
                }
                ?>
            </div>
            <div class="sb-footer-md-col">
                <h2 class="sb-footer-md-heading">
                    <span class="sb-footer-md-bullet"></span>
                    Contact
                </h2>
                <p class="sb-footer-md-contact">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html((string)parse_url(home_url(), PHP_URL_HOST)); ?></a>
                </p>
            </div>
        </div>
        <div class="sb-footer-md-copyright">
            © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
