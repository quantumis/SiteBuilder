<?php
/**
 * Footer variant: CTA Band
 * A prominent accent call-to-action band on top (heading + button linking home),
 * then three columnar link blocks below (Navigation / Pages / Legal).
 * Copyright row at the very bottom.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-cta" role="contentinfo">
    <div class="sb-footer-cta-inner">
        <div class="sb-footer-cta-band">
            <div class="sb-footer-cta-band-text">
                <h2 class="sb-footer-cta-title"><?php echo esc_html(get_bloginfo('name')); ?></h2>
                <p class="sb-footer-cta-desc"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <a class="sb-footer-cta-btn" href="<?php echo esc_url(home_url('/')); ?>">
                <?php echo esc_html(sb_t('home')); ?>
            </a>
        </div>
        <div class="sb-footer-cta-grid">
            <div class="sb-footer-cta-col">
                <h3 class="sb-footer-cta-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
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
            <div class="sb-footer-cta-col">
                <h3 class="sb-footer-cta-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-cta-list">
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
            <div class="sb-footer-cta-col">
                <h3 class="sb-footer-cta-heading"><?php echo esc_html(sb_t('legal')); ?></h3>
                <ul class="sb-footer-cta-list">
                    <?php
                    $utility_pages = get_pages(['parent' => 0, 'sort_column' => 'menu_order']);
                    foreach ($utility_pages as $p) {
                        if ((int)get_post_meta($p->ID, 'fsr_utility', true) !== 1) continue;
                        if ((int)get_post_meta($p->ID, 'fsr_articles_grid', true) === 1) continue;
                        echo '<li><a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        <div class="sb-footer-cta-copyright">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
