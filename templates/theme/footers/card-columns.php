<?php
/**
 * Footer variant: Card Columns
 * Three columns, each wrapped in a bordered "card" with subtle elevation.
 * Columns: [brand + tagline] [navigation] [contact + social icons].
 * Copyright row at the very bottom.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-card" role="contentinfo">
    <div class="sb-footer-card-inner">
        <div class="sb-footer-card-grid">
            <div class="sb-footer-card-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-card-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                ?>
                <p class="sb-footer-card-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="sb-footer-card-col">
                <h2 class="sb-footer-card-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
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
            <div class="sb-footer-card-col">
                <h2 class="sb-footer-card-heading"><?php echo esc_html(sb_t('contact')); ?></h2>
                <p class="sb-footer-card-contact">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html((string)parse_url(home_url(), PHP_URL_HOST)); ?></a>
                </p>
            </div>
        </div>
        <div class="sb-footer-card-copyright">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
