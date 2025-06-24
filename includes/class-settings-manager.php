<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Settings_Manager {

    public function render_settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['msd_settings_nonce'], 'msd_settings')) {
            $this->save_settings();
            return;
        }

        $widget_options = [
            'msd_network_overview' => 'Network Overview',
            'msd_quick_site_management' => 'Quick Site Management',
            'msd_storage_performance' => 'Storage Usage',
            'msd_server_info' => 'Server Information',
            'msd_quick_links' => 'Quick Links',
            'msd_version_info' => 'Version Information',
            'msd_custom_news' => 'Network News',
            'msd_user_management' => 'User Management',
            'msd_contact_info' => 'Contact Information',
            'msd_last_edits' => 'Recent Network Activity',
            'msd_todo_widget' => 'Todo List'
        ];

        include WP_MSD_PLUGIN_DIR . 'templates/settings-page.php';
    }

    private function save_settings() {
        $enabled_widgets = [];
        $widget_options = [
            'msd_network_overview',
            'msd_quick_site_management',
            'msd_storage_performance',
            'msd_server_info',
            'msd_quick_links',
            'msd_version_info',
            'msd_custom_news',
            'msd_user_management',
            'msd_contact_info',
            'msd_last_edits',
            'msd_todo_widget'
        ];

        foreach ($widget_options as $widget_id) {
            if (isset($_POST['widgets'][$widget_id])) {
                $enabled_widgets[$widget_id] = 1;
            }
        }

        update_site_option('msd_enabled_widgets', $enabled_widgets);

        $disabled_system_widgets = [];
        if (isset($_POST['system_widgets']) && is_array($_POST['system_widgets'])) {
            $available_widgets = $this->get_available_system_widgets();
            foreach ($available_widgets as $widget_id => $widget_data) {
                if (!$widget_data['is_custom'] && !isset($_POST['system_widgets'][$widget_id])) {
                    $disabled_system_widgets[] = $widget_id;
                }
            }
        }

        update_site_option('msd_disabled_system_widgets', $disabled_system_widgets);

        wp_safe_redirect(add_query_arg('updated', 'true', network_admin_url('settings.php?page=msd-settings')));
        exit;
    }

    public function get_available_system_widgets() {
        $known_system_widgets = [
            'network_dashboard_right_now' => [
                'id' => 'network_dashboard_right_now',
                'title' => 'Right Now',
                'context' => 'normal',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => true
            ],
            'dashboard_activity' => [
                'id' => 'dashboard_activity',
                'title' => 'Activity',
                'context' => 'normal',
                'priority' => 'high',
                'is_custom' => false,
                'is_system' => true
            ],
            'dashboard_plugins' => [
                'id' => 'dashboard_plugins',
                'title' => 'Plugins',
                'context' => 'normal',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => true
            ],
            'dashboard_primary' => [
                'id' => 'dashboard_primary',
                'title' => 'WordPress Events and News',
                'context' => 'side',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => true
            ],
            'dashboard_secondary' => [
                'id' => 'dashboard_secondary',
                'title' => 'Other WordPress News',
                'context' => 'side',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => true
            ]
        ];

        $available_widgets = $known_system_widgets;

        $cached_widgets = get_site_transient('msd_detected_widgets');
        if ($cached_widgets && is_array($cached_widgets)) {
            foreach ($cached_widgets as $widget_id => $widget_data) {
                if (!isset($available_widgets[$widget_id])) {
                    $available_widgets[$widget_id] = $widget_data;
                }
            }
        }

        return $available_widgets;
    }

    public function get_widget_description($widget_id) {
        $descriptions = [
            'msd_network_overview' => __('Network statistics and multisite configuration information', 'wp-multisite-dashboard'),
            'msd_quick_site_management' => __('Quick access to recently active sites with favicons', 'wp-multisite-dashboard'),
            'msd_storage_performance' => __('Top 5 sites by storage usage and performance insights', 'wp-multisite-dashboard'),
            'msd_server_info' => __('Server specifications and WordPress environment details', 'wp-multisite-dashboard'),
            'msd_quick_links' => __('Customizable quick access links for common tasks with drag-and-drop reordering', 'wp-multisite-dashboard'),
            'msd_version_info' => __('Plugin version and system information with help links', 'wp-multisite-dashboard'),
            'msd_custom_news' => __('Custom news sources and updates', 'wp-multisite-dashboard'),
            'msd_network_settings' => __('Network configuration and settings overview', 'wp-multisite-dashboard'),
            'msd_user_management' => __('Recent user registrations and user management tools', 'wp-multisite-dashboard'),
            'msd_contact_info' => __('Network administrator contact information with instant messaging and QR code support', 'wp-multisite-dashboard'),
            'msd_last_edits' => __('Recent posts, pages, and content activity across the network', 'wp-multisite-dashboard'),
            'msd_todo_widget' => __('Simple todo list for network administrators with priority levels', 'wp-multisite-dashboard')
        ];

        return $descriptions[$widget_id] ?? '';
    }
}
