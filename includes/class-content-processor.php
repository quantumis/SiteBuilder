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

        // Reduce to <main> first (if present) — otherwise we'd pick the site logo
        // from <header> as the page thumbnail in modern full-page archives.
        $content = $this->extract_body($html);

        // First image in the content becomes featured/thumbnail and is removed.
        if (preg_match('/<img[^>]+src=["\']([^"\'>]+)["\'][^>]*>/i', $content, $img_match, PREG_OFFSET_CAPTURE)) {
            $full_tag = $img_match[0][0];
            $img_offset = $img_match[0][1];
            $src = $img_match[1][0];
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/i', $full_tag, $alt_m)) {
                $alt = $alt_m[1];
            }

            $local_path = $this->resolve_image_path($src, $current_dir, $fallback_image_dir);
            if ($local_path) {
                $attach_id = $this->media->upload_image($local_path, $alt);
                if ($attach_id) {
                    $thumbnail_id = $attach_id;
                    // Try to remove the whole hero-wrapper (figure, picture, hero-div)
                    // around the image, not just the <img> tag. This avoids an empty
                    // styled container being left behind on the page.
                    $range = $this->find_image_wrapper_range($content, $img_offset, strlen($full_tag));
                    $content = substr_replace($content, '', $range['start'], $range['length']);
                }
            }
        }

        // Replace first <h1> with <h2> for SEO
        $content = preg_replace('/<h1([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $content, 1);

        // Rewrite remaining inline images
        $content = $this->rewrite_inline_images($content, $current_dir, $fallback_image_dir);

        return [
            'content'      => $content,
            'thumbnail_id' => $thumbnail_id,
        ];
    }

    /**
     * Decide what portion of $html to remove for the featured-image extraction.
     *
     * If the <img> sits inside a simple hero-wrapper (<figure>, <picture>, or a
     * <div>/<a> whose contents are essentially just the image), the whole wrapper
     * is removed — otherwise the page would be left with an empty styled
     * container that introduces unwanted margins/padding.
     *
     * If no such wrapper is found (e.g. the image is a direct child of <article>),
     * only the <img> tag itself is removed.
     *
     * Returns ['start' => int, 'length' => int] suitable for substr_replace.
     */
    private function find_image_wrapper_range(string $html, int $img_offset, int $img_length): array {
        $default = ['start' => $img_offset, 'length' => $img_length];

        // Walk back from $img_offset to find the closest unmatched opening tag.
        // We don't need a real parser — we only care about the IMMEDIATE parent.
        // Scan backwards for the nearest `<tagname` opener; track close tags on the way.
        $before = substr($html, 0, $img_offset);

        // Find positions of all opening/closing tags before the image
        if (!preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $before, $matches, PREG_OFFSET_CAPTURE)) {
            return $default;
        }

        // Walk from the end backwards, balancing closers and openers
        $depth = 0;
        $parent_tag = null;
        $parent_start = -1;
        $parent_tag_end = -1; // end of opening tag, i.e. position right after '>'
        for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
            $is_closer = $matches[1][$i][0] === '/';
            $tag_name = strtolower($matches[2][$i][0]);
            $tag_start = $matches[0][$i][1];
            $tag_end = $tag_start + strlen($matches[0][$i][0]);

            // Skip self-closing/void elements — they cannot be parents
            if (in_array($tag_name, ['br', 'hr', 'img', 'meta', 'link', 'source', 'input', 'col'], true)) {
                continue;
            }

            if ($is_closer) {
                $depth++;
            } else {
                if ($depth === 0) {
                    // Found the unmatched opener — this is the parent
                    $parent_tag = $tag_name;
                    $parent_start = $tag_start;
                    $parent_tag_end = $tag_end;
                    break;
                }
                $depth--;
            }
        }

        if ($parent_tag === null) return $default;

        // Now find the matching closing tag of the parent.
        // Scan forward from $parent_tag_end, balancing nested same-named tags.
        $remaining = substr($html, $parent_tag_end);
        $offset = 0;
        $nested = 0;
        $parent_end = -1;
        while (preg_match('/<(\/?)' . preg_quote($parent_tag, '/') . '\b[^>]*>/i', $remaining, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $is_closer = $m[1][0] === '/';
            $match_start = $m[0][1];
            $match_len = strlen($m[0][0]);
            if ($is_closer) {
                if ($nested === 0) {
                    $parent_end = $parent_tag_end + $match_start + $match_len;
                    break;
                }
                $nested--;
            } else {
                $nested++;
            }
            $offset = $match_start + $match_len;
        }

        if ($parent_end === -1) return $default;

        $wrapper_html = substr($html, $parent_start, $parent_end - $parent_start);

        // Decide whether to delete the wrapper or just the image.
        if (!$this->wrapper_qualifies_for_removal($parent_tag, $wrapper_html)) {
            return $default;
        }

        // Also swallow any trailing whitespace/newlines so we don't leave a blank line behind
        $after_start = $parent_end;
        $after = substr($html, $after_start);
        if (preg_match('/^\s+/', $after, $ws_m)) {
            $parent_end += strlen($ws_m[0]);
        }

        return ['start' => $parent_start, 'length' => $parent_end - $parent_start];
    }

    /**
     * Wrapper qualifies for removal if:
     *   - <figure> or <picture>: always (semantic image containers)
     *   - <a>:                   always (image-link wrapper)
     *   - <div>/<section>/<span>: only if class hints at "hero/image/media/featured"
     *                            AND wrapper contains nothing but the image (no <p>,
     *                            <h1>-<h6>, or substantial text). This is conservative
     *                            on purpose — better to leave an unfamiliar div than
     *                            accidentally delete real content.
     *   - anything else:         no
     */
    private function wrapper_qualifies_for_removal(string $tag, string $wrapper_html): bool {
        if (in_array($tag, ['figure', 'picture', 'a'], true)) {
            return true;
        }
        if (!in_array($tag, ['div', 'section', 'span'], true)) {
            return false;
        }
        // Check class hint
        if (!preg_match('/<' . $tag . '[^>]*class=["\']([^"\']*)["\']/i', $wrapper_html, $class_m)) {
            return false;
        }
        $class = strtolower($class_m[1]);
        $hints = ['hero', 'image', 'media', 'featured', 'thumb', 'illustration', 'banner', 'cover'];
        $matched = false;
        foreach ($hints as $h) {
            if (strpos($class, $h) !== false) { $matched = true; break; }
        }
        if (!$matched) return false;

        // Check that wrapper has no real content besides the image:
        // strip all tags, see if there's any meaningful text left.
        $stripped = trim(strip_tags($wrapper_html));
        $len = function_exists('mb_strlen') ? mb_strlen($stripped) : strlen($stripped);
        if ($stripped !== '' && $len > 5) {
            // Has substantive text content — refuse to delete, preserve safely.
            return false;
        }
        return true;
    }

    /**
     * Process the HUB index.html: extract <main>, inject shortcodes, upload images.
     * Also extracts the first image as a thumbnail candidate for the home page.
     *
     * Returns ['content' => string, 'thumbnail_id' => int|null].
     */
    public function process_hub_main(string $html, string $hub_dir, array $shortcodes): array {
        // Identify the first <img> before any other processing — it becomes the home thumbnail.
        $thumbnail_id = null;
        if (preg_match('/<img[^>]+src=["\']([^"\'>]+)["\'][^>]*>/i', $html, $first_img)) {
            $src = $first_img[1];
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/i', $first_img[0], $alt_m)) {
                $alt = $alt_m[1];
            }
            $local_path = $this->resolve_image_path($src, $hub_dir);
            if ($local_path) {
                $attach_id = $this->media->upload_image($local_path, $alt);
                if ($attach_id) {
                    $thumbnail_id = $attach_id;
                }
            }
        }

        $html = $this->rewrite_inline_images($html, $hub_dir);

        if (!preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $main_m)) {
            return ['content' => '', 'thumbnail_id' => $thumbnail_id];
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

        return ['content' => $content, 'thumbnail_id' => $thumbnail_id];
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

        // Resolve in this order:
        //   1. As-written, relative to current_dir
        //      Covers src="images/hero.webp" and src="./images/hero.webp" — most common.
        //   2. current_dir/images/<basename>
        //      Covers src="hero.webp" where the file is actually in current_dir/images/.
        //      Seen in newer archives where the editorial team writes src without the
        //      images/ prefix even though the file lives there.
        //   3. fallback_dir/<basename>
        //      Covers HUB images referenced from non-HUB pages (e.g. shared logos).
        $candidates = [
            $current_dir . '/' . ltrim($src, '/'),
            $current_dir . '/images/' . basename($src),
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

    /**
     * Extract the meaningful content portion from an HTML document.
     *
     * Priority chain (preserves backward compatibility with old "fragment"-style archives):
     *   1. <main>...</main>     — modern full-page archives where headers/footers are siblings
     *                             of <main> and would otherwise duplicate the WordPress theme.
     *   2. <body>...</body>     — older archives whose body contained only the content fragment.
     *   3. Whole document       — minimal/fragment HTML with no body wrapper at all.
     */
    private function extract_body(string $html): string {
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $main_m)) {
            return trim($main_m[1]);
        }
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $body_m)) {
            return trim($body_m[1]);
        }
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<html[^>]*>|<\/html>/i', '', $html);
        $html = preg_replace('/<head>.*?<\/head>/is', '', $html);
        return trim($html);
    }
}
