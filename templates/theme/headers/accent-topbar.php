<?php
/**
 * Header variant: Accent Topbar
 * — thin accent-colored strip on top (utility: home link + tagline)
 * — main bar: logo left, horizontal menu right
 * — 3-level dropdown on hover (desktop)
 * — burger on mobile (<800px); accent strip hides on mobile
 * — h1 wrap around logo ONLY on front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-at">
    <div class="sb-header-at-strip">
        <div class="sb-header-at-strip-inner">
            <span class="sb-header-at-tagline"><?php echo esc_html(get_bloginfo('description')); ?></span>
            <a class="sb-header-at-home" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a>
        </div>
    </div>
    <div class="sb-header-at-main">
        <div class="sb-header-at-inner">
            <?php
            $logo_html = '';
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
            } else {
                $logo_html = '<a class="sb-header-at-name" href="' . esc_url(home_url('/')) . '">'
                           . esc_html(get_bloginfo('name')) . '</a>';
            }
            if (is_front_page()) {
                echo '<h1 class="sb-header-at-brand">' . $logo_html . '</h1>';
            } else {
                echo '<div class="sb-header-at-brand">' . $logo_html . '</div>';
            }
            ?>
            <button class="sb-header-at-burger" type="button" aria-controls="sb-at-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <span></span><span></span><span></span>
            </button>
            <nav id="sb-at-nav" class="sb-header-at-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location'  => 'primary',
                    'container'       => false,
                    'menu_class'      => 'sb-menu sb-menu-at',
                    'depth'           => 3,
                    'fallback_cb'     => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
