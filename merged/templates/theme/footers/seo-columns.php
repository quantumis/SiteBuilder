<?php
/**
 * Footer variant: SEO Columns
 * Dense, link-rich footer: [brand + tagline] [footer menu] [pages]
 * [legal] [contact]. Great for content/affiliate sites with lots of internal
 * links. Copyright row at the bottom, separated by a thin border.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-seo">
    <div class="sb-footer-seo-inner">
        <div class="sb-footer-seo-grid">
            <div class="sb-footer-seo-col sb-footer-seo-brand-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a class="sb-footer-seo-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
                }
                ?>
                <p class="sb-footer-seo-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="sb-footer-seo-col">
                <h3 class="sb-footer-seo-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
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
            <div class="sb-footer-seo-col">
                <h3 class="sb-footer-seo-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-seo-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php
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
            <div class="sb-footer-seo-col">
            
                <?php echo do_shortcode('[sb_regulators
                    title_class="sb-footer-newvariant-heading sb-footer-seo-heading"
                    list_class="sb-footer-newvariant-list sb-footer-seo-list"
                ]'); ?>
            
            </div>
            <div class="sb-footer-seo-col">
                <h3 class="sb-footer-seo-heading"><?php echo esc_html(sb_t('contact')); ?></h3>
                <p class="sb-footer-seo-contact-line">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html((string)parse_url(home_url(), PHP_URL_HOST)); ?></a>
                </p>
            </div>
        </div>
        <div class="sb-footer-seo-copyright">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
