<?php
/**
 * Footer variant: Minimal 3
 * Three understated columns with generous whitespace and light typography.
 * Columns: [brand + tagline] [navigation] [pages]. Centered copyright below.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-min" role="contentinfo">
    <div class="sb-footer-min-inner">
        <div class="sb-footer-min-grid">
            <div class="sb-footer-min-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-min-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                ?>
                <p class="sb-footer-min-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="sb-footer-min-col">
                <h2 class="sb-footer-min-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
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
            <div class="sb-footer-min-col">
                <h2 class="sb-footer-min-heading"><?php echo esc_html(sb_t('site')); ?></h2>
                <ul class="sb-footer-min-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php
                    $pages = get_pages(['parent' => 0, 'sort_column' => 'menu_order', 'number' => 5]);
                    foreach ($pages as $p) {
                        if ((int)get_post_meta($p->ID, 'fsr_utility', true) === 1) continue;
                        echo '<li><a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        <div class="sb-footer-min-copyright">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
