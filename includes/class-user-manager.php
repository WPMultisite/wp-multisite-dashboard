<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_User_Manager {

    private $wpdb;
    private $cache_group = 'msd_users';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_recent_users_data($limit = 10) {
        $cache_key = "recent_users_data_{$limit}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $recent_users = $this->get_recent_registered_users($limit);
        $pending_users = $this->get_pending_users($limit);
        $super_admins = get_super_admins();

        $data = [
            'recent_registrations' => $recent_users,
            'pending_activations' => $pending_users,
            'total_users' => $this->get_total_users_count(),
            'super_admin_count' => count($super_admins),
            'user_actions' => $this->get_available_user_actions(),
            'registration_status' => $this->get_registration_status(),
            'last_updated' => current_time('mysql')
        ];

        wp_cache_set($cache_key, $data, $this->cache_group, 1800);
        return $data;
    }

    private function get_recent_registered_users($limit = 10) {
        $args = [
            'number' => $limit,
            'orderby' => 'registered',
            'order' => 'DESC',
            'fields' => 'all'
        ];

        $users = get_users($args);
        $user_data = [];

        foreach ($users as $user) {
            $last_login = get_user_meta($user->ID, 'msd_last_login', true);
            $login_count = get_user_meta($user->ID, 'msd_login_count', true);

            $user_sites = get_blogs_of_user($user->ID);
            $sites_count = count($user_sites);

            $user_data[] = [
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'registered_ago' => human_time_diff(strtotime($user->user_registered)) . ' ago',
                'is_super_admin' => is_super_admin($user->ID),
                'sites_count' => $sites_count,
                'last_login' => $last_login,
                'last_login_human' => $last_login ? human_time_diff(strtotime($last_login)) . ' ago' : __('Never', 'wp-multisite-dashboard'),
                'login_count' => intval($login_count),
                'status' => $this->get_user_status_from_activity($last_login),
                'profile_url' => network_admin_url('user-edit.php?user_id=' . $user->ID),
                'sites_url' => network_admin_url('users.php?action=allusers&s=' . urlencode($user->user_login)),
                'avatar_url' => $this->get_user_avatar($user->ID, 32)
            ];
        }

        return $user_data;
    }

    private function get_user_avatar($user_id, $size = 32) {
        $cache_key = "user_avatar_{$user_id}_{$size}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $avatar_url = get_avatar_url($user_id, [
            'size' => $size,
            'default' => 'mp',
            'force_default' => false
        ]);

        if (empty($avatar_url)) {
            $avatar_url = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIyMCIgZmlsbD0iI2Y2ZjdmNyIgc3Ryb2tlPSIjZGRkIi8+PGNpcmNsZSBjeD0iMjAiIGN5PSIxNSIgcj0iNiIgZmlsbD0iIzk5OSIvPjxlbGxpcHNlIGN4PSIyMCIgY3k9IjMzIiByeD0iMTAiIHJ5PSI3IiBmaWxsPSIjOTk5Ii8+PC9zdmc+';
        }

        wp_cache_set($cache_key, $avatar_url, $this->cache_group, 3600);
        return $avatar_url;
    }

    private function get_pending_users($limit = 10) {
        $pending_users = [];

        $signup_table = $this->wpdb->base_prefix . 'signups';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$signup_table}'") === $signup_table;

        if ($table_exists) {
            $signups = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$signup_table}
                    WHERE active = 0
                    ORDER BY registered DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );

            foreach ($signups as $signup) {
                $pending_users[] = [
                    'ID' => $signup['signup_id'],
                    'user_login' => $signup['user_login'],
                    'user_email' => $signup['user_email'],
                    'domain' => $signup['domain'],
                    'path' => $signup['path'],
                    'registered' => $signup['registered'],
                    'registered_ago' => human_time_diff(strtotime($signup['registered'])) . ' ago',
                    'activation_key' => $signup['activation_key'],
                    'meta' => maybe_unserialize($signup['meta']),
                    'activate_url' => network_admin_url('users.php?action=activate&key=' . $signup['activation_key'])
                ];
            }
        }

        return $pending_users;
    }

    private function get_user_status_from_activity($last_login) {
        if (empty($last_login)) {
            return 'never_logged_in';
        }

        $days_since_login = (time() - strtotime($last_login)) / DAY_IN_SECONDS;

        if ($days_since_login <= 7) {
            return 'active';
        } elseif ($days_since_login <= 30) {
            return 'recent';
        } elseif ($days_since_login <= 90) {
            return 'inactive';
        } else {
            return 'very_inactive';
        }
    }

    private function get_total_users_count() {
        $user_count = count_users();
        return $user_count['total_users'];
    }

    private function get_available_user_actions() {
        return [
            'edit_user' => __('Edit User', 'wp-multisite-dashboard'),
            'delete_user' => __('Delete User', 'wp-multisite-dashboard'),
            'send_password_reset' => __('Send Password Reset', 'wp-multisite-dashboard'),
            'promote_super_admin' => __('Make Super Admin', 'wp-multisite-dashboard'),
            'demote_super_admin' => __('Remove Super Admin', 'wp-multisite-dashboard'),
            'activate_user' => __('Activate User', 'wp-multisite-dashboard'),
            'deactivate_user' => __('Deactivate User', 'wp-multisite-dashboard')
        ];
    }

    private function get_registration_status() {
        $registration = get_network_option(null, 'registration');

        $status_map = [
            'none' => __('Registration disabled', 'wp-multisite-dashboard'),
            'user' => __('User registration enabled', 'wp-multisite-dashboard'),
            'blog' => __('Site registration enabled', 'wp-multisite-dashboard'),
            'all' => __('User and site registration enabled', 'wp-multisite-dashboard')
        ];

        return [
            'setting' => $registration,
            'description' => $status_map[$registration] ?? __('Unknown', 'wp-multisite-dashboard'),
            'settings_url' => network_admin_url('settings.php')
        ];
    }

    public function search_users($search_term = '', $role_filter = '', $activity_filter = '', $limit = 100) {
        $cache_key = "search_users_" . md5($search_term . $role_filter . $activity_filter . $limit);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $query_args = [
            'number' => $limit,
            'fields' => 'all',
            'orderby' => 'registered',
            'order' => 'DESC'
        ];

        if (!empty($search_term)) {
            $query_args['search'] = '*' . esc_attr($search_term) . '*';
            $query_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($role_filter)) {
            $query_args['role'] = $role_filter;
        }

        $users = get_users($query_args);
        $user_ids = array_column($users, 'ID');

        $user_sites_data = $this->get_batch_user_sites($user_ids);
        $user_meta_data = $this->get_batch_user_meta($user_ids);
        $filtered_users = [];

        foreach ($users as $user) {
            $user_id = $user->ID;

            $user_data = [
                'ID' => $user_id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'sites' => $user_sites_data[$user_id] ?? [],
                'roles' => $this->get_user_network_roles($user_id, $user_sites_data[$user_id] ?? []),
                'last_activity' => $user_meta_data[$user_id]['last_activity'] ?? '',
                'login_count' => $user_meta_data[$user_id]['login_count'] ?? 0,
                'status' => $this->get_user_status($user_meta_data[$user_id]['last_activity'] ?? ''),
                'is_super_admin' => is_super_admin($user_id),
                'capabilities' => $this->get_user_capabilities_summary($user_id)
            ];

            $user_data['last_activity_human'] = $this->format_last_activity($user_data['last_activity']);

            if ($this->user_matches_activity_filter($user_data, $activity_filter)) {
                $filtered_users[] = $user_data;
            }
        }

        wp_cache_set($cache_key, $filtered_users, $this->cache_group, 1800);
        return $filtered_users;
    }

    private function get_batch_user_sites($user_ids) {
        if (empty($user_ids)) {
            return [];
        }

        $user_sites = [];
        $sites = get_sites(['number' => 1000]);

        foreach ($sites as $site) {
            $blog_id = $site->blog_id;
            $capabilities_key = $this->wpdb->get_blog_prefix($blog_id) . 'capabilities';

            $user_meta_results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT user_id, meta_value FROM {$this->wpdb->usermeta}
                    WHERE meta_key = %s AND user_id IN (" . implode(',', array_map('intval', $user_ids)) . ")",
                    $capabilities_key
                ),
                ARRAY_A
            );

            $site_name = $this->get_site_name($blog_id);

            foreach ($user_meta_results as $result) {
                $user_id = $result['user_id'];
                $capabilities = maybe_unserialize($result['meta_value']);

                if (is_array($capabilities) && !empty($capabilities)) {
                    if (!isset($user_sites[$user_id])) {
                        $user_sites[$user_id] = [];
                    }

                    $user_sites[$user_id][] = [
                        'blog_id' => $blog_id,
                        'blogname' => $site_name,
                        'domain' => $site->domain . $site->path,
                        'roles' => array_keys($capabilities)
                    ];
                }
            }
        }

        return $user_sites;
    }

    private function get_batch_user_meta($user_ids) {
        if (empty($user_ids)) {
            return [];
        }

        $user_meta = [];
        $user_ids_str = implode(',', array_map('intval', $user_ids));

        $results = $this->wpdb->get_results(
            "SELECT user_id, meta_key, meta_value
             FROM {$this->wpdb->usermeta}
             WHERE user_id IN ($user_ids_str)
             AND meta_key IN ('msd_last_activity', 'last_activity', 'msd_login_count', 'msd_last_login', 'msd_user_status')",
            ARRAY_A
        );

        foreach ($user_ids as $user_id) {
            $user_meta[$user_id] = [
                'last_activity' => '',
                'login_count' => 0,
                'last_login' => '',
                'user_status' => 'active'
            ];
        }

        foreach ($results as $row) {
            $user_id = $row['user_id'];

            switch ($row['meta_key']) {
                case 'msd_last_activity':
                case 'last_activity':
                    if (empty($user_meta[$user_id]['last_activity']) ||
                        strtotime($row['meta_value']) > strtotime($user_meta[$user_id]['last_activity'])) {
                        $user_meta[$user_id]['last_activity'] = $row['meta_value'];
                    }
                    break;
                case 'msd_login_count':
                    $user_meta[$user_id]['login_count'] = intval($row['meta_value']);
                    break;
                case 'msd_last_login':
                    $user_meta[$user_id]['last_login'] = $row['meta_value'];
                    break;
                case 'msd_user_status':
                    $user_meta[$user_id]['user_status'] = $row['meta_value'];
                    break;
            }
        }

        return $user_meta;
    }

    private function get_site_name($blog_id) {
        $cache_key = "site_name_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $site_details = get_blog_details($blog_id);
        $name = $site_details ? $site_details->blogname : __('Unknown Site', 'wp-multisite-dashboard');

        wp_cache_set($cache_key, $name, $this->cache_group, 3600);
        return $name;
    }

    private function get_user_network_roles($user_id, $sites_data) {
        $roles = [];

        foreach ($sites_data as $site) {
            if (!empty($site['roles'])) {
                $roles[$site['blog_id']] = $site['roles'];
            }
        }

        return $roles;
    }

    private function get_user_capabilities_summary($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $capabilities = [];

        if (is_super_admin($user_id)) {
            $capabilities[] = 'Super Admin';
        }

        if ($user->has_cap('manage_network')) {
            $capabilities[] = 'Network Admin';
        }

        if ($user->has_cap('manage_options')) {
            $capabilities[] = 'Site Admin';
        }

        if ($user->has_cap('edit_posts')) {
            $capabilities[] = 'Content Editor';
        }

        return array_unique($capabilities);
    }

    private function get_user_status($last_activity) {
        if (empty($last_activity)) {
            return 'inactive';
        }

        $last_active_timestamp = strtotime($last_activity);
        $now = current_time('timestamp');
        $days_inactive = ($now - $last_active_timestamp) / DAY_IN_SECONDS;

        if ($days_inactive > 180) {
            return 'inactive_180';
        } elseif ($days_inactive > 90) {
            return 'inactive_90';
        } elseif ($days_inactive > 30) {
            return 'inactive_30';
        }

        return 'active';
    }

    private function format_last_activity($last_activity) {
        if (empty($last_activity)) {
            return __('Never', 'wp-multisite-dashboard');
        }

        $timestamp = strtotime($last_activity);
        if ($timestamp === false) {
            return __('Unknown', 'wp-multisite-dashboard');
        }

        return human_time_diff($timestamp) . ' ago';
    }

    private function user_matches_activity_filter($user_data, $activity_filter) {
        if (empty($activity_filter)) {
            return true;
        }

        switch ($activity_filter) {
            case 'active':
                return $user_data['status'] === 'active';
            case 'inactive_30':
                return in_array($user_data['status'], ['inactive_30', 'inactive_90', 'inactive_180', 'inactive']);
            case 'inactive_90':
                return in_array($user_data['status'], ['inactive_90', 'inactive_180', 'inactive']);
            case 'super_admins':
                return $user_data['is_super_admin'];
            case 'no_sites':
                return empty($user_data['sites']);
            default:
                return true;
        }
    }

    public function perform_bulk_action($action, $user_ids, $additional_data = []) {
        if (!current_user_can('manage_network_users')) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'wp-multisite-dashboard')];
        }

        if (empty($user_ids) || !is_array($user_ids)) {
            return ['success' => false, 'message' => __('No users selected', 'wp-multisite-dashboard')];
        }

        $user_ids = array_map('intval', $user_ids);
        $user_ids = array_filter($user_ids);

        if (empty($user_ids)) {
            return ['success' => false, 'message' => __('Invalid user selection', 'wp-multisite-dashboard')];
        }

        if ($action === 'delete_user') {
            return $this->perform_bulk_delete($user_ids);
        }

        $results = [];
        $success_count = 0;
        $error_count = 0;

        foreach ($user_ids as $user_id) {
            $result = $this->perform_single_user_action($action, $user_id, $additional_data);
            $results[] = [
                'user_id' => $user_id,
                'success' => $result['success'],
                'message' => $result['message']
            ];

            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        $this->log_bulk_action($action, $user_ids, $success_count, $error_count);
        $this->clear_user_caches($user_ids);

        return [
            'success' => true,
            'message' => sprintf(
                __('Bulk action completed: %d successful, %d failed', 'wp-multisite-dashboard'),
                $success_count,
                $error_count
            ),
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count
        ];
    }

    private function perform_bulk_delete($user_ids) {
        if (!current_user_can('delete_users')) {
            return ['success' => false, 'message' => __('Insufficient permissions to delete users', 'wp-multisite-dashboard')];
        }

        $success_count = 0;
        $error_count = 0;
        $results = [];
        $super_admins = get_super_admins();

        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);

            if (!$user) {
                $error_count++;
                $results[] = [
                    'user_id' => $user_id,
                    'success' => false,
                    'message' => __('User not found', 'wp-multisite-dashboard')
                ];
                continue;
            }

            if (in_array($user->user_login, $super_admins)) {
                $error_count++;
                $results[] = [
                    'user_id' => $user_id,
                    'success' => false,
                    'message' => __('Cannot delete super admin', 'wp-multisite-dashboard')
                ];
                continue;
            }

            if ($user_id === get_current_user_id()) {
                $error_count++;
                $results[] = [
                    'user_id' => $user_id,
                    'success' => false,
                    'message' => __('Cannot delete your own account', 'wp-multisite-dashboard')
                ];
                continue;
            }

            require_once ABSPATH . 'wp-admin/includes/user.php';
            $result = wpmu_delete_user($user_id);

            if ($result) {
                $success_count++;
                $results[] = [
                    'user_id' => $user_id,
                    'success' => true,
                    'message' => __('User deleted', 'wp-multisite-dashboard')
                ];
            } else {
                $error_count++;
                $results[] = [
                    'user_id' => $user_id,
                    'success' => false,
                    'message' => __('Failed to delete user', 'wp-multisite-dashboard')
                ];
            }
        }

        $this->log_bulk_action('delete_user', $user_ids, $success_count, $error_count);

        return [
            'success' => true,
            'message' => sprintf(
                __('Bulk delete completed: %d successful, %d failed', 'wp-multisite-dashboard'),
                $success_count,
                $error_count
            ),
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count
        ];
    }

    public function perform_single_user_action($action, $user_id, $additional_data = []) {
        try {
            switch ($action) {
                case 'add_to_site':
                    return $this->add_user_to_site($user_id, $additional_data);

                case 'remove_from_site':
                    return $this->remove_user_from_site($user_id, $additional_data);

                case 'change_role':
                    return $this->change_user_role($user_id, $additional_data);

                case 'send_password_reset':
                    return $this->send_password_reset($user_id);

                case 'activate_user':
                    return $this->activate_user($user_id);

                case 'deactivate_user':
                    return $this->deactivate_user($user_id);

                case 'promote_to_super_admin':
                    return $this->promote_to_super_admin($user_id);

                case 'demote_from_super_admin':
                    return $this->demote_from_super_admin($user_id);

                case 'delete_user':
                    return $this->delete_single_user($user_id);

                default:
                    return ['success' => false, 'message' => __('Unknown action', 'wp-multisite-dashboard')];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function delete_single_user($user_id) {
        if (!current_user_can('delete_users')) {
            return ['success' => false, 'message' => __('Insufficient permissions to delete users', 'wp-multisite-dashboard')];
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return ['success' => false, 'message' => __('User not found', 'wp-multisite-dashboard')];
        }

        $super_admins = get_super_admins();
        if (in_array($user->user_login, $super_admins)) {
            return ['success' => false, 'message' => __('Cannot delete super admin', 'wp-multisite-dashboard')];
        }

        if ($user_id === get_current_user_id()) {
            return ['success' => false, 'message' => __('Cannot delete your own account', 'wp-multisite-dashboard')];
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $result = wpmu_delete_user($user_id);

        if ($result) {
            return ['success' => true, 'message' => __('User deleted successfully', 'wp-multisite-dashboard')];
        } else {
            return ['success' => false, 'message' => __('Failed to delete user', 'wp-multisite-dashboard')];
        }
    }

    private function add_user_to_site($user_id, $data) {
        $site_id = intval($data['target_site_id'] ?? 0);
        $role = sanitize_text_field($data['target_role'] ?? 'subscriber');

        if (!$site_id) {
            return ['success' => false, 'message' => __('Site ID required', 'wp-multisite-dashboard')];
        }

        if (!get_blog_details($site_id)) {
            return ['success' => false, 'message' => __('Site not found', 'wp-multisite-dashboard')];
        }

        $result = add_user_to_blog($site_id, $user_id, $role);

        if (is_wp_error($result)) {
            return ['success' => false, 'message' => $result->get_error_message()];
        }

        return ['success' => true, 'message' => __('User added to site', 'wp-multisite-dashboard')];
    }

    private function remove_user_from_site($user_id, $data) {
        $site_id = intval($data['target_site_id'] ?? 0);

        if (!$site_id) {
            return ['success' => false, 'message' => __('Site ID required', 'wp-multisite-dashboard')];
        }

        if ($site_id == 1 && is_super_admin($user_id)) {
            return ['success' => false, 'message' => __('Cannot remove super admin from main site', 'wp-multisite-dashboard')];
        }

        $result = remove_user_from_blog($user_id, $site_id);

        if (is_wp_error($result)) {
            return ['success' => false, 'message' => $result->get_error_message()];
        }

        return ['success' => true, 'message' => __('User removed from site', 'wp-multisite-dashboard')];
    }

    private function change_user_role($user_id, $data) {
        $site_id = intval($data['target_site_id'] ?? 0);
        $new_role = sanitize_text_field($data['new_role'] ?? $data['target_role'] ?? '');

        if (!$site_id || !$new_role) {
            return ['success' => false, 'message' => __('Site ID and role required', 'wp-multisite-dashboard')];
        }

        switch_to_blog($site_id);
        $user = get_userdata($user_id);

        if (!$user) {
            restore_current_blog();
            return ['success' => false, 'message' => __('User not found', 'wp-multisite-dashboard')];
        }

        $user->set_role($new_role);
        restore_current_blog();

        return ['success' => true, 'message' => __('User role changed', 'wp-multisite-dashboard')];
    }

    private function send_password_reset($user_id) {
        $user = get_userdata($user_id);

        if (!$user) {
            return ['success' => false, 'message' => __('User not found', 'wp-multisite-dashboard')];
        }

        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return ['success' => false, 'message' => $key->get_error_message()];
        }

        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

        $message = sprintf(
            __('A password reset has been requested for your account on %s.', 'wp-multisite-dashboard'),
            get_network_option(null, 'site_name')
        ) . "\r\n\r\n";

        $message .= sprintf(__('Username: %s'), $user->user_login) . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
        $message .= $reset_url . "\r\n\r\n";
        $message .= __('This link will expire in 24 hours.') . "\r\n";

        $title = sprintf(__('[%s] Password Reset'), get_network_option(null, 'site_name'));

        $sent = wp_mail($user->user_email, $title, $message);

        if (!$sent) {
            return ['success' => false, 'message' => __('Failed to send password reset email', 'wp-multisite-dashboard')];
        }

        return ['success' => true, 'message' => __('Password reset email sent', 'wp-multisite-dashboard')];
    }

    private function activate_user($user_id) {
        $user = get_userdata($user_id);

        if (!$user) {
            return ['success' => false, 'message' => __('User not found', 'wp-multisite-dashboard')];
        }

        delete_user_meta($user_id, 'msd_user_deactivated');
        update_user_meta($user_id, 'msd_user_status', 'active');

        return ['success' => true, 'message' => __('User activated', 'wp-multisite-dashboard')];
    }

    private function deactivate_user($user_id) {
        if (is_super_admin($user_id)) {
            return ['success' => false, 'message' => __('Cannot deactivate super admin', 'wp-multisite-dashboard')];
        }

        if ($user_id === get_current_user_id()) {
            return ['success' => false, 'message' => __('Cannot deactivate your own account', 'wp-multisite-dashboard')];
        }

        update_user_meta($user_id, 'msd_user_deactivated', current_time('mysql'));
        update_user_meta($user_id, 'msd_user_status', 'deactivated');

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        return ['success' => true, 'message' => __('User deactivated', 'wp-multisite-dashboard')];
    }

    private function promote_to_super_admin($user_id) {
        if (!current_user_can('manage_network')) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'wp-multisite-dashboard')];
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return ['success' => false, 'message' => __('User not found', 'wp-multisite-dashboard')];
        }

        if (is_super_admin($user_id)) {
            return ['success' => false, 'message' => __('User is already a super admin', 'wp-multisite-dashboard')];
        }

        grant_super_admin($user_id);

        return ['success' => true, 'message' => __('User promoted to super admin', 'wp-multisite-dashboard')];
    }

    private function demote_from_super_admin($user_id) {
        if (!current_user_can('manage_network')) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'wp-multisite-dashboard')];
        }

        if ($user_id === get_current_user_id()) {
            return ['success' => false, 'message' => __('Cannot demote yourself', 'wp-multisite-dashboard')];
        }

        $super_admins = get_super_admins();
        if (count($super_admins) <= 1) {
            return ['success' => false, 'message' => __('Cannot demote the last super admin', 'wp-multisite-dashboard')];
        }

        revoke_super_admin($user_id);

        return ['success' => true, 'message' => __('User demoted from super admin', 'wp-multisite-dashboard')];
    }

    public function get_inactive_users($days = 90, $limit = 100) {
        $cache_key = "inactive_users_{$days}_{$limit}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $users = get_users([
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'msd_last_activity',
                    'value' => $cutoff_date,
                    'compare' => '<',
                    'type' => 'DATETIME'
                ],
                [
                    'key' => 'msd_last_activity',
                    'compare' => 'NOT EXISTS'
                ]
            ],
            'fields' => 'all',
            'number' => $limit,
            'orderby' => 'registered',
            'order' => 'ASC'
        ]);

        $user_ids = array_column($users, 'ID');
        $user_sites_data = $this->get_batch_user_sites($user_ids);
        $user_meta_data = $this->get_batch_user_meta($user_ids);

        $inactive_users = array_map(function($user) use ($user_sites_data, $user_meta_data) {
            $user_id = $user->ID;
            return [
                'ID' => $user_id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'last_activity' => $user_meta_data[$user_id]['last_activity'] ?? '',
                'last_activity_human' => $this->format_last_activity($user_meta_data[$user_id]['last_activity'] ?? ''),
                'sites_count' => count($user_sites_data[$user_id] ?? []),
                'is_super_admin' => is_super_admin($user_id)
            ];
        }, $users);

        wp_cache_set($cache_key, $inactive_users, $this->cache_group, 3600);
        return $inactive_users;
    }

    public function get_user_activity_summary($user_id) {
        $cache_key = "user_activity_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        $user_sites = $this->get_batch_user_sites([$user_id])[$user_id] ?? [];

        $summary = [
            'user_info' => [
                'ID' => $user_id,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'registered' => $user->user_registered
            ],
            'network_stats' => [
                'total_sites' => count($user_sites),
                'total_posts' => 0,
                'total_comments' => 0,
                'last_login' => get_user_meta($user_id, 'msd_last_login', true),
                'login_count' => get_user_meta($user_id, 'msd_login_count', true)
            ],
            'roles_summary' => [],
            'capabilities' => $this->get_user_capabilities_summary($user_id),
            'security_info' => [
                'is_super_admin' => is_super_admin($user_id),
                'last_ip' => get_user_meta($user_id, 'msd_last_login_ip', true),
                'failed_logins' => $this->get_user_failed_logins($user_id)
            ]
        ];

        foreach ($user_sites as $site) {
            $blog_id = $site['blog_id'];

            switch_to_blog($blog_id);

            $posts = get_posts([
                'author' => $user_id,
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields' => 'ids'
            ]);
            $summary['network_stats']['total_posts'] += count($posts);

            $comments = get_comments([
                'user_id' => $user_id,
                'status' => 'approve',
                'count' => true
            ]);
            $summary['network_stats']['total_comments'] += intval($comments);

            restore_current_blog();

            if (!empty($site['roles'])) {
                $summary['roles_summary'][$site['blogname']] = $site['roles'];
            }
        }

        wp_cache_set($cache_key, $summary, $this->cache_group, 1800);
        return $summary;
    }

    private function get_user_failed_logins($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return 0;
        }

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->base_prefix}msd_security_log
                WHERE event_type = 'failed_login'
                AND description LIKE %s
                AND created_at >= %s",
                '%' . $user->user_login . '%',
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );

        return intval($count);
    }

    public function get_user_statistics() {
        $cache_key = 'user_statistics';
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $total_users = count_users();
        $inactive_30 = count($this->get_inactive_users(30, 1000));
        $inactive_90 = count($this->get_inactive_users(90, 1000));
        $super_admins = count(get_super_admins());

        $statistics = [
            'total_users' => $total_users['total_users'],
            'active_users' => $total_users['total_users'] - $inactive_30,
            'inactive_30_days' => $inactive_30,
            'inactive_90_days' => $inactive_90,
            'super_admins' => $super_admins,
            'users_by_role' => $total_users['avail_roles']
        ];

        wp_cache_set($cache_key, $statistics, $this->cache_group, 3600);
        return $statistics;
    }

    private function log_bulk_action($action, $user_ids, $success_count, $error_count) {
        if (class_exists('WP_MSD_Network_Data')) {
            $network_data = new WP_MSD_Network_Data();
            $network_data->log_activity(
                0,
                'bulk_user_action',
                sprintf(
                    __('Bulk %s performed on %d users: %d successful, %d failed', 'wp-multisite-dashboard'),
                    $action,
                    count($user_ids),
                    $success_count,
                    $error_count
                ),
                'medium'
            );
        }
    }

    private function clear_user_caches($user_ids = []) {
        wp_cache_flush_group($this->cache_group);

        if (!empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                wp_cache_delete("user_activity_{$user_id}", $this->cache_group);
            }
        }
    }

    public function cleanup_inactive_users($days = 180, $dry_run = true) {
        if (!current_user_can('delete_users')) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'wp-multisite-dashboard')];
        }

        $inactive_users = $this->get_inactive_users($days, 1000);
        $super_admins = get_super_admins();
        $candidates_for_deletion = [];

        foreach ($inactive_users as $user) {
            if (!in_array($user['user_login'], $super_admins) && $user['sites_count'] === 0) {
                $candidates_for_deletion[] = $user;
            }
        }

        if ($dry_run) {
            return [
                'success' => true,
                'message' => sprintf(__('%d users would be deleted', 'wp-multisite-dashboard'), count($candidates_for_deletion)),
                'users' => $candidates_for_deletion
            ];
        }

        $deleted_count = 0;
        foreach ($candidates_for_deletion as $user) {
            if (wpmu_delete_user($user['ID'])) {
                $deleted_count++;
            }
        }

        $this->clear_user_caches();

        return [
            'success' => true,
            'message' => sprintf(__('%d inactive users deleted', 'wp-multisite-dashboard'), $deleted_count),
            'deleted_count' => $deleted_count
        ];
    }
}
