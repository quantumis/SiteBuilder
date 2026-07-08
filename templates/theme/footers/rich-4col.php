<?php
/**
 * Footer variant: Rich 4-column
 * Columns: [brand + tagline] [footer menu] [popular categories] [contact block]
 * Copyright row at the bottom, separated by a thin border.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-rich" role="contentinfo">
    <div class="sb-footer-rich-inner">
        <div class="sb-footer-rich-grid">
            <div class="sb-footer-rich-col sb-footer-rich-brand-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a class="sb-footer-rich-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
                }
                ?>
                <p class="sb-footer-rich-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="sb-footer-rich-col">
                <h2 class="sb-footer-rich-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
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
            <div class="sb-footer-rich-col">
                <h2 class="sb-footer-rich-heading"><?php echo esc_html(sb_t('site')); ?></h2>
                <ul class="sb-footer-rich-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php
                    // Show top-level pages (excluding utility ones)
                    $pages = get_pages([
                        'parent'      => 0,
                        'sort_column' => 'menu_order',
                        'number'      => 6,
                    ]);
                    foreach ($pages as $p) {
                        if ((int)get_post_meta($p->ID, 'fsr_utility', true) === 1) continue;
                        echo '<li><a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
            <div class="sb-footer-rich-col">
                <h2 class="sb-footer-rich-heading">Contact</h2>
                <p class="sb-footer-rich-contact-line">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html((string)parse_url(home_url(), PHP_URL_HOST)); ?></a>
                </p>
            </div>
        </div>
        <div class="sb-footer-rich-copyright">
            © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
