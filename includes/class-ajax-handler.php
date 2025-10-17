<?php

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Ajax_Handler
{
    public function __construct()
    {
        $this->register_ajax_actions();
        
        // Ultra-aggressive output cleaning for MSD AJAX requests
        if (isset($_POST['action']) && strpos($_POST['action'], 'msd_') === 0) {
            // Immediate cleanup
            $this->force_clean_output();
            
            // Hook into multiple early actions
            add_action('init', [$this, 'clean_output_buffer'], 1);
            add_action('wp_loaded', [$this, 'clean_output_buffer'], 1);
            add_action('admin_init', [$this, 'clean_output_buffer'], 1);
            
            // Also clean before each AJAX action
            add_action('wp_ajax_' . $_POST['action'], [$this, 'force_clean_output'], 1);
        }
    }

    /**
     * Clean output buffer before sending AJAX response
     */
    public function clean_output_buffer()
    {
        // Aggressive output buffer cleaning
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering if we're in an AJAX request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            ob_start();
        }
    }

    /**
     * Force clean all output - ultra-aggressive method
     */
    public function force_clean_output()
    {
        // Clean all output buffers aggressively
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Capture any remaining output
        ob_start();
        
        // Use output control to prevent any unwanted output
        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(false);
        }
    }

    /**
     * Send clean JSON response
     */
    public function send_clean_json_response($success, $data = null)
    {
        // Clean output one more time
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh for JSON output
        ob_start();
        
        // Send response and exit (headers already set in method)
        if ($success) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error($data);
        }
    }

    private function register_ajax_actions()
    {
        $ajax_actions = [
            "msd_get_network_overview",
            "msd_get_site_list",
            "msd_get_storage_data",
            "msd_get_server_info",
            "msd_get_version_info",
            "msd_get_custom_news",
            "msd_get_network_settings",
            "msd_get_user_management",
            "msd_get_last_edits",
            "msd_get_todo_items",
            "msd_save_news_sources",
            "msd_save_quick_links",
            "msd_save_contact_info",
            "msd_save_todo_item",
            "msd_update_todo_item",
            "msd_delete_todo_item",
            "msd_toggle_todo_complete",
            "msd_reorder_quick_links",
            "msd_toggle_widget",
            "msd_refresh_widget_data",
            "msd_clear_cache",
            "msd_manage_user_action",
            "msd_check_plugin_update",
            "msd_clear_widget_cache",
            "msd_force_widget_detection",
            "msd_validate_import_file",
            "msd_get_cache_status",
            "msd_get_performance_stats",
            "msd_get_memory_stats",
            "msd_optimize_cache",
            "msd_analyze_database",
            "msd_optimize_database",
            "msd_get_widget_list",
            "msd_get_error_log",
            "msd_clear_error_log",
            "msd_get_error_logs",
            "msd_clear_error_logs",
            "msd_get_404_stats",
            "msd_toggle_404_monitoring",
            "msd_clear_404_logs",
        ];

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_{$action}", [
                $this,
                str_replace("msd_", "", $action),
            ]);
        }
        
        // Add early hook to clean output buffer for critical AJAX requests
        add_action('wp_ajax_msd_toggle_widget', [$this, 'prepare_ajax_response'], 1);
        add_action('wp_ajax_msd_get_widget_list', [$this, 'prepare_ajax_response'], 1);
    }
    
    /**
     * Prepare AJAX response by cleaning output buffer
     */
    public function prepare_ajax_response()
    {
        // Clean any output that might have been generated
        $this->clean_output_buffer();
        
        // Start fresh output buffering
        if (ob_get_level() == 0) {
            ob_start();
        }
    }

    public function get_network_overview()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $overview = [
                "total_posts" => $network_data->get_total_posts(),
                "total_pages" => $network_data->get_total_pages(),
                "multisite_config" => $network_data->get_multisite_configuration(),
                "network_info" => $network_data->get_network_information(),
                "critical_alerts" => 0,
                "network_status" => $network_data->get_overall_network_status(),
                "last_updated" => current_time("mysql"),
            ];


            wp_send_json_success($overview);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load network overview", "wp-multisite-dashboard")
            );
        }
    }

    public function get_site_list()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $sites = $network_data->get_recent_active_sites(10);
            wp_send_json_success($sites);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load sites", "wp-multisite-dashboard")
            );
        }
    }

    public function get_storage_data()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $storage_data = $network_data->get_storage_usage_data(5);
            wp_send_json_success($storage_data);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load storage data", "wp-multisite-dashboard")
            );
        }
    }

    public function get_server_info()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        global $wpdb, $wp_version;

        $server_info = [
            "wordpress_version" => $wp_version,
            "php_version" => phpversion(),
            "mysql_version" => $wpdb->db_version(),
            "server_software" =>
                $_SERVER["SERVER_SOFTWARE"] ??
                __("Unknown", "wp-multisite-dashboard"),
            "server_time" => current_time("Y-m-d H:i:s"),
            "php_memory_limit" => ini_get("memory_limit"),
            "max_upload_size" => size_format(wp_max_upload_size()),
            "active_plugins" => count(get_option("active_plugins", [])),
            "total_users" => count_users()["total_users"],
            "last_updated" => current_time("mysql"),
        ];

        wp_send_json_success($server_info);
    }

    public function get_version_info()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data(
            WP_MSD_PLUGIN_DIR . "wp-multisite-dashboard.php"
        );
        global $wpdb;

        $activity_table = $wpdb->base_prefix . "msd_activity_log";
        $activity_exists =
            $wpdb->get_var("SHOW TABLES LIKE '{$activity_table}'") ===
            $activity_table;

        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $update_checker = $plugin_core->get_update_checker();
        $update_available = false;

        if ($update_checker) {
            $update = $update_checker->checkForUpdates();
            if (
                $update &&
                version_compare($update->version, WP_MSD_VERSION, ">")
            ) {
                $update_available = [
                    "version" => $update->version,
                    "details_url" => $update->details_url ?? "#",
                ];
            }
        }

        $version_info = [
            "plugin_name" => $plugin_data["Name"],
            "plugin_version" => $plugin_data["Version"],
            "plugin_author" => $plugin_data["Author"],
            "plugin_uri" => $plugin_data["AuthorURI"],
            "text_domain" => $plugin_data["TextDomain"],
            "required_php" => $plugin_data["RequiresPHP"],
            "description" => strip_tags($plugin_data["Description"]),
            "database_status" => $activity_exists ? "active" : "missing",
            "database_message" => $activity_exists
                ? __("Activity table created", "wp-multisite-dashboard")
                : __("Activity table missing", "wp-multisite-dashboard"),
            "update_available" => $update_available ? true : false,
            "update_info" => $update_available ?: null,
            "last_updated" => current_time("mysql"),
        ];

        wp_send_json_success($version_info);
    }

    public function get_custom_news()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $news_sources = get_site_option("msd_news_sources", []);
            $news_items = [];

            foreach ($news_sources as $source) {
                if (!$source["enabled"]) {
                    continue;
                }

                $feed_items = $this->fetch_rss_feed($source["url"], 5);
                foreach ($feed_items as $item) {
                    $item["source"] = $source["name"];
                    $news_items[] = $item;
                }
            }

            usort($news_items, function ($a, $b) {
                return strtotime($b["date"]) - strtotime($a["date"]);
            });

            $news_items = array_slice($news_items, 0, 10);

            wp_send_json_success($news_items);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load news", "wp-multisite-dashboard")
            );
        }
    }

    public function get_network_settings()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $settings_data = $network_data->get_network_settings_overview();
            wp_send_json_success($settings_data);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load network settings", "wp-multisite-dashboard")
            );
        }
    }

    public function get_user_management()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $user_manager = new WP_MSD_User_Manager();
            $user_data = $user_manager->get_recent_users_data();
            wp_send_json_success($user_data);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load user data", "wp-multisite-dashboard")
            );
        }
    }

    public function get_last_edits()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $last_edits = $network_data->get_recent_network_activity(10);
            wp_send_json_success($last_edits);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load recent activity", "wp-multisite-dashboard")
            );
        }
    }

    public function get_todo_items()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $todos = get_user_meta(get_current_user_id(), "msd_todos", true);
            if (!is_array($todos)) {
                $todos = [];
            }

            foreach ($todos as &$todo) {
                if (isset($todo["created_at"])) {
                    $todo["created_at_human"] =
                        human_time_diff(strtotime($todo["created_at"])) .
                        " " .
                        __("ago", "wp-multisite-dashboard");
                }
                if (isset($todo["updated_at"])) {
                    $todo["updated_at_human"] =
                        human_time_diff(strtotime($todo["updated_at"])) .
                        " " .
                        __("ago", "wp-multisite-dashboard");
                }
            }

            wp_send_json_success($todos);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to load todo items", "wp-multisite-dashboard")
            );
        }
    }

    public function save_news_sources()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $sources = [];
        if (isset($_POST["sources"]) && is_array($_POST["sources"])) {
            foreach ($_POST["sources"] as $source) {
                if (!empty($source["name"]) && !empty($source["url"])) {
                    $sources[] = [
                        "name" => sanitize_text_field($source["name"]),
                        "url" => esc_url_raw($source["url"]),
                        "enabled" => !empty($source["enabled"]),
                    ];
                }
            }
        }

        update_site_option("msd_news_sources", $sources);

        $cache_keys = [];
        foreach ($sources as $source) {
            $cache_keys[] = "msd_rss_" . md5($source["url"]);
        }

        foreach ($cache_keys as $key) {
            delete_site_transient($key);
        }

        wp_send_json_success([
            "message" => __(
                "News sources saved successfully",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function save_quick_links()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $links = [];
        if (isset($_POST["links"]) && is_array($_POST["links"])) {
            foreach ($_POST["links"] as $link) {
                if (!empty($link["title"]) && !empty($link["url"])) {
                    $links[] = [
                        "title" => sanitize_text_field($link["title"]),
                        "url" => esc_url_raw($link["url"]),
                        "icon" => sanitize_text_field($link["icon"]),
                        "new_tab" => !empty($link["new_tab"]),
                    ];
                }
            }
        }

        update_site_option("msd_quick_links", $links);
        wp_send_json_success([
            "message" => __(
                "Quick links saved successfully",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function save_contact_info()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $contact_info = [
            "name" => sanitize_text_field($_POST["name"] ?? ""),
            "email" => sanitize_email($_POST["email"] ?? ""),
            "phone" => sanitize_text_field($_POST["phone"] ?? ""),
            "website" => esc_url_raw($_POST["website"] ?? ""),
            "description" => sanitize_textarea_field(
                $_POST["description"] ?? ""
            ),
            "qq" => sanitize_text_field($_POST["qq"] ?? ""),
            "wechat" => sanitize_text_field($_POST["wechat"] ?? ""),
            "whatsapp" => sanitize_text_field($_POST["whatsapp"] ?? ""),
            "telegram" => sanitize_text_field($_POST["telegram"] ?? ""),
            "qr_code" => esc_url_raw($_POST["qr_code"] ?? ""),
        ];

        update_site_option("msd_contact_info", $contact_info);
        wp_send_json_success([
            "message" => __(
                "Contact information saved successfully",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function save_todo_item()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $title = sanitize_text_field($_POST["title"] ?? "");
            $description = sanitize_textarea_field($_POST["description"] ?? "");

            if (empty($title)) {
                wp_send_json_error(
                    __("Title is required", "wp-multisite-dashboard")
                );
                return;
            }

            $todos = get_user_meta(get_current_user_id(), "msd_todos", true);
            if (!is_array($todos)) {
                $todos = [];
            }

            $new_todo = [
                "id" => uniqid(),
                "title" => $title,
                "description" => $description,
                "completed" => false,
                "priority" => sanitize_text_field(
                    $_POST["priority"] ?? "medium"
                ),
                "created_at" => current_time("mysql"),
                "updated_at" => current_time("mysql"),
            ];

            $todos[] = $new_todo;

            $result = update_user_meta(
                get_current_user_id(),
                "msd_todos",
                $todos
            );

            if ($result) {
                wp_send_json_success([
                    "message" => __(
                        "Todo item created",
                        "wp-multisite-dashboard"
                    ),
                ]);
            } else {
                wp_send_json_error(
                    __("Failed to create todo item", "wp-multisite-dashboard")
                );
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to create todo item", "wp-multisite-dashboard")
            );
        }
    }

    public function update_todo_item()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST["id"] ?? "");
            $title = sanitize_text_field($_POST["title"] ?? "");
            $description = sanitize_textarea_field($_POST["description"] ?? "");

            if (empty($id) || empty($title)) {
                wp_send_json_error(
                    __("ID and title are required", "wp-multisite-dashboard")
                );
                return;
            }

            $todos = get_user_meta(get_current_user_id(), "msd_todos", true);
            if (!is_array($todos)) {
                wp_send_json_error(
                    __("No todos found", "wp-multisite-dashboard")
                );
                return;
            }

            $updated = false;
            foreach ($todos as &$todo) {
                if ($todo["id"] === $id) {
                    $todo["title"] = $title;
                    $todo["description"] = $description;
                    if (isset($_POST["priority"])) {
                        $todo["priority"] = sanitize_text_field(
                            $_POST["priority"]
                        );
                    }
                    $todo["updated_at"] = current_time("mysql");
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                $result = update_user_meta(
                    get_current_user_id(),
                    "msd_todos",
                    $todos
                );
                if ($result) {
                    wp_send_json_success([
                        "message" => __(
                            "Todo item updated",
                            "wp-multisite-dashboard"
                        ),
                    ]);
                } else {
                    wp_send_json_error(
                        __(
                            "Failed to update todo item",
                            "wp-multisite-dashboard"
                        )
                    );
                }
            } else {
                wp_send_json_error(
                    __("Todo item not found", "wp-multisite-dashboard")
                );
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to update todo item", "wp-multisite-dashboard")
            );
        }
    }

    public function delete_todo_item()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST["id"] ?? "");

            if (empty($id)) {
                wp_send_json_error(
                    __("ID is required", "wp-multisite-dashboard")
                );
                return;
            }

            $todos = get_user_meta(get_current_user_id(), "msd_todos", true);
            if (!is_array($todos)) {
                wp_send_json_error(
                    __("No todos found", "wp-multisite-dashboard")
                );
                return;
            }

            $filtered_todos = array_filter($todos, function ($todo) use ($id) {
                return $todo["id"] !== $id;
            });

            if (count($filtered_todos) < count($todos)) {
                $result = update_user_meta(
                    get_current_user_id(),
                    "msd_todos",
                    array_values($filtered_todos)
                );
                if ($result) {
                    wp_send_json_success([
                        "message" => __(
                            "Todo item deleted",
                            "wp-multisite-dashboard"
                        ),
                    ]);
                } else {
                    wp_send_json_error(
                        __(
                            "Failed to delete todo item",
                            "wp-multisite-dashboard"
                        )
                    );
                }
            } else {
                wp_send_json_error(
                    __("Todo item not found", "wp-multisite-dashboard")
                );
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to delete todo item", "wp-multisite-dashboard")
            );
        }
    }

    public function toggle_todo_complete()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST["id"] ?? "");

            if (empty($id)) {
                wp_send_json_error(
                    __("ID is required", "wp-multisite-dashboard")
                );
                return;
            }

            $todos = get_user_meta(get_current_user_id(), "msd_todos", true);
            if (!is_array($todos)) {
                wp_send_json_error(
                    __("No todos found", "wp-multisite-dashboard")
                );
                return;
            }

            $updated = false;
            foreach ($todos as &$todo) {
                if ($todo["id"] === $id) {
                    $todo["completed"] = !$todo["completed"];
                    $todo["updated_at"] = current_time("mysql");
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                $result = update_user_meta(
                    get_current_user_id(),
                    "msd_todos",
                    $todos
                );
                if ($result) {
                    wp_send_json_success([
                        "message" => __(
                            "Todo status updated",
                            "wp-multisite-dashboard"
                        ),
                    ]);
                } else {
                    wp_send_json_error(
                        __(
                            "Failed to update todo status",
                            "wp-multisite-dashboard"
                        )
                    );
                }
            } else {
                wp_send_json_error(
                    __("Todo item not found", "wp-multisite-dashboard")
                );
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to update todo status", "wp-multisite-dashboard")
            );
        }
    }

    public function reorder_quick_links()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $order = $_POST["order"] ?? [];
        if (!is_array($order)) {
            wp_send_json_error(
                __("Invalid order data", "wp-multisite-dashboard")
            );
            return;
        }

        $current_links = get_site_option("msd_quick_links", []);
        $reordered_links = [];

        foreach ($order as $index) {
            $index = intval($index);
            if (isset($current_links[$index])) {
                $reordered_links[] = $current_links[$index];
            }
        }

        update_site_option("msd_quick_links", $reordered_links);
        wp_send_json_success([
            "message" => __(
                "Links reordered successfully",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function toggle_widget()
    {
        // IMMEDIATELY clean output and set headers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering
        ob_start();
        
        // Set headers immediately if not already sent
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
        }
        
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget_id = sanitize_text_field($_POST["widget_id"] ?? "");
        $enabled = !empty($_POST["enabled"]);

        if (empty($widget_id)) {
            wp_send_json_error(
                __("Invalid widget ID", "wp-multisite-dashboard")
            );
        }

        $enabled_widgets = get_site_option("msd_enabled_widgets", []);
        $enabled_widgets[$widget_id] = $enabled ? 1 : 0;
        update_site_option("msd_enabled_widgets", $enabled_widgets);

        // Send clean JSON response
        $this->send_clean_json_response(true, [
            "message" => __(
                "Widget settings updated",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function refresh_widget_data()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget = sanitize_text_field($_POST["widget"] ?? "");

        $network_data = new WP_MSD_Network_Data();
        $network_data->clear_widget_cache($widget);

        wp_send_json_success([
            "message" => __("Cache cleared", "wp-multisite-dashboard"),
        ]);
    }

    public function clear_cache()
    {
        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard")
            );
            return;
        }

        $nonce = $_POST["nonce"] ?? "";
        if (
            !wp_verify_nonce($nonce, "msd_ajax_nonce") &&
            !wp_verify_nonce($nonce, "msd_clear_cache")
        ) {
            wp_send_json_error(__("Invalid nonce", "wp-multisite-dashboard"));
            return;
        }

        $cache_type = sanitize_text_field($_POST["cache_type"] ?? "all");

        try {
            switch ($cache_type) {
                case "network":
                    $network_data = new WP_MSD_Network_Data();
                    $network_data->clear_all_caches();
                    break;

                case "all":
                default:
                    $network_data = new WP_MSD_Network_Data();
                    $network_data->clear_all_caches();
                    wp_cache_flush();
                    break;
            }

            wp_send_json_success([
                "message" => __(
                    "Cache cleared successfully",
                    "wp-multisite-dashboard"
                ),
            ]);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to clear cache", "wp-multisite-dashboard")
            );
        }
    }

    public function manage_user_action()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $action = sanitize_text_field($_POST["user_action"] ?? "");
            $user_id = intval($_POST["user_id"] ?? 0);
            $additional_data = $_POST["additional_data"] ?? [];

            $user_manager = new WP_MSD_User_Manager();
            $result = $user_manager->perform_single_user_action(
                $action,
                $user_id,
                $additional_data
            );

            if ($result["success"]) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result["message"]);
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to perform user action", "wp-multisite-dashboard")
            );
        }
    }

    public function check_plugin_update()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $update_checker = $plugin_core->get_update_checker();

        if (!$update_checker) {
            wp_send_json_success([
                "message" => __(
                    "No updates available",
                    "wp-multisite-dashboard"
                ),
            ]);
            return;
        }

        $update = $update_checker->checkForUpdates();

        if ($update && version_compare($update->version, WP_MSD_VERSION, ">")) {
            wp_send_json_success([
                "version" => $update->version,
                "details_url" => $update->details_url ?? "#",
            ]);
        } else {
            wp_send_json_success([
                "message" => __(
                    "No updates available",
                    "wp-multisite-dashboard"
                ),
            ]);
        }
    }

    public function clear_widget_cache()
    {
        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard")
            );
            return;
        }

        $nonce = $_POST["nonce"] ?? "";
        if (
            !wp_verify_nonce($nonce, "msd_ajax_nonce") &&
            !wp_verify_nonce($nonce, "msd_clear_cache")
        ) {
            wp_send_json_error(__("Invalid nonce", "wp-multisite-dashboard"));
            return;
        }

        delete_site_transient("msd_detected_widgets");

        wp_send_json_success([
            "message" => __(
                "Widget cache cleared successfully",
                "wp-multisite-dashboard"
            ),
        ]);
    }

    public function force_widget_detection()
    {
        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard")
            );
            return;
        }

        $nonce = $_POST["nonce"] ?? "";
        if (!wp_verify_nonce($nonce, "msd_ajax_nonce")) {
            wp_send_json_error(__("Invalid nonce", "wp-multisite-dashboard"));
            return;
        }

        try {
            // Increase time limit for detection
            @set_time_limit(120);
            
            // Check if child site detection is requested
            $enable_child_sites = !empty($_POST["include_child_sites"]);
            
            // Clear existing cache first
            delete_site_transient("msd_detected_widgets");
            
            // For AJAX context, we need to simulate dashboard initialization
            global $wp_meta_boxes;
            
            // Initialize dashboard widgets if not already done
            if (!isset($wp_meta_boxes["dashboard-network"])) {
                // Manually trigger the dashboard setup actions
                require_once ABSPATH . 'wp-admin/includes/dashboard.php';
                
                // Set up network dashboard widgets
                do_action('wp_network_dashboard_setup');
            }
            
            $plugin_core = WP_MSD_Plugin_Core::get_instance();
            $detected_widgets = $plugin_core->force_widget_detection($enable_child_sites);
            
            if ($detected_widgets === false || !is_array($detected_widgets)) {
                wp_send_json_error(
                    __("Widget detection failed. Please visit the network dashboard first to initialize widgets.", "wp-multisite-dashboard")
                );
                return;
            }
            
            $widget_count = count($detected_widgets);
            
            $message = $enable_child_sites 
                ? __("Deep widget detection completed. Found %d widgets including child sites.", "wp-multisite-dashboard")
                : __("Widget detection completed. Found %d widgets.", "wp-multisite-dashboard");
            
            wp_send_json_success([
                "message" => sprintf($message, $widget_count),
                "widget_count" => $widget_count,
            ]);
        } catch (Exception $e) {
            wp_send_json_error(
                sprintf(
                    __("Failed to detect widgets: %s", "wp-multisite-dashboard"),
                    $e->getMessage()
                )
            );
        }
    }

    public function validate_import_file()
    {
        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard")
            );
            return;
        }

        $nonce = $_POST["nonce"] ?? "";
        if (!wp_verify_nonce($nonce, "msd_ajax_nonce")) {
            wp_send_json_error(__("Invalid nonce", "wp-multisite-dashboard"));
            return;
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(
                __("File upload error", "wp-multisite-dashboard")
            );
            return;
        }

        // 验证文件类型和大小
        $file_info = $_FILES['import_file'];
        $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== 'json') {
            wp_send_json_error(
                __("Invalid file type. Only JSON files are allowed.", "wp-multisite-dashboard")
            );
            return;
        }
        
        if ($file_info['size'] > 1024 * 1024) { // 1MB limit
            wp_send_json_error(
                __("File too large. Maximum size is 1MB.", "wp-multisite-dashboard")
            );
            return;
        }

        $file_content = file_get_contents($file_info['tmp_name']);
        
        if ($file_content === false) {
            wp_send_json_error(
                __("Unable to read file content.", "wp-multisite-dashboard")
            );
            return;
        }
        
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(
                sprintf(
                    __("Invalid JSON format: %s", "wp-multisite-dashboard"),
                    json_last_error_msg()
                )
            );
            return;
        }

        $settings_manager = new WP_MSD_Settings_Manager();
        $reflection = new ReflectionClass($settings_manager);
        $validate_method = $reflection->getMethod('validate_import_data');
        $validate_method->setAccessible(true);
        
        if (!$validate_method->invoke($settings_manager, $import_data)) {
            wp_send_json_error(
                __("Invalid file format", "wp-multisite-dashboard")
            );
            return;
        }

        wp_send_json_success([
            "message" => __("File is valid and ready to import", "wp-multisite-dashboard"),
            "export_date" => $import_data['export_date'] ?? '',
            "version" => $import_data['version'] ?? '',
            "site_url" => $import_data['site_url'] ?? ''
        ]);
    }

    public function get_cache_status()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $widget_cache = get_site_transient('msd_detected_widgets');
            $network_data = new WP_MSD_Network_Data();
            
            // Get transient count (approximate)
            global $wpdb;
            $transient_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_msd_%'"
            );

            $cache_status = [
                'widget_cache' => !empty($widget_cache),
                'network_cache' => !empty(get_site_transient('msd_network_overview')),
                'transient_count' => (int) $transient_count,
                'last_updated' => current_time('Y-m-d H:i:s')
            ];

            wp_send_json_success($cache_status);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to get cache status", "wp-multisite-dashboard")
            );
        }
    }

    public function get_performance_stats()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $performance_manager = WP_MSD_Performance_Manager::get_instance();
            $stats = $performance_manager->get_cache_stats();
            
            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to get performance stats", "wp-multisite-dashboard")
            );
        }
    }

    public function get_memory_stats()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $memory_stats = [
                'current_usage' => size_format(memory_get_usage(true)),
                'peak_usage' => size_format(memory_get_peak_usage(true)),
                'limit' => ini_get('memory_limit')
            ];
            
            wp_send_json_success($memory_stats);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to get memory stats", "wp-multisite-dashboard")
            );
        }
    }

    public function optimize_cache()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $performance_manager = WP_MSD_Performance_Manager::get_instance();
            
            // 清理过期缓存
            $performance_manager->cleanup_memory();
            
            // 清理旧的transients
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE '_site_transient_timeout_msd_%' 
                 AND meta_value < UNIX_TIMESTAMP()"
            );
            
            wp_send_json_success([
                'message' => __('Cache optimization completed', 'wp-multisite-dashboard')
            ]);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to optimize cache", "wp-multisite-dashboard")
            );
        }
    }

    public function analyze_database()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            global $wpdb;
            
            // 获取数据库统计信息
            $query_count = $wpdb->num_queries;
            
            // 检查慢查询（简化版本）
            $slow_queries = 0;
            
            // 获取数据库大小
            $database_size = $wpdb->get_var(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
                 FROM information_schema.tables 
                 WHERE table_schema = DATABASE()"
            );
            
            $stats = [
                'query_count' => $query_count,
                'slow_queries' => $slow_queries,
                'database_size' => $database_size . ' MB'
            ];
            
            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to analyze database", "wp-multisite-dashboard")
            );
        }
    }

    public function optimize_database()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            global $wpdb;
            
            // 获取所有MSD相关的表
            $tables = $wpdb->get_results(
                "SHOW TABLES LIKE '{$wpdb->prefix}%'",
                ARRAY_N
            );
            
            $optimized_tables = 0;
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                $result = $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
                if ($result !== false) {
                    $optimized_tables++;
                }
            }
            
            // 清理过期的transients
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE '_site_transient_timeout_%' 
                 AND meta_value < UNIX_TIMESTAMP()"
            );
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Optimized %d tables successfully', 'wp-multisite-dashboard'),
                    $optimized_tables
                )
            ]);
        } catch (Exception $e) {
            wp_send_json_error(
                __("Failed to optimize database", "wp-multisite-dashboard")
            );
        }
    }

    public function get_widget_list()
    {
        // Ultra-aggressive output cleaning
        $this->force_clean_output();
        
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $settings_manager = new WP_MSD_Settings_Manager();
            $available_widgets = $settings_manager->get_available_system_widgets();
            $disabled_widgets = get_site_option("msd_disabled_system_widgets", []);

            // 生成HTML
            $html = $this->generate_widget_list_html($available_widgets, $disabled_widgets);
            
            // 统计信息
            $stats = [
                'total' => count($available_widgets),
                'system' => count(array_filter($available_widgets, function($widget) {
                    return $widget['is_system'] ?? false;
                })),
                'third_party' => count(array_filter($available_widgets, function($widget) {
                    return !($widget['is_system'] ?? false) && !($widget['is_custom'] ?? false);
                }))
            ];

            // Send clean JSON response
            $this->send_clean_json_response(true, [
                'html' => $html,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            // Send clean error response
            $this->send_clean_json_response(false, 
                __("Failed to get widget list", "wp-multisite-dashboard")
            );
        }
    }

    private function generate_widget_list_html($available_widgets, $disabled_widgets)
    {
        $system_widgets = array_filter($available_widgets, function ($widget) {
            return $widget["is_system"] ?? false;
        });

        $third_party_widgets = array_filter($available_widgets, function ($widget) {
            return !($widget["is_system"] ?? false) && !($widget["is_custom"] ?? false);
        });

        $html = '';

        // 检测状态信息栏
        $stats = $this->get_widget_detection_stats($available_widgets);
        $html .= '<div class="msd-detection-status">';
        $html .= '<p><span class="dashicons dashicons-info"></span>';
        $html .= '<strong>' . __('Detection Status:', 'wp-multisite-dashboard') . '</strong> ';
        $html .= sprintf(
            __('Found %d widgets (%d system, %d third-party). Last detection: %s', 'wp-multisite-dashboard'),
            $stats['total'],
            $stats['system'],
            $stats['third_party'],
            $stats['last_detection_human']
        );
        $html .= '</p></div>';

        // 系统小工具部分
        if (!empty($system_widgets)) {
            $html .= '<div class="msd-widget-section">';
            $html .= '<h4>' . __('WordPress System Widgets', 'wp-multisite-dashboard') . '</h4>';
            
            foreach ($system_widgets as $widget_id => $widget_data) {
                $checked = !in_array($widget_id, $disabled_widgets) ? 'checked' : '';
                $html .= '<div class="msd-widget-toggle">';
                $html .= '<label>';
                $html .= '<input type="checkbox" name="system_widgets[' . esc_attr($widget_id) . ']" value="1" ' . $checked . ' />';
                $html .= esc_html($widget_data["title"]);
                $html .= '<span class="msd-widget-meta">(' . esc_html($widget_data["context"]) . ')</span>';
                $html .= '</label>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        // 第三方小工具部分
        $html .= '<div class="msd-widget-section">';
        $html .= '<h4>' . __('Third-Party Plugin Widgets', 'wp-multisite-dashboard') . '</h4>';
        
        if (!empty($third_party_widgets)) {
            // 显示检测到的第三方小工具
            foreach ($third_party_widgets as $widget_id => $widget_data) {
                $checked = !in_array($widget_id, $disabled_widgets) ? 'checked' : '';
                $html .= '<div class="msd-widget-toggle">';
                $html .= '<label>';
                $html .= '<input type="checkbox" name="system_widgets[' . esc_attr($widget_id) . ']" value="1" ' . $checked . ' />';
                $html .= esc_html($widget_data["title"]);
                $html .= '<span class="msd-widget-meta">(' . esc_html($widget_data["context"]) . ')</span>';
                if (isset($widget_data["source"]) && $widget_data["source"] === "child_site") {
                    $html .= '<span class="msd-widget-source">' . __('Child Site', 'wp-multisite-dashboard') . '</span>';
                }
                $html .= '</label>';
                $html .= '</div>';
            }
            
            // 显示重新扫描提示
            $html .= '<div class="msd-rescan-section">';
            $html .= '<p class="description">' . __('Found third-party widgets above. You can rescan to detect new widgets or refresh the list using the controls below.', 'wp-multisite-dashboard') . '</p>';
            $html .= '</div>';
        } else {
            // 没有检测到第三方小工具
            $html .= '<div class="msd-no-third-party">';
            $html .= '<p>' . __('No third-party widgets detected yet.', 'wp-multisite-dashboard') . '</p>';
            $html .= '<p class="description">' . __('Third-party widgets are automatically detected when you visit the network dashboard. If you have plugins that add dashboard widgets, visit the dashboard first, then use the detection controls below.', 'wp-multisite-dashboard') . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';

        // 始终显示检测按钮区域（独立于小工具列表）
        $html .= $this->generate_detection_buttons_html();

        return $html;
    }

    /**
     * 生成检测按钮HTML（始终显示）
     */
    private function generate_detection_buttons_html()
    {
        $html = '<div class="msd-detection-controls">';
        $html .= '<h4>' . __('Widget Detection Controls', 'wp-multisite-dashboard') . '</h4>';
        $html .= '<p class="description">' . __('Use these controls to detect new widgets or refresh the current list.', 'wp-multisite-dashboard') . '</p>';
        $html .= '<div class="msd-action-buttons">';
        $html .= '<a href="' . network_admin_url() . '" class="button button-secondary">';
        $html .= '<span class="dashicons dashicons-external"></span>';
        $html .= __('Visit Network Dashboard', 'wp-multisite-dashboard');
        $html .= '</a>';
        $html .= '<button type="button" class="button" onclick="MSD.clearWidgetCache(this)">';
        $html .= '<span class="dashicons dashicons-update"></span>';
        $html .= __('Refresh Widget Detection', 'wp-multisite-dashboard');
        $html .= '</button>';
        $html .= '<button type="button" class="button button-primary" onclick="MSD.forceNetworkWidgetDetection(this)">';
        $html .= '<span class="dashicons dashicons-search"></span>';
        $html .= __('Force Network Scan', 'wp-multisite-dashboard');
        $html .= '</button>';
        $html .= '<button type="button" class="button" onclick="MSD.forceWidgetDetection(true, this)" style="margin-left: 5px;">';
        $html .= '<span class="dashicons dashicons-admin-multisite"></span>';
        $html .= __('Deep Scan (Include Child Sites)', 'wp-multisite-dashboard');
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    private function get_widget_detection_stats($available_widgets)
    {
        $last_detection = get_site_option('msd_last_widget_detection', 0);
        
        $stats = [
            'total' => count($available_widgets),
            'system' => count(array_filter($available_widgets, function($widget) {
                return $widget['is_system'] ?? false;
            })),
            'third_party' => count(array_filter($available_widgets, function($widget) {
                return !($widget['is_system'] ?? false) && !($widget['is_custom'] ?? false);
            })),
            'last_detection' => $last_detection,
            'last_detection_human' => $last_detection ? human_time_diff($last_detection) . ' ' . __('ago', 'wp-multisite-dashboard') : __('Never', 'wp-multisite-dashboard')
        ];
        
        return $stats;
    }

    private function generate_no_widgets_html()
    {
        $html = '<div class="msd-no-widgets">';
        $html .= '<p>' . __('No system widgets found.', 'wp-multisite-dashboard') . '</p>';
        $html .= '<p>' . __('Visit the network dashboard to detect available widgets.', 'wp-multisite-dashboard') . '</p>';
        $html .= '<div class="msd-action-buttons">';
        $html .= '<a href="' . network_admin_url() . '" class="button button-secondary">' . __('Visit Network Dashboard', 'wp-multisite-dashboard') . '</a>';
        $html .= '<button type="button" class="button" onclick="MSD.clearWidgetCache(this)">' . __('Refresh Widget Detection', 'wp-multisite-dashboard') . '</button>';
        $html .= '<button type="button" class="button button-primary" onclick="MSD.forceNetworkWidgetDetection(this)">' . __('Force Network Scan', 'wp-multisite-dashboard') . '</button>';
        $html .= '<button type="button" class="button" onclick="MSD.forceWidgetDetection(true, this)" style="margin-left: 5px;">' . __('Deep Scan (Include Child Sites)', 'wp-multisite-dashboard') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    private function verify_ajax_request()
    {
        if (!wp_verify_nonce($_POST["nonce"] ?? "", "msd_ajax_nonce")) {
            wp_send_json_error(__("Invalid nonce", "wp-multisite-dashboard"));
            return false;
        }

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard")
            );
            return false;
        }

        return true;
    }

    private function fetch_rss_feed($url, $limit = 5)
    {
        $cache_key = "msd_rss_" . md5($url);
        $cached = get_site_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        if (!function_exists('fetch_feed')) {
            include_once ABSPATH . WPINC . '/feed.php';
        }

        $feed = fetch_feed($url);
        if (is_wp_error($feed)) {
            return [];
        }

        $maxitems = $feed->get_item_quantity($limit);
        $items = $feed->get_items(0, $maxitems);
        $feed_items = [];

        foreach ($items as $item) {
            $title = html_entity_decode($item->get_title(), ENT_QUOTES, 'UTF-8');
            $link = $item->get_link();
            $description_raw = $item->get_description() ?: $item->get_content();
            $description = wp_trim_words(strip_tags(html_entity_decode($description_raw, ENT_QUOTES, 'UTF-8')), 20);
            $date = $item->get_date('Y-m-d H:i:s') ?: '';

            if (!empty($title) && !empty($link)) {
                $feed_items[] = [
                    'title' => $title,
                    'link' => $link,
                    'description' => $description,
                    'date' => $date,
                ];
            }
        }

        set_site_transient($cache_key, $feed_items, 3600);
        return $feed_items;
    }

    public function get_error_log()
    {
        $error_handler = WP_MSD_Error_Handler::get_instance();
        $error_handler->get_error_log();
    }

    public function clear_error_log()
    {
        $error_handler = WP_MSD_Error_Handler::get_instance();
        $error_handler->clear_error_log();
    }

    /**
     * Get error logs (new monitoring feature)
     */
    public function get_error_logs()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $error_log_manager = WP_MSD_Error_Log_Manager::get_instance();
            
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
            $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
            
            $result = $error_log_manager->get_error_logs($limit, $filters);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(
                __('Failed to load error logs', 'wp-multisite-dashboard')
            );
        }
    }

    /**
     * Clear error logs (new monitoring feature)
     */
    public function clear_error_logs()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $error_log_manager = WP_MSD_Error_Log_Manager::get_instance();
            $result = $error_log_manager->clear_log_file();
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message']
                ]);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __('Failed to clear error logs', 'wp-multisite-dashboard')
            );
        }
    }

    /**
     * Get 404 statistics
     */
    public function get_404_stats()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $monitor_404 = WP_MSD_404_Monitor::get_instance();
            
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
            $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
            
            $stats = $monitor_404->get_statistics($limit, $days);
            
            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error(
                __('Failed to load 404 statistics', 'wp-multisite-dashboard')
            );
        }
    }

    /**
     * Toggle 404 monitoring
     */
    public function toggle_404_monitoring()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $monitor_404 = WP_MSD_404_Monitor::get_instance();
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
            
            $monitor_404->set_monitoring_enabled($enabled);
            
            $status = $enabled ? __('enabled', 'wp-multisite-dashboard') : __('disabled', 'wp-multisite-dashboard');
            
            wp_send_json_success([
                'message' => sprintf(
                    __('404 monitoring %s successfully', 'wp-multisite-dashboard'),
                    $status
                ),
                'enabled' => $enabled
            ]);
        } catch (Exception $e) {
            wp_send_json_error(
                __('Failed to toggle 404 monitoring', 'wp-multisite-dashboard')
            );
        }
    }

    /**
     * Clear 404 logs
     */
    public function clear_404_logs()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $monitor_404 = WP_MSD_404_Monitor::get_instance();
            $result = $monitor_404->clear_all_records();
            
            if ($result) {
                wp_send_json_success([
                    'message' => __('404 logs cleared successfully', 'wp-multisite-dashboard')
                ]);
            } else {
                wp_send_json_error(
                    __('Failed to clear 404 logs', 'wp-multisite-dashboard')
                );
            }
        } catch (Exception $e) {
            wp_send_json_error(
                __('Failed to clear 404 logs', 'wp-multisite-dashboard')
            );
        }
    }
}
