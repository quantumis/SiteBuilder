<?php
/**
 * Site Builder — content blocks styling.
 *
 * Provides CSS for the 17 built-in container blocks parsed by the plugin's
 * Blocks_Parser at import time. HTML is already in post_content when this
 * runs — we only inject the stylesheet.
 *
 * Uses the theme's CSS custom properties (--sb-color-*) throughout, so blocks
 * automatically pick up the active color scheme (Bright / Dark / Elegant).
 *
 * Loaded on every singular page — the CSS is small (~7 KB) and blocks may
 * appear on any content page.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_blocks_styles')) {
    function sb_blocks_styles() {
        if (is_admin()) return;

        wp_register_style('sb-blocks', false);
        wp_enqueue_style('sb-blocks');
        $css = <<<CSS
/* ===== Common block shell ===== */
.sb-block {
    margin: 20px 0;
    line-height: 1.55;
}
.sb-block-content > p:first-child { margin-top: 0; }
.sb-block-content > p:last-child  { margin-bottom: 0; }

/* ===== Callouts (info / success / warning / danger) ===== */
.sb-block-callout {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    border-radius: 8px;
    border-left: 4px solid var(--sb-callout-accent, #6b7280);
    background: var(--sb-callout-bg, rgba(107, 114, 128, 0.06));
    color: var(--sb-color-text, #111);
}
.sb-block-callout .sb-block-icon {
    flex-shrink: 0;
    width: 24px; height: 24px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background: var(--sb-callout-accent, #6b7280);
    color: #fff;
    font-size: 0.85em;
    font-weight: 700;
    margin-top: 2px;
}
.sb-block-callout .sb-block-content { flex: 1; }

.sb-block-info-callout    { --sb-callout-accent: #2563eb; --sb-callout-bg: rgba(37, 99, 235, 0.06); }
.sb-block-success-callout { --sb-callout-accent: #16a34a; --sb-callout-bg: rgba(22, 163, 74, 0.06); }
.sb-block-warning-callout { --sb-callout-accent: #d97706; --sb-callout-bg: rgba(217, 119, 6, 0.08); }
.sb-block-danger-callout  { --sb-callout-accent: #dc2626; --sb-callout-bg: rgba(220, 38, 38, 0.08); }

/* ===== Key takeaway (single) ===== */
.sb-block-key-takeaway {
    display: flex; flex-direction: column; gap: 6px;
    padding: 18px 20px;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.06), rgba(37, 99, 235, 0.02));
    border-left: 4px solid var(--sb-color-link, #2563eb);
    border-radius: 8px;
}
.sb-block-key-takeaway .sb-block-label {
    font-size: 0.72em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--sb-color-link, #2563eb);
}
.sb-block-key-takeaway .sb-block-content {
    font-size: 1.05em;
    font-weight: 500;
    color: var(--sb-color-text, #111);
}

/* ===== Key takeaways (list) ===== */
.sb-block-key-takeaways {
    padding: 18px 22px;
    background: var(--sb-color-bg-alt, #f9fafb);
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 8px;
}
.sb-block-key-takeaways .sb-block-label {
    display: block;
    font-size: 0.72em; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--sb-color-muted, #6b7280);
    margin-bottom: 10px;
}
.sb-block-key-takeaways .sb-block-list {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column; gap: 8px;
}
.sb-block-key-takeaways .sb-block-list li {
    position: relative; padding-left: 22px;
}
.sb-block-key-takeaways .sb-block-list li::before {
    content: "→";
    position: absolute; left: 0; top: 0;
    color: var(--sb-color-link, #2563eb);
    font-weight: 600;
}

/* ===== Odds example ===== */
.sb-block-odds-example {
    padding: 14px 18px;
    background: rgba(37, 99, 235, 0.04);
    border-radius: 6px;
    font-family: ui-monospace, "SF Mono", Monaco, "Cascadia Code", monospace;
    font-size: 0.95em;
    color: var(--sb-color-text, #111);
}

/* ===== Hero label + subtitle ===== */
.sb-block-hero-label {
    display: inline-block;
    padding: 4px 12px;
    margin: 0 0 10px;
    background: var(--sb-color-link, #2563eb);
    color: #fff;
    font-size: 0.75em; font-weight: 700;
    letter-spacing: 0.06em;
    border-radius: 4px;
    text-transform: uppercase;
}
.sb-block-hero-subtitle {
    margin: -8px 0 24px;
    font-size: 1.1em;
    color: var(--sb-color-muted, #6b7280);
    font-weight: 400;
    line-height: 1.5;
}

/* ===== Pre-bet checklist ===== */
.sb-block-pre-bet-checklist {
    padding: 20px 22px;
    background: var(--sb-color-bg-alt, #f9fafb);
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 8px;
}
.sb-block-pre-bet-checklist .sb-block-label {
    display: block;
    font-size: 0.72em; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--sb-color-muted, #6b7280);
    margin-bottom: 12px;
}
.sb-block-checklist {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column; gap: 10px;
}
.sb-block-checklist li {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 4px 0;
}
.sb-block-checklist .sb-block-checkbox {
    flex-shrink: 0;
    width: 20px; height: 20px;
    border: 2px solid var(--sb-color-border, #d1d5db);
    border-radius: 4px;
    margin-top: 2px;
}
.sb-block-checklist .sb-block-check-text { flex: 1; }

/* ===== Glossary term ===== */
.sb-block-glossary-term {
    padding: 16px 20px;
    border-left: 3px solid var(--sb-color-border, #d1d5db);
    background: var(--sb-color-bg-alt, #f9fafb);
    border-radius: 0 6px 6px 0;
    margin: 20px 0;
}
.sb-block-glossary-term dt {
    font-weight: 700;
    font-size: 1.05em;
    margin-bottom: 6px;
    color: var(--sb-color-text, #111);
}
.sb-block-glossary-term dd {
    margin: 0;
    color: var(--sb-color-text, #111);
}

/* ===== Details / accordion ===== */
.sb-block-details {
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 8px;
    background: var(--sb-color-bg-alt, #f9fafb);
    overflow: hidden;
    transition: border-color 0.2s;
}
.sb-block-details[open] { border-color: var(--sb-color-link, #2563eb); }
.sb-block-details-summary {
    padding: 14px 18px;
    cursor: pointer;
    font-weight: 600;
    color: var(--sb-color-text, #111);
    list-style: none;
    position: relative;
    padding-right: 40px;
}
.sb-block-details-summary::-webkit-details-marker { display: none; }
.sb-block-details-summary::after {
    content: "";
    position: absolute;
    right: 18px; top: 50%;
    width: 8px; height: 8px;
    border-right: 2px solid var(--sb-color-muted, #6b7280);
    border-bottom: 2px solid var(--sb-color-muted, #6b7280);
    transform: translateY(-70%) rotate(45deg);
    transition: transform 0.2s;
}
.sb-block-details[open] .sb-block-details-summary::after {
    transform: translateY(-30%) rotate(-135deg);
}
.sb-block-details .sb-block-content {
    padding: 6px 18px 16px;
    background: var(--sb-color-bg, #fff);
    border-top: 1px solid var(--sb-color-border, #e5e7eb);
}

/* ===== Card grid ===== */
.sb-block-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin: 24px 0;
}
.sb-block-card {
    padding: 18px 20px;
    background: var(--sb-color-bg-alt, #f9fafb);
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 8px;
    transition: transform 0.15s, box-shadow 0.15s;
}
.sb-block-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06); }
.sb-block-card-title {
    margin: 0 0 8px;
    font-size: 1em;
    font-weight: 600;
    color: var(--sb-color-text, #111);
}
.sb-block-card-body {
    font-size: 0.92em;
    color: var(--sb-color-muted, #6b7280);
    line-height: 1.55;
}
.sb-block-card-body > *:first-child { margin-top: 0; }
.sb-block-card-body > *:last-child  { margin-bottom: 0; }

/* ===== FAQ ===== */
.sb-block-faq {
    display: flex; flex-direction: column;
    gap: 8px;
    margin: 24px 0;
}
.sb-block-faq-item {
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 6px;
    background: var(--sb-color-bg-alt, #f9fafb);
}
.sb-block-faq-question {
    padding: 14px 44px 14px 18px;
    cursor: pointer;
    position: relative;
    list-style: none;
    margin: 0;
}
.sb-block-faq-question::-webkit-details-marker { display: none; }
.sb-block-faq-question-text {
    /* h3 inside <summary> — reset its browser defaults so it doesn't add
       extra margins or size differently from the summary line. Semantic
       heading level for SEO, visual weight from the summary styling. */
    margin: 0;
    font-size: inherit;
    font-weight: 600;
    color: var(--sb-color-text, #111);
    line-height: 1.4;
    display: inline;
}
.sb-block-faq-question::after {
    content: "+";
    position: absolute;
    right: 18px; top: 50%;
    transform: translateY(-50%);
    color: var(--sb-color-muted, #6b7280);
    font-size: 1.4em;
    font-weight: 300;
    transition: transform 0.2s;
}
.sb-block-faq-item[open] .sb-block-faq-question::after {
    transform: translateY(-50%) rotate(45deg);
}
.sb-block-faq-answer {
    padding: 6px 18px 16px;
    background: var(--sb-color-bg, #fff);
    color: var(--sb-color-text, #111);
    border-top: 1px solid var(--sb-color-border, #e5e7eb);
}
.sb-block-faq-answer > *:first-child { margin-top: 8px; }
.sb-block-faq-answer > *:last-child  { margin-bottom: 0; }

/* ===== At a glance ===== */
.sb-block-at-a-glance {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0;
    padding: 18px 4px;
    background: var(--sb-color-bg-alt, #f9fafb);
    border: 1px solid var(--sb-color-border, #e5e7eb);
    border-radius: 8px;
    margin: 24px 0;
}
.sb-block-glance-item {
    padding: 4px 18px;
    border-left: 1px solid var(--sb-color-border, #e5e7eb);
}
.sb-block-glance-item:first-child { border-left: none; }
.sb-block-glance-label {
    font-size: 0.72em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sb-color-muted, #6b7280);
    margin-bottom: 6px;
}
.sb-block-glance-value {
    font-size: 1.15em;
    font-weight: 600;
    color: var(--sb-color-text, #111);
}
@media (max-width: 500px) {
    .sb-block-glance-item {
        border-left: none;
        border-top: 1px solid var(--sb-color-border, #e5e7eb);
        padding: 12px 18px;
    }
    .sb-block-glance-item:first-child { border-top: none; }
}

/* ===== Do's & Don'ts ===== */
.sb-block-dos-donts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin: 24px 0;
}
.sb-block-dosdonts-col {
    padding: 18px 20px;
    border-radius: 8px;
    border: 1px solid transparent;
}
.sb-block-dosdonts-do {
    background: rgba(22, 163, 74, 0.06);
    border-color: rgba(22, 163, 74, 0.2);
}
.sb-block-dosdonts-dont {
    background: rgba(220, 38, 38, 0.06);
    border-color: rgba(220, 38, 38, 0.2);
}
.sb-block-dosdonts-heading {
    margin: 0 0 12px;
    font-size: 1em;
    font-weight: 700;
}
.sb-block-dosdonts-do   .sb-block-dosdonts-heading { color: #16a34a; }
.sb-block-dosdonts-dont .sb-block-dosdonts-heading { color: #dc2626; }
.sb-block-dosdonts-list {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column; gap: 8px;
}
.sb-block-dosdonts-list li {
    position: relative; padding-left: 24px;
    color: var(--sb-color-text, #111);
}
.sb-block-dosdonts-do   .sb-block-dosdonts-list li::before {
    content: "✓";
    position: absolute; left: 0; top: 0;
    color: #16a34a; font-weight: 700;
}
.sb-block-dosdonts-dont .sb-block-dosdonts-list li::before {
    content: "✕";
    position: absolute; left: 0; top: 0;
    color: #dc2626; font-weight: 700;
}
@media (max-width: 600px) {
    .sb-block-dos-donts { grid-template-columns: 1fr; }
}

/* ===== Worked example (numbered steps) ===== */
.sb-block-worked-example {
    list-style: none;
    padding: 0; margin: 24px 0;
    display: flex; flex-direction: column;
    counter-reset: sb-step;
}
.sb-block-step {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid var(--sb-color-border, #e5e7eb);
}
.sb-block-step:last-child { border-bottom: none; }
.sb-block-step-num {
    flex-shrink: 0;
    width: 36px; height: 36px;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--sb-color-link, #2563eb);
    color: #fff;
    font-weight: 700;
    border-radius: 50%;
}
.sb-block-step-body {
    flex: 1;
    color: var(--sb-color-text, #111);
    padding-top: 4px;
}
.sb-block-step-body > *:first-child { margin-top: 0; }
.sb-block-step-body > *:last-child  { margin-bottom: 0; }
CSS;
        wp_add_inline_style('sb-blocks', $css);
    }
    add_action('wp_enqueue_scripts', 'sb_blocks_styles');
}
