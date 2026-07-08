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
                <div class="sb-footer-md-social" aria-label="Social">
                    <a href="#" class="sb-footer-md-social-link" aria-label="Twitter">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 3H21l-6.7 7.7L22 21h-6.6l-5.2-6.8L4.3 21H2.2l7.2-8.3L2 3h6.8l4.7 6.2L18.9 3Zm-1.1 16.4h1.5L7 4.5H5.4l12.4 14.9Z"/></svg>
                    </a>
                    <a href="#" class="sb-footer-md-social-link" aria-label="Facebook">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H8v-3h2.4V9.8c0-2.4 1.4-3.7 3.6-3.7 1 0 2.1.2 2.1.2v2.3h-1.2c-1.2 0-1.6.7-1.6 1.5V12h2.7l-.4 3h-2.3v7A10 10 0 0 0 22 12Z"/></svg>
                    </a>
                    <a href="#" class="sb-footer-md-social-link" aria-label="Instagram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2 0 1.8.3 2.2.4.6.2 1 .5 1.4 1s.7.9 1 1.4c.2.4.4 1 .4 2.2 0 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c0 1.2-.3 1.8-.4 2.2-.2.6-.5 1-1 1.4s-.9.7-1.4 1c-.4.2-1 .4-2.2.4-1.2 0-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2 0-1.8-.3-2.2-.4-.6-.2-1-.5-1.4-1s-.7-.9-1-1.4c-.2-.4-.4-1-.4-2.2 0-1.2-.1-1.6-.1-4.8s0-3.6.1-4.8c0-1.2.3-1.8.4-2.2.2-.6.5-1 1-1.4s.9-.7 1.4-1c.4-.2 1-.4 2.2-.4 1.2 0 1.6-.1 4.8-.1M12 0C8.7 0 8.3 0 7.1.1 5.8.1 5 .3 4.2.6c-.8.3-1.5.7-2.2 1.4C1.3 2.7.9 3.4.6 4.2.3 5 .1 5.8.1 7.1 0 8.3 0 8.7 0 12s0 3.7.1 4.9c0 1.3.3 2.1.5 2.9.3.8.7 1.5 1.4 2.2.7.7 1.4 1.1 2.2 1.4.8.3 1.6.5 2.9.5C8.3 24 8.7 24 12 24s3.7 0 4.9-.1c1.3 0 2.1-.3 2.9-.5.8-.3 1.5-.7 2.2-1.4.7-.7 1.1-1.4 1.4-2.2.3-.8.5-1.6.5-2.9 0-1.2.1-1.6.1-4.9s0-3.7-.1-4.9c0-1.3-.3-2.1-.5-2.9-.3-.8-.7-1.5-1.4-2.2C21.3 1.3 20.6.9 19.8.6c-.8-.3-1.6-.5-2.9-.5C15.7 0 15.3 0 12 0Zm0 5.8a6.2 6.2 0 1 0 0 12.4 6.2 6.2 0 0 0 0-12.4Zm0 10.2a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm6.4-11.9a1.4 1.4 0 1 0 0 2.9 1.4 1.4 0 0 0 0-2.9Z"/></svg>
                    </a>
                </div>
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
