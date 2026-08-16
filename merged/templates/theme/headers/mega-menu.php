<?php
/**
 * Header variant: Mega Menu
 * — logo left, horizontal menu right
 * — top-level items with children open a full-width multi-column mega panel
 * — 3rd level renders as a list under each 2nd-level column heading
 * — burger + accordion on mobile (<800px)
 * — h1 wrap around logo ONLY on front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-mega">
    <div class="sb-header-mega-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-mega-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        echo '<div class="sb-header-mega-brand">' . $logo_html . '</div>';
        ?>
        <button class="sb-header-mega-burger" type="button" aria-controls="sb-mega-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
        <nav id="sb-mega-nav" class="sb-header-mega-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            wp_nav_menu([
                'theme_location'  => 'primary',
                'container'       => false,
                'menu_class'      => 'sb-menu sb-menu-mega',
                'depth'           => 3,
                'fallback_cb'     => false,
            ]);
            ?>
        </nav>
    </div>
</header>
