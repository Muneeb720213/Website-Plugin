<?php
/**
 * Plugin Name: Systeme.io Hook Sync
 * Plugin URI: https://yourwebsite.com/systemeio-hook-sync
 * Description: Sync WordPress form submissions and user registrations with Systeme.io CRM, automatically adding/removing tags based on triggered actions.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: systemeio-hook-sync
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('SYSTEMEIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SYSTEMEIO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SYSTEMEIO_PLUGIN_VERSION', '1.0.0');
define('SYSTEMEIO_LOG_LIMIT', 100);

// Check if dependencies are loaded
if (!function_exists('wp_remote_post')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Systeme.io Hook Sync requires WordPress HTTP API functions. Please contact your hosting provider.', 'systemeio-hook-sync');
        echo '</p></div>';
    });
    return;
}

// Include required files
$required_files = [
    'includes/class-systemeio-api.php',
    'includes/class-hook-logger.php',
    'includes/class-tag-manager.php',
    'includes/class-admin-settings.php',
    'includes/helper-functions.php'
];

foreach ($required_files as $file) {
    if (file_exists(SYSTEMEIO_PLUGIN_DIR . $file)) {
        require_once SYSTEMEIO_PLUGIN_DIR . $file;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>';
            printf(
                esc_html__('Systeme.io Hook Sync is missing required file: %s', 'systemeio-hook-sync'),
                esc_html($file)
            );
            echo '</p></div>';
        });
        return;
    }
}

// Initialize plugin classes
function systemeio_hook_sync_init() {
    $api = new SystemeIO_API();
    $hook_logger = new SystemeIO_Hook_Logger();
    $tag_manager = new SystemeIO_Tag_Manager($api);
    $admin_settings = new SystemeIO_Admin_Settings($api, $hook_logger, $tag_manager);
    
    register_activation_hook(__FILE__, array($admin_settings, 'activate'));
    register_deactivation_hook(__FILE__, array($admin_settings, 'deactivate'));
    register_uninstall_hook(__FILE__, array('SystemeIO_Admin_Settings', 'uninstall'));
}
add_action('plugins_loaded', 'systemeio_hook_sync_init');

// Load text domain
function systemeio_hook_sync_load_textdomain() {
    load_plugin_textdomain(
        'systemeio-hook-sync',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('init', 'systemeio_hook_sync_load_textdomain');