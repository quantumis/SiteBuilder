<?php
/**
 * Footer variant: Brand Statement
 * Large left column = brand statement (logo + description + domain link).
 * Three narrower link columns to the right (Navigation / Pages / Legal).
 * Copyright row at the very bottom.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-bs">
    <div class="sb-footer-bs-inner">
        <div class="sb-footer-bs-grid">
            <div class="sb-footer-bs-brand-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-bs-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                ?>
                <p class="sb-footer-bs-desc"><?php echo esc_html(get_bloginfo('description')); ?></p>
                <a class="sb-footer-bs-domain" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html((string)parse_url(home_url(), PHP_URL_HOST)); ?>
                </a>
            </div>
            <div class="sb-footer-bs-col">
                <h3 class="sb-footer-bs-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
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
            <div class="sb-footer-bs-col">
                <h3 class="sb-footer-bs-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-bs-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php
                    $pages = get_pages(['parent' => 0, 'sort_column' => 'menu_order', 'number' => 4]);
                    foreach ($pages as $p) {
                        if ((int)get_post_meta($p->ID, 'fsr_utility', true) === 1) continue;
                        echo '<li><a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
           <div class="sb-footer-newvariant-col">
                <?php echo do_shortcode('[sb_regulators
                    title_class="sb-footer-newvariant-heading sb-footer-bs-heading"
                    list_class="sb-footer-newvariant-list sb-footer-bs-list "
                ]'); ?>
            </div>
        </div>
        <div class="sb-footer-bs-copyright">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
