<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processes raw HTML from archive files: extracts metadata, rewrites images,
 * isolates main content. Preserves all tags (per project requirement — source is trusted).
 */
class Site_Builder_Content_Processor {

    private Site_Builder_Media_Handler $media;

    public function __construct(Site_Builder_Media_Handler $media) {
        $this->media = $media;
    }

    /**
     * Extract title and meta description from comments or HTML tags.
     */
    public function extract_meta(string $html): array {
        $result = ['title' => '', 'desc' => ''];

        if (preg_match('/<!--\s*Title:\s*(.*?)\s*-->/i', $html, $m)) {
            $result['title'] = trim($m[1]);
        } elseif (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $result['title'] = trim($m[1]);
        }

        if (preg_match('/<!--\s*Meta:\s*(.*?)\s*-->/i', $html, $m)) {
            $result['desc'] = trim($m[1]);
        } elseif (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m)) {
            $result['desc'] = trim($m[1]);
        }

        return $result;
    }

    /**
     * Process a regular page's HTML.
     * Returns ['content' => string, 'thumbnail_id' => int|null].
     */
    public function process_page(string $html, string $current_dir, string $fallback_image_dir = ''): array {
        $thumbnail_id = null;

        // First image becomes featured/thumbnail and is removed from content
        if (preg_match('/<img[^>]+src=["\']([^"\'>]+)["\'][^>]*>/i', $html, $img_match)) {
            $full_tag = $img_match[0];
            $src = $img_match[1];
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/i', $full_tag, $alt_m)) {
                $alt = $alt_m[1];
            }

            $local_path = $this->resolve_image_path($src, $current_dir, $fallback_image_dir);
            if ($local_path) {
                $attach_id = $this->media->upload_image($local_path, $alt);
                if ($attach_id) {
                    $thumbnail_id = $attach_id;
                    $html = str_replace($full_tag, '', $html);
                }
            }
        }

        // Replace first <h1> with <h2> for SEO
        $html = preg_replace('/<h1([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $html, 1);

        // Rewrite remaining inline images
        $html = $this->rewrite_inline_images($html, $current_dir, $fallback_image_dir);

        $content = $this->extract_body($html);

        return [
            'content'      => $content,
            'thumbnail_id' => $thumbnail_id,
        ];
    }

    /**
     * Process the HUB index.html: extract <main>, inject shortcodes, upload images.
     */
    public function process_hub_main(string $html, string $hub_dir, array $shortcodes): string {
        $html = $this->rewrite_inline_images($html, $hub_dir);

        if (!preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $main_m)) {
            return '';
        }
        $content = trim($main_m[1]);

        $shortcode_block = "\n<?php\n";
        foreach ($shortcodes as $sc) {
            $sc_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $sc);
            if (!$sc_safe) continue;
            $shortcode_block .= "    if (shortcode_exists('$sc_safe')) { echo do_shortcode('[$sc_safe]'); }\n";
        }
        $shortcode_block .= "?>\n";

        if (preg_match('/<img[^>]+>/i', $content, $img_m, PREG_OFFSET_CAPTURE)) {
            $insert_pos = $img_m[0][1] + strlen($img_m[0][0]);
            $content = substr_replace($content, $shortcode_block, $insert_pos, 0);
        } else {
            $content = $shortcode_block . $content;
        }

        return $content;
    }

    /**
     * Extract <footer>...</footer> with all images rewritten.
     */
    public function process_hub_footer(string $html, string $hub_dir): string {
        if (!preg_match('/<footer[^>]*>(.*?)<\/footer>/is', $html, $footer_m)) {
            return '';
        }
        return $this->rewrite_inline_images($footer_m[0], $hub_dir);
    }

    private function rewrite_inline_images(string $html, string $current_dir, string $fallback_dir = ''): string {
        if (!preg_match_all('/<img[^>]+src=["\']([^"\'>]+)["\']/i', $html, $matches)) {
            return $html;
        }
        $seen = [];
        foreach ($matches[1] as $src) {
            if (isset($seen[$src])) continue;
            $seen[$src] = true;

            $local_path = $this->resolve_image_path($src, $current_dir, $fallback_dir);
            if (!$local_path) continue;

            $attach_id = $this->media->upload_image($local_path);
            if (!$attach_id) continue;

            $new_url = wp_make_link_relative(wp_get_attachment_url($attach_id));
            $quoted = preg_quote($src, '/');
            $html = preg_replace('/(src=["\'])' . $quoted . '(["\'])/i', '$1' . $new_url . '$2', $html);
        }
        return $html;
    }

    private function resolve_image_path(string $src, string $current_dir, string $fallback_dir = ''): ?string {
        $src = trim($src);
        if ($src === '') return null;
        if (preg_match('#^(https?:)?//#i', $src)) return null;
        if (preg_match('#^data:#i', $src)) return null;

        $candidates = [
            $current_dir . '/' . ltrim($src, '/'),
        ];
        if ($fallback_dir !== '') {
            $candidates[] = $fallback_dir . '/' . basename($src);
        }

        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real && file_exists($real)) return $real;
        }
        return null;
    }

    private function extract_body(string $html): string {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $body_m)) {
            return trim($body_m[1]);
        }
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<html[^>]*>|<\/html>/i', '', $html);
        $html = preg_replace('/<head>.*?<\/head>/is', '', $html);
        return trim($html);
    }
}
