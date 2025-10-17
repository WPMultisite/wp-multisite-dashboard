<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 后台任务管理器
 * 处理重操作和定时任务，避免阻塞用户请求
 */
class WP_MSD_Background_Tasks
{
    private static $instance = null;
    private $performance_manager;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->performance_manager = WP_MSD_Performance_Manager::get_instance();
        $this->init_hooks();
    }

    /**
     * 初始化钩子和定时任务
     */
    private function init_hooks()
    {
        // 注册定时任务
        add_action('msd_widget_detection_cron', [$this, 'run_widget_detection']);
        add_action('msd_cache_warmup_cron', [$this, 'warmup_cache']);
        add_action('msd_cleanup_cron', [$this, 'cleanup_old_data']);
        add_action('msd_performance_check_cron', [$this, 'check_performance']);
        
        // 调度任务（如果尚未调度）
        add_action('init', [$this, 'schedule_tasks']);
        
        // 清理任务（插件停用时）
        register_deactivation_hook(WP_MSD_PLUGIN_DIR . 'wp-multisite-dashboard.php', [$this, 'clear_scheduled_tasks']);
    }

    /**
     * 调度所有定时任务
     */
    public function schedule_tasks()
    {
        // 小工具检测 - 每小时
        if (!wp_next_scheduled('msd_widget_detection_cron')) {
            wp_schedule_event(time(), 'hourly', 'msd_widget_detection_cron');
        }

        // 缓存预热 - 每30分钟
        if (!wp_next_scheduled('msd_cache_warmup_cron')) {
            wp_schedule_event(time(), 'msd_30min', 'msd_cache_warmup_cron');
        }

        // 数据清理 - 每天
        if (!wp_next_scheduled('msd_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'msd_cleanup_cron');
        }

        // 性能检查 - 每小时
        if (!wp_next_scheduled('msd_performance_check_cron')) {
            wp_schedule_event(time(), 'hourly', 'msd_performance_check_cron');
        }
    }

    /**
     * 添加自定义定时间隔
     */
    public static function add_cron_intervals($schedules)
    {
        $schedules['msd_30min'] = [
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'wp-multisite-dashboard')
        ];
        
        $schedules['msd_15min'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'wp-multisite-dashboard')
        ];
        
        return $schedules;
    }

    /**
     * 后台小工具检测
     */
    public function run_widget_detection()
    {
        try {
            // 加载仪表板所需的函数
            if (!function_exists('wp_add_dashboard_widget')) {
                require_once ABSPATH . 'wp-admin/includes/dashboard.php';
            }

            $plugin_core = WP_MSD_Plugin_Core::get_instance();
            $detected_widgets = $plugin_core->detect_network_widgets();
            
            if (!empty($detected_widgets)) {
                $this->performance_manager->set_cache(
                    'detected_widgets',
                    $detected_widgets,
                    WP_MSD_Performance_Manager::CACHE_EXTENDED,
                    'msd_widgets'
                );
                update_site_option('msd_last_widget_detection', time());
            }
        } catch (Exception $e) {
            error_log('MSD Background Task: Widget detection failed - ' . $e->getMessage());
        }
    }

    /**
     * 缓存预热 - 预加载常用数据
     */
    public function warmup_cache()
    {
        try {
            $network_data = new WP_MSD_Network_Data();
            
            // 预加载关键数据
            $critical_data = [
                'total_sites' => [$network_data, 'get_total_sites'],
                'total_users' => [$network_data, 'get_total_users'],
                'network_stats' => [$network_data, 'get_network_stats_summary'],
            ];

            foreach ($critical_data as $key => $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback);
                }
            }
        } catch (Exception $e) {
            error_log('MSD Background Task: Cache warmup failed - ' . $e->getMessage());
        }
    }

    /**
     * 清理旧数据
     */
    public function cleanup_old_data()
    {
        global $wpdb;
        
        try {
            // 清理30天前的404记录
            $table_404 = $wpdb->base_prefix . 'msd_404_log';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_404}'") === $table_404) {
                $deleted = $wpdb->query(
                    "DELETE FROM {$table_404} 
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                
                if ($deleted !== false) {
                    error_log("MSD Cleanup: Deleted {$deleted} old 404 records");
                }
            }

            // 清理60天前的活动日志
            $table_activity = $wpdb->base_prefix . 'msd_activity_log';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_activity}'") === $table_activity) {
                $deleted = $wpdb->query(
                    "DELETE FROM {$table_activity} 
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
                );
                
                if ($deleted !== false) {
                    error_log("MSD Cleanup: Deleted {$deleted} old activity records");
                }
            }

            // 清理过期的临时缓存
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE '_site_transient_timeout_msd_%' 
                 AND meta_value < UNIX_TIMESTAMP()"
            );

            // 优化数据库表
            $this->optimize_tables();

        } catch (Exception $e) {
            error_log('MSD Background Task: Cleanup failed - ' . $e->getMessage());
        }
    }

    /**
     * 优化数据库表
     */
    private function optimize_tables()
    {
        global $wpdb;
        
        $tables = [
            $wpdb->base_prefix . 'msd_404_log',
            $wpdb->base_prefix . 'msd_activity_log',
            $wpdb->sitemeta
        ];

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }
    }

    /**
     * 性能检查
     */
    public function check_performance()
    {
        try {
            $stats = $this->performance_manager->get_performance_stats();
            
            // 检查缓存命中率
            if (isset($stats['cache_hit_ratio']) && $stats['cache_hit_ratio'] < 50) {
                error_log('MSD Performance Warning: Low cache hit ratio - ' . $stats['cache_hit_ratio'] . '%');
            }

            // 检查内存使用
            $memory_usage = memory_get_usage(true);
            $memory_limit = $this->get_memory_limit_bytes();
            $usage_percent = ($memory_usage / $memory_limit) * 100;
            
            if ($usage_percent > 80) {
                error_log('MSD Performance Warning: High memory usage - ' . round($usage_percent, 2) . '%');
                
                // 触发内存清理
                $this->performance_manager->cleanup_memory();
            }

            // 保存性能统计
            $this->performance_manager->set_cache(
                'performance_stats',
                $stats,
                WP_MSD_Performance_Manager::CACHE_MEDIUM,
                'msd_performance'
            );

        } catch (Exception $e) {
            error_log('MSD Background Task: Performance check failed - ' . $e->getMessage());
        }
    }

    /**
     * 获取内存限制（字节）
     */
    private function get_memory_limit_bytes()
    {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * 清除所有定时任务
     */
    public function clear_scheduled_tasks()
    {
        $tasks = [
            'msd_widget_detection_cron',
            'msd_cache_warmup_cron',
            'msd_cleanup_cron',
            'msd_performance_check_cron'
        ];

        foreach ($tasks as $task) {
            $timestamp = wp_next_scheduled($task);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $task);
            }
        }
    }

    /**
     * 手动触发缓存预热
     */
    public function manual_cache_warmup()
    {
        $this->warmup_cache();
        return true;
    }

    /**
     * 手动触发数据清理
     */
    public function manual_cleanup()
    {
        $this->cleanup_old_data();
        return true;
    }
}

// 添加自定义定时间隔
add_filter('cron_schedules', ['WP_MSD_Background_Tasks', 'add_cron_intervals']);
