<?php
/**
 * Front-page template — the homepage.
 *
 * Renders the homepage content via the standard WordPress loop. We do NOT
 * use the legacy injection marker here — that was a workaround for the
 * minipages theme whose layout had no the_content() slot. Since we generate
 * this theme ourselves, we can place the_content() exactly where it belongs,
 * which means:
 *   - no injection step (the FSR importer sees the_content() without the
 *     marker and skips this file unchanged)
 *   - no duplicated content
 *   - the_content() filters (GEO shortcodes, similar posts) apply
 *   - h1 is rendered via fsr_headline meta or the_title
 *
 * Other themes (e.g. legacy minipages) keep the marker in their front-page.php
 * and continue to receive injected content as before.
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
        <?php
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
