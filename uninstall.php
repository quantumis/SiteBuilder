<?php
/**
 * Runs when the plugin is deleted from the admin (not on deactivation).
 * Drops plugin tables and removes all plugin options.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-installer.php';
Site_Builder_Installer::drop_tables();

delete_option('site_builder_version');
delete_option('site_builder_db_version');
delete_option('site_builder_active_import');
delete_option('site_builder_activated_at');
delete_option('site_builder_pristine_theme_files');
