<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Plugin_Core {

    private static $instance = null;
    private $enabled_widgets = [];
    private $update_checker = null;
    private $ajax_handler = null;
    private $admin_interface = null;
    private $settings_manager = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('network_admin_menu', [$this, 'add_admin_menu']);

        $this->init_update_checker();
        $this->init_components();
    }

    private function init_components() {
        $this->ajax_handler = new WP_MSD_Ajax_Handler();
        $this->admin_interface = new WP_MSD_Admin_Interface();
        $this->settings_manager = new WP_MSD_Settings_Manager();
    }

    private function init_update_checker() {
        if (file_exists(plugin_dir_path(__FILE__) . '../lib/plugin-update-checker/plugin-update-checker.php')) {
            require_once plugin_dir_path(__FILE__) . '../lib/plugin-update-checker/plugin-update-checker.php';

            if (class_exists('YahnisElsts\PluginUpdateChecker\v5p3\PucFactory')) {
                $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5p3\PucFactory::buildUpdateChecker(
                    'https://updates.weixiaoduo.com/wp-multisite-dashboard.json',
                    WP_MSD_PLUGIN_DIR . 'wp-multisite-dashboard.php',
                    'wp-multisite-dashboard'
                );
            }
        }
    }

    public function init() {
        load_plugin_textdomain('wp-multisite-dashboard');

        $this->enabled_widgets = get_site_option('msd_enabled_widgets', [
            'msd_network_overview' => 1,
            'msd_quick_site_management' => 1,
            'msd_storage_performance' => 1,
            'msd_server_info' => 1,
            'msd_quick_links' => 1,
            'msd_version_info' => 1,
            'msd_custom_news' => 1,
            'msd_network_settings' => 1,
            'msd_user_management' => 1,
            'msd_contact_info' => 1,
            'msd_last_edits' => 1,
            'msd_todo_widget' => 1
        ]);

        $this->enhance_network_dashboard();
    }

    public function admin_init() {
        if (!current_user_can('manage_network')) {
            return;
        }

        add_action('wp_network_dashboard_setup', [$this->admin_interface, 'add_network_widgets']);
        add_action('wp_network_dashboard_setup', [$this, 'manage_system_widgets'], 20);
        add_action('wp_network_dashboard_setup', [$this, 'cache_detected_widgets'], 30);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'settings.php',
            'Dashboard Settings',
            'Dashboard Settings',
            'manage_network',
            'msd-settings',
            [$this->settings_manager, 'render_settings_page']
        );
    }

    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = ['index.php', 'dashboard.php', 'settings_page_msd-settings'];

        if (!in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_script(
            'msd-dashboard-core',
            WP_MSD_PLUGIN_URL . 'assets/dashboard-core.js',
            ['jquery'],
            WP_MSD_VERSION,
            true
        );

        wp_enqueue_script(
            'msd-dashboard-widgets',
            WP_MSD_PLUGIN_URL . 'assets/dashboard-widgets.js',
            ['msd-dashboard-core'],
            WP_MSD_VERSION,
            true
        );

        wp_enqueue_script(
            'msd-dashboard-modals',
            WP_MSD_PLUGIN_URL . 'assets/dashboard-modals.js',
            ['msd-dashboard-core', 'jquery-ui-sortable'],
            WP_MSD_VERSION,
            true
        );

        wp_enqueue_style(
            'msd-dashboard',
            WP_MSD_PLUGIN_URL . 'assets/dashboard.css',
            [],
            WP_MSD_VERSION
        );

        wp_localize_script('msd-dashboard-core', 'msdAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msd_ajax_nonce'),
            'strings' => [
                'confirm_action' => __('Are you sure?', 'wp-multisite-dashboard'),
                'loading' => __('Loading...', 'wp-multisite-dashboard'),
                'error_occurred' => __('An error occurred', 'wp-multisite-dashboard'),
                'refresh_success' => __('Data refreshed successfully', 'wp-multisite-dashboard'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wp-multisite-dashboard'),
                'save_success' => __('Saved successfully', 'wp-multisite-dashboard')
            ]
        ]);
    }

    private function enhance_network_dashboard() {
        add_filter('dashboard_recent_posts_query_args', [$this, 'enhance_recent_posts']);
        add_action('wp_network_dashboard_setup', [$this, 'enhance_right_now_widget']);
        add_action('admin_footer', [$this, 'add_right_now_enhancements']);
    }

    public function enhance_recent_posts($query_args) {
        $query_args['post_status'] = 'publish';
        return $query_args;
    }

    public function enhance_right_now_widget() {
        add_action('network_dashboard_right_now_content_table_end', [$this, 'add_right_now_plugin_link']);
    }

    public function add_right_now_plugin_link() {
        if (!current_user_can('manage_network_plugins')) {
            return;
        }

        echo '<tr>';
        echo '<td class="first b"><a href="' . network_admin_url('plugin-install.php') . '">' . __('Add New Plugin', 'wp-multisite-dashboard') . '</a></td>';
        echo '<td class="t"><a href="' . network_admin_url('plugins.php') . '">' . __('Manage Plugins', 'wp-multisite-dashboard') . '</a></td>';
        echo '</tr>';
    }

    public function add_right_now_enhancements() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'dashboard-network') {
            return;
        }

        $sites_count = get_sites(['count' => true]);
        $users_count = count_users()['total_users'];
        $themes_count = count(wp_get_themes(['allowed' => 'network']));
        $plugins_count = count(get_plugins());
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
                var pluginLink = '<li class="add-plugin"> | <a href="<?php echo network_admin_url('plugin-install.php'); ?>">Add New Plugin</a></li>';
                $subsubsub.append(pluginLink);
            }

            var $youhave = $('.youhave');
            if ($youhave.length) {
                var enhancedText = 'You have ' +
                    '<span class="stat-number"><?php echo $sites_count; ?></span> <span class="stat-label">sites</span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $users_count; ?></span> <span class="stat-label">users</span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $themes_count; ?></span> <span class="stat-label">themes</span>' +
                    '<span class="stat-separator">•</span>' +
                    '<span class="stat-number"><?php echo $plugins_count; ?></span> <span class="stat-label">plugins</span>';

                $youhave.html(enhancedText).addClass('youhave-enhanced');
            }
        });
        </script>
        <?php
    }

    public function manage_system_widgets() {
        global $wp_meta_boxes;

        $disabled_widgets = get_site_option('msd_disabled_system_widgets', []);

        if (!empty($disabled_widgets) && isset($wp_meta_boxes['dashboard-network'])) {
            foreach ($disabled_widgets as $widget_id) {
                if (isset($wp_meta_boxes['dashboard-network']['normal']['core'][$widget_id])) {
                    unset($wp_meta_boxes['dashboard-network']['normal']['core'][$widget_id]);
                }
                if (isset($wp_meta_boxes['dashboard-network']['side']['core'][$widget_id])) {
                    unset($wp_meta_boxes['dashboard-network']['side']['core'][$widget_id]);
                }
                if (isset($wp_meta_boxes['dashboard-network']['normal']['high'][$widget_id])) {
                    unset($wp_meta_boxes['dashboard-network']['normal']['high'][$widget_id]);
                }
                if (isset($wp_meta_boxes['dashboard-network']['side']['high'][$widget_id])) {
                    unset($wp_meta_boxes['dashboard-network']['side']['high'][$widget_id]);
                }
            }
        }
    }

    public function cache_detected_widgets() {
        global $wp_meta_boxes;

        if (!isset($wp_meta_boxes['dashboard-network'])) {
            return;
        }

        $detected_widgets = [];

        foreach ($wp_meta_boxes['dashboard-network'] as $context => $priorities) {
            if (!is_array($priorities)) continue;

            foreach ($priorities as $priority => $widgets) {
                if (!is_array($widgets)) continue;

                foreach ($widgets as $widget_id => $widget_data) {
                    if (strpos($widget_id, 'msd_') !== 0) {
                        $widget_title = $widget_data['title'] ?? $widget_id;
                        if (is_callable($widget_title)) {
                            $widget_title = $widget_id;
                        }

                        $detected_widgets[$widget_id] = [
                            'id' => $widget_id,
                            'title' => $widget_title,
                            'context' => $context,
                            'priority' => $priority,
                            'is_custom' => false,
                            'is_system' => in_array($widget_id, [
                                'network_dashboard_right_now',
                                'dashboard_activity',
                                'dashboard_plugins',
                                'dashboard_primary',
                                'dashboard_secondary'
                            ])
                        ];
                    }
                }
            }
        }

        set_site_transient('msd_detected_widgets', $detected_widgets, 12 * HOUR_IN_SECONDS);
    }

    public function get_enabled_widgets() {
        return $this->enabled_widgets;
    }

    public function get_update_checker() {
        return $this->update_checker;
    }
}
