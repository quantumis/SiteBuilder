<?php
/**
 * Runs when the plugin is deleted from the admin (not on deactivation).
 * Cleans up plugin-specific options. Will expand in later stages.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('site_builder_version');
delete_option('site_builder_activated_at');
