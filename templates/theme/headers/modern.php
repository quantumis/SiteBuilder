<?php
/**
 * Header variant: Modern.
 * Centered logo on top, menu below. Premium feel with more breathing room.
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header sb-header-modern">
    <div class="sb-header-top">
        <div class="sb-header-logo">
            <?php
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                the_custom_logo();
            } else {
                echo '<a href="' . esc_url(home_url('/')) . '" class="sb-site-title">' . esc_html(get_bloginfo('name')) . '</a>';
            }
            ?>
        </div>
        <?php $sb_tagline = get_bloginfo('description'); ?>
        <?php if ($sb_tagline) : ?>
            <p class="sb-site-tagline"><?php echo esc_html($sb_tagline); ?></p>
        <?php endif; ?>
    </div>
    <?php if (has_nav_menu('primary')) : ?>
        <nav class="sb-header-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'sb-menu sb-menu-primary',
                'depth'          => 2,
            ]);
            ?>
        </nav>
    <?php endif; ?>
</header>
