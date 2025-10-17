<?php
if (!defined("ABSPATH")) {
    exit();
}

// Handle messages
if (isset($_GET["updated"]) && $_GET["updated"] === "true") {
    echo '<div class="notice notice-success is-dismissible"><p>' .
        __("Settings saved successfully!", "wp-multisite-dashboard") .
        "</p></div>";
}

if (isset($_GET["import_success"]) && $_GET["import_success"] === "true") {
    echo '<div class="notice notice-success is-dismissible"><p>' .
        __("Settings imported successfully!", "wp-multisite-dashboard") .
        "</p></div>";
}

if (isset($_GET["import_error"])) {
    $error_messages = [
        "file_error" => __(
            "File upload error. Please try again.",
            "wp-multisite-dashboard",
        ),
        "invalid_json" => __(
            "Invalid JSON format. Please check your file.",
            "wp-multisite-dashboard",
        ),
        "invalid_format" => __(
            "Invalid file format. Please use a valid export file.",
            "wp-multisite-dashboard",
        ),
    ];

    $error_key = sanitize_key($_GET["import_error"]);
    $error_message =
        $error_messages[$error_key] ??
        __("Import failed. Please try again.", "wp-multisite-dashboard");

    echo '<div class="notice notice-error is-dismissible"><p>' .
        esc_html($error_message) .
        "</p></div>";
}

$plugin_core = WP_MSD_Plugin_Core::get_instance();
$stored_enabled = get_site_option("msd_enabled_widgets", null);
$enabled_widgets = is_array($stored_enabled)
    ? $stored_enabled
    : $plugin_core->get_enabled_widgets();
$settings_manager = new WP_MSD_Settings_Manager();

$widget_options = [
    "msd_network_overview" => __("Network Overview", "wp-multisite-dashboard"),
    "msd_quick_site_management" => __(
        "Quick Site Management",
        "wp-multisite-dashboard",
    ),
    "msd_storage_performance" => __("Storage Usage", "wp-multisite-dashboard"),
    "msd_server_info" => __("Server Information", "wp-multisite-dashboard"),
    "msd_quick_links" => __("Quick Links", "wp-multisite-dashboard"),
    "msd_version_info" => __("Version Information", "wp-multisite-dashboard"),
    "msd_custom_news" => __("Network News", "wp-multisite-dashboard"),
    "msd_user_management" => __("User Management", "wp-multisite-dashboard"),
    "msd_contact_info" => __("Contact Information", "wp-multisite-dashboard"),
    "msd_last_edits" => __("Recent Network Activity", "wp-multisite-dashboard"),
    "msd_todo_widget" => __("Todo List", "wp-multisite-dashboard"),
    "msd_error_logs" => __("PHP Error Logs", "wp-multisite-dashboard"),
    "msd_404_monitor" => __("404 Monitor", "wp-multisite-dashboard"),
];

// Determine active tab
$active_tab = isset($_GET["tab"]) ? sanitize_key($_GET["tab"]) : "widgets";
?>

<div class="wrap msd-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php printf(
                esc_html__("Version: %s", "wp-multisite-dashboard"),
                esc_html(WP_MSD_VERSION),
            ); ?>
        </span>
        <a href="https://wpmultisite.com/document/wp-multisite-dashboard" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e("Documentation", "wp-multisite-dashboard"); ?>
        </a>
        <a href="https://wpmultisite.com/support/" target="_blank" class="button button-secondary">
            <?php esc_html_e("Support", "wp-multisite-dashboard"); ?>
        </a>
    </h1>

    <div id="msd-status" class="notice" style="display:none; margin-top: 10px;"></div>

    <div class="msd-card">
        <div class="msd-tabs-wrapper">
            <div class="msd-sync-tabs">
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "widgets"
                                                            ? "active"
                                                            : ""; ?>" data-tab="widgets">
                    <?php _e("Widgets", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "system"
                                                            ? "active"
                                                            : ""; ?>" data-tab="system">
                    <?php _e("System Widgets", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "cache"
                                                            ? "active"
                                                            : ""; ?>" data-tab="cache">
                    <?php _e("Cache", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "import-export"
                                                            ? "active"
                                                            : ""; ?>" data-tab="import-export">
                    <?php _e("Import/Export", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "performance"
                                                            ? "active"
                                                            : ""; ?>" data-tab="performance">
                    <?php _e("Performance", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "monitoring"
                                                            ? "active"
                                                            : ""; ?>" data-tab="monitoring">
                    <?php _e("Monitoring", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="msd-tab <?php echo $active_tab ===
                                                            "info"
                                                            ? "active"
                                                            : ""; ?>" data-tab="info">
                    <?php _e("Info", "wp-multisite-dashboard"); ?>
                </button>
            </div>
        </div>

        <!-- Widget Settings Tab -->
        <div class="msd-section <?php echo $active_tab === "widgets"
                                    ? "active"
                                    : ""; ?>" id="msd-section-widgets">
            <h2><?php _e(
                    "Widget Configuration",
                    "wp-multisite-dashboard",
                ); ?></h2>
            <p class="msd-page-description"><?php _e(
                                                "Enable or disable dashboard widgets. Integration widgets require their corresponding plugins to be installed and activated.",
                                                "wp-multisite-dashboard",
                                            ); ?></p>

            <form method="post" action="" id="msd-widgets-form">
                <?php wp_nonce_field("msd_settings", "msd_settings_nonce"); ?>
                <input type="hidden" name="msd_form" value="widgets" />
                <input type="hidden" name="current_tab" value="widgets" />

                <!-- Built-in Widgets Section -->
                <div class="msd-widget-config-section">
                    <div class="msd-section-header">
                        <h3><?php _e("Built-in Widgets", "wp-multisite-dashboard"); ?></h3>
                        <p class="msd-section-desc"><?php _e(
                                                        "Core dashboard widgets provided by WP Multisite Dashboard.",
                                                        "wp-multisite-dashboard"
                                                    ); ?></p>
                    </div>

                    <div class="msd-settings-grid">
                        <?php foreach (
                            $widget_options
                            as $widget_id => $widget_name
                        ):
                            // Skip integration widgets in this section
                            if (strpos($widget_id, 'msd_domain_mapping') !== false) {
                                continue;
                            }
                        ?>
                            <div class="msd-widget-toggle">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="widgets[<?php echo esc_attr($widget_id); ?>]"
                                        value="1"
                                        <?php checked(!empty($enabled_widgets[$widget_id])); ?> />
                                    <span class="msd-widget-label"><?php echo esc_html($widget_name); ?></span>
                                </label>
                                <p class="description">
                                    <?php echo $settings_manager->get_widget_description($widget_id); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Integration Widgets Section -->
                <div class="msd-widget-config-section msd-integration-widgets-section">
                    <div class="msd-section-header">
                        <h3><?php _e("Integration Widgets", "wp-multisite-dashboard"); ?></h3>
                        <p class="msd-section-desc"><?php _e(
                                                        "Widgets that integrate with other plugins. These widgets are only available when the required plugins are installed and activated.",
                                                        "wp-multisite-dashboard"
                                                    ); ?></p>
                    </div>

                    <?php
                    // Get all integration widgets
                    $integration_widgets = [
                        'msd_domain_mapping_overview' => [
                            'name' => __('Domain Mapping Overview', 'wp-multisite-dashboard'),
                            'description' => __('Network-wide domain mapping statistics, health monitoring, and quick access to domain management', 'wp-multisite-dashboard'),
                            'required_plugin' => 'WP Domain Mapping',
                            'plugin_file' => 'wp-domain-mapping/wp-domain-mapping.php',
                            'integration' => WP_MSD_Domain_Mapping_Integration::get_instance(),
                        ],
                        // Future integrations can be added here
                    ];
                    ?>

                    <div class="msd-integration-widgets-grid">
                        <?php foreach ($integration_widgets as $widget_id => $widget_info):
                            $integration = $widget_info['integration'];
                            $status = $integration->get_plugin_status();
                            $is_enabled = !empty($enabled_widgets[$widget_id]);
                            $is_available = ($status === 'active');
                        ?>
                            <div class="msd-integration-widget-box msd-plugin-status-<?php echo esc_attr($status); ?>">
                                <div class="msd-integration-widget-main">
                                    <div class="msd-widget-checkbox-wrapper">
                                        <label class="<?php echo !$is_available ? 'msd-disabled' : ''; ?>">
                                            <input
                                                type="checkbox"
                                                name="integration_widgets[<?php echo esc_attr($widget_id); ?>]"
                                                value="1"
                                                <?php checked($is_enabled); ?>
                                                <?php disabled(!$is_available); ?> />
                                            <span class="msd-widget-title"><?php echo esc_html($widget_info['name']); ?></span>
                                        </label>
                                    </div>

                                    <div class="msd-plugin-status-badge msd-status-<?php echo esc_attr($status); ?>">
                                        <?php if ($status === 'active'): ?>
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <span><?php _e('Active', 'wp-multisite-dashboard'); ?></span>
                                        <?php elseif ($status === 'installed'): ?>
                                            <span class="dashicons dashicons-warning"></span>
                                            <span><?php _e('Not Activated', 'wp-multisite-dashboard'); ?></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <span><?php _e('Not Installed', 'wp-multisite-dashboard'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="msd-widget-description">
                                    <?php echo esc_html($widget_info['description']); ?>
                                </p>

                                <div class="msd-integration-widget-footer">
                                    <div class="msd-required-plugin-info">
                                        <span class="dashicons dashicons-admin-plugins"></span>
                                        <span><?php printf(__('Requires: %s', 'wp-multisite-dashboard'), '<strong>' . esc_html($widget_info['required_plugin']) . '</strong>'); ?></span>
                                    </div>

                                    <?php if ($status !== 'active'): ?>
                                        <div class="msd-plugin-actions">
                                            <?php if ($status === 'installed'): ?>
                                                <a href="<?php echo wp_nonce_url(network_admin_url('plugins.php?action=activate&plugin=' . $widget_info['plugin_file']), 'activate-plugin_' . $widget_info['plugin_file']); ?>" class="button button-small button-primary">
                                                    <span class="dashicons dashicons-yes"></span>
                                                    <?php _e('Activate', 'wp-multisite-dashboard'); ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo network_admin_url('plugin-install.php?s=wp+domain+mapping&tab=search'); ?>" class="button button-small button-primary">
                                                    <span class="dashicons dashicons-download"></span>
                                                    <?php _e('Install', 'wp-multisite-dashboard'); ?>
                                                </a>
                                            <?php endif; ?>
                                            <a href="https://wenpai.org/plugins/wp-domain-mapping/" target="_blank" class="button button-small">
                                                <?php _e('Learn More', 'wp-multisite-dashboard'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="msd-submit-wrapper">
                    <?php submit_button(
                        __("Save Widget Settings", "wp-multisite-dashboard"),
                        "primary",
                        "submit",
                        false,
                    ); ?>
                </div>
            </form>
        </div>

        <!-- System Widgets Tab -->
        <div class="msd-section <?php echo $active_tab === "system"
                                    ? "active"
                                    : ""; ?>" id="msd-section-system">
            <h2><?php _e(
                    "System & Third-Party Widgets",
                    "wp-multisite-dashboard",
                ); ?></h2>
            <p class="msd-section-desc"><?php _e(
                                            "Control the display of WordPress system widgets and widgets from other plugins.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>

            <?php // 显示检测状态

            $stats = $settings_manager->get_widget_detection_stats(); ?>
            <div class="msd-detection-status show">
                <p>
                    <span class="dashicons dashicons-info"></span>
                    <strong><?php _e(
                                "Detection Status:",
                                "wp-multisite-dashboard",
                            ); ?></strong>
                    <?php printf(
                        __(
                            "Found %d widgets (%d system, %d third-party). Last detection: %s",
                            "wp-multisite-dashboard",
                        ),
                        $stats["total_widgets"],
                        $stats["system_widgets"],
                        $stats["third_party_widgets"],
                        $stats["last_detection_human"],
                    ); ?>
                </p>
            </div>

            <form method="post" action="" id="msd-system-form">
                <?php wp_nonce_field("msd_settings", "msd_settings_nonce"); ?>
                <input type="hidden" name="msd_form" value="system" />
                <input type="hidden" name="current_tab" value="system" />

                <?php
                $available_widgets = $settings_manager->get_available_system_widgets();
                $disabled_widgets = get_site_option(
                    "msd_disabled_system_widgets",
                    [],
                );

                if (!empty($available_widgets)):

                    $system_widgets = array_filter(
                        $available_widgets,
                        function ($widget) {
                            return $widget["is_system"] ?? false;
                        },
                    );

                    $third_party_widgets = array_filter(
                        $available_widgets,
                        function ($widget) {
                            return !($widget["is_system"] ?? false) &&
                                !($widget["is_custom"] ?? false);
                        },
                    );
                ?>

                    <div class="msd-system-widgets-grid">
                        <?php if (!empty($system_widgets)): ?>
                            <div class="msd-widget-section">
                                <h4><?php _e(
                                        "WordPress System Widgets",
                                        "wp-multisite-dashboard",
                                    ); ?></h4>
                                <?php foreach (
                                    $system_widgets
                                    as $widget_id => $widget_data
                                ): ?>
                                    <div class="msd-widget-toggle">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="system_widgets[<?php echo esc_attr(
                                                                            $widget_id,
                                                                        ); ?>]"
                                                value="1"
                                                <?php checked(
                                                    !in_array(
                                                        $widget_id,
                                                        $disabled_widgets,
                                                    ),
                                                ); ?> />
                                            <?php echo esc_html(
                                                $widget_data["title"],
                                            ); ?>
                                            <span class="msd-widget-meta">(<?php echo esc_html(
                                                                                $widget_data["context"],
                                                                            ); ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- 第三方小工具部分 -->
                        <div class="msd-widget-section">
                            <h4><?php _e(
                                    "Third-Party Plugin Widgets",
                                    "wp-multisite-dashboard",
                                ); ?></h4>

                            <?php if (!empty($third_party_widgets)): ?>
                                <!-- 显示检测到的第三方小工具 -->
                                <?php foreach (
                                    $third_party_widgets
                                    as $widget_id => $widget_data
                                ): ?>
                                    <div class="msd-widget-toggle">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="system_widgets[<?php echo esc_attr(
                                                                            $widget_id,
                                                                        ); ?>]"
                                                value="1"
                                                <?php checked(
                                                    !in_array(
                                                        $widget_id,
                                                        $disabled_widgets,
                                                    ),
                                                ); ?> />
                                            <?php echo esc_html(
                                                $widget_data["title"],
                                            ); ?>
                                            <span class="msd-widget-meta">(<?php echo esc_html(
                                                                                $widget_data["context"],
                                                                            ); ?>)</span>
                                            <?php if (
                                                isset($widget_data["source"]) &&
                                                $widget_data["source"] ===
                                                "child_site"
                                            ): ?>
                                                <span class="msd-widget-source"><?php _e(
                                                                                    "Child Site",
                                                                                    "wp-multisite-dashboard",
                                                                                ); ?></span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                                <!-- 重新扫描提示 -->
                                <div class="msd-rescan-section">
                                    <p class="description"><?php _e(
                                                                "Found third-party widgets above. You can rescan to detect new widgets or refresh the list.",
                                                                "wp-multisite-dashboard",
                                                            ); ?></p>
                                </div>
                            <?php else: ?>
                                <!-- 没有检测到第三方小工具 -->
                                <div class="msd-no-third-party">
                                    <p><?php _e(
                                            "No third-party widgets detected yet.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>
                                    <p class="description"><?php _e(
                                                                "Third-party widgets are automatically detected when you visit the network dashboard. If you have plugins that add dashboard widgets, visit the dashboard first, then return here to see them.",
                                                                "wp-multisite-dashboard",
                                                            ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 检测控制区域（始终显示） -->
                        <div class="msd-detection-controls">
                            <h4><?php _e(
                                    "Widget Detection Controls",
                                    "wp-multisite-dashboard",
                                ); ?></h4>
                            <p class="description"><?php _e(
                                                        "Use these controls to detect new widgets or refresh the current list.",
                                                        "wp-multisite-dashboard",
                                                    ); ?></p>
                            <div class="msd-action-buttons">
                                <a href="<?php echo network_admin_url(); ?>" class="button button-secondary">
                                    <?php _e(
                                        "Visit Network Dashboard",
                                        "wp-multisite-dashboard",
                                    ); ?>
                                </a>
                                <button type="button" class="button" onclick="MSD.clearWidgetCache()">
                                    <?php _e(
                                        "Refresh Widget Detection",
                                        "wp-multisite-dashboard",
                                    ); ?>
                                </button>
                                <button type="button" class="button button-primary" onclick="MSD.forceNetworkWidgetDetection()">
                                    <?php _e(
                                        "Force Network Scan",
                                        "wp-multisite-dashboard",
                                    ); ?>
                                </button>
                                <button type="button" class="button" onclick="MSD.forceWidgetDetection(true)" style="margin-left: 5px;">
                                    <?php _e(
                                        "Deep Scan (Include Child Sites)",
                                        "wp-multisite-dashboard",
                                    ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="msd-submit-wrapper">
                        <?php submit_button(
                            __(
                                "Save Widget Settings",
                                "wp-multisite-dashboard",
                            ),
                            "primary",
                            "submit",
                            false,
                        ); ?>
                    </div>

                <?php
                else:
                ?>
                    <div class="msd-no-widgets">
                        <p><?php _e(
                                "No system widgets found.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                        <p><?php _e(
                                "Visit the network dashboard to detect available widgets.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                        <div class="msd-action-buttons">
                            <a href="<?php echo network_admin_url(); ?>" class="button button-secondary">
                                <?php _e(
                                    "Visit Network Dashboard",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </a>
                            <button type="button" class="button" onclick="MSD.clearWidgetCache()">
                                <?php _e(
                                    "Refresh Widget Detection",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </button>
                            <button type="button" class="button button-primary" onclick="MSD.forceNetworkWidgetDetection()">
                                <?php _e(
                                    "Force Network Scan",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </button>
                            <button type="button" class="button" onclick="MSD.forceWidgetDetection(true)" style="margin-left: 5px;">
                                <?php _e(
                                    "Deep Scan (Include Child Sites)",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </button>
                        </div>
                    </div>
                <?php
                endif;
                ?>
            </form>
        </div>

        <!-- Cache Management Tab -->
        <div class="msd-section <?php echo $active_tab === "cache"
                                    ? "active"
                                    : ""; ?>" id="msd-section-cache">
            <h2><?php _e("Cache Management", "wp-multisite-dashboard"); ?></h2>
            <p class="msd-section-desc"><?php _e(
                                            "Clear cached data to refresh dashboard widgets and improve performance.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>

            <div class="msd-cache-stats-card">
                <h3><?php _e("Cache Status", "wp-multisite-dashboard"); ?></h3>
                <div id="msd-cache-stats">
                    <p><?php _e(
                            'Click "Check Cache Status" to view current cache information.',
                            "wp-multisite-dashboard",
                        ); ?></p>
                </div>
                <div class="msd-action-buttons">
                    <button type="button" class="button" onclick="MSD.checkCacheStatus()">
                        <?php _e(
                            "Check Cache Status",
                            "wp-multisite-dashboard",
                        ); ?>
                    </button>
                </div>
            </div>

            <div class="msd-cache-actions-card">
                <h3><?php _e(
                        "Cache Operations",
                        "wp-multisite-dashboard",
                    ); ?></h3>
                <p class="description">
                    <?php _e(
                        "Clearing caches will force the dashboard widgets to reload fresh data on the next page visit. Widget cache contains the list of detected third-party widgets.",
                        "wp-multisite-dashboard",
                    ); ?>
                </p>

                <div class="msd-cache-actions">
                    <button type="button" class="button" onclick="MSD.clearCache('all')">
                        <?php _e(
                            "Clear All Caches",
                            "wp-multisite-dashboard",
                        ); ?>
                    </button>
                    <button type="button" class="button" onclick="MSD.clearCache('network')">
                        <?php _e(
                            "Clear Network Data",
                            "wp-multisite-dashboard",
                        ); ?>
                    </button>
                    <button type="button" class="button" onclick="MSD.clearWidgetCache()">
                        <?php _e(
                            "Clear Widget Cache",
                            "wp-multisite-dashboard",
                        ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Import & Export Tab -->
        <div class="msd-section <?php echo $active_tab === "import-export"
                                    ? "active"
                                    : ""; ?>" id="msd-section-import-export">
            <h2><?php _e(
                    "Import & Export Settings",
                    "wp-multisite-dashboard",
                ); ?></h2>
            <p class="msd-section-desc"><?php _e(
                                            "Backup and restore your dashboard configuration, widget settings, and custom data.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>

            <div class="msd-import-export-section">
                <div class="msd-export-section">
                    <h3><?php _e(
                            "Export Settings",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <p class="description"><?php _e(
                                                "Download all plugin settings as a JSON file for backup or migration.",
                                                "wp-multisite-dashboard",
                                            ); ?></p>
                    <a href="<?php echo add_query_arg(
                                    [
                                        "msd_action" => "export_settings",
                                    ],
                                    network_admin_url("settings.php?page=msd-settings"),
                                ); ?>"
                        class="button button-primary">
                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                        <?php _e(
                            "Export Settings",
                            "wp-multisite-dashboard",
                        ); ?>
                    </a>
                </div>

                <div class="msd-import-section">
                    <h3><?php _e(
                            "Import Settings",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <p class="description"><?php _e(
                                                "Upload a previously exported settings file to restore configuration.",
                                                "wp-multisite-dashboard",
                                            ); ?></p>

                    <form method="post" enctype="multipart/form-data" id="msd-import-form">
                        <?php wp_nonce_field(
                            "msd_import_settings",
                            "msd_import_nonce",
                        ); ?>

                        <div class="msd-file-input-wrapper">
                            <input type="file"
                                name="import_file"
                                id="msd-import-file"
                                accept=".json"
                                required>
                            <label for="msd-import-file" class="button">
                                <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                                <?php _e(
                                    "Choose File",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </label>
                            <span class="msd-file-name"></span>
                        </div>

                        <div class="msd-import-preview" id="msd-import-preview" style="display: none;">
                            <h4><?php _e(
                                    "Import Preview",
                                    "wp-multisite-dashboard",
                                ); ?></h4>
                            <div class="msd-import-details"></div>
                        </div>

                        <div class="msd-import-actions">
                            <button type="button"
                                class="button"
                                onclick="MSD.validateImportFile()"
                                id="msd-validate-btn"
                                disabled>
                                <?php _e(
                                    "Validate File",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </button>

                            <button type="submit"
                                name="msd_import_settings"
                                class="button button-primary"
                                id="msd-import-btn"
                                disabled>
                                <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                                <?php _e(
                                    "Import Settings",
                                    "wp-multisite-dashboard",
                                ); ?>
                            </button>
                        </div>
                    </form>

                    <div class="msd-import-warning">
                        <p><strong><?php _e(
                                        "Warning:",
                                        "wp-multisite-dashboard",
                                    ); ?></strong>
                            <?php _e(
                                "Importing settings will overwrite your current configuration. Make sure to export your current settings first as a backup.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Tab -->
        <div class="msd-section <?php echo $active_tab === "performance"
                                    ? "active"
                                    : ""; ?>" id="msd-section-performance">
            <h2><?php _e(
                    "Performance Monitoring",
                    "wp-multisite-dashboard",
                ); ?></h2>
            <p class="msd-section-desc"><?php _e(
                                            "Monitor plugin performance, cache efficiency, and system resource usage.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>

            <div class="msd-performance-grid">
                <div class="msd-performance-card">
                    <h3><?php _e(
                            "Cache Performance",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <div id="msd-cache-performance">
                        <p><?php _e(
                                'Click \"Check Performance\" to view cache statistics.',
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                    <div class="msd-action-buttons">
                        <button type="button" class="button button-primary" onclick="MSD.checkCachePerformance()">
                            <?php _e(
                                "Check Performance",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                        <button type="button" class="button" onclick="MSD.optimizeCache()">
                            <?php _e(
                                "Optimize Cache",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                    </div>
                </div>

                <div class="msd-performance-card">
                    <h3><?php _e(
                            "Memory Usage",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <div id="msd-memory-usage">
                        <div class="msd-memory-stats">
                            <div class="msd-stat-item">
                                <span class="msd-stat-label"><?php _e(
                                                                    "Current Usage:",
                                                                    "wp-multisite-dashboard",
                                                                ); ?></span>
                                <span class="msd-stat-value" id="current-memory"><?php echo size_format(
                                                                                        memory_get_usage(true),
                                                                                    ); ?></span>
                            </div>
                            <div class="msd-stat-item">
                                <span class="msd-stat-label"><?php _e(
                                                                    "Peak Usage:",
                                                                    "wp-multisite-dashboard",
                                                                ); ?></span>
                                <span class="msd-stat-value" id="peak-memory"><?php echo size_format(
                                                                                    memory_get_peak_usage(true),
                                                                                ); ?></span>
                            </div>
                            <div class="msd-stat-item">
                                <span class="msd-stat-label"><?php _e(
                                                                    "Memory Limit:",
                                                                    "wp-multisite-dashboard",
                                                                ); ?></span>
                                <span class="msd-stat-value"><?php echo ini_get(
                                                                    "memory_limit",
                                                                ); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="msd-action-buttons">
                        <button type="button" class="button" onclick="MSD.refreshMemoryStats()">
                            <?php _e(
                                "Refresh Stats",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                    </div>
                </div>

                <div class="msd-performance-card">
                    <h3><?php _e(
                            "Database Performance",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <div id="msd-database-performance">
                        <p><?php _e(
                                'Click \"Analyze Database\" to check query performance.',
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                    <div class="msd-action-buttons">
                        <button type="button" class="button" onclick="MSD.analyzeDatabasePerformance()">
                            <?php _e(
                                "Analyze Database",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                        <button type="button" class="button" onclick="MSD.optimizeDatabase()">
                            <?php _e(
                                "Optimize Tables",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                    </div>
                </div>

                <div class="msd-performance-card">
                    <h3><?php _e(
                            "System Information",
                            "wp-multisite-dashboard",
                        ); ?></h3>
                    <div class="msd-system-info">
                        <div class="msd-stat-item">
                            <span class="msd-stat-label"><?php _e(
                                                                "PHP Version:",
                                                                "wp-multisite-dashboard",
                                                            ); ?></span>
                            <span class="msd-stat-value"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="msd-stat-item">
                            <span class="msd-stat-label"><?php _e(
                                                                "WordPress Version:",
                                                                "wp-multisite-dashboard",
                                                            ); ?></span>
                            <span class="msd-stat-value"><?php echo get_bloginfo(
                                                                "version",
                                                            ); ?></span>
                        </div>
                        <div class="msd-stat-item">
                            <span class="msd-stat-label"><?php _e(
                                                                "Object Cache:",
                                                                "wp-multisite-dashboard",
                                                            ); ?></span>
                            <span class="msd-stat-value"><?php echo wp_using_ext_object_cache()
                                                                ? __("Enabled", "wp-multisite-dashboard")
                                                                : __(
                                                                    "Disabled",
                                                                    "wp-multisite-dashboard",
                                                                ); ?></span>
                        </div>
                        <div class="msd-stat-item">
                            <span class="msd-stat-label"><?php _e(
                                                                "Multisite Sites:",
                                                                "wp-multisite-dashboard",
                                                            ); ?></span>
                            <span class="msd-stat-value"><?php echo get_sites([
                                                                "count" => true,
                                                            ]); ?></span>
                        </div>
                    </div>
                    <div class="msd-action-buttons">
                        <button type="button" class="button" onclick="MSD.refreshMemoryStats()">
                            <?php _e("Refresh", "wp-multisite-dashboard"); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="msd-performance-recommendations">
                <h3><?php _e(
                        "Performance Recommendations",
                        "wp-multisite-dashboard",
                    ); ?></h3>
                <div id="msd-performance-tips">
                    <div class="msd-tip">
                        <p><?php _e(
                                "Enable object caching (Redis/Memcached) for better performance in large networks.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                    <div class="msd-tip">
                        <p><?php _e(
                                "Regularly clear old transients and optimize database tables.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                    <div class="msd-tip">
                        <p><?php _e(
                                "Monitor memory usage and increase PHP memory limit if needed.",
                                "wp-multisite-dashboard",
                            ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php include WP_MSD_PLUGIN_DIR . "templates/monitoring-tab.php"; ?>

        <!-- Plugin Info Tab -->
        <div class="msd-section <?php echo $active_tab === "info"
                                    ? "active"
                                    : ""; ?>" id="msd-section-info">
            <h2><?php _e(
                    "Plugin Information",
                    "wp-multisite-dashboard",
                ); ?></h2>
            <p class="msd-section-desc"><?php _e(
                                            "Current plugin status and update information.",
                                            "wp-multisite-dashboard",
                                        ); ?></p>

            <div class="msd-plugin-info">
                <div class="msd-info-row">
                    <span class="msd-info-label"><?php _e(
                                                        "Current Version:",
                                                        "wp-multisite-dashboard",
                                                    ); ?></span>
                    <span class="msd-info-value"><?php echo esc_html(
                                                        WP_MSD_VERSION,
                                                    ); ?></span>
                </div>

                <div class="msd-info-row">
                    <span class="msd-info-label"><?php _e(
                                                        "Update Status:",
                                                        "wp-multisite-dashboard",
                                                    ); ?></span>
                    <span class="msd-info-value" id="msd-update-status">
                        <button type="button" class="button button-small" onclick="MSD.checkForUpdates()">
                            <?php _e(
                                "Check for Updates",
                                "wp-multisite-dashboard",
                            ); ?>
                        </button>
                    </span>
                </div>

                <div class="msd-info-row">
                    <span class="msd-info-label"><?php _e(
                                                        "Documentation:",
                                                        "wp-multisite-dashboard",
                                                    ); ?></span>
                    <span class="msd-info-value">
                        <a href="https://wpmultisite.com/document/wp-multisite-dashboard" target="_blank" class="button button-small">
                            <?php _e(
                                "View Documentation ↗",
                                "wp-multisite-dashboard",
                            ); ?>
                        </a>
                    </span>
                </div>

                <div class="msd-info-row">
                    <span class="msd-info-label"><?php _e(
                                                        "Support:",
                                                        "wp-multisite-dashboard",
                                                    ); ?></span>
                    <span class="msd-info-value">
                        <a href="https://wpmultisite.com/support/" target="_blank" class="button button-small">
                            <?php _e(
                                "Get Support ↗",
                                "wp-multisite-dashboard",
                            ); ?>
                        </a>
                    </span>
                </div>

                <div class="msd-info-row">
                    <span class="msd-info-label"><?php _e(
                                                        "GitHub Repository:",
                                                        "wp-multisite-dashboard",
                                                    ); ?></span>
                    <span class="msd-info-value">
                        <a href="https://github.com/wpmultisite/wp-multisite-dashboard" target="_blank" class="button button-small">
                            <?php _e(
                                "View on GitHub ↗",
                                "wp-multisite-dashboard",
                            ); ?>
                        </a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>