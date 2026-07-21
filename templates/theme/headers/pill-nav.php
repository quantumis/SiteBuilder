<?php
/**
 * Header variant: Pill Nav
 * — logo left, pill-shaped menu items grouped in a rounded container (right)
 * — active item is a filled accent pill
 * — 3-level dropdown on hover (desktop)
 * — burger on mobile (<800px)
 * — h1 wrap around logo ONLY on front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-pill">
    <div class="sb-header-pill-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-pill-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        if (is_front_page()) {
            echo '<h1 class="sb-header-pill-brand">' . $logo_html . '</h1>';
        } else {
            echo '<div class="sb-header-pill-brand">' . $logo_html . '</div>';
        }
        ?>
        <button class="sb-header-pill-burger" type="button" aria-controls="sb-pill-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
        <nav id="sb-pill-nav" class="sb-header-pill-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            wp_nav_menu([
                'theme_location'  => 'primary',
                'container'       => false,
                'menu_class'      => 'sb-menu sb-menu-pill',
                'depth'           => 3,
                'fallback_cb'     => false,
            ]);
            ?>
        </nav>
    </div>
</header>
