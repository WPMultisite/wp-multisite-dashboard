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
                    <?php _e("Plugin Widgets", "wp-multisite-dashboard"); ?>
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
                "Plugin Widget Configuration",
                "wp-multisite-dashboard",
            ); ?></h2>
            <p><?php _e(
                "Enable or disable custom dashboard widgets provided by this plugin.",
                "wp-multisite-dashboard",
            ); ?></p>

            <form method="post" action="" id="msd-widgets-form">
                <?php wp_nonce_field("msd_settings", "msd_settings_nonce"); ?>
                <input type="hidden" name="msd_form" value="widgets" />
                <input type="hidden" name="current_tab" value="widgets" />

                <div class="msd-settings-grid">
                    <?php foreach (
                        $widget_options
                        as $widget_id => $widget_name
                    ): ?>
                        <div class="msd-widget-toggle">
                            <label>
                                <input
                                    type="checkbox"
                                    name="widgets[<?php echo esc_attr(
                                        $widget_id,
                                    ); ?>]"
                                    value="1"
                                    <?php checked(
                                        !empty($enabled_widgets[$widget_id]),
                                    ); ?>
                                />
                                <?php echo esc_html($widget_name); ?>
                            </label>
                            <p class="description">
                                <?php echo $settings_manager->get_widget_description(
                                    $widget_id,
                                ); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
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
                                                ); ?>
                                            />
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
                                                ); ?>
                                            />
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

<style>
.msd-cache-actions {
    display: flex;
    gap: 12px;
    margin: 16px 0;
    flex-wrap: wrap;
}

.msd-cache-actions .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

.msd-plugin-info {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.msd-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.msd-info-row:last-child {
    border-bottom: none;
}

.msd-info-label {
    font-weight: 600;
    color: var(--msd-text);
}

.msd-info-value {
    color: var(--msd-text-light);
}

.msd-update-available {
    color: #d63638;
    font-weight: 600;
}

.msd-update-current {
    color: #00a32a;
    font-weight: 600;
}

.msd-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.msd-widget-toggle {
    background: var(--msd-bg-light);
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    padding: 20px;
    transition: all 0.2s ease;
}

.msd-widget-toggle:hover {
    border-color: var(--msd-primary);
}

.msd-widget-toggle label {
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    cursor: pointer;
}

.msd-widget-toggle input[type="checkbox"] {
    margin: 0;
    width: 18px;
    height: 18px;
}

.msd-widget-toggle .description {
    margin: 0;
    font-size: 11px;
    color: #8c8c8c;
    line-height: 1.4;
}

.msd-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-width: unset;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    overflow: hidden;
}

.msd-system-widgets-grid {
    margin-top: 20px;
}

.msd-widget-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.msd-widget-section h4 {
    margin: 0 0 15px 0;
    color: var(--msd-text);
    font-size: 15px;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid #007cba;
}

.msd-widget-section .msd-widget-toggle {
    margin-bottom: 12px;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: border-color 0.2s ease;
}

.msd-widget-section .msd-widget-toggle:hover {
    border-color: var(--msd-primary);
}

.msd-widget-section .msd-widget-toggle label {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.msd-widget-meta {
    font-size: 12px;
    color: var(--msd-text-light);
    font-style: italic;
    margin-left: auto;
}

.msd-no-widgets,
.msd-no-third-party {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 2px dashed #ddd;
    color: var(--msd-text-light);
}

/* MSD Settings Styles - Based on WPAvatar */
.msd-settings .form-table th {
    width: 220px;
}

.msd-settings .description {
    font-size: 13px;
    margin: 2px 0 5px;
    color: #666;
}

.msd-tabs-wrapper {
    margin-bottom: 20px;
}

.msd-sync-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 20px;
}

.msd-tab {
    padding: 8px 16px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 2px solid transparent;
    color: #50575e;
}

.msd-tab.active {
    border-bottom: 2px solid #007cba;
    font-weight: 600;
    background: #f0f0f1;
}

.msd-tab:hover:not(.active) {
    background: #f6f7f7;
}

.msd-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    overflow: hidden;
}

.msd-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f1;
}

.msd-section-desc {
    color: #666;
    margin-bottom: 20px;
}

.msd-cache-stats-card,
.msd-cache-actions-card {
    background: #f9f9f9;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.msd-cache-stats-card h3,
.msd-cache-actions-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.msd-action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.msd-submit-wrapper {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f1;
}

.msd-import-export-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.msd-export-section,
.msd-import-section {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.msd-export-section h3,
.msd-import-section h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.msd-file-input-wrapper {
    position: relative;
    margin: 15px 0;
}

.msd-file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0.1px;
    height: 0.1px;
    overflow: hidden;
}

.msd-file-input-wrapper label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    margin-right: 10px;
}

.msd-file-name {
    font-style: italic;
    color: #666;
}

.msd-import-preview {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.msd-import-preview h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.msd-import-details {
    font-size: 13px;
    line-height: 1.5;
}

.msd-import-details strong {
    color: #333;
}

.msd-import-actions {
    display: flex;
    gap: 10px;
    margin: 15px 0;
}

.msd-import-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 12px;
    margin-top: 15px;
}

.msd-import-warning p {
    margin: 0;
    color: #856404;
    font-size: 13px;
}

/* Widget Detection Styles */
.msd-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 6px;
}

.msd-loading-overlay p {
    margin: 0;
    padding: 20px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.msd-loading-overlay p:before {
    content: '';
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007cba;
    border-radius: 50%;
    animation: msd-spin 1s linear infinite;
}

@keyframes msd-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.msd-widget-source {
    background: #007cba;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
}

.msd-widget-toggle {
    transition: all 0.2s ease;
}

.msd-widget-toggle:hover {
    background: #f8f9fa;
}

.msd-detection-controls {
    background: #f0f6fc;
    border: 1px solid #007cba;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.msd-detection-controls h4 {
    margin: 0 0 10px 0;
    color: #007cba;
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.msd-detection-controls h4:before {
    content: '';
    width: 4px;
    height: 20px;
    background: #007cba;
    border-radius: 2px;
}

.msd-detection-controls .description {
    margin-bottom: 15px;
    color: #333;
}

.msd-detection-controls .msd-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.msd-detection-controls .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.msd-detection-controls .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.msd-rescan-section {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 12px;
    margin: 15px 0;
}

.msd-rescan-section .description {
    margin: 0;
    color: #856404;
    font-style: italic;
}

.msd-detection-status {
    background: #f0f6fc;
    border: 1px solid #007cba;
    border-radius: 4px;
    padding: 12px;
    margin: 15px 0;
    display: none;
}

.msd-detection-status.show {
    display: block;
}

.msd-detection-status .dashicons {
    color: #007cba;
    margin-right: 5px;
}

/* Performance Monitoring Styles */
.msd-performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.msd-performance-card {
    background: #f9f9f9;
    border-radius: 6px;
    padding: 20px;
    border: 1px solid #e9ecef;
}

.msd-performance-card h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.msd-performance-card h3:before {
    content: '';
    width: 4px;
    height: 20px;
    background: #007cba;
    border-radius: 2px;
}

.msd-memory-stats,
.msd-system-info {
    margin: 15px 0;
}

.msd-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.msd-stat-item:last-child {
    border-bottom: none;
}

.msd-stat-label {
    font-weight: 500;
    color: #333;
}

.msd-stat-value {
    color: #666;
    font-family: monospace;
    font-size: 13px;
}

.msd-performance-recommendations {
    margin-top: 30px;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
}

.msd-performance-recommendations h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.msd-tip {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 15px;
    padding: 12px;
    background: #f0f6fc;
    border-left: 4px solid #007cba;
    border-radius: 4px;
}

.msd-tip:last-child {
    margin-bottom: 0;
}

.msd-tip .dashicons {
    color: #007cba;
    margin-top: 2px;
}

.msd-tip p {
    margin: 0;
    color: #333;
    font-size: 14px;
    line-height: 1.5;
}

.msd-performance-meter {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.msd-performance-meter-fill {
    height: 100%;
    background: linear-gradient(90deg, #00a32a 0%, #dba617 70%, #d63638 100%);
    transition: width 0.3s ease;
}

@media screen and (max-width: 768px) {
    .msd-cache-actions {
        flex-direction: column;
    }

    .msd-info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .msd-widget-section {
        margin-bottom: 20px;
        padding: 15px;
    }

    .msd-widget-section .msd-widget-toggle label {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .msd-widget-meta {
        margin-left: 0;
    }

    .msd-settings-grid {
        grid-template-columns: 1fr;
    }

    .msd-import-export-section {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .msd-import-actions {
        flex-direction: column;
    }
}

/* Monitoring Tab Styles */
.msd-monitoring-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
}

.msd-monitoring-card h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
}

.msd-error-log-controls, .msd-filter-group {
    display: flex;
    gap: 15px;
    margin: 15px 0;
    flex-wrap: wrap;
    align-items: center;
}

.msd-filter-group label {
    font-weight: 500;
    margin-right: 5px;
}

.msd-filter-group select, .msd-filter-group input {
    min-width: 200px;
}

.msd-stats-row {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.msd-stat-box {
    flex: 1;
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 2px solid #e9ecef;
}

.msd-stat-box.fatal {
    border-color: #d63638;
    background: #fcf0f1;
}

.msd-stat-box.warning {
    border-color: #dba617;
    background: #fcf9e8;
}

.msd-stat-box.msd-type-notice {
    border-color: #007cba;
    background: #f0f6fc;
}

.msd-stat-box .msd-stat-number {
    display: block;
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.msd-stat-box .msd-stat-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

.msd-log-display {
    margin-top: 20px;
    max-height: 600px;
    overflow-y: auto;
}

.msd-error-list {
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.msd-error-item {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

.msd-error-item:last-child {
    border-bottom: none;
}

.msd-error-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.msd-error-type {
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.msd-error-fatal .msd-error-type {
    background: #d63638;
    color: #fff;
}

.msd-error-warning .msd-error-type {
    background: #dba617;
    color: #fff;
}

.msd-error-notice .msd-error-type {
    background: #007cba;
    color: #fff;
}

.msd-error-time {
    font-size: 12px;
    color: #666;
}

.msd-error-message pre {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.5;
    margin: 0;
}

.msd-404-toggle {
    margin: 15px 0;
}

.msd-switch {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.msd-switch input[type="checkbox"] {
    position: relative;
    width: 50px;
    height: 26px;
    appearance: none;
    background: #ccc;
    border-radius: 13px;
    transition: background 0.3s;
    cursor: pointer;
}

.msd-switch input[type="checkbox"]:checked {
    background: #007cba;
}

.msd-switch input[type="checkbox"]::before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 22px;
    height: 22px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.3s;
}

.msd-switch input[type="checkbox"]:checked::before {
    transform: translateX(24px);
}

.msd-switch-label {
    font-weight: 500;
}

.msd-404-stats table {
    margin-top: 15px;
}

.msd-404-stats table code {
    word-break: break-all;
}

.msd-monitoring-disabled-notice {
    padding: 15px;
    background: #f8f9fa;
    border-left: 4px solid #666;
    margin-top: 15px;
}
</style>
