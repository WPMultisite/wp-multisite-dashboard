<?php
// Ensure no whitespace before opening PHP tag
ob_start(); // Start output buffering
// Monitoring Tab Content
if (!defined("ABSPATH")) {
    exit();
}

$error_log_manager = WP_MSD_Error_Log_Manager::get_instance();
$monitor_404 = WP_MSD_404_Monitor::get_instance();
?>

<!-- Monitoring Tab -->
<div class="msd-section <?php echo $active_tab === "monitoring"
    ? "active"
    : ""; ?>" id="msd-section-monitoring">
    <h2><?php _e("Site Monitoring", "wp-multisite-dashboard"); ?></h2>
    <p class="msd-section-desc"><?php _e(
        "Monitor PHP errors and 404 pages across your network.",
        "wp-multisite-dashboard",
    ); ?></p>

    <!-- Error Log Viewer -->
    <div class="msd-monitoring-card">
        <div class="msd-card-header">
            <div class="msd-card-title">
                <h3><?php _e(
                    "PHP Error Log Viewer",
                    "wp-multisite-dashboard",
                ); ?></h3>
            </div>
            <?php if ($error_log_manager->is_error_logging_enabled()): ?>
                <span class="msd-badge msd-badge-success"><?php _e(
                    "Active",
                    "wp-multisite-dashboard",
                ); ?></span>
            <?php else: ?>
                <span class="msd-badge msd-badge-warning"><?php _e(
                    "Disabled",
                    "wp-multisite-dashboard",
                ); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$error_log_manager->is_error_logging_enabled()): ?>
        <div class="msd-card-description">
            <p>
                <strong><?php _e(
                    "Debug logging is not enabled.",
                    "wp-multisite-dashboard",
                ); ?></strong><br>
                <span class="msd-hint"><?php _e(
                    "To enable: Set WP_DEBUG and WP_DEBUG_LOG to true in wp-config.php",
                    "wp-multisite-dashboard",
                ); ?></span>
            </p>
        </div>
        <?php endif; ?>

        <div class="msd-error-log-controls">
            <div class="msd-filter-group">
                <select id="msd-error-type-filter">
                    <option value="all"><?php _e(
                        "All Types",
                        "wp-multisite-dashboard",
                    ); ?></option>
                    <option value="fatal"><?php _e(
                        "Fatal",
                        "wp-multisite-dashboard",
                    ); ?></option>
                    <option value="warning"><?php _e(
                        "Warning",
                        "wp-multisite-dashboard",
                    ); ?></option>
                    <option value="notice"><?php _e(
                        "Notice",
                        "wp-multisite-dashboard",
                    ); ?></option>
                    <option value="deprecated"><?php _e(
                        "Deprecated",
                        "wp-multisite-dashboard",
                    ); ?></option>
                </select>
            </div>

            <div class="msd-filter-group">
                <input type="text" id="msd-error-search" placeholder="<?php _e(
                    "Search errors...",
                    "wp-multisite-dashboard",
                ); ?>" />
            </div>

            <div class="msd-action-buttons">
                <button type="button" class="button button-primary" onclick="MSD.loadErrorLogs()">
                    <?php _e("Load Errors", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="button" onclick="MSD.clearErrorLogs()">
                    <?php _e("Clear Logs", "wp-multisite-dashboard"); ?>
                </button>
            </div>
        </div>

        <div id="msd-error-log-stats" class="msd-stats-row" style="display:none;">
            <div class="msd-stat-box">
                <span class="msd-stat-number" id="error-count-total">0</span>
                <span class="msd-stat-label"><?php _e(
                    "Total",
                    "wp-multisite-dashboard",
                ); ?></span>
            </div>
            <div class="msd-stat-box fatal">
                <span class="msd-stat-number" id="error-count-fatal">0</span>
                <span class="msd-stat-label"><?php _e(
                    "Fatal",
                    "wp-multisite-dashboard",
                ); ?></span>
            </div>
            <div class="msd-stat-box warning">
                <span class="msd-stat-number" id="error-count-warning">0</span>
                <span class="msd-stat-label"><?php _e(
                    "Warnings",
                    "wp-multisite-dashboard",
                ); ?></span>
            </div>
            <div class="msd-stat-box msd-type-notice">
                <span class="msd-stat-number" id="error-count-notice">0</span>
                <span class="msd-stat-label"><?php _e(
                    "Notices",
                    "wp-multisite-dashboard",
                ); ?></span>
            </div>
        </div>

        <div id="msd-error-log-display" class="msd-log-display">
            <p class="description"><?php _e(
                'Click "Load Errors" to view recent PHP errors.',
                "wp-multisite-dashboard",
            ); ?></p>
        </div>
    </div>

    <!-- 404 Monitor -->
    <div class="msd-monitoring-card">
        <div class="msd-card-header">
            <div class="msd-card-title">
                <h3><?php _e(
                    "404 Error Monitor",
                    "wp-multisite-dashboard",
                ); ?></h3>
            </div>
            <?php if ($monitor_404->is_monitoring_enabled()): ?>
                <span class="msd-badge msd-badge-success"><?php _e(
                    "Active",
                    "wp-multisite-dashboard",
                ); ?></span>
            <?php else: ?>
                <span class="msd-badge msd-badge-inactive"><?php _e(
                    "Inactive",
                    "wp-multisite-dashboard",
                ); ?></span>
            <?php endif; ?>
        </div>

        <div class="msd-404-toggle">
            <label class="msd-switch">
                <input type="checkbox" id="msd-404-monitoring-toggle" <?php checked(
                    $monitor_404->is_monitoring_enabled(),
                ); ?>>
                <span class="msd-slider"></span>
                <span class="msd-switch-label">
                    <?php _e(
                        "Enable 404 Monitoring",
                        "wp-multisite-dashboard",
                    ); ?>
                </span>
            </label>
            <p class="description" style="margin-top: 8px;">
                <?php _e(
                    "Track 404 errors to identify broken links and improve SEO.",
                    "wp-multisite-dashboard",
                ); ?>
            </p>
        </div>

        <div id="msd-404-stats-container" style="<?php echo !$monitor_404->is_monitoring_enabled()
            ? "display:none;"
            : ""; ?>">
            <div class="msd-action-buttons" style="margin-top: 12px;">
                <button type="button" class="button button-primary" onclick="MSD.load404Stats()">
                    <?php _e("Load Statistics", "wp-multisite-dashboard"); ?>
                </button>
                <button type="button" class="button" onclick="MSD.clear404Logs()">
                    <?php _e("Clear Logs", "wp-multisite-dashboard"); ?>
                </button>
            </div>

            <div id="msd-404-stats-display" class="msd-404-stats">
                <p class="description"><?php _e(
                    'Click "Load Statistics" to view 404 error data.',
                    "wp-multisite-dashboard",
                ); ?></p>
            </div>
        </div>

        <?php if (!$monitor_404->is_monitoring_enabled()): ?>
        <div class="msd-monitoring-disabled-notice">
            <p><?php _e(
                "404 monitoring is currently disabled. Enable it above to start tracking.",
                "wp-multisite-dashboard",
            ); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
