<?php
/**
 * Header variant: Beacon Bar ("Live Wire" edition)
 * — brand left, primary menu inline on the right, a compact "signal" trigger
 * — signature: a live oscilloscope line runs across the whole bar and reacts
 *   to menu hover with a pulse spike over the active item (the "broadcast")
 * — the inline menu is capped: items that don't fit collapse into the signal
 *   panel opened by the trigger, so the bar can never overflow
 * — full menu also available in a drop panel via the trigger (accordion subs)
 * — closes via X / backdrop / Escape; page scroll locks while the panel is open
 * — h1 wraps the logo ONLY on the front page (SEO best practice)
 */
if (!defined('ABSPATH')) exit;
?>
<header class="sb-header-beacon-bar" role="banner">
    <div class="sb-header-beacon-bar-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-beacon-bar-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        echo '<div class="sb-header-beacon-bar-brand">' . $logo_html . '</div>';
        ?>

        <span class="sb-header-beacon-bar-wire" aria-hidden="true">
            <svg class="sb-header-beacon-bar-wire-svg" viewBox="0 0 600 40" preserveAspectRatio="none" focusable="false">
                <path class="sb-header-beacon-bar-wire-base" d="M0,20 L600,20" />
                <path class="sb-header-beacon-bar-wire-live" d="M0,20 L600,20" />
            </svg>
        </span>

        <button class="sb-header-beacon-bar-trigger" type="button" aria-controls="sb-beacon-bar-signal" aria-expanded="false">
            <span class="sb-header-beacon-bar-trigger-wave" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </span>
            <span class="sb-header-beacon-bar-trigger-label"><?php echo esc_html(sb_t('navigation')); ?></span>
        </button>
    </div>

    <div id="sb-beacon-bar-signal" class="sb-header-beacon-bar-signal" hidden>
        <div class="sb-header-beacon-bar-signal-backdrop" data-bb-close></div>
        <div class="sb-header-beacon-bar-signal-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <div class="sb-header-beacon-bar-signal-head">
                <span class="sb-header-beacon-bar-signal-eyebrow"><?php echo esc_html(sb_t('section_overview')); ?></span>
                <button class="sb-header-beacon-bar-close" type="button" data-bb-close aria-label="<?php echo esc_attr(sb_t('back_to_top')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <nav class="sb-header-beacon-bar-signal-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location'  => 'primary',
                    'container'       => false,
                    'menu_class'      => 'sb-menu sb-menu-beacon-bar',
                    'depth'           => 3,
                    'fallback_cb'     => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
