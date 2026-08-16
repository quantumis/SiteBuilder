<?php
// templates/theme/headers/split-minimal.php
if (!defined('ABSPATH')) exit;
?>
<div class="sb-header-split-minimal-mobile-bar">
    <?php if (has_custom_logo()) { 
        the_custom_logo(); 
    } else { 
        echo '<a href="' . esc_url(home_url('/')) . '" class="sb-header-split-minimal-mobile-logo">' . esc_html(get_bloginfo('name')) . '</a>'; 
    } ?>
    
    <button class="sb-header-split-minimal-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>
</div>

<header class="sb-header-split-minimal" role="banner">
    <div class="sb-header-split-minimal-inner">
        <div class="sb-header-split-minimal-brand">
            <?php if (has_custom_logo()) { 
                the_custom_logo(); 
            } else { 
                echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>'; 
            } ?>
        </div>

        <nav class="sb-header-split-minimal-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'sb-menu',
                'depth' => 3,
                'fallback_cb' => false,
            ]); ?>
        </nav>
    </div>
</header>
