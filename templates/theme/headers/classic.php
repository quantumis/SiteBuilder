<?php
/**
 * Header variant: Classic.
 * Logo on the left, primary menu on the right, thin border below.
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header sb-header-classic">
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
        <nav class="sb-header-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'sb-menu sb-menu-primary',
                    'depth'          => 2,
                ]);
            }
            ?>
        </nav>
    </div>
</header>
