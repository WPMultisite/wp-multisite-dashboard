<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_Error_Log_Manager
{
    private static $instance = null;
    private $cache_key = 'msd_error_log_cache';
    private $cache_duration = 300; // 5 minutes

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get error log file path
     */
    public function get_log_file_path()
    {
        // Check debug.log in wp-content
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_file)) {
            return false;
        }
        
        return $log_file;
    }

    /**
     * Check if error logging is enabled
     */
    public function is_error_logging_enabled()
    {
        return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }

    /**
     * Get error log entries with performance optimization
     * Only reads last N lines instead of entire file
     */
    public function get_error_logs($limit = 100, $filters = [])
    {
        $log_file = $this->get_log_file_path();
        
        if (!$log_file || !is_readable($log_file)) {
            return [
                'success' => false,
                'message' => __('Debug log file not found or not readable.', 'wp-multisite-dashboard'),
                'logs' => []
            ];
        }

        // Check cache first
        $cache_key = $this->cache_key . '_' . md5(json_encode($filters) . $limit);
        $cached = get_site_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        // Get file size
        $file_size = filesize($log_file);
        
        if ($file_size === 0) {
            return [
                'success' => true,
                'message' => __('Log file is empty.', 'wp-multisite-dashboard'),
                'logs' => [],
                'file_size' => 0,
                'total_lines' => 0
            ];
        }

        // Read last N lines efficiently (for large files)
        $lines = $this->read_last_lines($log_file, $limit * 5); // Read more to account for multi-line entries
        
        // Parse log entries
        $parsed_logs = $this->parse_log_lines($lines, $limit, $filters);
        
        // Calculate statistics
        $stats = [
            'total_errors' => 0,
            'fatal_count' => 0,
            'warning_count' => 0,
            'notice_count' => 0,
            'deprecated_count' => 0,
            'parse_count' => 0,
            'other_count' => 0
        ];
        
        foreach ($parsed_logs as $log) {
            $stats['total_errors']++;
            switch ($log['type']) {
                case 'fatal':
                    $stats['fatal_count']++;
                    break;
                case 'warning':
                    $stats['warning_count']++;
                    break;
                case 'notice':
                    $stats['notice_count']++;
                    break;
                case 'deprecated':
                    $stats['deprecated_count']++;
                    break;
                case 'parse':
                    $stats['parse_count']++;
                    break;
                default:
                    $stats['other_count']++;
                    break;
            }
        }
        
        $result = array_merge([
            'success' => true,
            'logs' => $parsed_logs,
            'file_size' => size_format($file_size),
            'file_size_bytes' => $file_size,
            'total_lines' => count($lines),
            'displayed_count' => count($parsed_logs),
            'log_enabled' => $this->is_error_logging_enabled()
        ], $stats);

        // Cache the result
        set_site_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Read last N lines from file efficiently
     * Uses reverse reading to avoid loading entire file into memory
     */
    private function read_last_lines($file, $lines = 100)
    {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return [];
        }

        $line_count = 0;
        $pos = -2;
        $lines_array = [];
        $current_line = '';

        // Seek to end of file
        fseek($handle, 0, SEEK_END);
        $file_size = ftell($handle);

        // Read backwards
        while ($line_count < $lines && $pos >= -$file_size) {
            fseek($handle, $pos, SEEK_END);
            $char = fgetc($handle);
            
            if ($char === "\n") {
                if ($current_line !== '') {
                    $lines_array[] = strrev($current_line);
                    $line_count++;
                    $current_line = '';
                }
            } else {
                $current_line .= $char;
            }
            
            $pos--;
        }

        // Add last line if exists
        if ($current_line !== '') {
            $lines_array[] = strrev($current_line);
        }

        fclose($handle);

        return array_reverse($lines_array);
    }

    /**
     * Parse log lines into structured format
     */
    private function parse_log_lines($lines, $limit, $filters = [])
    {
        $logs = [];
        $current_entry = null;

        foreach ($lines as $line) {
            // Check if this is a new error entry (starts with date)
            if (preg_match('/^\[(\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2}\s+\w+)\]\s+(.+)/', $line, $matches)) {
                // Save previous entry if exists
                if ($current_entry !== null) {
                    if ($this->matches_filters($current_entry, $filters)) {
                        $logs[] = $current_entry;
                        
                        if (count($logs) >= $limit) {
                            break;
                        }
                    }
                }

                // Start new entry
                $current_entry = [
                    'timestamp' => $matches[1],
                    'message' => trim($matches[2]),
                    'type' => $this->detect_error_type($matches[2]),
                    'raw' => $line
                ];
            } else if ($current_entry !== null) {
                // Multi-line error, append to current entry
                $current_entry['message'] .= "\n" . trim($line);
                $current_entry['raw'] .= "\n" . $line;
            }
        }

        // Add last entry
        if ($current_entry !== null && $this->matches_filters($current_entry, $filters)) {
            $logs[] = $current_entry;
        }

        return array_slice($logs, 0, $limit);
    }

    /**
     * Detect error type from message
     */
    private function detect_error_type($message)
    {
        $message_lower = strtolower($message);
        
        if (strpos($message_lower, 'fatal error') !== false) {
            return 'fatal';
        } else if (strpos($message_lower, 'warning') !== false) {
            return 'warning';
        } else if (strpos($message_lower, 'notice') !== false) {
            return 'notice';
        } else if (strpos($message_lower, 'deprecated') !== false) {
            return 'deprecated';
        } else if (strpos($message_lower, 'parse error') !== false) {
            return 'parse';
        } else {
            return 'other';
        }
    }

    /**
     * Check if log entry matches filters
     */
    private function matches_filters($entry, $filters)
    {
        if (empty($filters)) {
            return true;
        }

        // Filter by type
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            if ($entry['type'] !== $filters['type']) {
                return false;
            }
        }

        // Filter by search term
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            if (strpos(strtolower($entry['message']), $search) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear error log file
     */
    public function clear_log_file()
    {
        $log_file = $this->get_log_file_path();
        
        if (!$log_file || !is_writable($log_file)) {
            return [
                'success' => false,
                'message' => __('Log file not found or not writable.', 'wp-multisite-dashboard')
            ];
        }

        // Clear the file
        $result = @file_put_contents($log_file, '');
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to clear log file.', 'wp-multisite-dashboard')
            ];
        }

        // Clear cache
        $this->clear_cache();

        return [
            'success' => true,
            'message' => __('Log file cleared successfully.', 'wp-multisite-dashboard')
        ];
    }

    /**
     * Get error statistics
     */
    public function get_error_stats()
    {
        $log_data = $this->get_error_logs(500); // Get more for accurate stats
        
        if (!$log_data['success']) {
            return [];
        }

        $stats = [
            'total' => count($log_data['logs']),
            'fatal' => 0,
            'warning' => 0,
            'notice' => 0,
            'deprecated' => 0,
            'parse' => 0,
            'other' => 0
        ];

        foreach ($log_data['logs'] as $log) {
            if (isset($stats[$log['type']])) {
                $stats[$log['type']]++;
            }
        }

        return $stats;
    }

    /**
     * Clear cache
     */
    public function clear_cache()
    {
        global $wpdb;
        
        // Delete all related transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like('_site_transient_' . $this->cache_key) . '%'
            )
        );
    }
}
