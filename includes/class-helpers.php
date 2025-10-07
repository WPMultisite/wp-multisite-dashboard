<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Helpers {

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
            $title_attr = esc_attr__('Refresh', 'wp-multisite-dashboard');
            echo '<button class="msd-refresh-btn" title="' . $title_attr . '" data-widget="' . esc_attr($widget_id) . '">↻</button>';
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
            $label = esc_html__('Try Again', 'wp-multisite-dashboard');
            echo '<button class="button msd-retry-btn" onclick="' . esc_attr($retry_action) . '">' . $label . '</button>';
        }

        echo '</div>';
    }

    public static function get_priority_badge($priority) {
        $badges = [
            'low' => [__('Low Priority', 'wp-multisite-dashboard'), 'msd-priority-low'],
            'medium' => [__('Medium Priority', 'wp-multisite-dashboard'), 'msd-priority-medium'],
            'high' => [__('High Priority', 'wp-multisite-dashboard'), 'msd-priority-high']
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
            'active' => [__('Active', 'wp-multisite-dashboard'), 'msd-status-good'],
            'inactive' => [__('Inactive', 'wp-multisite-dashboard'), 'msd-status-warning'],
            'critical' => [__('Critical', 'wp-multisite-dashboard'), 'msd-status-critical'],
            'warning' => [__('Warning', 'wp-multisite-dashboard'), 'msd-status-warning'],
            'good' => [__('Good', 'wp-multisite-dashboard'), 'msd-status-good'],
            'neutral' => [__('Neutral', 'wp-multisite-dashboard'), 'msd-status-neutral']
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

    public static function format_number($num) {
        if ($num >= 1000000) {
            return number_format($num / 1000000, 1) . 'M';
        } elseif ($num >= 1000) {
            return number_format($num / 1000, 1) . 'K';
        }
        return number_format($num);
    }

    public static function truncate_text($text, $length = 50, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }

    public static function escape_js($text) {
        return esc_js($text);
    }

    public static function get_default_avatar_url() {
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIyMCIgZmlsbD0iI2Y2ZjdmNyIgc3Ryb2tlPSIjZGRkIi8+PGNpcmNsZSBjeD0iMjAiIGN5PSIxNSIgcj0iNiIgZmlsbD0iIzk5OSIvPjxlbGxpcHNlIGN4PSIyMCIgY3k9IjMzIiByeD0iMTAiIHJ5PSI3IiBmaWxsPSIjOTk5Ii8+PC9zdmc+';
    }

    public static function get_default_favicon_url() {
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDMyIDMyIj48cmVjdCB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIGZpbGw9IiNmMGYwZjAiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TPC90ZXh0Pjwvc3ZnPg==';
    }

    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function is_valid_email($email) {
        return is_email($email);
    }

    public static function get_storage_status_class($percentage) {
        if ($percentage > 90) {
            return 'critical';
        } elseif ($percentage > 75) {
            return 'warning';
        }
        return 'good';
    }

    public static function get_user_status_class($status) {
        $status_map = [
            'active' => 'good',
            'recent' => 'good',
            'inactive' => 'warning',
            'very_inactive' => 'critical',
            'never_logged_in' => 'neutral'
        ];
        return $status_map[$status] ?? 'neutral';
    }

    public static function get_user_status_label($status) {
        $status_labels = [
            'active' => __('Active', 'wp-multisite-dashboard'),
            'recent' => __('Recent', 'wp-multisite-dashboard'),
            'inactive' => __('Inactive', 'wp-multisite-dashboard'),
            'very_inactive' => __('Very Inactive', 'wp-multisite-dashboard'),
            'never_logged_in' => __('Never Logged In', 'wp-multisite-dashboard')
        ];
        return $status_labels[$status] ?? __('Unknown', 'wp-multisite-dashboard');
    }

    public static function decode_html_entities($text) {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    public static function strip_tags_and_limit($text, $limit = 100) {
        $text = strip_tags($text);
        return self::truncate_text($text, $limit);
    }
}
