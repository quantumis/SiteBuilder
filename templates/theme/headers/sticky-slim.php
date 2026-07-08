<?php
/**
 * Header variant: Sticky Slim
 * — thin sticky bar (48px), logo left, menu right
 * — 3-level dropdown on hover (desktop)
 * — burger toggle on mobile (<800px)
 * — h1 wrap around logo ONLY on front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-slim" role="banner">
    <div class="sb-header-slim-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-slim-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        if (is_front_page()) {
            echo '<h1 class="sb-header-slim-brand">' . $logo_html . '</h1>';
        } else {
            echo '<div class="sb-header-slim-brand">' . $logo_html . '</div>';
        }
        ?>
        <button class="sb-header-slim-burger" type="button" aria-controls="sb-slim-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
        <nav id="sb-slim-nav" class="sb-header-slim-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            wp_nav_menu([
                'theme_location'  => 'primary',
                'container'       => false,
                'menu_class'      => 'sb-menu sb-menu-slim',
                'depth'           => 3,
                'fallback_cb'     => false,
            ]);
            ?>
        </nav>
    </div>
</header>
