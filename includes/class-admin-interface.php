<?php

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Admin_Interface
{
    public function __construct()
    {
        add_action("admin_footer", [$this, "render_modals"]);
    }

    public function render_modals()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === "dashboard-network") {
            include_once WP_MSD_PLUGIN_DIR . "templates/admin-modals.php";
        }
    }

    public function add_network_widgets()
    {
        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $enabled_widgets = $plugin_core->get_enabled_widgets();

        $widgets = [
            "msd_network_overview" => [
                __("Network Overview", "wp-multisite-dashboard"),
                "render_network_overview_widget",
            ],
            "msd_quick_site_management" => [
                __("Quick Site Management", "wp-multisite-dashboard"),
                "render_quick_site_widget",
            ],
            "msd_storage_performance" => [
                __("Storage Usage", "wp-multisite-dashboard"),
                "render_storage_performance_widget",
            ],
            "msd_server_info" => [
                __("Server Information", "wp-multisite-dashboard"),
                "render_server_info_widget",
            ],
            "msd_quick_links" => [
                __("Quick Links", "wp-multisite-dashboard"),
                "render_quick_links_widget",
            ],
            "msd_version_info" => [
                __("Version Information", "wp-multisite-dashboard"),
                "render_version_info_widget",
            ],
            "msd_custom_news" => [
                __("Network News", "wp-multisite-dashboard"),
                "render_custom_news_widget",
            ],
            "msd_user_management" => [
                __("User Management", "wp-multisite-dashboard"),
                "render_user_management_widget",
            ],
            "msd_contact_info" => [
                __("Contact Information", "wp-multisite-dashboard"),
                "render_contact_info_widget",
            ],
            "msd_last_edits" => [
                __("Recent Network Activity", "wp-multisite-dashboard"),
                "render_last_edits_widget",
            ],
            "msd_todo_widget" => [
                __("Todo List", "wp-multisite-dashboard"),
                "render_todo_widget",
            ],
            // New monitoring widgets
            "msd_error_logs" => [
                __("PHP Error Logs", "wp-multisite-dashboard"),
                "render_error_logs_widget",
            ],
            "msd_404_monitor" => [
                __("404 Monitor", "wp-multisite-dashboard"),
                "render_404_monitor_widget",
            ],
        ];

        foreach ($widgets as $widget_id => $widget_data) {
            if (!empty($enabled_widgets[$widget_id])) {
                wp_add_dashboard_widget($widget_id, $widget_data[0], [
                    $this,
                    $widget_data[1],
                ]);
            }
        }
    }

    public function render_network_overview_widget()
    {
        echo '<div id="msd-network-overview" class="msd-widget-content" data-widget="network_overview">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_quick_site_widget()
    {
        echo '<div id="msd-quick-sites" class="msd-widget-content" data-widget="site_list">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_storage_performance_widget()
    {
        echo '<div id="msd-storage-performance" class="msd-widget-content" data-widget="storage_data">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_server_info_widget()
    {
        echo '<div id="msd-server-info" class="msd-widget-content" data-widget="server_info">';
        echo '<button class="msd-refresh-btn" title="' .
            esc_attr__("Refresh", "wp-multisite-dashboard") .
            '" data-widget="server_info">↻</button>';
        $this->render_server_info_content();
        echo "</div>";
    }

    private function render_server_info_content()
    {
        global $wpdb, $wp_version;

        $data = [
            __("PHP Version", "wp-multisite-dashboard") => phpversion(),
            __(
                "MySQL Version",
                "wp-multisite-dashboard"
            ) => $wpdb->db_version(),
            __("Server Software", "wp-multisite-dashboard") =>
                $_SERVER["SERVER_SOFTWARE"] ??
                __("Unknown", "wp-multisite-dashboard"),
            __("Server Time", "wp-multisite-dashboard") => current_time(
                "Y-m-d H:i:s"
            ),
            __("Memory Limit", "wp-multisite-dashboard") => ini_get(
                "memory_limit"
            ),
            __("Max Upload Size", "wp-multisite-dashboard") => size_format(
                wp_max_upload_size()
            ),
        ];

        $icons = [
            __(
                "PHP Version",
                "wp-multisite-dashboard"
            ) => "dashicons-editor-code",
            __(
                "MySQL Version",
                "wp-multisite-dashboard"
            ) => "dashicons-database",
            __(
                "Server Software",
                "wp-multisite-dashboard"
            ) => "dashicons-admin-tools",
            __("Server Time", "wp-multisite-dashboard") => "dashicons-clock",
            __(
                "Memory Limit",
                "wp-multisite-dashboard"
            ) => "dashicons-performance",
            __(
                "Max Upload Size",
                "wp-multisite-dashboard"
            ) => "dashicons-upload",
        ];

        echo '<div class="msd-server-specs">';
        foreach ($data as $label => $value) {
            $icon = $icons[$label] ?? "dashicons-info";
            echo '<div class="msd-spec-item">';
            echo '<span class="msd-spec-icon dashicons ' .
                esc_attr($icon) .
                '"></span>';
            echo '<span class="msd-spec-label">' . esc_html($label) . "</span>";
            echo '<span class="msd-spec-value">' . esc_html($value) . "</span>";
            echo "</div>";
        }
        echo "</div>";
    }

    public function render_version_info_widget()
    {
        echo '<div id="msd-version-info" class="msd-widget-content" data-widget="version_info">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_custom_news_widget()
    {
        echo '<div id="msd-custom-news" class="msd-widget-content" data-widget="custom_news">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_user_management_widget()
    {
        echo '<div id="msd-user-management" class="msd-widget-content" data-widget="user_management">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_contact_info_widget()
    {
        echo '<div id="msd-contact-info" class="msd-widget-content" data-widget="contact_info">';
        echo '<button class="msd-refresh-btn" title="' .
            esc_attr__("Refresh", "wp-multisite-dashboard") .
            '" data-widget="contact_info">↻</button>';
        $this->render_contact_info_content();
        echo "</div>";
    }

    private function render_contact_info_content()
    {
        $contact_info = get_site_option("msd_contact_info", [
            "name" => get_network_option(null, "site_name"),
            "email" => get_network_option(null, "admin_email"),
            "phone" => "",
            "website" => network_home_url(),
            "description" => __(
                "Network Administrator Contact Information",
                "wp-multisite-dashboard"
            ),
            "qq" => "",
            "wechat" => "",
            "whatsapp" => "",
            "telegram" => "",
            "qr_code" => "",
        ]);

        echo '<div class="msd-contact-card">';
        echo '<div class="msd-contact-header">';
        echo '<h3><span class="dashicons dashicons-coffee"></span> ' .
            esc_html($contact_info["name"]) .
            "</h3>";
        echo "</div>";

        echo '<div class="msd-contact-details">';

        if (!empty($contact_info["description"])) {
            echo '<p class="msd-contact-description">' .
                esc_html($contact_info["description"]) .
                "</p>";
        }

        echo '<div class="msd-contact-item">';
        echo '<span class="dashicons dashicons-email"></span>';
        echo '<a href="mailto:' .
            esc_attr($contact_info["email"]) .
            '">' .
            esc_html($contact_info["email"]) .
            "</a>";
        echo "</div>";

        if (!empty($contact_info["phone"])) {
            echo '<div class="msd-contact-item">';
            echo '<span class="dashicons dashicons-phone"></span>';
            echo '<a href="tel:' .
                esc_attr($contact_info["phone"]) .
                '">' .
                esc_html($contact_info["phone"]) .
                "</a>";
            echo "</div>";
        }

        echo '<div class="msd-contact-item">';
        echo '<span class="dashicons dashicons-admin-links"></span>';
        echo '<a href="' .
            esc_url($contact_info["website"]) .
            '" target="_blank">' .
            esc_html($contact_info["website"]) .
            "</a>";
        echo "</div>";

        $im_fields = [
            "qq" => ["QQ", "dashicons-admin-users"],
            "wechat" => [
                __("WeChat", "wp-multisite-dashboard"),
                "dashicons-format-chat",
            ],
            "whatsapp" => [
                __("WhatsApp", "wp-multisite-dashboard"),
                "dashicons-smartphone",
            ],
            "telegram" => [
                __("Telegram", "wp-multisite-dashboard"),
                "dashicons-email-alt",
            ],
        ];

        foreach ($im_fields as $field => $data) {
            if (!empty($contact_info[$field])) {
                echo '<div class="msd-contact-item">';
                echo '<span class="dashicons ' . $data[1] . '"></span>';
                echo "<span>" .
                    esc_html($data[0]) .
                    ": " .
                    esc_html($contact_info[$field]) .
                    "</span>";
                echo "</div>";
            }
        }

        if (!empty($contact_info["qr_code"])) {
            echo '<div class="msd-contact-qr">';
            echo '<img src="' .
                esc_url($contact_info["qr_code"]) .
                '" alt="' .
                esc_attr__("QR Code", "wp-multisite-dashboard") .
                '" class="msd-qr-image">';
            echo "</div>";
        }

        echo "</div>";

        echo '<div class="msd-contact-actions">';
        echo '<button class="button button-small" onclick="MSD.showContactInfoModal()">' .
            __("Edit Contact Info", "wp-multisite-dashboard") .
            "</button>";
        echo "</div>";

        echo "</div>";
    }

    public function render_last_edits_widget()
    {
        echo '<div id="msd-last-edits" class="msd-widget-content" data-widget="last_edits">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_quick_links_widget()
    {
        $quick_links = get_site_option("msd_quick_links", []);

        echo '<div id="msd-quick-links" class="msd-widget-content">';

        if (empty($quick_links)) {
            echo '<div class="msd-empty-state">';
            echo "<p>" .
                __("No quick links configured.", "wp-multisite-dashboard") .
                "</p>";
            echo '<button class="button button-primary button-small" onclick="MSD.showQuickLinksModal()">' .
                __("Add Links", "wp-multisite-dashboard") .
                "</button>";
            echo "</div>";
        } else {
            echo '<div class="msd-quick-links-grid" id="msd-sortable-links">';
            foreach ($quick_links as $index => $link) {
                $target = !empty($link["new_tab"]) ? "_blank" : "_self";
                echo '<a href="' .
                    esc_url($link["url"]) .
                    '" target="' .
                    $target .
                    '" class="msd-quick-link-item" data-index="' .
                    $index .
                    '">';

                if (!empty($link["icon"])) {
                    if (strpos($link["icon"], "dashicons-") === 0) {
                        echo '<span class="dashicons ' .
                            esc_attr($link["icon"]) .
                            '"></span>';
                    } elseif (
                        mb_strlen($link["icon"]) <= 4 &&
                        preg_match("/[\x{1F000}-\x{1F9FF}]/u", $link["icon"])
                    ) {
                        echo '<span class="msd-emoji-icon">' .
                            esc_html($link["icon"]) .
                            "</span>";
                    }
                }

                echo "<span>" . esc_html($link["title"]) . "</span>";
                echo "</a>";
            }
            echo "</div>";
            echo '<div class="msd-widget-footer">';
            echo '<button class="button button-secondary button-small" onclick="MSD.showQuickLinksModal()">' .
                __("Edit Links", "wp-multisite-dashboard") .
                "</button>";
            echo "</div>";
        }

        echo "</div>";
    }

    public function render_todo_widget()
    {
        echo '<div id="msd-todo-widget" class="msd-widget-content" data-widget="todo_items">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' .
            __("Loading...", "wp-multisite-dashboard") .
            "</div>";
        echo "</div>";
    }

    public function render_error_logs_widget()
    {
        echo '<div id="msd-error-logs" class="msd-widget-content" data-widget="error_logs">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __("Loading...", "wp-multisite-dashboard") . '</div>';
        echo '</div>';
    }

    public function render_404_monitor_widget()
    {
        echo '<div id="msd-404-monitor" class="msd-widget-content" data-widget="monitor_404">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __("Loading...", "wp-multisite-dashboard") . '</div>';
        echo '</div>';
    }
}
