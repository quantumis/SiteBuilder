<?php
/**
 * Header variant: Crest Marquee ("Command Deck" edition)
 * — slim black bar: brand left, gold CTA + a deck trigger on the right rail
 * — the trigger drops a "command deck": the menu opens as a HUD panel with
 *   monospace coordinate indices, corner target brackets, and gold tick rails;
 *   items sweep in on a radar-like arc, and hover "locks target" on a row
 * — sub-menus open as an accordion under a row
 * — closes via X / backdrop / Escape; page scroll locks while open
 * — the bar only holds brand + CTA + trigger, so it can never overflow
 * — h1 wraps the logo ONLY on the front page (SEO best practice)
 * — CTA target resolves to the first utility page flagged fsr_cta, else home
 */
if (!defined('ABSPATH')) exit;

$sb_cta_url   = home_url('/');
$sb_cta_pages = get_pages([
    'meta_key'    => 'fsr_cta',
    'meta_value'  => '1',
    'number'      => 1,
    'sort_column' => 'menu_order',
]);
if (!empty($sb_cta_pages)) {
    $sb_cta_url = get_permalink($sb_cta_pages[0]);
}
?>
<header class="sb-header-crest-marquee" role="banner">
    <div class="sb-header-crest-marquee-inner">
        <?php
        $logo_html = '';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            ob_start(); the_custom_logo(); $logo_html = ob_get_clean();
        } else {
            $logo_html = '<a class="sb-header-crest-marquee-name" href="' . esc_url(home_url('/')) . '">'
                       . esc_html(get_bloginfo('name')) . '</a>';
        }
        if (is_front_page()) {
            echo '<h1 class="sb-header-crest-marquee-brand">' . $logo_html . '</h1>';
        } else {
            echo '<div class="sb-header-crest-marquee-brand">' . $logo_html . '</div>';
        }
        ?>

        <div class="sb-header-crest-marquee-rail">
            <a class="sb-header-crest-marquee-cta-btn" href="<?php echo esc_url($sb_cta_url); ?>">
                <?php echo esc_html(sb_t('cta_label')); ?>
            </a>
            <button class="sb-header-crest-marquee-trigger" type="button" aria-controls="sb-crest-marquee-deck" aria-expanded="false">
                <span class="sb-header-crest-marquee-trigger-reticle" aria-hidden="true"></span>
                <span class="sb-header-crest-marquee-trigger-label"><?php echo esc_html(sb_t('navigation')); ?></span>
            </button>
        </div>
    </div>

    <div id="sb-crest-marquee-deck" class="sb-header-crest-marquee-deck" hidden>
        <div class="sb-header-crest-marquee-deck-backdrop" data-cm-close></div>
        <div class="sb-header-crest-marquee-deck-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <div class="sb-header-crest-marquee-deck-head">
                <span class="sb-header-crest-marquee-deck-eyebrow"><?php echo esc_html(sb_t('section_overview')); ?></span>
                <button class="sb-header-crest-marquee-close" type="button" data-cm-close aria-label="<?php echo esc_attr(sb_t('back_to_top')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <nav class="sb-header-crest-marquee-deck-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
                <?php
                wp_nav_menu([
                    'theme_location'  => 'primary',
                    'container'       => false,
                    'menu_class'      => 'sb-menu sb-menu-crest-marquee',
                    'depth'           => 3,
                    'fallback_cb'     => false,
                ]);
                ?>
            </nav>
        </div>
    </div>
</header>
