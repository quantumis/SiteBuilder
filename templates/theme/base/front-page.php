<?php
/**
 * Front-page template — the homepage.
 *
 * Two-track rendering strategy:
 *   1. The FSR importer injects the root page's HTML into the <!-- Enter Code -->
 *      marker below (in-place replacement, replacing the whole the_content()
 *      block). This is how it worked in the original minipages theme.
 *   2. If the importer did NOT inject (e.g. you're using this theme without
 *      having run an FSR import yet, or you're running the importer on an
 *      external theme), the standard the_content() call still renders the
 *      page set as "page on front" in Settings → Reading.
 *
 * Either way, the homepage shows content — no blank state.
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
    <div class="sb-content">
        <!-- Enter Code -->
        <?php
        // Fallback rendering: if the marker above wasn't replaced by an FSR import,
        // render the page content the standard way.
        if (have_posts()) {
            while (have_posts()) {
                the_post();
                $sb_headline = (string)get_post_meta(get_the_ID(), 'fsr_headline', true);
                if ($sb_headline !== '') {
                    echo '<h1>' . esc_html($sb_headline) . '</h1>';
                } else {
                    the_title('<h1>', '</h1>');
                }
                the_content();
            }
        }
        ?>
    </div>
</main>

<?php get_footer(); ?>
<?php wp_footer(); ?>
</body>
</html>
