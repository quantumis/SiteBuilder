<?php
/**
 * Header variant: Edge Frame
 * — framed horizontal header with a vertical brand divider
 * — 3-level dropdown on desktop
 * — burger on mobile
 * — h1 around logo only on the front page
 */
if (!defined('ABSPATH')) exit;

$logo_html = '';
if (function_exists('the_custom_logo') && has_custom_logo()) {
    ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
} else {
    $logo_html = '<a class="sb-header-ef-name" href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
}
?>
<header class="sb-header-edge-frame" role="banner">
    <div class="sb-header-ef-inner">
        <div class="sb-header-ef-brand-wrap">
            <div class="sb-header-ef-brand"><?php echo $logo_html; ?></div>
        </div>

        <nav id="sb-ef-nav" class="sb-header-ef-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'sb-menu sb-menu-ef',
                'depth' => 3,
                'fallback_cb' => false,
            ]); ?>
        </nav>

        <button class="sb-header-ef-burger" type="button" aria-controls="sb-ef-nav" aria-expanded="false" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>
