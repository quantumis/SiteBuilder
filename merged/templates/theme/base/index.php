<?php
/**
 * Fallback template — used for any view where a more specific template doesn't exist.
 * For pages WordPress will pick page.php first; for the front page it'll pick
 * front-page.php; this is the safety net.
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
                the_title('<h1>', '</h1>');
                the_content();
            }
        } else {
            echo '<p>Контент не найден.</p>';
        }
        ?>
    </div>
</main>

<?php get_footer(); ?>