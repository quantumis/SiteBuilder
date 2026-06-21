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
            <nav class="sb-footer-nav" aria-label="<?php echo esc_attr(sb_t('footer_menu')); ?>">
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
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
