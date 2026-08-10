<?php
/**
 * Header variant: Split Rail (command-bar edition)
 * — slim bar: brand left, a single accent "menu" pill on the right
 * — clicking the pill opens a full-screen overlay with the whole menu
 * — overlay items reveal with a staggered cascade; sub-menus are accordions
 * — closes via the X button, a backdrop click, or the Escape key
 * — because the bar only ever holds one pill, it can never overflow no matter
 *   how many top-level items the menu has (fixes the earlier "разъезжается")
 * — h1 wraps the logo ONLY on the front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-split-rail" role="banner">
    <div class="sb-header-split-rail-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-split-rail-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        if (is_front_page()) {
            echo '<h1 class="sb-header-split-rail-brand">' . $logo_html . '</h1>';
        } else {
            echo '<div class="sb-header-split-rail-brand">' . $logo_html . '</div>';
        }
        ?>

        <button class="sb-header-split-rail-trigger" type="button" aria-controls="sb-split-rail-overlay" aria-expanded="false">
            <span class="sb-header-split-rail-trigger-lines" aria-hidden="true"><span></span><span></span></span>
            <span class="sb-header-split-rail-trigger-label"><?php echo esc_html(sb_t('navigation')); ?></span>
        </button>
    </div>

    <div id="sb-split-rail-overlay" class="sb-header-split-rail-overlay" hidden>
        <div class="sb-header-split-rail-overlay-backdrop" data-sr-close></div>
        <div class="sb-header-split-rail-overlay-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <button class="sb-header-split-rail-close" type="button" data-sr-close aria-label="<?php echo esc_attr(sb_t('back_to_top')); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
            <nav class="sb-header-split-rail-overlay-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location'  => 'primary',
                    'container'       => false,
                    'menu_class'      => 'sb-menu sb-menu-split-rail',
                    'depth'           => 3,
                    'fallback_cb'     => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
