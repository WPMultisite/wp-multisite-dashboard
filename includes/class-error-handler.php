<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Error_Handler
{
    private static $instance = null;
    private $log_file;
    private $max_log_size = 1048576; // 1MB

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/msd-error.log';

        // 注册错误处理钩子
        add_action('wp_ajax_msd_get_error_log', [$this, 'get_error_log']);
        add_action('wp_ajax_msd_clear_error_log', [$this, 'clear_error_log']);
    }

    /**
     * 记录错误信息
     */
    public function log_error($message, $context = [], $level = 'error')
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? get_userdata($user_id) : null;
        $username = $user_info ? $user_info->user_login : 'guest';

        $log_entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'user' => $username,
            'context' => $context,
            'memory_usage' => size_format(memory_get_usage(true)),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];

        $log_line = json_encode($log_entry) . "\n";

        // 检查日志文件大小
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotate_log();
        }

        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录性能信息
     */
    public function log_performance($operation, $duration, $memory_usage = null)
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $context = [
            'operation' => $operation,
            'duration' => $duration . 's',
            'memory_usage' => $memory_usage ?: size_format(memory_get_usage(true)),
        ];

        $this->log_error("Performance: {$operation} completed in {$duration}s", $context, 'performance');
    }

    /**
     * 记录缓存操作
     */
    public function log_cache_operation($operation, $key, $hit = null)
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $context = [
            'operation' => $operation,
            'cache_key' => $key,
        ];

        if ($hit !== null) {
            $context['cache_hit'] = $hit ? 'yes' : 'no';
        }

        $this->log_error("Cache: {$operation} for key {$key}", $context, 'cache');
    }

    /**
     * 轮转日志文件
     */
    private function rotate_log()
    {
        if (!file_exists($this->log_file)) {
            return;
        }

        $backup_file = $this->log_file . '.old';

        if (file_exists($backup_file)) {
            unlink($backup_file);
        }

        rename($this->log_file, $backup_file);
    }

    /**
     * 获取错误日志（AJAX处理器）
     */
    public function get_error_log()
    {
        // Clean any output buffer before sending JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'msd_ajax_nonce')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return;
        }

        if (!file_exists($this->log_file)) {
            wp_send_json_success([
                'logs' => [],
                'message' => __('No error log found', 'wp-multisite-dashboard')
            ]);
            return;
        }

        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        // 获取最后50行
        $recent_lines = array_slice($lines, -50);

        foreach ($recent_lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $logs[] = $log_entry;
            }
        }

        wp_send_json_success([
            'logs' => array_reverse($logs), // 最新的在前面
            'total_lines' => count($lines)
        ]);
    }

    /**
     * 清除错误日志（AJAX处理器）
     */
    public function clear_error_log()
    {
        // Clean any output buffer before sending JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-multisite-dashboard'));
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'msd_ajax_nonce')) {
            wp_send_json_error(__('Invalid nonce', 'wp-multisite-dashboard'));
            return;
        }

        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }

        $backup_file = $this->log_file . '.old';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }

        wp_send_json_success([
            'message' => __('Error log cleared successfully', 'wp-multisite-dashboard')
        ]);
    }

    /**
     * 获取日志统计信息
     */
    public function get_log_stats()
    {
        if (!file_exists($this->log_file)) {
            return [
                'total_entries' => 0,
                'file_size' => 0,
                'last_modified' => null
            ];
        }

        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $file_size = filesize($this->log_file);
        $last_modified = filemtime($this->log_file);

        return [
            'total_entries' => count($lines),
            'file_size' => size_format($file_size),
            'last_modified' => $last_modified ? date('Y-m-d H:i:s', $last_modified) : null
        ];
    }

    /**
     * 安全地执行操作并记录错误
     */
    public function safe_execute($callback, $operation_name, $default_return = null)
    {
        $start_time = microtime(true);

        try {
            $result = call_user_func($callback);

            $duration = microtime(true) - $start_time;
            if ($duration > 1.0) { // 记录超过1秒的操作
                $this->log_performance($operation_name, round($duration, 3));
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error(
                "Operation failed: {$operation_name}",
                [
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return $default_return;
        }
    }
}
