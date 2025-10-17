<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Error_Handler
{
    private static $instance = null;
    private $log_file;
    private $max_log_size = 1048576; // 1MB
    private $performance_manager;
    private $log_size_cache_key = 'error_log_file_size';
    private $log_stats_cache_key = 'error_log_stats';

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
        
        // 初始化性能管理器
        $this->performance_manager = WP_MSD_Performance_Manager::get_instance();

        // 注册错误处理钩子
        add_action('wp_ajax_msd_get_error_log', [$this, 'get_error_log']);
        add_action('wp_ajax_msd_clear_error_log', [$this, 'clear_error_log']);
    }

    /**
     * 记录错误信息 - 优化版本使用缓存的文件大小
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

        // 使用缓存的文件大小，避免每次都检查
        $cached_size = $this->performance_manager->get_cache(
            $this->log_size_cache_key,
            'msd_error_log'
        );
        
        if ($cached_size === false && file_exists($this->log_file)) {
            $cached_size = filesize($this->log_file);
            $this->performance_manager->set_cache(
                $this->log_size_cache_key,
                $cached_size,
                WP_MSD_Performance_Manager::CACHE_SHORT,
                'msd_error_log'
            );
        }
        
        // 检查是否需要轮转
        if ($cached_size !== false && $cached_size > $this->max_log_size) {
            $this->rotate_log();
            $this->performance_manager->delete_cache($this->log_size_cache_key, 'msd_error_log');
            $cached_size = 0;
        }

        // 写入日志
        $bytes_written = @file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // 更新缓存的大小
        if ($bytes_written !== false && $cached_size !== false) {
            $new_size = $cached_size + $bytes_written;
            $this->performance_manager->set_cache(
                $this->log_size_cache_key,
                $new_size,
                WP_MSD_Performance_Manager::CACHE_SHORT,
                'msd_error_log'
            );
        }
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

        // 使用更高效的方法读取最后N行
        $limit = 50;
        $recent_lines = $this->get_last_lines($this->log_file, $limit);
        $logs = [];

        foreach ($recent_lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $logs[] = $log_entry;
            }
        }

        // 获取总行数（从缓存的统计信息）
        $stats = $this->get_log_stats();
        $total_lines = $stats['total_entries'];

        wp_send_json_success([
            'logs' => array_reverse($logs), // 最新的在前面
            'total_lines' => $total_lines
        ]);
    }

    /**
     * 高效读取文件最后N行
     * 使用系统命令或文件指针，避免加载整个文件
     */
    private function get_last_lines($file, $lines = 50)
    {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        // 尝试使用tail命令（Unix/Linux/Mac）
        if (function_exists('exec') && !$this->is_windows()) {
            $output = [];
            $command = sprintf('tail -n %d %s 2>&1', $lines, escapeshellarg($file));
            @exec($command, $output, $return_var);
            
            if ($return_var === 0 && !empty($output)) {
                return $output;
            }
        }

        // 备用方案：使用PHP读取
        return $this->read_last_lines_php($file, $lines);
    }

    /**
     * 使用PHP读取最后N行（备用方案）
     */
    private function read_last_lines_php($file, $lines = 50)
    {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return [];
        }

        $line_buffer = [];
        $buffer_size = $lines * 2; // 缓冲区大小

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false && trim($line) !== '') {
                $line_buffer[] = trim($line);
                
                // 保持缓冲区大小
                if (count($line_buffer) > $buffer_size) {
                    array_shift($line_buffer);
                }
            }
        }

        fclose($handle);
        
        // 返回最后N行
        return array_slice($line_buffer, -$lines);
    }

    /**
     * 检查是否为Windows系统
     */
    private function is_windows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
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

        // 清除相关缓存
        $this->performance_manager->delete_cache($this->log_size_cache_key, 'msd_error_log');
        $this->performance_manager->delete_cache($this->log_stats_cache_key, 'msd_error_log');

        wp_send_json_success([
            'message' => __('Error log cleared successfully', 'wp-multisite-dashboard')
        ]);
    }

    /**
     * 获取日志统计信息 - 优化版本使用缓存
     */
    public function get_log_stats()
    {
        // 尝试从缓存获取
        $cached_stats = $this->performance_manager->get_cache(
            $this->log_stats_cache_key,
            'msd_error_log'
        );
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // 缓存未命中，计算统计信息
        if (!file_exists($this->log_file)) {
            $stats = [
                'total_entries' => 0,
                'file_size' => 0,
                'last_modified' => null
            ];
        } else {
            // 使用更高效的方法计算行数
            $line_count = 0;
            $handle = @fopen($this->log_file, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    $line = fgets($handle);
                    if ($line !== false && trim($line) !== '') {
                        $line_count++;
                    }
                }
                fclose($handle);
            }
            
            $file_size = filesize($this->log_file);
            $last_modified = filemtime($this->log_file);

            $stats = [
                'total_entries' => $line_count,
                'file_size' => size_format($file_size),
                'last_modified' => $last_modified ? date('Y-m-d H:i:s', $last_modified) : null
            ];
        }
        
        // 缓存5分钟
        $this->performance_manager->set_cache(
            $this->log_stats_cache_key,
            $stats,
            WP_MSD_Performance_Manager::CACHE_SHORT,
            'msd_error_log'
        );
        
        return $stats;
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
