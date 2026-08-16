<?php
/**
 * Footer variant: Boxed Directory
 * Footer content placed inside a rounded bordered card.
 *
 * Structure:
 * 1. Navigation — WordPress footer menu
 * 2. Site       — root pages
 * 3. Articles   — child pages of /articles/
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<footer class="sb-footer-boxed-directory" role="contentinfo">
    <div class="sb-footer-bd-card">
        <!-- Brand / Description -->
        <div class="sb-footer-bd-head">
            <div class="sb-footer-bd-brand">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-bd-name">'
                        . esc_html(get_bloginfo('name'))
                        . '</div>';
                }
                ?>
            </div>

            <?php if (get_bloginfo('description')): ?>
                <p class="sb-footer-bd-description">
                    <?php echo esc_html(get_bloginfo('description')); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Footer Directory -->
        <div class="sb-footer-bd-directory">
            <!-- Navigation -->
            <div class="sb-footer-bd-col">
                <h2 class="sb-footer-bd-heading">
                    <?php echo esc_html(sb_t('navigation')); ?>
                </h2>
                <?php
                if (has_nav_menu('footer')) {
                    wp_nav_menu([
                        'theme_location' => 'footer',
                        'container'      => false,
                        'menu_class'     => 'sb-menu',
                        'depth'          => 1,
                        'fallback_cb'    => false,
                    ]);
                }
                ?>
            </div>

            <!-- Site Pages -->
            <div class="sb-footer-bd-col">
                <h2 class="sb-footer-bd-heading">
                    <?php echo esc_html(sb_t('site')); ?>
                </h2>
                <ul class="sb-footer-bd-list">
                    <li>
                        <a href="<?php echo esc_url(home_url('/')); ?>">
                            <?php echo esc_html(sb_t('home')); ?>
                        </a>
                    </li>
                    <?php
                    $articles_page = get_page_by_path(
                        'articles',
                        OBJECT,
                        'page'
                    );

                    $exclude_pages = [];

                    if ($articles_page) {
                        $exclude_pages[] = $articles_page->ID;
                    }
                    $pages = get_pages([
                        'parent'      => 0,
                        'sort_column' => 'menu_order',
                        'sort_order'  => 'ASC',
                        'number'      => 6,
                        'exclude'     => $exclude_pages,
                        'post_status' => 'publish',
                    ]);

                    foreach ($pages as $page) {
                        if (
                            (int) get_post_meta(
                                $page->ID,
                                'fsr_utility',
                                true
                            ) === 1
                        ) {
                            continue;
                        }

                        echo '<li><a href="' . esc_url(get_permalink($page)) . '">';
                        echo esc_html(
                            get_the_title($page)
                        );
                        echo '</a></li>';
                    }
                    ?>
                </ul>
            </div>
            <!-- Articles -->
            <div class="sb-footer-bd-col">
                <?php echo do_shortcode('[sb_regulators
                    title_class="sb-footer-bd-heading"
                    list_class="sb-footer-bd-list"
                ]'); ?>
            </div>
        </div>
        <div class="sb-footer-bd-bottom">
            © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>

</body>
</html>