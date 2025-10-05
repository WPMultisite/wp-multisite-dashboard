<?php

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Settings_Manager
{
    public function render_settings_page()
    {
        if (
            isset($_POST["submit"]) &&
            wp_verify_nonce($_POST["msd_settings_nonce"], "msd_settings")
        ) {
            $this->save_settings();
            return;
        }

        $widget_options = [
            "msd_network_overview" => __(
                "Network Overview",
                "wp-multisite-dashboard"
            ),
            "msd_quick_site_management" => __(
                "Quick Site Management",
                "wp-multisite-dashboard"
            ),
            "msd_storage_performance" => __(
                "Storage Usage",
                "wp-multisite-dashboard"
            ),
            "msd_server_info" => __(
                "Server Information",
                "wp-multisite-dashboard"
            ),
            "msd_quick_links" => __("Quick Links", "wp-multisite-dashboard"),
            "msd_version_info" => __(
                "Version Information",
                "wp-multisite-dashboard"
            ),
            "msd_custom_news" => __("Network News", "wp-multisite-dashboard"),
            "msd_user_management" => __(
                "User Management",
                "wp-multisite-dashboard"
            ),
            "msd_contact_info" => __(
                "Contact Information",
                "wp-multisite-dashboard"
            ),
            "msd_last_edits" => __(
                "Recent Network Activity",
                "wp-multisite-dashboard"
            ),
            "msd_todo_widget" => __("Todo List", "wp-multisite-dashboard"),
        ];

        include WP_MSD_PLUGIN_DIR . "templates/settings-page.php";
    }

    private function save_settings()
    {
        if (!current_user_can('manage_network')) {
            wp_die(__('Insufficient permissions', 'wp-multisite-dashboard'));
        }

        $enabled_widgets = [];
        $widget_options = [
            "msd_network_overview",
            "msd_quick_site_management",
            "msd_storage_performance",
            "msd_server_info",
            "msd_quick_links",
            "msd_version_info",
            "msd_custom_news",
            "msd_user_management",
            "msd_contact_info",
            "msd_last_edits",
            "msd_todo_widget",
        ];

        foreach ($widget_options as $widget_id) {
            if (isset($_POST["widgets"][$widget_id])) {
                $enabled_widgets[$widget_id] = 1;
            }
        }

        update_site_option("msd_enabled_widgets", $enabled_widgets);

        $disabled_system_widgets = [];
        $all_available_widgets = $this->get_all_possible_widgets();

        // Sanitize and whitelist submitted system widgets
        $submitted_system = [];
        if (isset($_POST["system_widgets"]) && is_array($_POST["system_widgets"])) {
            $allowed_ids = array_keys($all_available_widgets);
            foreach (array_keys($_POST["system_widgets"]) as $id) {
                $id = sanitize_text_field($id);
                if (in_array($id, $allowed_ids, true)) {
                    $submitted_system[$id] = 1;
                }
            }
        }

        foreach ($all_available_widgets as $widget_id => $widget_data) {
            $is_custom = $widget_data["is_custom"] ?? false;
            if (!$is_custom && !isset($submitted_system[$widget_id])) {
                $disabled_system_widgets[] = $widget_id;
            }
        }

        update_site_option(
            "msd_disabled_system_widgets",
            $disabled_system_widgets
        );

        // Performance settings: storage scan site limit
        $scan_limit = isset($_POST['storage_scan_site_limit']) ? intval($_POST['storage_scan_site_limit']) : 100;
        if ($scan_limit < 10) { $scan_limit = 10; }
        if ($scan_limit > 2000) { $scan_limit = 2000; }
        update_site_option('msd_storage_scan_site_limit', $scan_limit);

        // Clear storage widget cache to reflect new scanning configuration
        $network_data = new WP_MSD_Network_Data();
        $network_data->clear_widget_cache('storage_data');

        wp_safe_redirect(
            add_query_arg(
                "updated",
                "true",
                network_admin_url("settings.php?page=msd-settings")
            )
        );
        exit();
    }

    public function get_available_system_widgets()
    {
        $cached_widgets = get_site_transient("msd_detected_widgets");
        $known_system_widgets = $this->get_known_system_widgets();

        if ($cached_widgets && is_array($cached_widgets)) {
            return array_merge($known_system_widgets, $cached_widgets);
        }

        return $known_system_widgets;
    }

    private function get_known_system_widgets()
    {
        return [
            "network_dashboard_right_now" => [
                "id" => "network_dashboard_right_now",
                "title" => __("Right Now", "wp-multisite-dashboard"),
                "context" => "normal",
                "priority" => "core",
                "is_custom" => false,
                "is_system" => true,
            ],
            "dashboard_activity" => [
                "id" => "dashboard_activity",
                "title" => __("Activity", "wp-multisite-dashboard"),
                "context" => "normal",
                "priority" => "high",
                "is_custom" => false,
                "is_system" => true,
            ],
            "dashboard_plugins" => [
                "id" => "dashboard_plugins",
                "title" => __("Plugins", "wp-multisite-dashboard"),
                "context" => "normal",
                "priority" => "core",
                "is_custom" => false,
                "is_system" => true,
            ],
            "dashboard_primary" => [
                "id" => "dashboard_primary",
                "title" => __(
                    "WordPress Events and News",
                    "wp-multisite-dashboard"
                ),
                "context" => "side",
                "priority" => "core",
                "is_custom" => false,
                "is_system" => true,
            ],
            "dashboard_secondary" => [
                "id" => "dashboard_secondary",
                "title" => __("Other WordPress News", "wp-multisite-dashboard"),
                "context" => "side",
                "priority" => "core",
                "is_custom" => false,
                "is_system" => true,
            ],
        ];
    }

    private function get_all_possible_widgets()
    {
        $known_widgets = $this->get_known_system_widgets();
        $cached_widgets = get_site_transient("msd_detected_widgets");
        $stored_disabled = get_site_option("msd_disabled_system_widgets", []);

        $all_widgets = $known_widgets;

        if ($cached_widgets && is_array($cached_widgets)) {
            foreach ($cached_widgets as $widget_id => $widget_data) {
                if (!isset($all_widgets[$widget_id])) {
                    $all_widgets[$widget_id] = $widget_data;
                }
            }
        }

        foreach ($stored_disabled as $widget_id) {
            if (!isset($all_widgets[$widget_id])) {
                $all_widgets[$widget_id] = [
                    "id" => $widget_id,
                    "title" => $this->generate_widget_title_from_id($widget_id),
                    "context" => "unknown",
                    "priority" => "default",
                    "is_custom" => false,
                    "is_system" => false,
                ];
            }
        }

        return $all_widgets;
    }

    private function generate_widget_title_from_id($widget_id)
    {
        $title = str_replace(["_", "-"], " ", $widget_id);
        $title = ucwords($title);
        return $title;
    }

    public function get_widget_description($widget_id)
    {
        $descriptions = [
            "msd_network_overview" => __(
                "Network statistics and multisite configuration information",
                "wp-multisite-dashboard"
            ),
            "msd_quick_site_management" => __(
                "Quick access to recently active sites with favicons",
                "wp-multisite-dashboard"
            ),
            "msd_storage_performance" => __(
                "Top 5 sites by storage usage and performance insights",
                "wp-multisite-dashboard"
            ),
            "msd_server_info" => __(
                "Server specifications and WordPress environment details",
                "wp-multisite-dashboard"
            ),
            "msd_quick_links" => __(
                "Customizable quick access links for common tasks with drag-and-drop reordering",
                "wp-multisite-dashboard"
            ),
            "msd_version_info" => __(
                "Plugin version and system information with help links",
                "wp-multisite-dashboard"
            ),
            "msd_custom_news" => __(
                "Custom news sources and updates",
                "wp-multisite-dashboard"
            ),
            "msd_network_settings" => __(
                "Network configuration and settings overview",
                "wp-multisite-dashboard"
            ),
            "msd_user_management" => __(
                "Recent user registrations and user management tools",
                "wp-multisite-dashboard"
            ),
            "msd_contact_info" => __(
                "Network administrator contact information with instant messaging and QR code support",
                "wp-multisite-dashboard"
            ),
            "msd_last_edits" => __(
                "Recent posts, pages, and content activity across the network",
                "wp-multisite-dashboard"
            ),
            "msd_todo_widget" => __(
                "Simple todo list for network administrators with priority levels",
                "wp-multisite-dashboard"
            ),
        ];

        return $descriptions[$widget_id] ?? "";
    }
}
