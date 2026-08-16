<?php
/**
 * Footer variant: Vault Ledger
 * — a bordered "ledger" card holds the whole footer, gold hairline dividers
 * — top band: [brand + description] + three titled link columns
 *     "Navigation" = the assigned footer menu (shown only if a menu is set)
 *     "Site"       = home link + top-level pages (utility pages excluded)
 *     "Legal"      = utility pages flagged fsr_utility (T&C, privacy, etc.)
 * — bottom band: copyright line + 18+ responsible-gambling disclaimer
 * — closes the document with wp_footer() so plugins can inject scripts
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer-vault-ledger" role="contentinfo">
    <div class="sb-footer-vault-ledger-inner">
        <div class="sb-footer-vault-ledger-card">
            <div class="sb-footer-vault-ledger-top">
                <div class="sb-footer-vault-ledger-intro">
                    <?php
                    if (function_exists('the_custom_logo') && has_custom_logo()) {
                        the_custom_logo();
                    } else {
                        echo '<span class="sb-footer-vault-ledger-name">' . esc_html(get_bloginfo('name')) . '</span>';
                    }
                    ?>
                    <?php $sb_desc = get_bloginfo('description'); ?>
                    <?php if ($sb_desc): ?>
                        <p class="sb-footer-vault-ledger-desc"><?php echo esc_html($sb_desc); ?></p>
                    <?php endif; ?>
                </div>

                <?php if (has_nav_menu('footer')): ?>
                    <div class="sb-footer-vault-ledger-col">
                        <h2 class="sb-footer-vault-ledger-heading"><?php echo esc_html(sb_t('navigation')); ?></h2>
                        <nav aria-label="<?php echo esc_attr(sb_t('footer_menu')); ?>">
                            <?php
                            wp_nav_menu([
                                'theme_location'  => 'footer',
                                'container'       => false,
                                'menu_class'      => 'sb-menu sb-menu-footer',
                                'depth'           => 1,
                                'fallback_cb'     => false,
                            ]);
                            ?>
                        </nav>
                    </div>
                <?php endif; ?>

                <div class="sb-footer-vault-ledger-col">
                    <h2 class="sb-footer-vault-ledger-heading"><?php echo esc_html(sb_t('site')); ?></h2>
                    <ul class="sb-footer-vault-ledger-list">
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                        <?php
                        $sb_pages = get_pages([
                            'parent'      => 0,
                            'sort_column' => 'menu_order',
                            'number'      => 6,
                        ]);
                        foreach ($sb_pages as $sb_p) {
                            if ((int)get_post_meta($sb_p->ID, 'fsr_utility', true) === 1) continue;
                            echo '<li><a href="' . esc_url(get_permalink($sb_p)) . '">' . esc_html(get_the_title($sb_p)) . '</a></li>';
                        }
                        ?>
                    </ul>
                </div>

                <div class="sb-footer-vault-ledger-col">
                    <?php 
                    echo do_shortcode('[sb_regulators 
                        title_class="sb-footer-vault-ledger-heading" 
                        list_class="sb-footer-vault-ledger-list"
                    ]'); 
                    ?>
                </div>
            </div>

            <div class="sb-footer-vault-ledger-bottom">
                <p class="sb-footer-vault-ledger-copy">
                    &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>.
                    <?php echo esc_html(sb_t('rights_reserved')); ?>
                </p>
                <p class="sb-footer-vault-ledger-disclaimer">
                    <span class="sb-footer-vault-ledger-age">18+</span>
                    <?php echo esc_html(sb_t('responsible_gambling')); ?>
                </p>
            </div>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
