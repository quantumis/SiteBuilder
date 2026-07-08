<?php
/**
 * Footer variant: Newsletter Highlight
 * Large left column = newsletter signup form (UI only, no backend — connect
 * to any provider). Three narrower link columns to the right.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-nl" role="contentinfo">
    <div class="sb-footer-nl-inner">
        <div class="sb-footer-nl-grid">
            <div class="sb-footer-nl-signup-col">
                <?php
                if (function_exists('the_custom_logo') && has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<div class="sb-footer-nl-name">' . esc_html(get_bloginfo('name')) . '</div>';
                }
                ?>
                <h2 class="sb-footer-nl-signup-title">Stay in the loop</h2>
                <p class="sb-footer-nl-signup-desc"><?php echo esc_html(get_bloginfo('description')); ?></p>
                <form class="sb-footer-nl-signup-form" onsubmit="return false" aria-label="Newsletter">
                    <input type="email" class="sb-footer-nl-input" placeholder="your@email.com" aria-label="Email" required />
                    <button type="submit" class="sb-footer-nl-btn">Subscribe</button>
                </form>
                <p class="sb-footer-nl-signup-tiny">We respect your inbox. Unsubscribe anytime.</p>
            </div>
            <div class="sb-footer-nl-col">
                <h3 class="sb-footer-nl-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
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
            <div class="sb-footer-nl-col">
                <h3 class="sb-footer-nl-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-nl-list">
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
            <div class="sb-footer-nl-col">
                <h3 class="sb-footer-nl-heading">Legal</h3>
                <ul class="sb-footer-nl-list">
                    <?php
                    // Utility pages (privacy, cookies, legal, etc)
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
        <div class="sb-footer-nl-copyright">
            © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
