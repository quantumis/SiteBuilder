<?php
/**
 * Site Builder — 404 template.
 *
 * WordPress falls back to this template when a request doesn't match any
 * post or page. It uses the same HTML wrapper pattern as page.php /
 * index.php / front-page.php: the full <!doctype><html><head>...
 * <body class="sb-site"> shell lives here, not in header.php. That's a
 * deliberate architecture choice — header.php in this theme is only the
 * <header> element (the site chrome), not the HTML document envelope.
 *
 * If you forget to open <html>/<head>/<body> here, wp_head() never runs,
 * meaning no stylesheets are enqueued (style.css doesn't load), body_class
 * doesn't get 'sb-site' (so the flex layout stops working), and the page
 * renders unstyled. That's exactly what beta7 and beta8 accidentally shipped.
 *
 * All copy runs through sb_t() for 36-locale coverage. Styles are inline
 * via wp_add_inline_style — see inc/404-styles.php.
 *
 * Design intent: bold "404" mark with gradient fill from the theme's
 * accent colors, a short sentence, one action button. No search box or
 * "popular posts" widget — deliberately minimal so the user's attention
 * lands on the recovery action.
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
    <div class="sb-content sb-404-page">
        <div class="sb-404-inner">
            <div class="sb-404-code" aria-hidden="true">404</div>
            <h1 class="sb-404-title"><?php echo esc_html(sb_t('not_found_title')); ?></h1>
            <p class="sb-404-message"><?php echo esc_html(sb_t('not_found_message')); ?></p>
            <a class="sb-404-action" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="sb-404-action-arrow" aria-hidden="true">←</span>
                <?php echo esc_html(sb_t('back_home')); ?>
            </a>
        </div>
    </div>
</main>

<?php get_footer(); ?>
<?php wp_footer(); ?>
</body>
</html>
