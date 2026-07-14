<?php
/**
 * Site Builder — SEO metabox.
 *
 * Adds a "Site Builder SEO" panel to the post/page editor with fields for
 * manually overriding SEO output. Values are written to _custom_seo_* meta
 * keys which the theme's seo.php module reads at highest priority (before
 * mapped Yoast/RankMath keys, before frontmatter fallbacks, before auto-generated
 * content).
 *
 * Behavior:
 *   - Hidden when a third-party SEO plugin (Yoast, RankMath, AIOSEO, SEOPress,
 *     TSF) is active. Those plugins provide their own richer SEO metabox and
 *     the theme's SEO output is disabled anyway — showing ours would confuse.
 *   - Shows current auto-generated values as placeholders so the admin knows
 *     what will be used if the field is left empty.
 *   - Fields:
 *       1. SEO Title            → _custom_seo_title
 *       2. Meta Description     → _custom_seo_desc
 *       3. Social Headline      → _custom_seo_headline    (og:title)
 *       4. OG Description       → _custom_seo_og_desc     (falls back to Meta Description)
 *       5. Custom OG Image URL  → _custom_seo_og_image    (falls back to featured image)
 *       6. noindex checkbox     → _custom_seo_noindex     (adds robots meta, skips JSON-LD)
 */
if (!defined('ABSPATH')) exit;

class Site_Builder_SEO_Metabox {

    const NONCE_ACTION = 'sb_seo_metabox_save';
    const NONCE_NAME   = 'sb_seo_metabox_nonce';

    public static function register(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post',      [__CLASS__, 'save'], 10, 2);
    }

    /**
     * Detects competing SEO plugins. Kept in sync with the theme's
     * sb_seo_should_run() so the metabox appears exactly when the theme's
     * built-in SEO is active.
     */
    public static function third_party_seo_active(): bool {
        if (defined('WPSEO_VERSION'))                                    return true; // Yoast
        if (function_exists('wpseo_init'))                               return true; // Yoast (legacy)
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath'))    return true; // RankMath
        if (defined('AIOSEO_VERSION') || function_exists('aioseo'))      return true; // AIOSEO
        if (defined('SEOPRESS_VERSION'))                                 return true; // SEOPress
        if (defined('THE_SEO_FRAMEWORK_VERSION'))                        return true; // TSF
        return false;
    }

    public static function add_metabox(): void {
        if (self::third_party_seo_active()) return;

        // Attach to all public post types the editor is likely to encounter.
        $screens = ['post', 'page'];
        foreach ($screens as $screen) {
            add_meta_box(
                'sb-seo-metabox',
                'Site Builder SEO',
                [__CLASS__, 'render'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function render($post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $post_id = (int)$post->ID;

        // Current custom values
        $title    = (string)get_post_meta($post_id, '_custom_seo_title',    true);
        $desc     = (string)get_post_meta($post_id, '_custom_seo_desc',     true);
        $headline = (string)get_post_meta($post_id, '_custom_seo_headline', true);
        $og_desc  = (string)get_post_meta($post_id, '_custom_seo_og_desc',  true);
        $og_img   = (string)get_post_meta($post_id, '_custom_seo_og_image', true);
        $noindex  = (int)get_post_meta($post_id, '_custom_seo_noindex', true) === 1;

        // Placeholders — show what would be used if the field is left empty.
        // These come from the fallback chain: mapped SEO keys → fsr_* → post fields.
        $ph_title    = self::first_meta_or_post($post_id, [
            '_yoast_wpseo_title', 'rank_math_title', 'fsr_title'
        ], $post->post_title);
        $ph_desc     = self::first_meta_or_post($post_id, [
            '_yoast_wpseo_metadesc', 'rank_math_description', 'fsr_description'
        ], wp_trim_words($post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content, 30, ''));
        $ph_headline = self::first_meta_or_post($post_id, [
            '_yoast_wpseo_opengraph-title', 'rank_math_facebook_title', 'fsr_headline'
        ], '');
        $ph_og_desc  = self::first_meta_or_post($post_id, [
            '_yoast_wpseo_opengraph-description', 'rank_math_facebook_description'
        ], '(будет использовано Meta Description)');
        $ph_og_img   = self::first_meta_or_post($post_id, [
            '_yoast_wpseo_opengraph-image', 'rank_math_facebook_image'
        ], '(будет использовано изображение записи)');

        ?>
        <style>
            .sb-seo-metabox-table { width: 100%; }
            .sb-seo-metabox-table th { text-align: left; padding: 12px 12px 8px 0; vertical-align: top; width: 180px; font-weight: 600; }
            .sb-seo-metabox-table td { padding: 8px 0; vertical-align: top; }
            .sb-seo-metabox-table input[type=text],
            .sb-seo-metabox-table input[type=url],
            .sb-seo-metabox-table textarea { width: 100%; padding: 6px 8px; }
            .sb-seo-metabox-table textarea { min-height: 60px; font-family: inherit; }
            .sb-seo-metabox-hint {
                color: #666;
                font-size: 12px;
                margin-top: 4px;
                line-height: 1.4;
            }
            .sb-seo-metabox-noindex {
                margin-top: 8px;
                padding: 10px 12px;
                background: #fff8e1;
                border-left: 4px solid #f0b429;
                border-radius: 3px;
            }
            .sb-seo-metabox-note {
                background: #f0f6ff;
                border-left: 4px solid #2271b1;
                padding: 10px 12px;
                margin: 0 0 12px;
                border-radius: 3px;
                font-size: 13px;
                line-height: 1.5;
            }
        </style>

        <div class="sb-seo-metabox-note">
            Значения ниже <strong>переопределяют</strong> импортированные из FSR-архива данные и попадают в теги
            <code>&lt;title&gt;</code>, <code>&lt;meta description&gt;</code>, Open Graph и Twitter Cards.
            Оставь поле пустым — будет использовано автоматическое значение (показано серым как plaсeholder).
        </div>

        <table class="sb-seo-metabox-table">
            <tr>
                <th><label for="sb-seo-title">SEO Title</label></th>
                <td>
                    <input type="text" id="sb-seo-title" name="sb_seo[title]"
                           value="<?php echo esc_attr($title); ?>"
                           placeholder="<?php echo esc_attr($ph_title); ?>" />
                    <div class="sb-seo-metabox-hint">Заголовок в теге <code>&lt;title&gt;</code> и в результатах Google. Оптимально: 50–60 символов.</div>
                </td>
            </tr>
            <tr>
                <th><label for="sb-seo-desc">Meta Description</label></th>
                <td>
                    <textarea id="sb-seo-desc" name="sb_seo[desc]"
                              placeholder="<?php echo esc_attr($ph_desc); ?>"><?php echo esc_textarea($desc); ?></textarea>
                    <div class="sb-seo-metabox-hint">Мета-описание для поисковиков. Оптимально: 150–160 символов.</div>
                </td>
            </tr>
            <tr>
                <th><label for="sb-seo-headline">Social Headline</label></th>
                <td>
                    <input type="text" id="sb-seo-headline" name="sb_seo[headline]"
                           value="<?php echo esc_attr($headline); ?>"
                           placeholder="<?php echo esc_attr($ph_headline !== '' ? $ph_headline : '(будет использован SEO Title)'); ?>" />
                    <div class="sb-seo-metabox-hint">Заголовок при расшаривании в соцсетях (og:title, twitter:title). Может быть более цепляющим чем SEO Title.</div>
                </td>
            </tr>
            <tr>
                <th><label for="sb-seo-og-desc">OG Description</label></th>
                <td>
                    <textarea id="sb-seo-og-desc" name="sb_seo[og_desc]"
                              placeholder="<?php echo esc_attr($ph_og_desc); ?>"><?php echo esc_textarea($og_desc); ?></textarea>
                    <div class="sb-seo-metabox-hint">Описание при расшаривании в соцсетях (og:description). Обычно копия Meta Description, но можно отдельно.</div>
                </td>
            </tr>
            <tr>
                <th><label for="sb-seo-og-image">Custom OG Image URL</label></th>
                <td>
                    <input type="url" id="sb-seo-og-image" name="sb_seo[og_image]"
                           value="<?php echo esc_attr($og_img); ?>"
                           placeholder="<?php echo esc_attr($ph_og_img); ?>" />
                    <div class="sb-seo-metabox-hint">Ссылка на изображение для соцсетей (og:image). Формат: полный URL <code>https://...</code>. По умолчанию используется Featured Image записи.</div>
                </td>
            </tr>
            <tr>
                <th>Индексация</th>
                <td>
                    <label>
                        <input type="checkbox" id="sb-seo-noindex" name="sb_seo[noindex]" value="1"
                               <?php checked($noindex, true); ?> />
                        <strong>noindex</strong> — не показывать эту страницу в результатах поисковых систем
                    </label>
                    <?php if ($noindex): ?>
                        <div class="sb-seo-metabox-noindex">
                            ⚠ Эта страница <strong>скрыта</strong> от поисковых систем. Robots-тег: <code>noindex,nofollow</code>. JSON-LD разметка не выводится.
                        </div>
                    <?php endif; ?>
                    <div class="sb-seo-metabox-hint">Полезно для служебных страниц (спасибо, 404, thank-you-pages), временных, дублирующих контент.</div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save handler — validates capabilities, verifies nonce, writes fields.
     * All meta keys start with `_custom_seo_` — WordPress hides underscore-prefixed
     * meta from the standard Custom Fields UI, which is what we want: the only
     * way to edit them is through this metabox (or third-party plugins that
     * override the hide-behavior, like ACF).
     */
    public static function save($post_id, $post): void {
        // Skip autosaves, revisions, quick edits
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        // Capability check — same as WP's edit_post
        $post_type = get_post_type($post_id);
        $caps = ($post_type === 'page') ? 'edit_page' : 'edit_post';
        if (!current_user_can($caps, $post_id)) return;

        // Nonce check — must exist and validate
        if (empty($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        // If the metabox wasn't rendered (third-party SEO active) our fields
        // won't be in POST — don't touch existing values in that case.
        if (!isset($_POST['sb_seo']) && !isset($_POST[self::NONCE_NAME])) return;

        $input = isset($_POST['sb_seo']) && is_array($_POST['sb_seo']) ? $_POST['sb_seo'] : [];

        $map = [
            'title'    => '_custom_seo_title',
            'desc'     => '_custom_seo_desc',
            'headline' => '_custom_seo_headline',
            'og_desc'  => '_custom_seo_og_desc',
            'og_image' => '_custom_seo_og_image',
        ];
        foreach ($map as $input_key => $meta_key) {
            $value = isset($input[$input_key]) ? trim((string)wp_unslash($input[$input_key])) : '';
            // For URL field, run through esc_url_raw
            if ($input_key === 'og_image' && $value !== '') {
                $value = esc_url_raw($value);
            } else {
                $value = sanitize_text_field($value);
            }
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        // noindex is a boolean checkbox
        $noindex = !empty($input['noindex']) ? 1 : 0;
        if ($noindex) {
            update_post_meta($post_id, '_custom_seo_noindex', 1);
        } else {
            delete_post_meta($post_id, '_custom_seo_noindex');
        }
    }

    private static function first_meta_or_post(int $post_id, array $keys, string $fallback): string {
        foreach ($keys as $k) {
            $v = trim((string)get_post_meta($post_id, $k, true));
            if ($v !== '') return $v;
        }
        return $fallback;
    }
}
