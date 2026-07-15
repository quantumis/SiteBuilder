<?php
/**
 * Site Builder — 404 template.
 *
 * WordPress falls back to this template when a request doesn't match any
 * post or page. The layout uses the theme's standard wrapper structure
 * (sb-site-content > sb-content) so it inherits max-width, padding, and
 * typography rules that page.php and index.php get — otherwise the 404
 * would sprawl across the full viewport with no theme styling.
 *
 * All copy runs through sb_t() for 36-locale coverage. Styles live in
 * inc/404-styles.php (registered as inline CSS via wp_enqueue_scripts) —
 * putting <style> in body works but is unreliable across CSS loaders and
 * validators, so we register through the standard WP mechanism.
 *
 * Design intent: bold "404" mark with gradient fill from the theme's
 * accent colors, a short sentence, one action button. No search box or
 * "popular posts" widget — deliberately minimal so the user's attention
 * lands on the recovery action.
 */
if (!defined('ABSPATH')) exit;

get_header(); ?>

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
