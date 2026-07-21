<?php
/**
 * Header variant: Underline Tabs
 * — thin sticky bar, logo left, menu right
 * — animated underline indicator on hover / current item
 * — 3-level dropdown on hover (desktop)
 * — burger on mobile (<800px)
 * — h1 wrap around logo ONLY on front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-ut">
    <div class="sb-header-ut-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-ut-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        if (is_front_page()) {
            echo '<h1 class="sb-header-ut-brand">' . $logo_html . '</h1>';
        } else {
            echo '<div class="sb-header-ut-brand">' . $logo_html . '</div>';
        }
        ?>
        <button class="sb-header-ut-burger" type="button" aria-controls="sb-ut-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
        <nav id="sb-ut-nav" class="sb-header-ut-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            wp_nav_menu([
                'theme_location'  => 'primary',
                'container'       => false,
                'menu_class'      => 'sb-menu sb-menu-ut',
                'depth'           => 3,
                'fallback_cb'     => false,
            ]);
            ?>
        </nav>
    </div>
</header>
