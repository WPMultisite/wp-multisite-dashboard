<?php
/**
 * WP Domain Mapping Integration
 *
 * Provides integration between WP Multisite Dashboard and WP Domain Mapping plugin
 *
 * @package WP_Multisite_Dashboard
 * @subpackage Integrations
 * @since 1.5.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Domain_Mapping_Integration
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Performance manager instance
     */
    private $performance_manager;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->performance_manager = WP_MSD_Performance_Manager::get_instance();
        $this->init();
    }

    /**
     * Initialize integration
     */
    private function init()
    {
        // Only initialize if Domain Mapping is active
        if (!$this->is_domain_mapping_active()) {
            return;
        }

        // Add AJAX handlers
        add_action("wp_ajax_msd_get_domain_mapping_data", [
            $this,
            "ajax_get_domain_mapping_data",
        ]);
        add_action("wp_ajax_msd_refresh_domain_health", [
            $this,
            "ajax_refresh_domain_health",
        ]);
    }

    /**
     * Check if WP Domain Mapping plugin is active
     *
     * @return bool
     */
    public function is_domain_mapping_active()
    {
        return class_exists("WP_Domain_Mapping") &&
            function_exists("dm_get_domains_by_blog_id") &&
            function_exists("dm_get_table_names");
    }

    /**
     * Check if WP Domain Mapping plugin is installed but not active
     *
     * @return bool
     */
    public function is_domain_mapping_installed()
    {
        if ($this->is_domain_mapping_active()) {
            return true;
        }

        // Check if plugin file exists
        $plugin_path =
            WP_PLUGIN_DIR . "/wp-domain-mapping/wp-domain-mapping.php";
        return file_exists($plugin_path);
    }

    /**
     * Get plugin status
     *
     * @return string 'active', 'installed', or 'not_installed'
     */
    public function get_plugin_status()
    {
        if ($this->is_domain_mapping_active()) {
            return "active";
        }

        if ($this->is_domain_mapping_installed()) {
            return "installed";
        }

        return "not_installed";
    }

    /**
     * Get domain mapping widget data
     *
     * @return array
     */
    public function get_widget_data()
    {
        $cache_key = "domain_mapping_widget_data";
        $cached = $this->performance_manager->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $tables = dm_get_table_names();

        // Get total domains count
        $total_domains = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables["domains"]}",
        );

        // Get active sites with domains
        $active_sites = $wpdb->get_var(
            "SELECT COUNT(DISTINCT blog_id) FROM {$tables["domains"]}",
        );

        // Get primary domains count
        $primary_domains = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables["domains"]} WHERE active = 1",
        );

        // Get recently added domains (last 7 days)
        $recent_domains = $wpdb->get_results(
            "SELECT d.*, b.domain as site_domain, b.path as site_path
             FROM {$tables["domains"]} d
             LEFT JOIN {$wpdb->blogs} b ON d.blog_id = b.blog_id
             ORDER BY d.id DESC
             LIMIT 5",
        );

        // Get health statistics
        $health_stats = $this->get_health_statistics();

        // Get recent activity
        $recent_activity = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM {$tables["logs"]} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             ORDER BY l.timestamp DESC
             LIMIT 5",
        );

        $data = [
            "total_domains" => (int) $total_domains,
            "active_sites" => (int) $active_sites,
            "primary_domains" => (int) $primary_domains,
            "secondary_domains" => (int) ($total_domains - $primary_domains),
            "recent_domains" => $recent_domains,
            "health_stats" => $health_stats,
            "recent_activity" => $recent_activity,
            "last_updated" => current_time("mysql"),
        ];

        // Cache for 5 minutes
        $this->performance_manager->set_cache($cache_key, $data, 300);

        return $data;
    }

    /**
     * Get health statistics
     *
     * @return array
     */
    private function get_health_statistics()
    {
        $cache_key = "domain_health_statistics";
        $cached = $this->performance_manager->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $tables = dm_get_table_names();

        // Get all domains
        $domains = $wpdb->get_col("SELECT domain FROM {$tables["domains"]}");

        $stats = [
            "total" => count($domains),
            "healthy" => 0,
            "warning" => 0,
            "error" => 0,
            "unchecked" => 0,
        ];

        foreach ($domains as $domain) {
            $health = dm_get_health_result($domain);

            if (!$health) {
                $stats["unchecked"]++;
                continue;
            }

            $is_healthy = true;
            $has_warning = false;

            // Check accessibility
            if (isset($health["accessible"]) && !$health["accessible"]) {
                $stats["error"]++;
                $is_healthy = false;
                continue;
            }

            // Check SSL
            if (isset($health["ssl_valid"]) && !$health["ssl_valid"]) {
                $has_warning = true;
            }

            // Check DNS
            if (
                isset($health["dns_status"]) &&
                $health["dns_status"] !== "success"
            ) {
                $has_warning = true;
            }

            if ($is_healthy && !$has_warning) {
                $stats["healthy"]++;
            } elseif ($has_warning) {
                $stats["warning"]++;
            }
        }

        // Cache for 15 minutes
        $this->performance_manager->set_cache($cache_key, $stats, 900);

        return $stats;
    }

    /**
     * Render domain mapping widget
     */
    public function render_widget()
    {
        echo '<div id="msd-domain-mapping" class="msd-widget-content" data-widget="domain_mapping">';
        echo '<button class="msd-refresh-btn" title="' .
            esc_attr__("Refresh", "wp-multisite-dashboard") .
            '" data-widget="domain_mapping">↻</button>';

        $this->render_widget_content();

        echo "</div>";
    }

    /**
     * Render widget content
     */
    private function render_widget_content()
    {
        $data = $this->get_widget_data(); ?>
        <div class="msd-dm-stats">
            <div class="msd-dm-stat-grid">
                <div class="msd-dm-stat-item">
                    <div class="msd-dm-stat-content">
                        <span class="msd-dm-stat-value"><?php echo esc_html(
                            $data["total_domains"],
                        ); ?></span>
                        <span class="msd-dm-stat-label"><?php _e(
                            "Total Domains",
                            "wp-multisite-dashboard",
                        ); ?></span>
                    </div>
                </div>
                <div class="msd-dm-stat-item">
                    <div class="msd-dm-stat-content">
                        <span class="msd-dm-stat-value"><?php echo esc_html(
                            $data["active_sites"],
                        ); ?></span>
                        <span class="msd-dm-stat-label"><?php _e(
                            "Active Sites",
                            "wp-multisite-dashboard",
                        ); ?></span>
                    </div>
                </div>
                <div class="msd-dm-stat-item">
                    <div class="msd-dm-stat-content">
                        <span class="msd-dm-stat-value"><?php echo esc_html(
                            $data["primary_domains"],
                        ); ?></span>
                        <span class="msd-dm-stat-label"><?php _e(
                            "Primary",
                            "wp-multisite-dashboard",
                        ); ?></span>
                    </div>
                </div>
                <div class="msd-dm-stat-item">
                    <div class="msd-dm-stat-content">
                        <span class="msd-dm-stat-value"><?php echo esc_html(
                            $data["secondary_domains"],
                        ); ?></span>
                        <span class="msd-dm-stat-label"><?php _e(
                            "Secondary",
                            "wp-multisite-dashboard",
                        ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($data["health_stats"])): ?>
        <div class="msd-dm-health">
            <h4><?php _e("Health Status", "wp-multisite-dashboard"); ?></h4>
            <div class="msd-dm-health-bars">
                <?php
                $health_stats = $data["health_stats"];
                $total = $health_stats["total"];

                if ($total > 0):

                    $healthy_pct = round(
                        ($health_stats["healthy"] / $total) * 100,
                    );
                    $warning_pct = round(
                        ($health_stats["warning"] / $total) * 100,
                    );
                    $error_pct = round(($health_stats["error"] / $total) * 100);
                    $unchecked_pct = round(
                        ($health_stats["unchecked"] / $total) * 100,
                    );
                    ?>
                <div class="msd-dm-health-bar">
                    <?php if ($healthy_pct > 0): ?>
                    <div class="msd-dm-health-segment msd-dm-health-good" style="width: <?php echo $healthy_pct; ?>%;"
                         title="<?php echo sprintf(
                             __("%d Healthy", "wp-multisite-dashboard"),
                             $health_stats["healthy"],
                         ); ?>">
                        <?php if ($healthy_pct > 15):
                            echo $health_stats["healthy"];
                        endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($warning_pct > 0): ?>
                    <div class="msd-dm-health-segment msd-dm-health-warning" style="width: <?php echo $warning_pct; ?>%;"
                         title="<?php echo sprintf(
                             __("%d Warnings", "wp-multisite-dashboard"),
                             $health_stats["warning"],
                         ); ?>">
                        <?php if ($warning_pct > 15):
                            echo $health_stats["warning"];
                        endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($error_pct > 0): ?>
                    <div class="msd-dm-health-segment msd-dm-health-error" style="width: <?php echo $error_pct; ?>%;"
                         title="<?php echo sprintf(
                             __("%d Errors", "wp-multisite-dashboard"),
                             $health_stats["error"],
                         ); ?>">
                        <?php if ($error_pct > 15):
                            echo $health_stats["error"];
                        endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($unchecked_pct > 0): ?>
                    <div class="msd-dm-health-segment msd-dm-health-unchecked" style="width: <?php echo $unchecked_pct; ?>%;"
                         title="<?php echo sprintf(
                             __("%d Unchecked", "wp-multisite-dashboard"),
                             $health_stats["unchecked"],
                         ); ?>">
                        <?php if ($unchecked_pct > 15):
                            echo $health_stats["unchecked"];
                        endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="msd-dm-health-legend">
                    <span class="msd-dm-legend-item">
                        <span class="msd-dm-legend-color msd-dm-health-good"></span>
                        <?php printf(
                            __("%d Healthy", "wp-multisite-dashboard"),
                            $health_stats["healthy"],
                        ); ?>
                    </span>
                    <?php if ($health_stats["warning"] > 0): ?>
                    <span class="msd-dm-legend-item">
                        <span class="msd-dm-legend-color msd-dm-health-warning"></span>
                        <?php printf(
                            __("%d Warnings", "wp-multisite-dashboard"),
                            $health_stats["warning"],
                        ); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($health_stats["error"] > 0): ?>
                    <span class="msd-dm-legend-item">
                        <span class="msd-dm-legend-color msd-dm-health-error"></span>
                        <?php printf(
                            __("%d Errors", "wp-multisite-dashboard"),
                            $health_stats["error"],
                        ); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($health_stats["unchecked"] > 0): ?>
                    <span class="msd-dm-legend-item">
                        <span class="msd-dm-legend-color msd-dm-health-unchecked"></span>
                        <?php printf(
                            __("%d Unchecked", "wp-multisite-dashboard"),
                            $health_stats["unchecked"],
                        ); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php
                endif;
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($data["recent_domains"])): ?>
        <div class="msd-dm-recent">
            <h4><?php _e(
                "Recently Added Domains",
                "wp-multisite-dashboard",
            ); ?></h4>
            <ul class="msd-dm-domain-list">
                <?php foreach ($data["recent_domains"] as $domain): ?>
                <li class="msd-dm-domain-item">
                    <span class="dashicons dashicons-admin-links"></span>
                    <a href="<?php echo esc_url(
                        "http://" . $domain->domain,
                    ); ?>" target="_blank" class="msd-dm-domain-link">
                        <?php echo esc_html($domain->domain); ?>
                    </a>
                    <?php if ($domain->active): ?>
                    <span class="msd-dm-badge msd-dm-badge-primary"><?php _e(
                        "Primary",
                        "wp-multisite-dashboard",
                    ); ?></span>
                    <?php endif; ?>
                    <?php if ($domain->site_domain): ?>
                    <span class="msd-dm-site-info">
                        → <?php echo esc_html(
                            $domain->site_domain . $domain->site_path,
                        ); ?>
                    </span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($data["recent_activity"])): ?>
        <div class="msd-dm-activity">
            <h4><?php _e("Recent Activity", "wp-multisite-dashboard"); ?></h4>
            <ul class="msd-dm-activity-list">
                <?php foreach ($data["recent_activity"] as $activity): ?>
                <li class="msd-dm-activity-item">
                    <span class="msd-dm-activity-action msd-dm-action-<?php echo esc_attr(
                        $activity->action,
                    ); ?>">
                        <?php echo esc_html(ucfirst($activity->action)); ?>
                    </span>
                    <span class="msd-dm-activity-domain"><?php echo esc_html(
                        $activity->domain,
                    ); ?></span>
                    <span class="msd-dm-activity-meta">
                        <?php printf(
                            __("by %s %s ago", "wp-multisite-dashboard"),
                            esc_html(
                                $activity->display_name ?:
                                __("Unknown", "wp-multisite-dashboard"),
                            ),
                            human_time_diff(
                                strtotime($activity->timestamp),
                                current_time("timestamp"),
                            ),
                        ); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="msd-dm-actions">
            <a href="<?php echo network_admin_url(
                "sites.php?page=domains",
            ); ?>" class="button button-primary">
                <?php _e("Manage All Domains", "wp-multisite-dashboard"); ?>
            </a>
            <button type="button" class="msd-settings-link msd-dm-refresh-health" data-action="refresh_health">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e("Refresh Health", "wp-multisite-dashboard"); ?>
            </button>
        </div>
        <?php
    }

    /**
     * AJAX handler for getting domain mapping data
     */
    public function ajax_get_domain_mapping_data()
    {
        check_ajax_referer("msd_ajax_nonce", "nonce");

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard"),
            );
        }

        ob_start();
        $this->render_widget_content();
        $html = ob_get_clean();

        wp_send_json_success(["html" => $html]);
    }

    /**
     * AJAX handler for refreshing domain health
     */
    public function ajax_refresh_domain_health()
    {
        check_ajax_referer("msd_ajax_nonce", "nonce");

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions", "wp-multisite-dashboard"),
            );
        }

        // Clear health cache
        $this->performance_manager->delete_cache("domain_health_statistics");
        $this->performance_manager->delete_cache("domain_mapping_widget_data");

        // Trigger health check if WP Domain Mapping Tools is available
        if (class_exists("WP_Domain_Mapping_Tools")) {
            $tools = WP_Domain_Mapping_Tools::get_instance();

            global $wpdb;
            $tables = dm_get_table_names();
            $domains = $wpdb->get_col(
                "SELECT domain FROM {$tables["domains"]} LIMIT 20",
            );

            foreach ($domains as $domain) {
                $result = $tools->check_domain_health($domain);
                dm_save_health_result($domain, $result);
            }
        }

        // Get fresh data
        ob_start();
        $this->render_widget_content();
        $html = ob_get_clean();

        wp_send_json_success([
            "html" => $html,
            "message" => __("Health check completed", "wp-multisite-dashboard"),
        ]);
    }

    /**
     * Get installation suggestion HTML for settings page
     *
     * @return string
     */
    public function get_installation_suggestion()
    {
        ob_start(); ?>
        <div class="msd-integration-suggestion">
            <div class="msd-integration-icon">
                <span class="dashicons dashicons-admin-plugins"></span>
            </div>
            <div class="msd-integration-content">
                <h3><?php _e(
                    "WP Domain Mapping Integration",
                    "wp-multisite-dashboard",
                ); ?></h3>
                <p><?php _e(
                    "Install WP Domain Mapping plugin to enable the Domain Mapping Overview widget. This widget provides network-wide domain mapping statistics, health monitoring, and quick access to domain management.",
                    "wp-multisite-dashboard",
                ); ?></p>
                <div class="msd-integration-features">
                    <ul>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e(
                            "Network-wide domain statistics",
                            "wp-multisite-dashboard",
                        ); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e(
                            "Domain health monitoring",
                            "wp-multisite-dashboard",
                        ); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e(
                            "Recent activity tracking",
                            "wp-multisite-dashboard",
                        ); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e(
                            "Quick domain management access",
                            "wp-multisite-dashboard",
                        ); ?></li>
                    </ul>
                </div>
                <div class="msd-integration-actions">
                    <a href="<?php echo network_admin_url(
                        "plugin-install.php?s=wp+domain+mapping&tab=search",
                    ); ?>" class="button button-primary">
                        <?php _e(
                            "Install WP Domain Mapping",
                            "wp-multisite-dashboard",
                        ); ?>
                    </a>
                    <a href="https://wenpai.org/plugins/wp-domain-mapping/" target="_blank" class="button button-secondary">
                        <?php _e("Learn More", "wp-multisite-dashboard"); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
