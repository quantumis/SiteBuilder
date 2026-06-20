<?php
/**
 * Footer variant: Classic.
 * Two-line footer: links row + copyright row.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer sb-footer-classic">
    <div class="sb-footer-inner">
        <?php if (has_nav_menu('footer')) : ?>
            <nav class="sb-footer-nav" aria-label="<?php esc_attr_e('Меню в подвале', 'site-builder'); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'sb-menu sb-menu-footer',
                    'depth'          => 1,
                ]);
                ?>
            </nav>
        <?php endif; ?>

        <div class="sb-footer-copyright">
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php esc_html_e('Все права защищены.', 'site-builder'); ?>
        </div>
    </div>
</footer>
