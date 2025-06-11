<?php
/**
 * Plugin Name: WP Multisite Dashboard
 * Plugin URI: https://wpmultisite.com/plugins/wp-multisite-dashboard
 * Description: Essential dashboard widgets for WordPress multisite administrators
 * Version: 1.2.0
 * Author: WPMultisite.com
 * Author URI: https://WPMultisite.com
 * License: GPLv2+
 * Text Domain: wp-multisite-dashboard
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_MSD_VERSION', '1.2.0');
define('WP_MSD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_MSD_PLUGIN_URL', plugin_dir_url(__FILE__));

class WP_Multisite_Dashboard {

    private static $instance = null;
    private $enabled_widgets = [];

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

        $this->register_ajax_actions();
    }

    private function register_ajax_actions() {
        $ajax_actions = [
            'msd_get_network_overview',
            'msd_get_site_list',
            'msd_get_storage_data',
            'msd_get_server_info',
            'msd_get_version_info',
            'msd_get_custom_news',
            'msd_get_network_settings',
            'msd_get_user_management',
            'msd_get_last_edits',
            'msd_get_todo_items',
            'msd_save_news_sources',
            'msd_save_quick_links',
            'msd_save_contact_info',
            'msd_save_todo_item',
            'msd_update_todo_item',
            'msd_delete_todo_item',
            'msd_toggle_todo_complete',
            'msd_reorder_quick_links',
            'msd_toggle_widget',
            'msd_refresh_widget_data',
            'msd_clear_cache',
            'msd_manage_user_action'
        ];

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_{$action}", [$this, str_replace('msd_', '', $action)]);
        }
    }

    public function init() {
        if (!is_multisite()) {
            add_action('admin_notices', [$this, 'multisite_required_notice']);
            return;
        }

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

        $this->load_dependencies();
        $this->enhance_network_dashboard();
    }

    private function load_dependencies() {
        require_once WP_MSD_PLUGIN_DIR . 'includes/class-network-data.php';
        require_once WP_MSD_PLUGIN_DIR . 'includes/class-user-manager.php';
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

    public function admin_init() {
        if (!is_multisite() || !current_user_can('manage_network')) {
            return;
        }

        add_action('wp_network_dashboard_setup', [$this, 'add_network_widgets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'settings.php',
            'Dashboard Settings',
            'Dashboard Settings',
            'manage_network',
            'msd-settings',
            [$this, 'render_settings_page']
        );
    }

    public function add_network_widgets() {
        $widgets = [
            'msd_network_overview' => ['Network Overview', 'render_network_overview_widget'],
            'msd_quick_site_management' => ['Quick Site Management', 'render_quick_site_widget'],
            'msd_storage_performance' => ['Storage Usage', 'render_storage_performance_widget'],
            'msd_server_info' => ['Server Information', 'render_server_info_widget'],
            'msd_quick_links' => ['Quick Links', 'render_quick_links_widget'],
            'msd_version_info' => ['Version Information', 'render_version_info_widget'],
            'msd_custom_news' => ['Network News', 'render_custom_news_widget'],
            'msd_user_management' => ['User Management', 'render_user_management_widget'],
            'msd_contact_info' => ['Contact Information', 'render_contact_info_widget'],
            'msd_last_edits' => ['Recent Network Activity', 'render_last_edits_widget'],
            'msd_todo_widget' => ['Todo List', 'render_todo_widget']
        ];

        foreach ($widgets as $widget_id => $widget_data) {
            if (!empty($this->enabled_widgets[$widget_id])) {
                wp_add_dashboard_widget(
                    $widget_id,
                    $widget_data[0],
                    [$this, $widget_data[1]]
                );
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = ['index.php', 'dashboard.php', 'settings_page_msd-settings'];

        if (!in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_script(
            'msd-dashboard',
            WP_MSD_PLUGIN_URL . 'assets/dashboard.js',
            ['jquery', 'jquery-ui-sortable'],
            WP_MSD_VERSION,
            true
        );

        wp_enqueue_style(
            'msd-dashboard',
            WP_MSD_PLUGIN_URL . 'assets/dashboard.css',
            [],
            WP_MSD_VERSION
        );

        wp_localize_script('msd-dashboard', 'msdAjax', [
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
        $this->enabled_widgets = $enabled_widgets;

        wp_safe_redirect(add_query_arg('updated', 'true', network_admin_url('settings.php?page=msd-settings')));
        exit;
    }

    private function get_widget_description($widget_id) {
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

    public function render_network_overview_widget() {
        echo '<div id="msd-network-overview" class="msd-widget-content" data-widget="network_overview">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_quick_site_widget() {
        echo '<div id="msd-quick-sites" class="msd-widget-content" data-widget="site_list">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_storage_performance_widget() {
        echo '<div id="msd-storage-performance" class="msd-widget-content" data-widget="storage_data">';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_contact_info_widget() {
        echo '<div id="msd-contact-info" class="msd-widget-content" data-widget="contact_info">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="contact_info">↻</button>';
        $this->render_contact_info_content();
        echo '</div>';
        $this->render_contact_info_modal();
    }

    private function render_contact_info_content() {
        $contact_info = get_site_option('msd_contact_info', [
            'name' => get_network_option(null, 'site_name'),
            'email' => get_network_option(null, 'admin_email'),
            'phone' => '',
            'website' => network_home_url(),
            'description' => 'Network Administrator Contact Information',
            'qq' => '',
            'wechat' => '',
            'whatsapp' => '',
            'telegram' => '',
            'qr_code' => ''
        ]);

        echo '<div class="msd-contact-card">';
        echo '<div class="msd-contact-header">';
        echo '<h3><span class="dashicons dashicons-coffee"></span> ' . esc_html($contact_info['name']) . '</h3>';
        echo '</div>';

        echo '<div class="msd-contact-details">';

        if (!empty($contact_info['description'])) {
            echo '<p class="msd-contact-description">' . esc_html($contact_info['description']) . '</p>';
        }

        echo '<div class="msd-contact-item">';
        echo '<span class="dashicons dashicons-email"></span>';
        echo '<a href="mailto:' . esc_attr($contact_info['email']) . '">' . esc_html($contact_info['email']) . '</a>';
        echo '</div>';

        if (!empty($contact_info['phone'])) {
            echo '<div class="msd-contact-item">';
            echo '<span class="dashicons dashicons-phone"></span>';
            echo '<a href="tel:' . esc_attr($contact_info['phone']) . '">' . esc_html($contact_info['phone']) . '</a>';
            echo '</div>';
        }

        echo '<div class="msd-contact-item">';
        echo '<span class="dashicons dashicons-admin-links"></span>';
        echo '<a href="' . esc_url($contact_info['website']) . '" target="_blank">' . esc_html($contact_info['website']) . '</a>';
        echo '</div>';

        $im_fields = [
            'qq' => ['QQ', 'dashicons-admin-users'],
            'wechat' => ['WeChat', 'dashicons-format-chat'],
            'whatsapp' => ['WhatsApp', 'dashicons-smartphone'],
            'telegram' => ['Telegram', 'dashicons-email-alt']
        ];

        foreach ($im_fields as $field => $data) {
            if (!empty($contact_info[$field])) {
                echo '<div class="msd-contact-item">';
                echo '<span class="dashicons ' . $data[1] . '"></span>';
                echo '<span>' . $data[0] . ': ' . esc_html($contact_info[$field]) . '</span>';
                echo '</div>';
            }
        }

        if (!empty($contact_info['qr_code'])) {
            echo '<div class="msd-contact-qr">';
            echo '<img src="' . esc_url($contact_info['qr_code']) . '" alt="QR Code" class="msd-qr-image">';
            echo '</div>';
        }

        echo '</div>';

        echo '<div class="msd-contact-actions">';
        echo '<button class="button button-small" onclick="MSD.showContactInfoModal()">' . __('Edit Contact Info', 'wp-multisite-dashboard') . '</button>';
        echo '</div>';

        echo '</div>';
    }

    private function render_contact_info_modal() {
        include WP_MSD_PLUGIN_DIR . 'templates/contact-info-modal.php';
    }

    public function render_last_edits_widget() {
        echo '<div id="msd-last-edits" class="msd-widget-content" data-widget="last_edits">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="last_edits">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_network_settings_widget() {
        echo '<div id="msd-network-settings" class="msd-widget-content" data-widget="network_settings">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="network_settings">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_user_management_widget() {
        echo '<div id="msd-user-management" class="msd-widget-content" data-widget="user_management">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="user_management">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_server_info_widget() {
        echo '<div id="msd-server-info" class="msd-widget-content" data-widget="server_info">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="server_info">↻</button>';
        $this->render_server_info_content();
        echo '</div>';
    }

    private function render_server_info_content() {
        global $wpdb, $wp_version;

        $data = [
            'PHP Version' => phpversion(),
            'MySQL Version' => $wpdb->db_version(),
            'Server Software' => $_SERVER["SERVER_SOFTWARE"] ?? 'Unknown',
            'Server Time' => current_time('Y-m-d H:i:s'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Upload Size' => size_format(wp_max_upload_size()),
        ];

        $icons = [
            'PHP Version' => 'dashicons-editor-code',
            'MySQL Version' => 'dashicons-database',
            'Server Software' => 'dashicons-admin-tools',
            'Server Time' => 'dashicons-clock',
            'Memory Limit' => 'dashicons-performance',
            'Max Upload Size' => 'dashicons-upload',
        ];

        echo '<div class="msd-server-specs">';
        foreach ($data as $label => $value) {
            $icon = $icons[$label] ?? 'dashicons-info';
            echo '<div class="msd-spec-item">';
            echo '<span class="msd-spec-icon dashicons ' . esc_attr($icon) . '"></span>';
            echo '<span class="msd-spec-label">' . esc_html($label) . '</span>';
            echo '<span class="msd-spec-value">' . esc_html($value) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function render_version_info_widget() {
        echo '<div id="msd-version-info" class="msd-widget-content" data-widget="version_info">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="version_info">↻</button>';
        $this->render_version_info_content();
        echo '</div>';
    }

    private function render_version_info_content() {
        $plugin_data = get_plugin_data(__FILE__);
        global $wpdb;

        echo '<div class="msd-version-header">';
        echo '<h3><span class="dashicons dashicons-admin-multisite"></span> ' . esc_html($plugin_data['Name']) . '</h3>';
        echo '<div class="msd-version-actions">';
        echo '<a href="https://wpmultisite.com/document/wp-multisite-dashboard" target="_blank" class="msd-help-btn msd-help-docs" title="Documentation">';
        echo '<span class="dashicons dashicons-book"></span>';
        echo '</a>';
        echo '<a href="https://wpmultisite.com/support/" target="_blank" class="msd-help-btn msd-help-support" title="Support">';
        echo '<span class="dashicons dashicons-admin-comments"></span>';
        echo '</a>';
        echo '<a href="https://github.com/wpmultisite/wp-multisite-dashboard" target="_blank" class="msd-help-btn msd-help-github" title="GitHub">';
        echo '<span class="dashicons dashicons-admin-links"></span>';
        echo '</a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="msd-version-specs">';

        echo '<div class="msd-version-item">';
        echo '<span class="msd-version-icon dashicons dashicons-tag"></span>';
        echo '<span class="msd-version-label">Plugin Version</span>';
        echo '<span class="msd-version-value">' . esc_html($plugin_data['Version']) . '</span>';
        echo '</div>';

        echo '<div class="msd-version-item">';
        echo '<span class="msd-version-icon dashicons dashicons-admin-links"></span>';
        echo '<span class="msd-version-label">Author URI</span>';
        echo '<span class="msd-version-value"><a href="' . esc_url($plugin_data['AuthorURI']) . '" target="_blank">' . esc_html($plugin_data['AuthorURI']) . '</a></span>';
        echo '</div>';

        echo '<div class="msd-version-item">';
        echo '<span class="msd-version-icon dashicons dashicons-editor-code"></span>';
        echo '<span class="msd-version-label">Required PHP</span>';
        echo '<span class="msd-version-value msd-status-good">' . esc_html($plugin_data['RequiresPHP']) . '</span>';
        echo '</div>';

        echo '<div class="msd-version-item">';
        echo '<span class="msd-version-icon dashicons dashicons-database"></span>';
        echo '<span class="msd-version-label">Database Tables</span>';
        $activity_table = $wpdb->base_prefix . 'msd_activity_log';
        $activity_exists = $wpdb->get_var("SHOW TABLES LIKE '{$activity_table}'") === $activity_table;
        if ($activity_exists) {
            echo '<span class="msd-version-value msd-db-status-good">✓ Activity table created</span>';
        } else {
            echo '<span class="msd-version-value msd-db-status-warning">⚠ Activity table missing</span>';
        }
        echo '</div>';

        echo '</div>';

        echo '<div class="msd-version-description">';
        echo '<p>' . esc_html(strip_tags($plugin_data['Description'])) . '</p>';
        echo '</div>';
    }

    public function render_custom_news_widget() {
        echo '<div id="msd-custom-news" class="msd-widget-content" data-widget="custom_news">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="custom_news">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
        $this->render_news_sources_modal();
    }

    private function render_news_sources_modal() {
        include WP_MSD_PLUGIN_DIR . 'templates/news-sources-modal.php';
    }

    public function render_quick_links_widget() {
        $quick_links = get_site_option('msd_quick_links', []);

        echo '<div id="msd-quick-links" class="msd-widget-content">';

        if (empty($quick_links)) {
            echo '<div class="msd-empty-state">';
            echo '<p>' . __('No quick links configured.', 'wp-multisite-dashboard') . '</p>';
            echo '<button class="button button-primary button-small" onclick="MSD.showQuickLinksModal()">' . __('Add Links', 'wp-multisite-dashboard') . '</button>';
            echo '</div>';
        } else {
            echo '<div class="msd-quick-links-grid" id="msd-sortable-links">';
            foreach ($quick_links as $index => $link) {
                $target = !empty($link['new_tab']) ? '_blank' : '_self';
                echo '<a href="' . esc_url($link['url']) . '" target="' . $target . '" class="msd-quick-link-item" data-index="' . $index . '">';

                if (!empty($link['icon'])) {
                    if (strpos($link['icon'], 'dashicons-') === 0) {
                        echo '<span class="dashicons ' . esc_attr($link['icon']) . '"></span>';
                    } elseif (mb_strlen($link['icon']) <= 4 && preg_match('/[\x{1F000}-\x{1F9FF}]/u', $link['icon'])) {
                        echo '<span class="msd-emoji-icon">' . esc_html($link['icon']) . '</span>';
                    }
                }

                echo '<span>' . esc_html($link['title']) . '</span>';
                echo '</a>';
            }
            echo '</div>';
            echo '<div class="msd-widget-footer">';
            echo '<button class="button button-secondary button-small" onclick="MSD.showQuickLinksModal()">' . __('Edit Links', 'wp-multisite-dashboard') . '</button>';
            echo '</div>';
        }

        echo '</div>';
        $this->render_quick_links_modal();
    }

    private function render_quick_links_modal() {
        include WP_MSD_PLUGIN_DIR . 'templates/quick-links-modal.php';
    }

    public function render_todo_widget() {
        echo '<div id="msd-todo-widget" class="msd-widget-content" data-widget="todo_items">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="todo_items">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function get_network_overview() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $overview = [
                'total_posts' => $network_data->get_total_posts(),
                'total_pages' => $network_data->get_total_pages(),
                'multisite_config' => $network_data->get_multisite_configuration(),
                'network_info' => $network_data->get_network_information(),
                'critical_alerts' => 0,
                'network_status' => $network_data->get_overall_network_status(),
                'last_updated' => current_time('mysql')
            ];

            wp_send_json_success($overview);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load network overview', 'wp-multisite-dashboard'));
        }
    }

    public function get_site_list() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $sites = $network_data->get_recent_active_sites(10);
            wp_send_json_success($sites);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load sites', 'wp-multisite-dashboard'));
        }
    }

    public function get_storage_data() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $storage_data = $network_data->get_storage_usage_data(5);
            wp_send_json_success($storage_data);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load storage data', 'wp-multisite-dashboard'));
        }
    }

    public function get_last_edits() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $last_edits = $network_data->get_recent_network_activity(10);
            wp_send_json_success($last_edits);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load recent activity', 'wp-multisite-dashboard'));
        }
    }

    public function get_todo_items() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $todos = get_user_meta(get_current_user_id(), 'msd_todos', true);
            if (!is_array($todos)) {
                $todos = [];
            }

            // Format todos with human readable dates
            foreach ($todos as &$todo) {
                if (isset($todo['created_at'])) {
                    $todo['created_at_human'] = human_time_diff(strtotime($todo['created_at'])) . ' ago';
                }
                if (isset($todo['updated_at'])) {
                    $todo['updated_at_human'] = human_time_diff(strtotime($todo['updated_at'])) . ' ago';
                }
            }

            wp_send_json_success($todos);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load todo items', 'wp-multisite-dashboard'));
        }
    }

    public function save_contact_info() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $contact_info = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'qq' => sanitize_text_field($_POST['qq'] ?? ''),
            'wechat' => sanitize_text_field($_POST['wechat'] ?? ''),
            'whatsapp' => sanitize_text_field($_POST['whatsapp'] ?? ''),
            'telegram' => sanitize_text_field($_POST['telegram'] ?? ''),
            'qr_code' => esc_url_raw($_POST['qr_code'] ?? '')
        ];

        update_site_option('msd_contact_info', $contact_info);
        wp_send_json_success(['message' => __('Contact information saved successfully', 'wp-multisite-dashboard')]);
    }

    public function save_todo_item() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');

            if (empty($title)) {
                wp_send_json_error(__('Title is required', 'wp-multisite-dashboard'));
                return;
            }

            $todos = get_user_meta(get_current_user_id(), 'msd_todos', true);
            if (!is_array($todos)) {
                $todos = [];
            }

            $new_todo = [
                'id' => uniqid(),
                'title' => $title,
                'description' => $description,
                'completed' => false,
                'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            $todos[] = $new_todo;

            $result = update_user_meta(get_current_user_id(), 'msd_todos', $todos);

            if ($result) {
                wp_send_json_success(['message' => __('Todo item created', 'wp-multisite-dashboard')]);
            } else {
                wp_send_json_error(__('Failed to create todo item', 'wp-multisite-dashboard'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to create todo item', 'wp-multisite-dashboard'));
        }
    }

    public function update_todo_item() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST['id'] ?? '');
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');

            if (empty($id) || empty($title)) {
                wp_send_json_error(__('ID and title are required', 'wp-multisite-dashboard'));
                return;
            }

            $todos = get_user_meta(get_current_user_id(), 'msd_todos', true);
            if (!is_array($todos)) {
                wp_send_json_error(__('No todos found', 'wp-multisite-dashboard'));
                return;
            }

            $updated = false;
            foreach ($todos as &$todo) {
                if ($todo['id'] === $id) {
                    $todo['title'] = $title;
                    $todo['description'] = $description;
                    if (isset($_POST['priority'])) {
                        $todo['priority'] = sanitize_text_field($_POST['priority']);
                    }
                    $todo['updated_at'] = current_time('mysql');
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                $result = update_user_meta(get_current_user_id(), 'msd_todos', $todos);
                if ($result) {
                    wp_send_json_success(['message' => __('Todo item updated', 'wp-multisite-dashboard')]);
                } else {
                    wp_send_json_error(__('Failed to update todo item', 'wp-multisite-dashboard'));
                }
            } else {
                wp_send_json_error(__('Todo item not found', 'wp-multisite-dashboard'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to update todo item', 'wp-multisite-dashboard'));
        }
    }

    public function delete_todo_item() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST['id'] ?? '');

            if (empty($id)) {
                wp_send_json_error(__('ID is required', 'wp-multisite-dashboard'));
                return;
            }

            $todos = get_user_meta(get_current_user_id(), 'msd_todos', true);
            if (!is_array($todos)) {
                wp_send_json_error(__('No todos found', 'wp-multisite-dashboard'));
                return;
            }

            $filtered_todos = array_filter($todos, function($todo) use ($id) {
                return $todo['id'] !== $id;
            });

            if (count($filtered_todos) < count($todos)) {
                $result = update_user_meta(get_current_user_id(), 'msd_todos', array_values($filtered_todos));
                if ($result) {
                    wp_send_json_success(['message' => __('Todo item deleted', 'wp-multisite-dashboard')]);
                } else {
                    wp_send_json_error(__('Failed to delete todo item', 'wp-multisite-dashboard'));
                }
            } else {
                wp_send_json_error(__('Todo item not found', 'wp-multisite-dashboard'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to delete todo item', 'wp-multisite-dashboard'));
        }
    }

    public function toggle_todo_complete() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $id = sanitize_text_field($_POST['id'] ?? '');

            if (empty($id)) {
                wp_send_json_error(__('ID is required', 'wp-multisite-dashboard'));
                return;
            }

            $todos = get_user_meta(get_current_user_id(), 'msd_todos', true);
            if (!is_array($todos)) {
                wp_send_json_error(__('No todos found', 'wp-multisite-dashboard'));
                return;
            }

            $updated = false;
            foreach ($todos as &$todo) {
                if ($todo['id'] === $id) {
                    $todo['completed'] = !$todo['completed'];
                    $todo['updated_at'] = current_time('mysql');
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                $result = update_user_meta(get_current_user_id(), 'msd_todos', $todos);
                if ($result) {
                    wp_send_json_success(['message' => __('Todo status updated', 'wp-multisite-dashboard')]);
                } else {
                    wp_send_json_error(__('Failed to update todo status', 'wp-multisite-dashboard'));
                }
            } else {
                wp_send_json_error(__('Todo item not found', 'wp-multisite-dashboard'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to update todo status', 'wp-multisite-dashboard'));
        }
    }

    public function reorder_quick_links() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            wp_send_json_error(__('Invalid order data', 'wp-multisite-dashboard'));
            return;
        }

        $current_links = get_site_option('msd_quick_links', []);
        $reordered_links = [];

        foreach ($order as $index) {
            $index = intval($index);
            if (isset($current_links[$index])) {
                $reordered_links[] = $current_links[$index];
            }
        }

        update_site_option('msd_quick_links', $reordered_links);
        wp_send_json_success(['message' => __('Links reordered successfully', 'wp-multisite-dashboard')]);
    }

    public function get_user_management() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $user_manager = new WP_MSD_User_Manager();
            $user_data = $user_manager->get_recent_users_data();
            wp_send_json_success($user_data);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load user data', 'wp-multisite-dashboard'));
        }
    }

    public function manage_user_action() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $action = sanitize_text_field($_POST['user_action'] ?? '');
            $user_id = intval($_POST['user_id'] ?? 0);
            $additional_data = $_POST['additional_data'] ?? [];

            $user_manager = new WP_MSD_User_Manager();
            $result = $user_manager->perform_single_user_action($action, $user_id, $additional_data);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to perform user action', 'wp-multisite-dashboard'));
        }
    }

    public function get_server_info() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        global $wpdb, $wp_version;

        $server_info = [
            'wordpress_version' => $wp_version,
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER["SERVER_SOFTWARE"] ?? 'Unknown',
            'server_time' => current_time('Y-m-d H:i:s'),
            'php_memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => size_format(wp_max_upload_size()),
            'active_plugins' => count(get_option('active_plugins', [])),
            'total_users' => count_users()['total_users'],
            'last_updated' => current_time('mysql')
        ];

        wp_send_json_success($server_info);
    }

    public function get_version_info() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $plugin_data = get_plugin_data(__FILE__);
        global $wpdb;

        $activity_table = $wpdb->base_prefix . 'msd_activity_log';
        $activity_exists = $wpdb->get_var("SHOW TABLES LIKE '{$activity_table}'") === $activity_table;

        $version_info = [
            'plugin_name' => $plugin_data['Name'],
            'plugin_version' => $plugin_data['Version'],
            'plugin_author' => $plugin_data['Author'],
            'plugin_uri' => $plugin_data['AuthorURI'],
            'text_domain' => $plugin_data['TextDomain'],
            'required_php' => $plugin_data['RequiresPHP'],
            'network_sites' => get_sites(['count' => true]),
            'description' => strip_tags($plugin_data['Description']),
            'database_status' => $activity_exists ? 'active' : 'missing',
            'database_message' => $activity_exists ? 'Activity table created' : 'Activity table missing',
            'last_updated' => current_time('mysql')
        ];

        wp_send_json_success($version_info);
    }

    public function get_custom_news() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $news_sources = get_site_option('msd_news_sources', []);
            $news_items = [];

            foreach ($news_sources as $source) {
                if (!$source['enabled']) continue;

                $feed_items = $this->fetch_rss_feed($source['url'], 5);
                foreach ($feed_items as $item) {
                    $item['source'] = $source['name'];
                    $news_items[] = $item;
                }
            }

            usort($news_items, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            $news_items = array_slice($news_items, 0, 10);

            wp_send_json_success($news_items);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load news', 'wp-multisite-dashboard'));
        }
    }

    private function fetch_rss_feed($url, $limit = 5) {
        $cache_key = 'msd_rss_' . md5($url);
        $cached = get_site_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'WP-Multisite-Dashboard/' . WP_MSD_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $feed_items = [];

        try {
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                return [];
            }

            $items = $xml->channel->item ?? $xml->entry ?? [];
            $count = 0;

            foreach ($items as $item) {
                if ($count >= $limit) break;

                $title = (string)($item->title ?? '');
                $link = (string)($item->link ?? $item->link['href'] ?? '');
                $description = (string)($item->description ?? $item->summary ?? '');
                $date = (string)($item->pubDate ?? $item->updated ?? '');

                if (!empty($title) && !empty($link)) {
                    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
                    $description = wp_trim_words(strip_tags($description), 20);

                    $feed_items[] = [
                        'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                        'link' => $link,
                        'description' => $description,
                        'date' => $date
                    ];
                    $count++;
                }
            }
        } catch (Exception $e) {
            return [];
        }

        set_site_transient($cache_key, $feed_items, 3600);
        return $feed_items;
    }

    public function save_news_sources() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $sources = [];
        if (isset($_POST['sources']) && is_array($_POST['sources'])) {
            foreach ($_POST['sources'] as $source) {
                if (!empty($source['name']) && !empty($source['url'])) {
                    $sources[] = [
                        'name' => sanitize_text_field($source['name']),
                        'url' => esc_url_raw($source['url']),
                        'enabled' => !empty($source['enabled'])
                    ];
                }
            }
        }

        update_site_option('msd_news_sources', $sources);

        $cache_keys = [];
        foreach ($sources as $source) {
            $cache_keys[] = 'msd_rss_' . md5($source['url']);
        }

        foreach ($cache_keys as $key) {
            delete_site_transient($key);
        }

        wp_send_json_success(['message' => __('News sources saved successfully', 'wp-multisite-dashboard')]);
    }

    public function save_quick_links() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $links = [];
        if (isset($_POST['links']) && is_array($_POST['links'])) {
            foreach ($_POST['links'] as $link) {
                if (!empty($link['title']) && !empty($link['url'])) {
                    $links[] = [
                        'title' => sanitize_text_field($link['title']),
                        'url' => esc_url_raw($link['url']),
                        'icon' => sanitize_text_field($link['icon']),
                        'new_tab' => !empty($link['new_tab'])
                    ];
                }
            }
        }

        update_site_option('msd_quick_links', $links);
        wp_send_json_success(['message' => __('Quick links saved successfully', 'wp-multisite-dashboard')]);
    }

    public function toggle_widget() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $enabled = !empty($_POST['enabled']);

        if (empty($widget_id)) {
            wp_send_json_error(__('Invalid widget ID', 'wp-multisite-dashboard'));
        }

        $this->enabled_widgets[$widget_id] = $enabled ? 1 : 0;
        update_site_option('msd_enabled_widgets', $this->enabled_widgets);

        wp_send_json_success(['message' => __('Widget settings updated', 'wp-multisite-dashboard')]);
    }

    public function refresh_widget_data() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget = sanitize_text_field($_POST['widget'] ?? '');

        if (class_exists('WP_MSD_Network_Data')) {
            $network_data = new WP_MSD_Network_Data();
            $network_data->clear_widget_cache($widget);
        }

        wp_send_json_success(['message' => __('Cache cleared', 'wp-multisite-dashboard')]);
    }

    public function clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'msd_clear_cache')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return;
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return;
        }

        $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');

        try {
            switch ($cache_type) {
                case 'network':
                    if (class_exists('WP_MSD_Network_Data')) {
                        $network_data = new WP_MSD_Network_Data();
                        $network_data->clear_all_caches();
                    }
                    break;

                case 'all':
                default:
                    if (class_exists('WP_MSD_Network_Data')) {
                        $network_data = new WP_MSD_Network_Data();
                        $network_data->clear_all_caches();
                    }
                    wp_cache_flush();
                    break;
            }

            wp_send_json_success(['message' => __('Cache cleared successfully', 'wp-multisite-dashboard')]);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to clear cache', 'wp-multisite-dashboard'));
        }
    }

    public function get_network_settings() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        try {
            $network_data = new WP_MSD_Network_Data();
            $settings_data = $network_data->get_network_settings_overview();
            wp_send_json_success($settings_data);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to load network settings', 'wp-multisite-dashboard'));
        }
    }

    private function verify_ajax_request() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'msd_ajax_nonce')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return false;
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return false;
        }

        return true;
    }

    public function multisite_required_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('WP Multisite Dashboard requires WordPress Multisite to be enabled.', 'wp-multisite-dashboard');
        echo '</p></div>';
    }
}

register_activation_hook(__FILE__, function() {
    if (!is_multisite()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WordPress Multisite to be enabled.', 'wp-multisite-dashboard'));
    }

    require_once WP_MSD_PLUGIN_DIR . 'includes/class-network-data.php';

    $network_data = new WP_MSD_Network_Data();
    $network_data->create_activity_log_table();

    set_site_transient('msd_activation_notice', true, 30);
});

add_action('network_admin_notices', function() {
    if (get_site_transient('msd_activation_notice')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('WP Multisite Dashboard has been activated successfully!', 'wp-multisite-dashboard') . '</p>';
        echo '</div>';
        delete_site_transient('msd_activation_notice');
    }
});

WP_Multisite_Dashboard::get_instance();

require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5p3\PucFactory;

$WPMultisiteDashboardUpdateChecker = PucFactory::buildUpdateChecker(
    'https://updates.weixiaoduo.com/wp-multisite-dashboard.json',
    __FILE__,
    'wp-multisite-dashboard'
);
