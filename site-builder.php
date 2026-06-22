<?php
/**
 * Plugin Name: Site Builder
 * Plugin URI:
 * Description: Инструмент массового импорта контента в WordPress. Создание сайтов с нуля и расширение существующих сайтов из подготовленных архивов.
 * Version: 1.1.2-beta5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Internal Tools
 * Text Domain: site-builder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SITE_BUILDER_VERSION', '1.1.2-beta5');
define('SITE_BUILDER_FILE', __FILE__);
define('SITE_BUILDER_PATH', plugin_dir_path(__FILE__));
define('SITE_BUILDER_URL', plugin_dir_url(__FILE__));
define('SITE_BUILDER_BASENAME', plugin_basename(__FILE__));
define('SITE_BUILDER_MENU_NAME', 'Main Auto Menu');
define('SITE_BUILDER_FOOTER_MENU_NAME', 'Footer Auto Menu');
define('SITE_BUILDER_THEME_CSS_DIR', 'imported-styles');
define('SITE_BUILDER_BATCH_SIZE', 15);

require_once SITE_BUILDER_PATH . 'includes/class-site-builder.php';

register_activation_hook(__FILE__, ['Site_Builder', 'activate']);
register_deactivation_hook(__FILE__, ['Site_Builder', 'deactivate']);

Site_Builder::get_instance();
