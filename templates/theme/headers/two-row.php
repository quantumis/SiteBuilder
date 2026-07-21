<?php
/**
 * Header variant: Two-Row
 * — first (top) row: logo left + utility area right
 * — second (bottom) row: full-width menu with 3-level dropdown
 * — mobile: second row collapses into a burger
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-tr">
    <div class="sb-header-tr-top">
        <div class="sb-header-tr-inner sb-header-tr-top-inner">
            <?php
            $logo_html = '';
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
            } else {
                $logo_html = '<a class="sb-header-tr-name" href="' . esc_url(home_url('/')) . '">'
                           . esc_html(get_bloginfo('name')) . '</a>';
            }
            if (is_front_page()) {
                echo '<h1 class="sb-header-tr-brand">' . $logo_html . '</h1>';
            } else {
                echo '<div class="sb-header-tr-brand">' . $logo_html . '</div>';
            }
            ?>
            <div class="sb-header-tr-utility">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="sb-header-tr-home-link" title="<?php echo esc_attr(sb_t('home')); ?>">
                    <?php echo esc_html(sb_t('home')); ?>
                </a>
            </div>
            <button class="sb-header-tr-burger" type="button" aria-controls="sb-tr-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
    <div class="sb-header-tr-bottom">
        <div class="sb-header-tr-inner">
            <nav id="sb-tr-nav" class="sb-header-tr-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location'  => 'primary',
                    'container'       => false,
                    'menu_class'      => 'sb-menu sb-menu-tr',
                    'depth'           => 3,
                    'fallback_cb'     => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
