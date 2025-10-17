<?php

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Plugin_Core
{
    private static $instance = null;
    private $enabled_widgets = [];
    private $update_checker = null;
    private $ajax_handler = null;
    private $admin_interface = null;
    private $settings_manager = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action("init", [$this, "init"]);
        add_action("admin_init", [$this, "admin_init"]);
        add_action("network_admin_menu", [$this, "add_admin_menu"]);

        // Add automatic cache invalidation hooks
        add_action("activated_plugin", [$this, "invalidate_widget_cache"]);
        add_action("deactivated_plugin", [$this, "invalidate_widget_cache"]);
        add_action("switch_theme", [$this, "invalidate_widget_cache"]);

        $this->init_update_checker();
        $this->init_components();
    }

    private function init_components()
    {
        $this->ajax_handler = new WP_MSD_Ajax_Handler();
        $this->admin_interface = new WP_MSD_Admin_Interface();
        $this->settings_manager = new WP_MSD_Settings_Manager();
    }

    private function init_update_checker()
    {
        if (
            file_exists(
                plugin_dir_path(__FILE__) .
                    "../lib/plugin-update-checker/plugin-update-checker.php"
            )
        ) {
            require_once plugin_dir_path(__FILE__) .
                "../lib/plugin-update-checker/plugin-update-checker.php";

            if (
                class_exists('YahnisElsts\PluginUpdateChecker\v5p3\PucFactory')
            ) {
                $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5p3\PucFactory::buildUpdateChecker(
                    "https://updates.weixiaoduo.com/wp-multisite-dashboard.json",
                    WP_MSD_PLUGIN_DIR . "wp-multisite-dashboard.php",
                    "wp-multisite-dashboard"
                );
            }
        }
    }

    public function init()
    {
        // Load enabled widgets with robust normalization
        $default_enabled = [
            "msd_network_overview" => 1,
            "msd_quick_site_management" => 1,
            "msd_storage_performance" => 1,
            "msd_server_info" => 1,
            "msd_quick_links" => 1,
            "msd_version_info" => 1,
            "msd_custom_news" => 1,
            "msd_network_settings" => 1,
            "msd_user_management" => 1,
            "msd_contact_info" => 1,
            "msd_last_edits" => 1,
            "msd_todo_widget" => 1,
            // New monitoring widgets
            "msd_error_logs" => 1,
            "msd_404_monitor" => 1,
        ];
        $enabled = get_site_option("msd_enabled_widgets", null);
        if (!is_array($enabled) || empty($enabled)) {
            // Fall back to defaults if option missing or corrupted
            $enabled = $default_enabled;
        } else {
            // Merge new default widgets with existing enabled widgets
            // This ensures new widgets are auto-enabled on plugin update
            foreach ($default_enabled as $widget_id => $status) {
                if (!isset($enabled[$widget_id])) {
                    $enabled[$widget_id] = $status;
                }
            }
            // Update option if new widgets were added
            if (count($enabled) > count(get_site_option("msd_enabled_widgets", []))) {
                update_site_option("msd_enabled_widgets", $enabled);
            }
        }
        $this->enabled_widgets = $enabled;

        $this->enhance_network_dashboard();
        
        // Initialize 404 monitor
        $monitor_404 = WP_MSD_404_Monitor::get_instance();
        $monitor_404->init();
    }

    public function admin_init()
    {
        if (!current_user_can("manage_network")) {
            return;
        }

        // Handle import/export actions
        $this->handle_import_export_actions();

        add_action("wp_network_dashboard_setup", [
            $this->admin_interface,
            "add_network_widgets",
        ]);
        add_action(
            "wp_network_dashboard_setup",
            [$this, "manage_system_widgets"],
            20
        );
        add_action(
            "wp_network_dashboard_setup",
            [$this, "cache_detected_widgets"],
            30
        );
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_scripts"]);
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            "settings.php",
            __("Dashboard Settings", "wp-multisite-dashboard"),
            __("Dashboard Settings", "wp-multisite-dashboard"),
            "manage_network",
            "msd-settings",
            [$this->settings_manager, "render_settings_page"]
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        $allowed_hooks = [
            "index.php",
            "dashboard.php",
            "settings_page_msd-settings",
        ];

        if (!in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_script(
            "msd-dashboard-core",
            WP_MSD_PLUGIN_URL . "assets/dashboard-core.js",
            ["jquery"],
            WP_MSD_VERSION,
            true
        );

        if ($hook === "settings_page_msd-settings") {
            wp_enqueue_script(
                "msd-settings",
                WP_MSD_PLUGIN_URL . "assets/dashboard-settings.js",
                ["msd-dashboard-core"],
                WP_MSD_VERSION,
                true
            );
        } else {
            wp_enqueue_script(
                "msd-dashboard-widgets",
                WP_MSD_PLUGIN_URL . "assets/dashboard-widgets.js",
                ["msd-dashboard-core"],
                WP_MSD_VERSION,
                true
            );

            wp_enqueue_script(
                "msd-dashboard-modals",
                WP_MSD_PLUGIN_URL . "assets/dashboard-modals.js",
                ["msd-dashboard-core", "jquery-ui-sortable"],
                WP_MSD_VERSION,
                true
            );
        }

        wp_enqueue_style(
            "msd-dashboard",
            WP_MSD_PLUGIN_URL . "assets/dashboard.css",
            [],
            WP_MSD_VERSION
        );

        wp_localize_script("msd-dashboard-core", "msdAjax", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("msd_ajax_nonce"),
            "settingsUrl" => network_admin_url('settings.php?page=msd-settings&tab=monitoring'),
            "strings" => [
                "confirm_action" => __(
                    "Are you sure?",
                    "wp-multisite-dashboard"
                ),
                "loading" => __("Loading...", "wp-multisite-dashboard"),
                "error_occurred" => __(
                    "An error occurred",
                    "wp-multisite-dashboard"
                ),
                "refresh_success" => __(
                    "Data refreshed successfully",
                    "wp-multisite-dashboard"
                ),
                "confirm_delete" => __(
                    "Are you sure you want to delete this item?",
                    "wp-multisite-dashboard"
                ),
                "save_success" => __(
                    "Saved successfully",
                    "wp-multisite-dashboard"
                ),
                "clear_cache_confirm" => __(
                    "Are you sure you want to clear the cache?",
                    "wp-multisite-dashboard"
                ),
                "cache_cleared" => __(
                    "Cache cleared successfully!",
                    "wp-multisite-dashboard"
                ),
                "cache_clear_failed" => __(
                    "Failed to clear cache",
                    "wp-multisite-dashboard"
                ),
                "check_updates" => __(
                    "Check for Updates",
                    "wp-multisite-dashboard"
                ),
                "checking_updates" => __(
                    "Checking...",
                    "wp-multisite-dashboard"
                ),
                "update_available" => __(
                    "Version {version} available!",
                    "wp-multisite-dashboard"
                ),
                "up_to_date" => __("Up to date", "wp-multisite-dashboard"),
                "update_check_failed" => __(
                    "Failed to check for updates",
                    "wp-multisite-dashboard"
                ),
                "clear_widget_cache_confirm" => __(
                    "Are you sure you want to clear the widget cache? This will refresh the list of detected widgets.",
                    "wp-multisite-dashboard"
                ),
                "widget_cache_cleared" => __(
                    "Widget cache cleared successfully! Please reload the page to see updated widgets.",
                    "wp-multisite-dashboard"
                ),
                "widget_cache_clear_failed" => __(
                    "Failed to clear widget cache",
                    "wp-multisite-dashboard"
                ),

                // Widget content strings
                "no_active_sites" => __(
                    "No active sites found.",
                    "wp-multisite-dashboard"
                ),
                "no_storage_data" => __(
                    "No storage data available.",
                    "wp-multisite-dashboard"
                ),
                "no_recent_activity" => __(
                    "No recent network activity found.",
                    "wp-multisite-dashboard"
                ),
                "no_news_items" => __(
                    "No news items available.",
                    "wp-multisite-dashboard"
                ),
                "configure_news_sources" => __(
                    "Configure news sources to see updates.",
                    "wp-multisite-dashboard"
                ),
                "no_recent_registrations" => __(
                    "No recent registrations",
                    "wp-multisite-dashboard"
                ),
                "no_todos" => __(
                    'No todos yet. Click "Add Todo" to get started!',
                    "wp-multisite-dashboard"
                ),
                "unable_to_load" => __(
                    "Unable to load data",
                    "wp-multisite-dashboard"
                ),
                "try_again" => __("Try Again", "wp-multisite-dashboard"),

                // Form validation strings
                "title_required" => __(
                    "Title is required",
                    "wp-multisite-dashboard"
                ),
                "fill_required_fields" => __(
                    "Please fill in all required fields correctly",
                    "wp-multisite-dashboard"
                ),
                "name_email_required" => __(
                    "Name and email are required",
                    "wp-multisite-dashboard"
                ),
                "id_required" => __("ID is required", "wp-multisite-dashboard"),
                "id_title_required" => __(
                    "ID and title are required",
                    "wp-multisite-dashboard"
                ),
                "invalid_order_data" => __(
                    "Invalid order data",
                    "wp-multisite-dashboard"
                ),
                "invalid_widget_id" => __(
                    "Invalid widget ID",
                    "wp-multisite-dashboard"
                ),

                // Status messages
                "saving" => __("Saving...", "wp-multisite-dashboard"),
                "processing" => __("Processing...", "wp-multisite-dashboard"),
                "refreshing" => __("Refreshing...", "wp-multisite-dashboard"),
                "widget_settings_updated" => __(
                    "Widget settings updated",
                    "wp-multisite-dashboard"
                ),
                "cache_cleared_success" => __(
                    "Cache cleared",
                    "wp-multisite-dashboard"
                ),
                "links_reordered" => __(
                    "Links reordered successfully",
                    "wp-multisite-dashboard"
                ),

                // Save messages
                "quick_links_saved" => __(
                    "Quick links saved successfully",
                    "wp-multisite-dashboard"
                ),
                "news_sources_saved" => __(
                    "News sources saved successfully",
                    "wp-multisite-dashboard"
                ),
                "contact_info_saved" => __(
                    "Contact information saved successfully",
                    "wp-multisite-dashboard"
                ),
                "todo_created" => __(
                    "Todo item created",
                    "wp-multisite-dashboard"
                ),
                "todo_updated" => __(
                    "Todo item updated",
                    "wp-multisite-dashboard"
                ),
                "todo_deleted" => __(
                    "Todo item deleted",
                    "wp-multisite-dashboard"
                ),
                "todo_status_updated" => __(
                    "Todo status updated",
                    "wp-multisite-dashboard"
                ),

                // Error messages
                "failed_save_links" => __(
                    "Failed to save quick links",
                    "wp-multisite-dashboard"
                ),
                "failed_save_sources" => __(
                    "Failed to save news sources",
                    "wp-multisite-dashboard"
                ),
                "failed_save_contact" => __(
                    "Failed to save contact information",
                    "wp-multisite-dashboard"
                ),
                "failed_create_todo" => __(
                    "Failed to create todo item",
                    "wp-multisite-dashboard"
                ),
                "failed_update_todo" => __(
                    "Failed to update todo item",
                    "wp-multisite-dashboard"
                ),
                "failed_delete_todo" => __(
                    "Failed to delete todo item",
                    "wp-multisite-dashboard"
                ),
                "failed_update_status" => __(
                    "Failed to update todo status",
                    "wp-multisite-dashboard"
                ),
                "failed_save_order" => __(
                    "Failed to save order",
                    "wp-multisite-dashboard"
                ),
                "no_todos_found" => __(
                    "No todos found",
                    "wp-multisite-dashboard"
                ),
                "todo_not_found" => __(
                    "Todo item not found",
                    "wp-multisite-dashboard"
                ),
                "failed_user_action" => __(
                    "Failed to perform user action",
                    "wp-multisite-dashboard"
                ),
                "insufficient_permissions" => __(
                    "Insufficient permissions",
                    "wp-multisite-dashboard"
                ),
                "invalid_nonce" => __(
                    "Invalid nonce",
                    "wp-multisite-dashboard"
                ),
                "network_error" => __(
                    "Network error occurred",
                    "wp-multisite-dashboard"
                ),

                // News cache
                "news_cache_cleared" => __(
                    "News cache cleared successfully",
                    "wp-multisite-dashboard"
                ),
                "failed_clear_news_cache" => __(
                    "Failed to clear news cache",
                    "wp-multisite-dashboard"
                ),
                "failed_detect_widgets" => __(
                    "Failed to detect widgets",
                    "wp-multisite-dashboard"
                ),
                "import_export" => __(
                    "Import & Export",
                    "wp-multisite-dashboard"
                ),
                "export_settings" => __(
                    "Export Settings",
                    "wp-multisite-dashboard"
                ),
                "import_settings" => __(
                    "Import Settings",
                    "wp-multisite-dashboard"
                ),
                "choose_file" => __(
                    "Choose File",
                    "wp-multisite-dashboard"
                ),
                "validate_file" => __(
                    "Validate File",
                    "wp-multisite-dashboard"
                ),
                "file_valid" => __(
                    "File is valid and ready to import",
                    "wp-multisite-dashboard"
                ),
                "widget_settings" => __(
                    "Widget Settings",
                    "wp-multisite-dashboard"
                ),
                "system_widgets" => __(
                    "System Widgets",
                    "wp-multisite-dashboard"
                ),
                "cache_management" => __(
                    "Cache Management",
                    "wp-multisite-dashboard"
                ),
                "plugin_info" => __(
                    "Plugin Info",
                    "wp-multisite-dashboard"
                ),

                // Time labels
                "never" => __("Never", "wp-multisite-dashboard"),
                "unknown" => __("Unknown", "wp-multisite-dashboard"),
                "yesterday" => __("Yesterday", "wp-multisite-dashboard"),
                "days_ago" => __("%d days ago", "wp-multisite-dashboard"),
                "ago" => __("ago", "wp-multisite-dashboard"),

                // Units and labels
                "users" => __("users", "wp-multisite-dashboard"),
                "sites" => __("sites", "wp-multisite-dashboard"),
                "total" => __("total", "wp-multisite-dashboard"),
                "done" => __("done", "wp-multisite-dashboard"),
                "active" => __("Active", "wp-multisite-dashboard"),
                "recent" => __("Recent", "wp-multisite-dashboard"),
                "inactive" => __("Inactive", "wp-multisite-dashboard"),
                "very_inactive" => __(
                    "Very Inactive",
                    "wp-multisite-dashboard"
                ),
                "never_logged_in" => __(
                    "Never Logged In",
                    "wp-multisite-dashboard"
                ),
                "no_activity" => __("No activity", "wp-multisite-dashboard"),
                "low" => __("low", "wp-multisite-dashboard"),
                "medium" => __("medium", "wp-multisite-dashboard"),
                "high" => __("high", "wp-multisite-dashboard"),

                // Widget header and content labels
                "multisite_configuration" => __(
                    "Multisite Configuration",
                    "wp-multisite-dashboard"
                ),
                "installation_type" => __(
                    "Installation Type",
                    "wp-multisite-dashboard"
                ),
                "network_admin_email" => __(
                    "Network Admin Email",
                    "wp-multisite-dashboard"
                ),
                "site_upload_quota" => __(
                    "Site Upload Quota",
                    "wp-multisite-dashboard"
                ),
                "max_upload_size" => __(
                    "Max Upload Size",
                    "wp-multisite-dashboard"
                ),
                "default_language" => __(
                    "Default Language",
                    "wp-multisite-dashboard"
                ),
                "registration" => __("Registration", "wp-multisite-dashboard"),
                "not_set" => __("Not set", "wp-multisite-dashboard"),
                "posts" => __("Posts", "wp-multisite-dashboard"),
                "pages" => __("Pages", "wp-multisite-dashboard"),
                "refresh" => __("Refresh", "wp-multisite-dashboard"),
                "admin" => __("Admin", "wp-multisite-dashboard"),
                "view" => __("View", "wp-multisite-dashboard"),
                "edit" => __("Edit", "wp-multisite-dashboard"),
                "view_details" => __("View Details", "wp-multisite-dashboard"),
                "configure_sources" => __(
                    "Configure Sources",
                    "wp-multisite-dashboard"
                ),
                "add_todo" => __("Add Todo", "wp-multisite-dashboard"),
                "network" => __("Network", "wp-multisite-dashboard"),
                "upload_limit" => __("Upload Limit", "wp-multisite-dashboard"),
                "active_plugins" => __(
                    "Active Plugins",
                    "wp-multisite-dashboard"
                ),
                "network_themes" => __(
                    "Network Themes",
                    "wp-multisite-dashboard"
                ),
                "settings" => __("Settings", "wp-multisite-dashboard"),
                "themes" => __("Themes", "wp-multisite-dashboard"),
                "plugins" => __("Plugins", "wp-multisite-dashboard"),
                "updates" => __("Updates", "wp-multisite-dashboard"),
                "admins" => __("admins", "wp-multisite-dashboard"),
                "pending_activations" => __(
                    "pending activation(s)",
                    "wp-multisite-dashboard"
                ),
                "activate" => __("Activate", "wp-multisite-dashboard"),
                "plugin_version" => __(
                    "Plugin Version",
                    "wp-multisite-dashboard"
                ),
                "author_uri" => __("Author URI", "wp-multisite-dashboard"),
                "required_php" => __("Required PHP", "wp-multisite-dashboard"),
                "database_tables" => __(
                    "Database Tables",
                    "wp-multisite-dashboard"
                ),
                "activity_table_created" => __(
                    "Activity table created",
                    "wp-multisite-dashboard"
                ),
                "activity_table_missing" => __(
                    "Activity table missing",
                    "wp-multisite-dashboard"
                ),
                "documentation" => __(
                    "Documentation",
                    "wp-multisite-dashboard"
                ),
                "support" => __("Support", "wp-multisite-dashboard"),
                "github" => __("GitHub", "wp-multisite-dashboard"),
                "top_5_sites_storage" => __(
                    "Top 5 sites by storage usage",
                    "wp-multisite-dashboard"
                ),
                "total_network_storage" => __(
                    "Total Network Storage",
                    "wp-multisite-dashboard"
                ),

                // Additional strings for fixed internationalization
                "refresh_symbol" => "↻",
                "check_mark" => "✓",
                "warning_mark" => "⚠",
                "zero_mb" => "0 MB",
                "zero_bytes" => "0 B",
                "not_available" => __("N/A", "wp-multisite-dashboard"),
                "mb" => __("MB", "wp-multisite-dashboard"),
                "separator" => __("•", "wp-multisite-dashboard"),
                "million_suffix" => __("M", "wp-multisite-dashboard"),
                "thousand_suffix" => __("K", "wp-multisite-dashboard"),
                "ellipsis" => "...",
                "unknown_error" => __(
                    "Unknown error",
                    "wp-multisite-dashboard"
                ),
                "network_error_occurred" => __(
                    "due to network error.",
                    "wp-multisite-dashboard"
                ),

                // Registration labels
                "registration_disabled" => __(
                    "Disabled",
                    "wp-multisite-dashboard"
                ),
                "registration_users_only" => __(
                    "Users Only",
                    "wp-multisite-dashboard"
                ),
                "registration_sites_only" => __(
                    "Sites Only",
                    "wp-multisite-dashboard"
                ),
                "registration_users_sites" => __(
                    "Users & Sites",
                    "wp-multisite-dashboard"
                ),

                // Priority labels
                "low_priority" => __("Low Priority", "wp-multisite-dashboard"),
                "medium_priority" => __(
                    "Medium Priority",
                    "wp-multisite-dashboard"
                ),
                "high_priority" => __(
                    "High Priority",
                    "wp-multisite-dashboard"
                ),

                // Form labels for modals
                "link_title" => __("Link Title", "wp-multisite-dashboard"),
                "url_placeholder" => "https://example.com",
                "icon_placeholder" => __(
                    "dashicons-admin-home or 🏠",
                    "wp-multisite-dashboard"
                ),
                "open_new_tab" => __(
                    "Open in new tab",
                    "wp-multisite-dashboard"
                ),
                "remove" => __("Remove", "wp-multisite-dashboard"),
                "source_name" => __("Source Name", "wp-multisite-dashboard"),
                "rss_feed_url" => __("RSS Feed URL", "wp-multisite-dashboard"),
                "enabled" => __("Enabled", "wp-multisite-dashboard"),
                "save_links" => __("Save Links", "wp-multisite-dashboard"),
                "save_news_sources" => __(
                    "Save News Sources",
                    "wp-multisite-dashboard"
                ),
                "save_contact_info" => __(
                    "Save Contact Info",
                    "wp-multisite-dashboard"
                ),
                "select_qr_image" => __(
                    "Select QR Image",
                    "wp-multisite-dashboard"
                ),
                "use_image" => __("Use Image", "wp-multisite-dashboard"),
                "enter_qr_url" => __(
                    "Enter QR code image URL:",
                    "wp-multisite-dashboard"
                ),

                // Todo form labels
                "title" => __("Title", "wp-multisite-dashboard"),
                "todo_placeholder" => __(
                    "Enter todo item title...",
                    "wp-multisite-dashboard"
                ),
                "description_optional" => __(
                    "Description (Optional)",
                    "wp-multisite-dashboard"
                ),
                "additional_details" => __(
                    "Additional details...",
                    "wp-multisite-dashboard"
                ),
                "priority" => __("Priority", "wp-multisite-dashboard"),
                "save" => __("Save", "wp-multisite-dashboard"),
                "cancel" => __("Cancel", "wp-multisite-dashboard"),
                "delete" => __("Delete", "wp-multisite-dashboard"),
                "action" => __("Action", "wp-multisite-dashboard"),
                "action_completed" => __(
                    "Action completed successfully",
                    "wp-multisite-dashboard"
                ),

                // Settings page
                "msd_settings_loaded" => __(
                    "MSD Settings loaded with functions",
                    "wp-multisite-dashboard"
                ),
            ],
        ]);
    }

    private function enhance_network_dashboard()
    {
        add_filter("dashboard_recent_posts_query_args", [
            $this,
            "enhance_recent_posts",
        ]);
        add_action("wp_network_dashboard_setup", [
            $this,
            "enhance_right_now_widget",
        ]);
        add_action("admin_footer", [$this, "add_right_now_enhancements"]);
    }

    public function enhance_recent_posts($query_args)
    {
        $query_args["post_status"] = "publish";
        return $query_args;
    }

    public function enhance_right_now_widget()
    {
        add_action("network_dashboard_right_now_content_table_end", [
            $this,
            "add_right_now_plugin_link",
        ]);
    }

    public function add_right_now_plugin_link()
    {
        if (!current_user_can("manage_network_plugins")) {
            return;
        }

        echo "<tr>";
        echo '<td class="first b"><a href="' .
            network_admin_url("plugin-install.php") .
            '">' .
            __("Add New Plugin", "wp-multisite-dashboard") .
            "</a></td>";
        echo '<td class="t"><a href="' .
            network_admin_url("plugins.php") .
            '">' .
            __("Manage Plugins", "wp-multisite-dashboard") .
            "</a></td>";
        echo "</tr>";
    }

    public function add_right_now_enhancements()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== "dashboard-network") {
            return;
        }

        // Use network-level counts for multisite where possible
        $sites_count = function_exists('get_blog_count')
            ? get_blog_count()
            : get_sites(["count" => true, "archived" => 0, "spam" => 0, "deleted" => 0]);

        $users_count = function_exists('get_user_count')
            ? get_user_count()
            : (count_users()["total_users"] ?? 0);

        $themes_count = count(wp_get_themes(["allowed" => "network"]));
        $plugins_count = count(get_plugins());

        $sites_label = __("sites", "wp-multisite-dashboard");
        $users_label = __("users", "wp-multisite-dashboard");
        $themes_label = __("themes", "wp-multisite-dashboard");
        $plugins_label = __("plugins", "wp-multisite-dashboard");
        $you_have_text = __("You have", "wp-multisite-dashboard");
        ?>
        <style>
        .youhave-enhanced {
            line-height: 1.6;
            margin: 12px 4px;
        }
        .youhave-enhanced .stat-number {
            margin: 0 2px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var $subsubsub = $('.subsubsub');
            if ($subsubsub.length) {
                var pluginLink = '<li class="add-plugin"> | <a href="<?php echo network_admin_url(
                    "plugin-install.php"
                ); ?>"><?php echo esc_js(
    __("Add New Plugin", "wp-multisite-dashboard")
); ?></a></li>';
                $subsubsub.append(pluginLink);
            }

            var $youhave = $('.youhave');
            if ($youhave.length) {
                var enhancedText = '<?php echo esc_js($you_have_text); ?> ' +
                    '<span class="stat-number"><?php echo $sites_count; ?></span> <span class="stat-label"><?php echo esc_js(
    $sites_label
); ?></span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $users_count; ?></span> <span class="stat-label"><?php echo esc_js(
    $users_label
); ?></span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $themes_count; ?></span> <span class="stat-label"><?php echo esc_js(
    $themes_label
); ?></span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $plugins_count; ?></span> <span class="stat-label"><?php echo esc_js(
    $plugins_label
); ?></span>';

                $youhave.html(enhancedText).addClass('youhave-enhanced');
            }
        });
        </script>
        <?php
    }

    public function manage_system_widgets()
    {
        global $wp_meta_boxes;

        $disabled_widgets = get_site_option("msd_disabled_system_widgets", []);

        if (empty($disabled_widgets)) {
            return;
        }

        // Handle network dashboard widgets
        $this->remove_widgets_from_dashboard("dashboard-network", $disabled_widgets);
        
        // Also handle regular dashboard widgets that might appear in network context
        $this->remove_widgets_from_dashboard("dashboard", $disabled_widgets);
    }

    private function remove_widgets_from_dashboard($dashboard_type, $disabled_widgets)
    {
        global $wp_meta_boxes;

        if (!isset($wp_meta_boxes[$dashboard_type])) {
            return;
        }

        foreach ($disabled_widgets as $widget_id) {
            // Remove from all possible contexts and priorities
            $contexts = ['normal', 'side', 'column3', 'column4'];
            $priorities = ['high', 'core', 'default', 'low'];

            foreach ($contexts as $context) {
                if (!isset($wp_meta_boxes[$dashboard_type][$context])) {
                    continue;
                }

                foreach ($priorities as $priority) {
                    if (isset($wp_meta_boxes[$dashboard_type][$context][$priority][$widget_id])) {
                        unset($wp_meta_boxes[$dashboard_type][$context][$priority][$widget_id]);
                    }
                }
            }
        }
    }

    public function cache_detected_widgets()
    {
        global $wp_meta_boxes;

        // Detect network dashboard widgets only for now to avoid memory issues
        $detected_widgets = $this->detect_network_widgets();
        
        // Only detect child site widgets if explicitly requested and memory allows
        if (defined('MSD_ENABLE_CHILD_SITE_DETECTION') && MSD_ENABLE_CHILD_SITE_DETECTION) {
            $child_site_widgets = $this->detect_child_site_widgets_safe();
            $detected_widgets = array_merge($detected_widgets, $child_site_widgets);
        }

        // 记录检测时间
        update_site_option('msd_last_widget_detection', time());

        set_site_transient(
            "msd_detected_widgets",
            $detected_widgets,
            12 * HOUR_IN_SECONDS
        );
    }

    public function detect_network_widgets()
    {
        global $wp_meta_boxes;

        // 确保网络仪表板已初始化
        if (!isset($wp_meta_boxes["dashboard-network"])) {
            // Only trigger dashboard setup if not in AJAX context to avoid recursion
            if (!wp_doing_ajax()) {
                do_action('wp_network_dashboard_setup');
            }
        }

        if (!isset($wp_meta_boxes["dashboard-network"])) {
            // Return empty array - widgets will be detected on next dashboard visit
            return [];
        }

        $detected_widgets = [];

        foreach (
            $wp_meta_boxes["dashboard-network"]
            as $context => $priorities
        ) {
            if (!is_array($priorities)) {
                continue;
            }

            foreach ($priorities as $priority => $widgets) {
                if (!is_array($widgets)) {
                    continue;
                }

                foreach ($widgets as $widget_id => $widget_data) {
                    // 跳过我们自己的小工具
                    if (strpos($widget_id, "msd_") === 0) {
                        continue;
                    }

                    $widget_title = $widget_data["title"] ?? $widget_id;
                    if (is_callable($widget_title)) {
                        $widget_title = $this->extract_callable_title($widget_title) ?: $widget_id;
                    }

                    // 确保标题是字符串
                    if (!is_string($widget_title)) {
                        $widget_title = $widget_id;
                    }

                    $detected_widgets[$widget_id] = [
                        "id" => $widget_id,
                        "title" => $widget_title,
                        "context" => $context,
                        "priority" => $priority,
                        "is_custom" => false,
                        "is_system" => $this->is_system_widget($widget_id),
                        "source" => "network",
                        "detected_at" => time(),
                    ];
                }
            }
        }

        return $detected_widgets;
    }

    private function detect_child_site_widgets_safe()
    {
        // Check memory usage before proceeding
        $memory_limit = $this->get_memory_limit_bytes();
        $current_memory = memory_get_usage(true);
        
        // Only proceed if we have at least 64MB of memory available
        if (($memory_limit - $current_memory) < (64 * 1024 * 1024)) {
            return [];
        }

        $child_widgets = [];
        $current_blog_id = get_current_blog_id();
        
        // Get only 3 most recent sites to avoid memory issues
        $sites = get_sites([
            'number' => 3,
            'orderby' => 'last_updated',
            'order' => 'DESC',
            'public' => 1,
            'archived' => 0,
            'spam' => 0,
            'deleted' => 0
        ]);

        foreach ($sites as $site) {
            // Skip if this is the current site to avoid recursion
            if ($site->blog_id == $current_blog_id) {
                continue;
            }

            try {
                switch_to_blog($site->blog_id);
                
                // Use a simpler approach - just check for known third-party widgets
                $known_third_party_widgets = $this->get_known_third_party_widgets();
                
                foreach ($known_third_party_widgets as $widget_id => $widget_info) {
                    if (!isset($child_widgets[$widget_id])) {
                        $child_widgets[$widget_id] = array_merge($widget_info, [
                            "source" => "child_site",
                            "detected_on_site" => $site->blog_id,
                        ]);
                    }
                }
                
            } catch (Exception $e) {
                // Silently continue on error
            } finally {
                restore_current_blog();
            }
        }

        return $child_widgets;
    }

    private function get_known_third_party_widgets()
    {
        // Return a list of commonly known third-party dashboard widgets
        return [
            'woocommerce_dashboard_status' => [
                'id' => 'woocommerce_dashboard_status',
                'title' => __('WooCommerce Status', 'wp-multisite-dashboard'),
                'context' => 'normal',
                'priority' => 'high',
                'is_custom' => false,
                'is_system' => false,
            ],
            'yoast_db_widget' => [
                'id' => 'yoast_db_widget',
                'title' => __('Yoast SEO Posts Overview', 'wp-multisite-dashboard'),
                'context' => 'normal',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => false,
            ],
            'jetpack_summary_widget' => [
                'id' => 'jetpack_summary_widget',
                'title' => __('Site Stats', 'wp-multisite-dashboard'),
                'context' => 'normal',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => false,
            ],
            'bbp-dashboard-right-now' => [
                'id' => 'bbp-dashboard-right-now',
                'title' => __('bbPress Forum Summary', 'wp-multisite-dashboard'),
                'context' => 'normal',
                'priority' => 'core',
                'is_custom' => false,
                'is_system' => false,
            ],
        ];
    }

    private function get_memory_limit_bytes()
    {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    // Removed setup_temporary_dashboard to prevent memory issues

    private function extract_callable_title($callable)
    {
        if (is_string($callable)) {
            return $callable;
        }
        
        if (is_array($callable) && count($callable) >= 2) {
            $method = $callable[1];
            // Try to extract meaningful name from method
            return ucwords(str_replace(['_', 'widget', 'dashboard'], [' ', '', ''], $method));
        }
        
        return null;
    }

    private function is_system_widget($widget_id)
    {
        $system_widgets = [
            'dashboard_right_now',
            'dashboard_activity',
            'dashboard_quick_press',
            'dashboard_recent_drafts',
            'dashboard_recent_comments',
            'dashboard_incoming_links',
            'dashboard_plugins',
            'dashboard_primary',
            'dashboard_secondary',
            'network_dashboard_right_now',
        ];
        
        return in_array($widget_id, $system_widgets);
    }

    public function get_enabled_widgets()
    {
        return $this->enabled_widgets;
    }

    public function get_update_checker()
    {
        return $this->update_checker;
    }

    public function invalidate_widget_cache()
    {
        delete_site_transient("msd_detected_widgets");
    }

    public function force_widget_detection($enable_child_sites = false)
    {
        // Clear existing cache
        $this->invalidate_widget_cache();
        
        // Temporarily enable child site detection if requested
        if ($enable_child_sites) {
            if (!defined('MSD_ENABLE_CHILD_SITE_DETECTION')) {
                define('MSD_ENABLE_CHILD_SITE_DETECTION', true);
            }
        }
        
        // Force immediate detection
        $this->cache_detected_widgets();
        
        return get_site_transient("msd_detected_widgets");
    }

    private function handle_import_export_actions()
    {
        // Handle export
        if (isset($_GET['msd_action']) && $_GET['msd_action'] === 'export_settings') {
            $this->settings_manager->export_settings();
        }

        // Handle import
        if (isset($_POST['msd_import_settings'])) {
            $this->settings_manager->import_settings();
        }
    }
}
