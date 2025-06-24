<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Admin_Interface {

    public function __construct() {
        add_action('admin_footer', [$this, 'render_modals']);
    }

    public function render_modals() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'dashboard-network') {
            include_once WP_MSD_PLUGIN_DIR . 'templates/admin-modals.php';
        }
    }

    public function add_network_widgets() {
        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $enabled_widgets = $plugin_core->get_enabled_widgets();

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
            if (!empty($enabled_widgets[$widget_id])) {
                wp_add_dashboard_widget(
                    $widget_id,
                    $widget_data[0],
                    [$this, $widget_data[1]]
                );
            }
        }
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
        $plugin_data = get_plugin_data(WP_MSD_PLUGIN_DIR . 'wp-multisite-dashboard.php');
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

        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $update_checker = $plugin_core->get_update_checker();
        $update_available = false;

        if ($update_checker) {
            $update = $update_checker->checkForUpdates();
            if ($update && version_compare($update->version, WP_MSD_VERSION, '>')) {
                $update_available = [
                    'version' => $update->version,
                    'details_url' => $update->details_url ?? '#'
                ];
            }
        }

        if ($update_available) {
            echo '<div class="msd-update-notice">';
            echo '<span class="dashicons dashicons-update"></span>';
            echo '<span>' . sprintf(__('Version %s available! ', 'wp-multisite-dashboard'), esc_html($update_available['version'])) . '</span>';
            echo '<a href="' . esc_url($update_available['details_url']) . '" target="_blank" class="msd-update-link">' . __('View Details', 'wp-multisite-dashboard') . '</a>';
            echo '</div>';
        }

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
    }

    public function render_custom_news_widget() {
        echo '<div id="msd-custom-news" class="msd-widget-content" data-widget="custom_news">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="custom_news">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_user_management_widget() {
        echo '<div id="msd-user-management" class="msd-widget-content" data-widget="user_management">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="user_management">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }

    public function render_contact_info_widget() {
        echo '<div id="msd-contact-info" class="msd-widget-content" data-widget="contact_info">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="contact_info">↻</button>';
        $this->render_contact_info_content();
        echo '</div>';
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

    public function render_last_edits_widget() {
        echo '<div id="msd-last-edits" class="msd-widget-content" data-widget="last_edits">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="last_edits">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
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
    }

    public function render_todo_widget() {
        echo '<div id="msd-todo-widget" class="msd-widget-content" data-widget="todo_items">';
        echo '<button class="msd-refresh-btn" title="Refresh" data-widget="todo_items">↻</button>';
        echo '<div class="msd-loading"><span class="msd-spinner"></span>' . __('Loading...', 'wp-multisite-dashboard') . '</div>';
        echo '</div>';
    }
}
