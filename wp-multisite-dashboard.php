<?php
/**
 * Plugin Name: WP Multisite Dashboard
 * Plugin URI: https://wpmultisite.com/plugins/wp-multisite-dashboard
 * Description: Essential dashboard widgets for WordPress multisite administrators
 * Version: 1.4.2
 * Author: WPMultisite.com
 * Author URI: https://WPMultisite.com
 * License: GPLv2+
 * Text Domain: wp-multisite-dashboard
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

if (!defined("ABSPATH")) {
    exit();
}

// AJAX output management - aggressive cleanup for MSD requests
if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'msd_') === 0) {
    // Ultra-early cleanup
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Additional cleanup hooks as backup
    add_action('init', function() {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }, 1);
    
    add_action('admin_init', function() {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }, 1);
}

// Also handle regular form submissions for settings
if (isset($_POST['submit']) && isset($_POST['msd_settings_nonce'])) {
    add_action('init', function() {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }, 1);
}

define("WP_MSD_VERSION", "1.4.2");
define("WP_MSD_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("WP_MSD_PLUGIN_URL", plugin_dir_url(__FILE__));

require_once WP_MSD_PLUGIN_DIR . "includes/class-error-handler.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-performance-manager.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-background-tasks.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-helpers.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-network-data.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-user-manager.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-ajax-handler.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-admin-interface.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-settings-manager.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-error-log-manager.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-404-monitor.php";
require_once WP_MSD_PLUGIN_DIR . "includes/class-plugin-core.php";

function wp_msd_init()
{
    // Clean any output that might have been generated during plugin loading
    if (is_admin() && (defined('DOING_AJAX') && DOING_AJAX || isset($_POST['submit']))) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }
    
    if (!is_multisite()) {
        add_action("admin_notices", "wp_msd_multisite_required_notice");
        return;
    }

    WP_MSD_Plugin_Core::get_instance();
    WP_MSD_Background_Tasks::get_instance();
}

function wp_msd_multisite_load_textdomain()
{
    $plugin_rel_path = dirname(plugin_basename(__FILE__)) . "/languages/";
    load_plugin_textdomain("wp-multisite-dashboard", false, $plugin_rel_path);
}
add_action("plugins_loaded", "wp_msd_multisite_load_textdomain");

function wp_msd_multisite_required_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo __(
        "WP Multisite Dashboard requires WordPress Multisite to be enabled.",
        "wp-multisite-dashboard"
    );
    echo "</p></div>";
}

register_activation_hook(__FILE__, "wp_msd_activation");
function wp_msd_activation()
{
    if (!is_multisite()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "This plugin requires WordPress Multisite to be enabled.",
                "wp-multisite-dashboard"
            )
        );
    }

    $network_data = new WP_MSD_Network_Data();
    $network_data->create_activity_log_table();
    
    // Create 404 monitor table
    WP_MSD_404_Monitor::create_table();

    set_site_transient("msd_activation_notice", true, 30);
}

add_action("network_admin_notices", "wp_msd_activation_notice");
function wp_msd_activation_notice()
{
    if (get_site_transient("msd_activation_notice")) {
        echo '<div class="notice notice-success is-dismissible">';
        echo "<p>" .
            __(
                "WP Multisite Dashboard has been activated successfully!",
                "wp-multisite-dashboard"
            ) .
            "</p>";
        echo "</div>";
        delete_site_transient("msd_activation_notice");
    }
}

add_action("plugins_loaded", "wp_msd_init");
