<?php
/**
 * Header variant: Split Accent
 * — clean horizontal header with a separated accent brand zone
 * — logo left, navigation right
 * — 3-level dropdown on desktop
 * — burger menu on mobile
 * — h1 around logo only on the front page
 */
if (!defined('ABSPATH')) exit;

$logo_html = '';
if (function_exists('the_custom_logo') && has_custom_logo()) {
    ob_start();
    the_custom_logo();
    $logo_html = ob_get_clean();
} else {
    $logo_html = '<a class="sb-header-sa-name" href="' . esc_url(home_url('/')) . '">'
               . esc_html(get_bloginfo('name'))
               . '</a>';
}
?>
<header class="sb-header-split-accent" role="banner">
    <div class="sb-header-sa-inner">
        <div class="sb-header-sa-brand-zone">
            <?php if (is_front_page()): ?>
                <h1 class="sb-header-sa-brand"><?php echo $logo_html; ?></h1>
            <?php else: ?>
                <div class="sb-header-sa-brand"><?php echo $logo_html; ?></div>
            <?php endif; ?>
        </div>

        <div class="sb-header-sa-nav-zone">
            <button
                class="sb-header-sa-burger"
                type="button"
                aria-controls="sb-sa-nav"
                aria-expanded="false"
                aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>"
            >
                <span></span><span></span><span></span>
            </button>

            <nav id="sb-sa-nav" class="sb-header-sa-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'sb-menu sb-menu-sa',
                    'depth'          => 3,
                    'fallback_cb'    => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
