<?php
/**
 * Page template — used for all pages EXCEPT the front page.
 * Renders the standard WordPress page content via the_content().
 */
if (!defined('ABSPATH')) exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('sb-site'); ?>>
<?php wp_body_open(); ?>
<?php get_header(); ?>

<main class="sb-site-content">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('sb-content'); ?>>
            <?php
            // Breadcrumbs render BEFORE the h1. sb_breadcrumbs_html() returns
            // empty on front-page or when disabled via the plugin's Theme tab
            // checkbox, so calling it unconditionally is safe. Breadcrumbs
            // always use get_the_title() which reads post_title from the WP
            // admin — decoupled from the H1 override below.
            if (function_exists('sb_breadcrumbs_html')) {
                echo sb_breadcrumbs_html();
            }
            // Display the H1 with a fallback chain:
            //   1. _custom_seo_h1 (manual override via Site Builder SEO metabox)
            //   2. fsr_headline (Social Headline from FSR frontmatter — historical
            //      fallback kept for backward compatibility with existing sites)
            //   3. post_title (the WordPress admin Title field — standard WP)
            // Editors get full separation: change H1 without touching breadcrumbs,
            // change breadcrumbs (post_title) without touching H1.
            $sb_h1 = trim((string)get_post_meta(get_the_ID(), '_custom_seo_h1', true));
            if ($sb_h1 === '') {
                $sb_h1 = trim((string)get_post_meta(get_the_ID(), 'fsr_headline', true));
            }
            if ($sb_h1 !== '') {
                // Custom H1 or fsr_headline — may contain [sb_year]/[sb_date] shortcodes
                echo '<h1>' . esc_html(do_shortcode($sb_h1)) . '</h1>';
            } else {
                the_title('<h1>', '</h1>');
            }
            the_content();
            ?>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>