<?php
/**
 * Header variant: Off-Canvas
 * — burger button visible on ALL screen sizes (even desktop)
 * — logo left, burger to the left of the logo
 * — click burger → off-canvas panel slides in from the left
 * — menu inside is a 3-level accordion
 * — max distraction-free reading experience
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-oc">
    <div class="sb-header-oc-inner">
        <button class="sb-header-oc-burger" type="button" aria-controls="sb-oc-panel" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-oc-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        echo '<div class="sb-header-oc-brand">' . $logo_html . '</div>';
        ?>
    </div>
</header>
<div class="sb-header-oc-backdrop" hidden></div>
<aside id="sb-oc-panel" class="sb-header-oc-panel" hidden aria-hidden="true" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
    <div class="sb-header-oc-panel-head">
        <span class="sb-header-oc-panel-title"><?php echo esc_html(sb_t('primary_menu')); ?></span>
        <button class="sb-header-oc-panel-close" type="button" aria-label="<?php echo esc_attr(sb_t('close')); ?>">×</button>
    </div>
    <nav class="sb-header-oc-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
        <?php
        wp_nav_menu([
            'theme_location'  => 'primary',
            'container'       => false,
            'menu_class'      => 'sb-menu sb-menu-oc',
            'depth'           => 3,
            'fallback_cb'     => false,
        ]);
        ?>
    </nav>
</aside>
