<?php
/**
 * Site Builder — 404 template.
 *
 * WordPress falls back to this template when a request doesn't match any
 * post or page. Stays close to the theme's aesthetic — uses --sb-color-*
 * variables and sb_t() for all copy, so it looks and reads correctly on
 * every color scheme and every one of the 36 supported locales.
 *
 * Design intent: bold "404" mark, a short apology, and a single action
 * (return to home). No search box, no popular posts widget — deliberately
 * minimal to avoid distracting from the fix.
 */
if (!defined('ABSPATH')) exit;

get_header(); ?>

<main class="sb-content sb-404">
    <div class="sb-404-inner">
        <div class="sb-404-code" aria-hidden="true">404</div>
        <h1 class="sb-404-title"><?php echo esc_html(sb_t('not_found_title')); ?></h1>
        <p class="sb-404-message"><?php echo esc_html(sb_t('not_found_message')); ?></p>
        <a class="sb-404-action" href="<?php echo esc_url(home_url('/')); ?>">
            <span class="sb-404-action-arrow" aria-hidden="true">←</span>
            <?php echo esc_html(sb_t('back_home')); ?>
        </a>
    </div>
</main>

<style>
    .sb-404 {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
    }
    .sb-404-inner {
        max-width: 500px;
        text-align: center;
    }
    .sb-404-code {
        font-family: var(--sb-font-heading, inherit);
        font-size: clamp(96px, 20vw, 180px);
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.04em;
        background: linear-gradient(135deg,
            var(--sb-color-link, #2563eb),
            var(--sb-color-accent, var(--sb-color-link, #2563eb)));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
        opacity: 0.9;
    }
    .sb-404-title {
        margin: 0 0 12px;
        font-size: clamp(24px, 4vw, 32px);
        font-weight: 700;
        color: var(--sb-color-text, #111);
        letter-spacing: -0.01em;
    }
    .sb-404-message {
        margin: 0 0 32px;
        font-size: 16px;
        line-height: 1.6;
        color: var(--sb-color-muted, #6b7280);
    }
    .sb-404-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: var(--sb-color-link, #2563eb);
        color: var(--sb-color-bg, #fff);
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        transition: opacity 0.15s, transform 0.15s;
    }
    .sb-404-action:hover {
        opacity: 0.9;
        transform: translateX(-2px);
        text-decoration: none;
        color: var(--sb-color-bg, #fff);
    }
    .sb-404-action-arrow {
        transition: transform 0.15s;
    }
    .sb-404-action:hover .sb-404-action-arrow {
        transform: translateX(-3px);
    }
</style>

<?php get_footer(); ?>
