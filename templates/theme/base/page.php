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
            // checkbox, so calling it unconditionally is safe.
            if (function_exists('sb_breadcrumbs_html')) {
                echo sb_breadcrumbs_html();
            }
            // Display the H1: prefer fsr_headline meta (set by the FSR importer)
            // if present, otherwise fall back to the post title.
            $sb_headline = (string)get_post_meta(get_the_ID(), 'fsr_headline', true);
            if ($sb_headline !== '') {
                echo '<h1>' . esc_html($sb_headline) . '</h1>';
            } else {
                the_title('<h1>', '</h1>');
            }
            the_content();
            ?>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
<?php wp_footer(); ?>
</body>
</html>
