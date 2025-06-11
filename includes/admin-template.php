<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Admin_Template {

    public static function render_dashboard_notice($message, $type = 'info', $dismissible = true) {
        $classes = ['notice', "notice-{$type}"];
        
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        $class_string = implode(' ', $classes);

        echo "<div class=\"{$class_string}\">";
        echo "<p>" . esc_html($message) . "</p>";
        echo "</div>";
    }

    public static function render_widget_header($title, $widget_id = null, $show_refresh = true) {
        echo '<div class="msd-widget-header">';
        
        if ($show_refresh && $widget_id) {
            echo '<button class="msd-refresh-btn" title="Refresh" data-widget="' . esc_attr($widget_id) . '">↻</button>';
        }
        
        if ($title) {
            echo '<h3 class="msd-widget-title">' . esc_html($title) . '</h3>';
        }
        
        echo '</div>';
    }

    public static function render_loading_state($message = null) {
        $message = $message ?: __('Loading...', 'wp-multisite-dashboard');
        
        echo '<div class="msd-loading">';
        echo '<span class="msd-spinner"></span>';
        echo esc_html($message);
        echo '</div>';
    }

    public static function render_empty_state($message, $action_text = null, $action_url = null) {
        echo '<div class="msd-empty-state">';
        echo '<p>' . esc_html($message) . '</p>';
        
        if ($action_text && $action_url) {
            echo '<a href="' . esc_url($action_url) . '" class="button button-primary">' . esc_html($action_text) . '</a>';
        }
        
        echo '</div>';
    }

    public static function render_error_state($message, $retry_action = null) {
        echo '<div class="msd-error-state">';
        echo '<p>' . esc_html($message) . '</p>';
        
        if ($retry_action) {
            echo '<button class="button msd-retry-btn" onclick="' . esc_attr($retry_action) . '">Try Again</button>';
        }
        
        echo '</div>';
    }

    public static function get_priority_badge($priority) {
        $badges = [
            'low' => ['Low Priority', 'msd-priority-low'],
            'medium' => ['Medium Priority', 'msd-priority-medium'],
            'high' => ['High Priority', 'msd-priority-high']
        ];

        if (!isset($badges[$priority])) {
            $priority = 'medium';
        }

        return sprintf(
            '<span class="msd-priority-badge %s">%s</span>',
            esc_attr($badges[$priority][1]),
            esc_html($badges[$priority][0])
        );
    }

    public static function get_status_badge($status, $label = null) {
        $badges = [
            'active' => ['Active', 'msd-status-good'],
            'inactive' => ['Inactive', 'msd-status-warning'],
            'critical' => ['Critical', 'msd-status-critical'],
            'warning' => ['Warning', 'msd-status-warning'],
            'good' => ['Good', 'msd-status-good'],
            'neutral' => ['Neutral', 'msd-status-neutral']
        ];

        if (!isset($badges[$status])) {
            $status = 'neutral';
        }

        $display_label = $label ?: $badges[$status][0];

        return sprintf(
            '<span class="msd-status-badge %s">%s</span>',
            esc_attr($badges[$status][1]),
            esc_html($display_label)
        );
    }

    public static function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public static function format_time_ago($timestamp) {
        if (empty($timestamp)) {
            return __('Never', 'wp-multisite-dashboard');
        }

        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        if (!$timestamp) {
            return __('Unknown', 'wp-multisite-dashboard');
        }

        return human_time_diff($timestamp) . ' ago';
    }

    public static function render_progress_bar($percentage, $label = null, $status = 'good') {
        $percentage = max(0, min(100, intval($percentage)));
        $status_class = "msd-progress-{$status}";

        echo '<div class="msd-progress-container">';
        
        if ($label) {
            echo '<div class="msd-progress-label">' . esc_html($label) . '</div>';
        }
        
        echo '<div class="msd-progress-bar">';
        echo '<div class="msd-progress-fill ' . esc_attr($status_class) . '" style="width: ' . $percentage . '%"></div>';
        echo '</div>';
        
        echo '<div class="msd-progress-text">' . $percentage . '%</div>';
        echo '</div>';
    }

    public static function render_data_table($headers, $rows, $empty_message = null) {
        if (empty($rows)) {
            if ($empty_message) {
                self::render_empty_state($empty_message);
            }
            return;
        }

        echo '<div class="msd-data-table-wrapper">';
        echo '<table class="msd-data-table">';
        
        if (!empty($headers)) {
            echo '<thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . esc_html($header) . '</th>';
            }
            echo '</tr></thead>';
        }
        
        echo '<tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $cell . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        
        echo '</table>';
        echo '</div>';
    }

    public static function render_action_buttons($actions) {
        if (empty($actions)) {
            return;
        }

        echo '<div class="msd-action-buttons">';
        
        foreach ($actions as $action) {
            $class = 'button ' . ($action['primary'] ?? false ? 'button-primary' : 'button-secondary');
            $attributes = '';
            
            if (!empty($action['attributes'])) {
                foreach ($action['attributes'] as $attr => $value) {
                    $attributes .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
                }
            }

            if (!empty($action['url'])) {
                echo '<a href="' . esc_url($action['url']) . '" class="' . esc_attr($class) . '"' . $attributes . '>';
                echo esc_html($action['text']);
                echo '</a>';
            } else {
                echo '<button type="button" class="' . esc_attr($class) . '"' . $attributes . '>';
                echo esc_html($action['text']);
                echo '</button>';
            }
        }
        
        echo '</div>';
    }

    public static function sanitize_widget_data($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize_widget_data'], $data);
        }

        if (is_string($data)) {
            return sanitize_text_field($data);
        }

        return $data;
    }

    public static function validate_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }

    public static function can_manage_network() {
        return current_user_can('manage_network');
    }

    public static function get_current_screen_id() {
        $screen = get_current_screen();
        return $screen ? $screen->id : '';
    }

    public static function is_network_admin_page($page_slug = null) {
        if (!is_network_admin()) {
            return false;
        }

        if ($page_slug) {
            return isset($_GET['page']) && $_GET['page'] === $page_slug;
        }

        return true;
    }
}