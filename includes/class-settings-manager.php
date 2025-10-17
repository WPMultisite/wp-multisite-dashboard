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
            isset($_POST["msd_settings_nonce"]) &&
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
            "msd_error_logs" => __("PHP Error Logs", "wp-multisite-dashboard"),
            "msd_404_monitor" => __("404 Monitor", "wp-multisite-dashboard"),
            // Integration widgets
            "msd_domain_mapping_overview" => __(
                "Domain Mapping Overview",
                "wp-multisite-dashboard"
            ),
        ];

        include WP_MSD_PLUGIN_DIR . "templates/settings-page.php";
    }

    private function save_settings()
    {
        // Aggressive output buffer cleaning before any processing
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering
        ob_start();
        
        $form_type = isset($_POST['msd_form']) ? sanitize_key($_POST['msd_form']) : '';

        // Handle plugin widget toggles when saving from the Widgets tab
        if ($form_type === 'widgets') {
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
                "msd_error_logs",
                "msd_404_monitor",
                "msd_domain_mapping_overview",
            ];

            foreach ($widget_options as $widget_id) {
                if (isset($_POST['widgets'][$widget_id])) {
                    $enabled_widgets[$widget_id] = 1;
                }
            }

            update_site_option('msd_enabled_widgets', $enabled_widgets);
        }

        // Handle integration widgets when saving from the Integrations tab
        if ($form_type === 'integrations') {
            $current_enabled = get_site_option('msd_enabled_widgets', []);
            
            // Integration widget IDs
            $integration_widget_ids = [
                'msd_domain_mapping_overview',
                // Future integration widgets can be added here
            ];
            
            // Update only integration widgets, preserve other widgets
            foreach ($integration_widget_ids as $widget_id) {
                if (isset($_POST['integration_widgets'][$widget_id])) {
                    $current_enabled[$widget_id] = 1;
                } else {
                    unset($current_enabled[$widget_id]);
                }
            }
            
            update_site_option('msd_enabled_widgets', $current_enabled);
        }

        // Handle system/third-party widgets when saving from the System tab
        if ($form_type === 'system') {
            $disabled_system_widgets = [];
            $all_available_widgets = $this->get_all_possible_widgets();

            if (isset($_POST['system_widgets']) && is_array($_POST['system_widgets'])) {
                foreach ($all_available_widgets as $widget_id => $widget_data) {
                    if (!($widget_data['is_custom'] ?? false) && !isset($_POST['system_widgets'][$widget_id])) {
                        $disabled_system_widgets[] = $widget_id;
                    }
                }
            } else {
                foreach ($all_available_widgets as $widget_id => $widget_data) {
                    if (!($widget_data['is_custom'] ?? false)) {
                        $disabled_system_widgets[] = $widget_id;
                    }
                }
            }

            update_site_option('msd_disabled_system_widgets', $disabled_system_widgets);
        }

        // Aggressive output buffer cleaning before redirect
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure no output has been sent
        if (!headers_sent()) {
            // Redirect back with success flag and preserve the current tab
            $current_tab = isset($_POST['current_tab']) ? sanitize_key($_POST['current_tab']) : 'widgets';
            $redirect_args = [
                'page' => 'msd-settings',
                'updated' => 'true',
                'tab' => $current_tab
            ];
            wp_safe_redirect(add_query_arg($redirect_args, network_admin_url('settings.php')));
            exit;
        } else {
            // If headers already sent, try to determine where output came from and handle gracefully
            $current_tab = isset($_POST['current_tab']) ? sanitize_key($_POST['current_tab']) : 'widgets';
            $redirect_url = add_query_arg([
                'page' => 'msd-settings',
                'updated' => 'true',
                'tab' => $current_tab
            ], network_admin_url('settings.php'));
            
            // Use meta refresh as a more reliable fallback
            echo '<!DOCTYPE html><html><head>';
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '">';
            echo '<script type="text/javascript">window.location.href = "' . esc_url($redirect_url) . '";</script>';
            echo '</head><body>';
            echo '<p>Settings saved. <a href="' . esc_url($redirect_url) . '">Click here if you are not redirected automatically</a>.</p>';
            echo '</body></html>';
            exit;
        }
    }

    public function get_available_system_widgets()
    {
        $cached_widgets = get_site_transient("msd_detected_widgets");
        $known_system_widgets = $this->get_known_system_widgets();

        if ($cached_widgets && is_array($cached_widgets)) {
            // 合并并去重
            $merged_widgets = array_merge($known_system_widgets, $cached_widgets);

            // 添加检测时间戳
            $detection_time = get_site_option('msd_last_widget_detection', 0);
            if ($detection_time) {
                foreach ($merged_widgets as &$widget) {
                    if (!isset($widget['detected_at'])) {
                        $widget['detected_at'] = $detection_time;
                    }
                }
            }

            return $merged_widgets;
        }

        // 如果没有缓存的小工具，在设置页避免触发检测，直接返回已知系统小工具以防超时/空白
        $screen_id = '';
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
        }
        if ($screen_id === 'settings_page_msd-settings') {
            return $known_system_widgets;
        }

        // 非设置页，再尝试静默检测
        $this->try_silent_detection();

        // 再次尝试获取缓存
        $cached_widgets = get_site_transient("msd_detected_widgets");
        if ($cached_widgets && is_array($cached_widgets)) {
            return array_merge($known_system_widgets, $cached_widgets);
        }

        return $known_system_widgets;
    }

    /**
     * 尝试静默检测小工具（不阻塞页面加载）
     */
    private function try_silent_detection()
    {
        // 检查是否最近已经尝试过检测
        $last_attempt = get_site_option('msd_last_detection_attempt', 0);
        $current_time = time();

        // 如果5分钟内已经尝试过，则跳过
        if (($current_time - $last_attempt) < 300) {
            return;
        }

        // 更新尝试时间
        update_site_option('msd_last_detection_attempt', $current_time);

        // 直接进行轻量级检测，避免异步复杂性
        $this->perform_lightweight_detection();
    }

    private function perform_lightweight_detection()
    {
        try {
            // 不在设置页面执行检测，因为wp_add_dashboard_widget()不可用
            // 检测将在用户访问仪表板时自动进行
            global $pagenow;
            if (isset($pagenow) && $pagenow === 'settings.php') {
                return;
            }

            // 只检测网络级小工具，避免内存问题
            $plugin_core = WP_MSD_Plugin_Core::get_instance();
            $detected_widgets = $plugin_core->detect_network_widgets();
            
            if (!empty($detected_widgets)) {
                set_site_transient('msd_detected_widgets', $detected_widgets, 12 * HOUR_IN_SECONDS);
                update_site_option('msd_last_widget_detection', time());
            }
        } catch (Exception $e) {
            // 静默失败，记录到错误日志
            error_log('MSD: Silent widget detection failed - ' . $e->getMessage());
        }
    }

    /**
     * 获取小工具检测统计信息
     */
    public function get_widget_detection_stats()
    {
        $available_widgets = $this->get_available_system_widgets();
        $last_detection = get_site_option('msd_last_widget_detection', 0);

        $stats = [
            'total_widgets' => count($available_widgets),
            'system_widgets' => count(array_filter($available_widgets, function ($widget) {
                return $widget['is_system'] ?? false;
            })),
            'third_party_widgets' => count(array_filter($available_widgets, function ($widget) {
                return !($widget['is_system'] ?? false) && !($widget['is_custom'] ?? false);
            })),
            'last_detection' => $last_detection,
            'last_detection_human' => $last_detection ? human_time_diff($last_detection) . ' ago' : 'Never'
        ];

        return $stats;
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
            "msd_error_logs" => __(
                "Monitor PHP error logs with filtering and search capabilities",
                "wp-multisite-dashboard"
            ),
            "msd_404_monitor" => __(
                "Track 404 errors to identify broken links and improve SEO",
                "wp-multisite-dashboard"
            ),
            "msd_domain_mapping_overview" => __(
                "Network-wide domain mapping statistics, health monitoring, and quick access to domain management (requires WP Domain Mapping plugin)",
                "wp-multisite-dashboard"
            ),
        ];

        return $descriptions[$widget_id] ?? "";
    }

    /**
     * Export all plugin settings to JSON format
     */
    public function export_settings()
    {
        if (!current_user_can('manage_network')) {
            wp_die(__('Insufficient permissions', 'wp-multisite-dashboard'));
        }

        $export_data = [
            'version' => WP_MSD_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => network_home_url(),
            'settings' => $this->get_all_settings()
        ];

        $filename = 'msd-settings-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Import settings from uploaded JSON file
     */
    public function import_settings()
    {
        if (!current_user_can('manage_network')) {
            wp_die(__('Insufficient permissions', 'wp-multisite-dashboard'));
        }

        if (!wp_verify_nonce($_POST['msd_import_nonce'], 'msd_import_settings')) {
            wp_die(__('Invalid nonce', 'wp-multisite-dashboard'));
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg([
                'page' => 'msd-settings',
                'import_error' => 'file_error'
            ], network_admin_url('settings.php')));
            exit;
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_safe_redirect(add_query_arg([
                'page' => 'msd-settings',
                'import_error' => 'invalid_json'
            ], network_admin_url('settings.php')));
            exit;
        }

        if (!$this->validate_import_data($import_data)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'msd-settings',
                'import_error' => 'invalid_format'
            ], network_admin_url('settings.php')));
            exit;
        }

        $this->apply_imported_settings($import_data['settings']);

        wp_safe_redirect(add_query_arg([
            'page' => 'msd-settings',
            'import_success' => 'true'
        ], network_admin_url('settings.php')));
        exit;
    }

    /**
     * Get all plugin settings for export
     */
    private function get_all_settings()
    {
        return [
            'enabled_widgets' => get_site_option('msd_enabled_widgets', []),
            'disabled_system_widgets' => get_site_option('msd_disabled_system_widgets', []),
            'quick_links' => get_site_option('msd_quick_links', []),
            'news_sources' => get_site_option('msd_news_sources', []),
            'contact_info' => get_site_option('msd_contact_info', []),
            'detected_widgets' => get_site_transient('msd_detected_widgets') ?: []
        ];
    }

    /**
     * Validate imported data structure
     */
    private function validate_import_data($data)
    {
        if (!is_array($data)) {
            return false;
        }

        $required_keys = ['version', 'export_date', 'settings'];
        foreach ($required_keys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        if (!is_array($data['settings'])) {
            return false;
        }

        return true;
    }

    /**
     * Apply imported settings
     */
    private function apply_imported_settings($settings)
    {
        $safe_options = [
            'msd_enabled_widgets',
            'msd_disabled_system_widgets',
            'msd_quick_links',
            'msd_news_sources',
            'msd_contact_info'
        ];

        foreach ($safe_options as $option_key) {
            $setting_key = str_replace('msd_', '', $option_key);
            if (isset($settings[$setting_key])) {
                update_site_option($option_key, $settings[$setting_key]);
            }
        }

        // Update detected widgets cache if provided
        if (isset($settings['detected_widgets']) && !empty($settings['detected_widgets'])) {
            set_site_transient('msd_detected_widgets', $settings['detected_widgets'], 12 * HOUR_IN_SECONDS);
        }

        // Clear other caches to ensure fresh data
        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $plugin_core->invalidate_widget_cache();
    }
}
