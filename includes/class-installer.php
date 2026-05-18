<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates and maintains the plugin's database tables.
 */
class Site_Builder_Installer {

    public const DB_VERSION = '1.0';

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $imports_table = $wpdb->prefix . 'site_builder_imports';
        $items_table   = $wpdb->prefix . 'site_builder_import_items';

        $sql_imports = "CREATE TABLE $imports_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            folder_name VARCHAR(255) NOT NULL,
            settings LONGTEXT NULL,
            queue LONGTEXT NULL,
            processed_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_count INT UNSIGNED NOT NULL DEFAULT 0,
            current_phase VARCHAR(30) NULL,
            started_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            errors LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY status_idx (status)
        ) $charset;";

        $sql_items = "CREATE TABLE $items_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id BIGINT(20) UNSIGNED NOT NULL,
            item_type VARCHAR(30) NOT NULL,
            ref_id BIGINT(20) UNSIGNED NULL,
            ref_path TEXT NULL,
            ref_data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY import_idx (import_id),
            KEY type_idx (item_type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_imports);
        dbDelta($sql_items);

        update_option('site_builder_db_version', self::DB_VERSION);
    }

    public static function drop_tables(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}site_builder_imports");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}site_builder_import_items");
        delete_option('site_builder_db_version');
    }
}
