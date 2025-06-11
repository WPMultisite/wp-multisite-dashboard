<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Todo_Manager {

    private $wpdb;
    private $table_name;
    private $cache_group = 'msd_todos';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->base_prefix . 'msd_todo_list';
    }

    public function create_todo_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item varchar(500) NOT NULL,
            completed tinyint(1) DEFAULT 0,
            priority enum('low','medium','high') DEFAULT 'medium',
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            due_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY completed (completed),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function get_todo_items($limit = 50, $user_id = null, $completed = null) {
        $cache_key = "todo_items_{$limit}_" . ($user_id ?? 'all') . "_" . ($completed ?? 'all');
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $where_clauses = ['1=1'];
        $where_values = [];

        if ($user_id !== null) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $user_id;
        }

        if ($completed !== null) {
            $where_clauses[] = 'completed = %d';
            $where_values[] = $completed ? 1 : 0;
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = "SELECT * FROM {$this->table_name} 
                WHERE {$where_sql} 
                ORDER BY completed ASC, priority DESC, created_at DESC 
                LIMIT %d";

        $where_values[] = $limit;

        $prepared_sql = $this->wpdb->prepare($sql, ...$where_values);
        $results = $this->wpdb->get_results($prepared_sql, ARRAY_A);

        $todos = [];
        foreach ($results as $row) {
            $user_data = get_userdata($row['user_id']);
            $todos[] = [
                'id' => intval($row['id']),
                'item' => $row['item'],
                'completed' => (bool)$row['completed'],
                'priority' => $row['priority'],
                'user_id' => intval($row['user_id']),
                'user_name' => $user_data ? $user_data->display_name : 'Unknown User',
                'user_avatar' => $user_data ? get_avatar_url($user_data->ID, 32) : '',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'due_date' => $row['due_date'],
                'created_human' => human_time_diff(strtotime($row['created_at'])) . ' ago',
                'due_human' => $row['due_date'] ? human_time_diff(strtotime($row['due_date'])) : null,
                'is_overdue' => $row['due_date'] && strtotime($row['due_date']) < current_time('timestamp') && !$row['completed'],
                'priority_label' => $this->get_priority_label($row['priority']),
                'priority_class' => $this->get_priority_class($row['priority'])
            ];
        }

        wp_cache_set($cache_key, $todos, $this->cache_group, 1800);
        return $todos;
    }

    public function add_todo_item($item, $priority = 'medium', $user_id = null, $due_date = null) {
        if (empty($item)) {
            return false;
        }

        $user_id = $user_id ?: get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        $data = [
            'item' => sanitize_text_field($item),
            'priority' => in_array($priority, ['low', 'medium', 'high']) ? $priority : 'medium',
            'user_id' => $user_id,
            'created_at' => current_time('mysql'),
            'due_date' => $due_date ? date('Y-m-d H:i:s', strtotime($due_date)) : null
        ];

        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            ['%s', '%s', '%d', '%s', '%s']
        );

        if ($result !== false) {
            $this->clear_cache();
            
            if (class_exists('WP_MSD_Network_Data')) {
                $network_data = new WP_MSD_Network_Data();
                $network_data->log_activity(
                    0,
                    'todo_added',
                    sprintf('Todo item added: %s', substr($item, 0, 50)),
                    'low',
                    $user_id
                );
            }
        }

        return $result !== false;
    }

    public function delete_todo_item($item_id, $user_id = null) {
        $item_id = intval($item_id);
        
        if (!$item_id) {
            return false;
        }

        $where = ['id' => $item_id];
        $where_format = ['%d'];

        if ($user_id) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        } elseif (!current_user_can('manage_network')) {
            $where['user_id'] = get_current_user_id();
            $where_format[] = '%d';
        }

        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT item FROM {$this->table_name} WHERE id = %d",
                $item_id
            )
        );

        $result = $this->wpdb->delete($this->table_name, $where, $where_format);

        if ($result !== false && $item) {
            $this->clear_cache();
            
            if (class_exists('WP_MSD_Network_Data')) {
                $network_data = new WP_MSD_Network_Data();
                $network_data->log_activity(
                    0,
                    'todo_deleted',
                    sprintf('Todo item deleted: %s', substr($item->item, 0, 50)),
                    'low'
                );
            }
        }

        return $result !== false;
    }

    public function toggle_todo_item($item_id, $completed = null, $user_id = null) {
        $item_id = intval($item_id);
        
        if (!$item_id) {
            return false;
        }

        $where_clause = 'id = %d';
        $where_values = [$item_id];

        if ($user_id) {
            $where_clause .= ' AND user_id = %d';
            $where_values[] = $user_id;
        } elseif (!current_user_can('manage_network')) {
            $where_clause .= ' AND user_id = %d';
            $where_values[] = get_current_user_id();
        }

        if ($completed === null) {
            $current_item = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT completed FROM {$this->table_name} WHERE {$where_clause}",
                    ...$where_values
                )
            );
            
            if (!$current_item) {
                return false;
            }
            
            $completed = !$current_item->completed;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            [
                'completed' => $completed ? 1 : 0,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $item_id],
            ['%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->clear_cache();
        }

        return $result !== false;
    }

    public function update_todo_item($item_id, $data, $user_id = null) {
        $item_id = intval($item_id);
        
        if (!$item_id) {
            return false;
        }

        $allowed_fields = ['item', 'priority', 'due_date', 'completed'];
        $update_data = [];
        $update_format = [];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed_fields)) {
                continue;
            }

            switch ($field) {
                case 'item':
                    $update_data['item'] = sanitize_text_field($value);
                    $update_format[] = '%s';
                    break;
                case 'priority':
                    if (in_array($value, ['low', 'medium', 'high'])) {
                        $update_data['priority'] = $value;
                        $update_format[] = '%s';
                    }
                    break;
                case 'due_date':
                    $update_data['due_date'] = $value ? date('Y-m-d H:i:s', strtotime($value)) : null;
                    $update_format[] = '%s';
                    break;
                case 'completed':
                    $update_data['completed'] = $value ? 1 : 0;
                    $update_format[] = '%d';
                    break;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';

        $where = ['id' => $item_id];
        $where_format = ['%d'];

        if ($user_id) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        } elseif (!current_user_can('manage_network')) {
            $where['user_id'] = get_current_user_id();
            $where_format[] = '%d';
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            $where,
            $update_format,
            $where_format
        );

        if ($result !== false) {
            $this->clear_cache();
        }

        return $result !== false;
    }

    public function get_todo_statistics() {
        $cache_key = 'todo_statistics';
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'overdue' => 0,
            'by_priority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ],
            'by_user' => []
        ];

        $total_result = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(completed) as completed,
                SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN due_date < NOW() AND completed = 0 THEN 1 ELSE 0 END) as overdue
             FROM {$this->table_name}"
        );

        if ($total_result) {
            $stats['total'] = intval($total_result->total);
            $stats['completed'] = intval($total_result->completed);
            $stats['pending'] = intval($total_result->pending);
            $stats['overdue'] = intval($total_result->overdue);
        }

        $priority_results = $this->wpdb->get_results(
            "SELECT priority, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE completed = 0 
             GROUP BY priority"
        );

        foreach ($priority_results as $priority_result) {
            if (isset($stats['by_priority'][$priority_result->priority])) {
                $stats['by_priority'][$priority_result->priority] = intval($priority_result->count);
            }
        }

        $user_results = $this->wpdb->get_results(
            "SELECT user_id, COUNT(*) as total, SUM(completed) as completed 
             FROM {$this->table_name} 
             GROUP BY user_id 
             ORDER BY total DESC 
             LIMIT 10"
        );

        foreach ($user_results as $user_result) {
            $user_data = get_userdata($user_result->user_id);
            $stats['by_user'][] = [
                'user_id' => intval($user_result->user_id),
                'user_name' => $user_data ? $user_data->display_name : 'Unknown User',
                'total' => intval($user_result->total),
                'completed' => intval($user_result->completed),
                'pending' => intval($user_result->total) - intval($user_result->completed)
            ];
        }

        wp_cache_set($cache_key, $stats, $this->cache_group, 3600);
        return $stats;
    }

    public function get_user_todos($user_id, $limit = 20) {
        return $this->get_todo_items($limit, $user_id);
    }

    public function get_overdue_todos($limit = 20) {
        $cache_key = "overdue_todos_{$limit}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->table_name} 
                WHERE due_date < NOW() 
                AND completed = 0 
                ORDER BY due_date ASC 
                LIMIT %d";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit),
            ARRAY_A
        );

        $todos = [];
        foreach ($results as $row) {
            $user_data = get_userdata($row['user_id']);
            $todos[] = [
                'id' => intval($row['id']),
                'item' => $row['item'],
                'priority' => $row['priority'],
                'user_id' => intval($row['user_id']),
                'user_name' => $user_data ? $user_data->display_name : 'Unknown User',
                'due_date' => $row['due_date'],
                'due_human' => human_time_diff(strtotime($row['due_date'])) . ' overdue',
                'created_at' => $row['created_at'],
                'priority_label' => $this->get_priority_label($row['priority']),
                'priority_class' => $this->get_priority_class($row['priority'])
            ];
        }

        wp_cache_set($cache_key, $todos, $this->cache_group, 1800);
        return $todos;
    }

    public function bulk_delete_completed($user_id = null) {
        $where_clause = 'completed = 1';
        $where_values = [];

        if ($user_id) {
            $where_clause .= ' AND user_id = %d';
            $where_values[] = $user_id;
        } elseif (!current_user_can('manage_network')) {
            $where_clause .= ' AND user_id = %d';
            $where_values[] = get_current_user_id();
        }

        if (!empty($where_values)) {
            $sql = "DELETE FROM {$this->table_name} WHERE {$where_clause}";
            $result = $this->wpdb->query($this->wpdb->prepare($sql, ...$where_values));
        } else {
            $result = $this->wpdb->delete($this->table_name, ['completed' => 1], ['%d']);
        }

        if ($result !== false) {
            $this->clear_cache();
            
            if (class_exists('WP_MSD_Network_Data')) {
                $network_data = new WP_MSD_Network_Data();
                $network_data->log_activity(
                    0,
                    'todo_bulk_delete',
                    sprintf('Bulk deleted %d completed todo items', $result),
                    'medium'
                );
            }
        }

        return $result !== false;
    }

    private function get_priority_label($priority) {
        $labels = [
            'low' => __('Low', 'wp-multisite-dashboard'),
            'medium' => __('Medium', 'wp-multisite-dashboard'),
            'high' => __('High', 'wp-multisite-dashboard')
        ];
        return $labels[$priority] ?? $labels['medium'];
    }

    private function get_priority_class($priority) {
        $classes = [
            'low' => 'msd-priority-low',
            'medium' => 'msd-priority-medium',
            'high' => 'msd-priority-high'
        ];
        return $classes[$priority] ?? $classes['medium'];
    }

    public function clear_cache() {
        wp_cache_flush_group($this->cache_group);
        return true;
    }

    public function cleanup_old_todos($days = 90) {
        if (!current_user_can('manage_network')) {
            return false;
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'completed' => 1,
                'updated_at' => $cutoff_date
            ],
            ['%d', '%s']
        );

        if ($result !== false) {
            $this->clear_cache();
            
            if (class_exists('WP_MSD_Network_Data')) {
                $network_data = new WP_MSD_Network_Data();
                $network_data->log_activity(
                    0,
                    'todo_cleanup',
                    sprintf('Cleaned up %d old completed todo items', $result),
                    'medium'
                );
            }
        }

        return $result !== false;
    }
}