<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Ajax_Handler {

    public function __construct() {
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
            'msd_manage_user_action',
            'msd_check_plugin_update',
            'msd_clear_widget_cache'
        ];

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_{$action}", [$this, str_replace('msd_', '', $action)]);
        }
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

        $plugin_data = get_plugin_data(WP_MSD_PLUGIN_DIR . 'wp-multisite-dashboard.php');
        global $wpdb;

        $activity_table = $wpdb->base_prefix . 'msd_activity_log';
        $activity_exists = $wpdb->get_var("SHOW TABLES LIKE '{$activity_table}'") === $activity_table;

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

        $version_info = [
            'plugin_name' => $plugin_data['Name'],
            'plugin_version' => $plugin_data['Version'],
            'plugin_author' => $plugin_data['Author'],
            'plugin_uri' => $plugin_data['AuthorURI'],
            'text_domain' => $plugin_data['TextDomain'],
            'required_php' => $plugin_data['RequiresPHP'],
            'description' => strip_tags($plugin_data['Description']),
            'database_status' => $activity_exists ? 'active' : 'missing',
            'database_message' => $activity_exists ? 'Activity table created' : 'Activity table missing',
            'update_available' => $update_available ? true : false,
            'update_info' => $update_available ?: null,
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

    public function toggle_widget() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $enabled = !empty($_POST['enabled']);

        if (empty($widget_id)) {
            wp_send_json_error(__('Invalid widget ID', 'wp-multisite-dashboard'));
        }

        $enabled_widgets = get_site_option('msd_enabled_widgets', []);
        $enabled_widgets[$widget_id] = $enabled ? 1 : 0;
        update_site_option('msd_enabled_widgets', $enabled_widgets);

        wp_send_json_success(['message' => __('Widget settings updated', 'wp-multisite-dashboard')]);
    }

    public function refresh_widget_data() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $widget = sanitize_text_field($_POST['widget'] ?? '');

        $network_data = new WP_MSD_Network_Data();
        $network_data->clear_widget_cache($widget);

        wp_send_json_success(['message' => __('Cache cleared', 'wp-multisite-dashboard')]);
    }

    public function clear_cache() {
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return;
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'msd_ajax_nonce') && !wp_verify_nonce($nonce, 'msd_clear_cache')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return;
        }

        $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');

        try {
            switch ($cache_type) {
                case 'network':
                    $network_data = new WP_MSD_Network_Data();
                    $network_data->clear_all_caches();
                    break;

                case 'all':
                default:
                    $network_data = new WP_MSD_Network_Data();
                    $network_data->clear_all_caches();
                    wp_cache_flush();
                    break;
            }

            wp_send_json_success(['message' => __('Cache cleared successfully', 'wp-multisite-dashboard')]);
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to clear cache', 'wp-multisite-dashboard'));
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

    public function check_plugin_update() {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $plugin_core = WP_MSD_Plugin_Core::get_instance();
        $update_checker = $plugin_core->get_update_checker();

        if (!$update_checker) {
            wp_send_json_success(['message' => __('No updates available', 'wp-multisite-dashboard')]);
            return;
        }

        $update = $update_checker->checkForUpdates();

        if ($update && version_compare($update->version, WP_MSD_VERSION, '>')) {
            wp_send_json_success([
                'version' => $update->version,
                'details_url' => $update->details_url ?? '#'
            ]);
        } else {
            wp_send_json_success(['message' => __('No updates available', 'wp-multisite-dashboard')]);
        }
    }

    public function clear_widget_cache() {
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return;
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'msd_ajax_nonce') && !wp_verify_nonce($nonce, 'msd_clear_cache')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return;
        }

        delete_site_transient('msd_detected_widgets');

        wp_send_json_success(['message' => __('Widget cache cleared successfully', 'wp-multisite-dashboard')]);
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
}
