<?php
/**
 * Footer variant: Columns.
 * Dynamic layout: 3 or 4 columns based on child pages external links.
 * Bottom row holds copyright.
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$sb_external_links = [];
$sb_current_host = parse_url(home_url(), PHP_URL_HOST);


$sb_excluded_domains = [
    'rp-darmstadt.hessen.de', 
    'gluecksspiel-behoerde.de'
];


$parent_page_title = 'Auszahlung & Verifizierung – TEST2';


$parent_id = $wpdb->get_var($wpdb->prepare(
    "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'page' AND post_status = 'publish' LIMIT 1",
    $parent_page_title
));

if ($parent_id) {
 
    $sb_child_posts = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'page' AND post_status = 'publish'",
        $parent_id
    ));

    if (!empty($sb_child_posts)) {
        $post_ids = implode(',', array_map('intval', $sb_child_posts));
        $post_contents = $wpdb->get_col("SELECT post_content FROM $wpdb->posts WHERE ID IN ($post_ids)");

        foreach ($post_contents as $content) {
         
            $clean_content = stripslashes($content);

            if (preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $clean_content, $matches)) {

                if (!empty($matches[1])) {
                    foreach ($matches[1] as $url) {
                        $link_host = parse_url($url, PHP_URL_HOST);
                        
                        if ($link_host && $link_host !== $sb_current_host) {
                            $clean_host = str_replace('www.', '', $link_host);
                            
                            if (!in_array($clean_host, $sb_excluded_domains)) {
 
                                $sb_external_links[$url] = [
                                    'domain' => $clean_host,
                                    'text'   => ucfirst(explode('.', $clean_host)[0]) 
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}


if (!empty($sb_external_links)) {
    uksort($sb_external_links, function() { return rand(-1, 1); });
    $sb_external_links = array_slice($sb_external_links, 0, 5, true);
}
?>
<footer class="sb-footer sb-footer-columns">
    <div class="sb-footer-inner">
    
        <div class="sb-footer-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            
            <!-- Колонка 1: Описание сайта -->
            <div class="sb-footer-col sb-footer-about">
                <div class="sb-footer-brand">
                    <?php
                    if (function_exists('the_custom_logo') && has_custom_logo()) {
                        the_custom_logo();
                    } else {
                        echo '<span class="sb-footer-brand-text">' . esc_html(get_bloginfo('name')) . '</span>';
                    }
                    ?>
                </div>
                <?php $sb_tagline = get_bloginfo('description'); ?>
                <?php if ($sb_tagline) : ?>
                    <p class="sb-footer-tagline"><?php echo esc_html($sb_tagline); ?></p>
                <?php endif; ?>
            </div>

         
            <?php if (has_nav_menu('footer')) : ?>
                <div class="sb-footer-col sb-footer-menu-col">
                    <h3 class="sb-footer-heading"><?php echo esc_html(sb_t('navigation')); ?></h3>
                    <nav aria-label="<?php echo esc_attr(sb_t('footer_menu')); ?>">
                        <?php
                        wp_nav_menu([
                            'theme_location' => 'footer',
                            'container'      => false,
                            'menu_class'     => 'sb-menu sb-menu-footer',
                            'depth'          => 1,
                        ]);
                        ?>
                    </nav>
                </div>
            <?php endif; ?>

            <!-- Колонка 3: Информация -->
            <div class="sb-footer-col sb-footer-info">
                <h3 class="sb-footer-heading"><?php echo esc_html(sb_t('site')); ?></h3>
                <ul class="sb-footer-info-list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sb_t('home')); ?></a></li>
                    <?php $sb_privacy_url = get_privacy_policy_url(); ?>
                    <?php if ($sb_privacy_url) : ?>
                        <li><a href="<?php echo esc_url($sb_privacy_url); ?>"><?php echo esc_html(sb_t('privacy_policy')); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

                
               <div class="sb-footer-col">
                <?php echo do_shortcode('[sb_regulators
                    title_class="sb-footer-newvariant-heading sb-footer-heading"
                    list_class="sb-footer-newvariant-list sb-footer-bs-list "
                ]'); ?>
            </div>


        </div>

        <div class="sb-footer-copyright">
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html(sb_t('rights_reserved')); ?>
        </div>
    </div>
</footer>
