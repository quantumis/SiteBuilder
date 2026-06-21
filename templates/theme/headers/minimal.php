<?php
/**
 * Header variant: Minimal.
 * Compact single-line bar: logo + (small) menu, no border, lots of whitespace.
 * Good for content-focused sites with short menus.
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header sb-header-minimal">
    <div class="sb-header-inner">
        <div class="sb-header-logo">
            <?php
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                the_custom_logo();
            } else {
                echo '<a href="' . esc_url(home_url('/')) . '" class="sb-site-title">' . esc_html(get_bloginfo('name')) . '</a>';
            }
            ?>
        </div>
        <?php if (has_nav_menu('primary')) : ?>
            <nav class="sb-header-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'sb-menu sb-menu-primary',
                    'depth'          => 1,
                ]);
                ?>
            </nav>
        <?php endif; ?>
    </div>
</header>
