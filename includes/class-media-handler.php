<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uploads images to the media library, deduplicating by filename.
 * Every newly-uploaded attachment is tracked for rollback.
 */
class Site_Builder_Media_Handler {

    private Site_Builder_Import_Tracker $tracker;
    private int $import_id;

    public function __construct(Site_Builder_Import_Tracker $tracker, int $import_id) {
        $this->tracker = $tracker;
        $this->import_id = $import_id;

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
    }

    /**
     * Upload an image to the media library. Returns attachment ID or null.
     *
     * Deduplication is by content hash (md5_file), not filename — this is critical
     * because archives commonly contain many files named "hero.webp" with completely
     * different content (one per page folder). Slug-based dedup would treat them as
     * the same image and assign the wrong thumbnail to every page.
     */
    public function upload_image(string $image_path, string $alt_text = ''): ?int {
        if (!file_exists($image_path) || !is_readable($image_path)) {
            return null;
        }

        $hash = md5_file($image_path);

        // 1. Look up by content hash among previously-uploaded attachments
        if ($hash) {
            global $wpdb;
            $existing_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sb_file_hash' AND meta_value = %s
                 LIMIT 1",
                $hash
            ));
            if ($existing_id) {
                $post = get_post($existing_id);
                if ($post && $post->post_type === 'attachment') {
                    if ($alt_text) {
                        update_post_meta($existing_id, '_wp_attachment_image_alt', $alt_text);
                    }
                    return $existing_id;
                }
            }
        }

        // 2. Upload as a new attachment. WordPress will auto-suffix the filename
        //    if one with the same name already exists in /uploads/ (e.g. hero-1.webp).
        $filename = basename($image_path);
        $name_no_ext = pathinfo($filename, PATHINFO_FILENAME);

        $contents = @file_get_contents($image_path);
        if ($contents === false) return null;

        $upload = wp_upload_bits($filename, null, $contents);
        if (!empty($upload['error'])) return null;

        $filetype = wp_check_filetype($upload['file'], null);
        $mime = $filetype['type'] ?: 'application/octet-stream';

        $attach_id = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title'     => sanitize_file_name($name_no_ext),
            'post_status'    => 'inherit',
            'post_content'   => '',
        ], $upload['file']);

        if (!$attach_id || is_wp_error($attach_id)) return null;

        // Performance: on slow shared hosting, wp_generate_attachment_metadata
        // is often the biggest per-page cost — for a large webp it can spawn
        // 5-8 thumbnail sizes, each requiring a full GD resample. On Hostinger
        // that adds up to 30-60 seconds for a page with 3 images, which
        // triggers the nginx 504 timeout.
        //
        // We temporarily restrict the size set to just thumbnail + medium
        // during import. These are the only sizes affiliate content typically
        // uses (thumbnail in the admin listing, medium in inline content).
        // Other sizes (medium_large, large, custom theme sizes) can be
        // regenerated later via a plugin like "Regenerate Thumbnails" if
        // ever needed — but 99% of the time they aren't.
        //
        // Filter is applied only around this one call, then removed, so the
        // WordPress admin behaves normally outside of imports.
        $size_filter = function ($sizes) {
            return array_intersect_key($sizes, ['thumbnail' => 1, 'medium' => 1]);
        };
        add_filter('intermediate_image_sizes_advanced', $size_filter, 10, 1);
        $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
        remove_filter('intermediate_image_sizes_advanced', $size_filter, 10);

        wp_update_attachment_metadata($attach_id, $metadata);

        if ($alt_text) {
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
        }
        if ($hash) {
            update_post_meta($attach_id, '_sb_file_hash', $hash);
        }

        $this->tracker->track_item($this->import_id, 'attachment', (int)$attach_id);
        return (int)$attach_id;
    }
}
