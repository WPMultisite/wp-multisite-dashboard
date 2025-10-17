<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Performance Manager - 高性能缓存和优化管理器
 * 实现分层缓存策略，数据库查询优化，内存使用优化
 */
class WP_MSD_Performance_Manager
{
    private static $instance = null;
    private $cache_layers = [];
    private $query_cache = [];
    private $memory_limit;
    private $performance_stats = [];
    
    // 缓存层级定义
    const CACHE_LAYER_MEMORY = 'memory';      // 内存缓存 (最快，容量小)
    const CACHE_LAYER_OBJECT = 'object';      // 对象缓存 (快，中等容量)
    const CACHE_LAYER_TRANSIENT = 'transient'; // 数据库缓存 (慢，大容量)
    
    // 缓存时间常量
    const CACHE_SHORT = 300;    // 5分钟
    const CACHE_MEDIUM = 1800;  // 30分钟
    const CACHE_LONG = 3600;    // 1小时
    const CACHE_EXTENDED = 86400; // 24小时

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->memory_limit = $this->get_memory_limit_bytes();
        $this->init_cache_layers();
        $this->init_performance_monitoring();
        
        // 注册性能优化钩子
        add_action('init', [$this, 'optimize_queries'], 1);
        add_action('wp_loaded', [$this, 'preload_critical_data'], 5);
        add_action('shutdown', [$this, 'cleanup_memory'], 999);
    }

    /**
     * 初始化缓存层
     */
    private function init_cache_layers()
    {
        // 内存缓存层 (PHP变量)
        $this->cache_layers[self::CACHE_LAYER_MEMORY] = [];
        
        // 检查对象缓存是否可用
        $this->cache_layers[self::CACHE_LAYER_OBJECT] = wp_using_ext_object_cache();
        
        // 数据库缓存层总是可用
        $this->cache_layers[self::CACHE_LAYER_TRANSIENT] = true;
    }

    /**
     * 初始化性能监控
     */
    private function init_performance_monitoring()
    {
        $this->performance_stats = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'query_count' => 0,
            'memory_usage' => 0,
            'execution_time' => microtime(true)
        ];
    }

    /**
     * 分层缓存获取
     */
    public function get_cache($key, $group = 'msd_default')
    {
        $cache_key = $this->generate_cache_key($key, $group);
        
        // 1. 尝试内存缓存
        if (isset($this->cache_layers[self::CACHE_LAYER_MEMORY][$cache_key])) {
            $cache_data = $this->cache_layers[self::CACHE_LAYER_MEMORY][$cache_key];
            // 检查是否过期
            if (!isset($cache_data['expires']) || $cache_data['expires'] > time()) {
                $this->performance_stats['cache_hits']++;
                return $cache_data['data'];
            } else {
                // 清理过期缓存
                unset($this->cache_layers[self::CACHE_LAYER_MEMORY][$cache_key]);
            }
        }
        
        // 2. 尝试对象缓存
        if ($this->cache_layers[self::CACHE_LAYER_OBJECT]) {
            $data = wp_cache_get($cache_key, $group);
            if ($data !== false) {
                // 回填到内存缓存
                $this->set_memory_cache($cache_key, $data, self::CACHE_SHORT);
                $this->performance_stats['cache_hits']++;
                return $data;
            }
        }
        
        // 3. 尝试数据库缓存
        $data = get_site_transient($cache_key);
        if ($data !== false) {
            // 回填到上层缓存
            if ($this->cache_layers[self::CACHE_LAYER_OBJECT]) {
                wp_cache_set($cache_key, $data, $group, self::CACHE_MEDIUM);
            }
            $this->set_memory_cache($cache_key, $data, self::CACHE_SHORT);
            $this->performance_stats['cache_hits']++;
            return $data;
        }
        
        $this->performance_stats['cache_misses']++;
        return false;
    }

    /**
     * 分层缓存设置
     */
    public function set_cache($key, $data, $expiration = self::CACHE_MEDIUM, $group = 'msd_default')
    {
        $cache_key = $this->generate_cache_key($key, $group);
        
        // 检查内存使用情况
        if ($this->is_memory_available()) {
            $this->set_memory_cache($cache_key, $data, min($expiration, self::CACHE_SHORT));
        }
        
        // 设置对象缓存
        if ($this->cache_layers[self::CACHE_LAYER_OBJECT]) {
            wp_cache_set($cache_key, $data, $group, $expiration);
        }
        
        // 设置数据库缓存
        set_site_transient($cache_key, $data, $expiration);
        
        return true;
    }

    /**
     * 删除缓存
     */
    public function delete_cache($key, $group = 'msd_default')
    {
        $cache_key = $this->generate_cache_key($key, $group);
        
        // 删除内存缓存
        unset($this->cache_layers[self::CACHE_LAYER_MEMORY][$cache_key]);
        
        // 删除对象缓存
        if ($this->cache_layers[self::CACHE_LAYER_OBJECT]) {
            wp_cache_delete($cache_key, $group);
        }
        
        // 删除数据库缓存
        delete_site_transient($cache_key);
        
        return true;
    }

    /**
     * 批量预加载关键数据
     */
    public function preload_critical_data()
    {
        if (!current_user_can('manage_network')) {
            return;
        }

        $critical_keys = [
            'network_overview_basic',
            'total_sites_count',
            'total_users_count',
            'system_widgets_list'
        ];

        foreach ($critical_keys as $key) {
            // 异步预加载，不阻塞页面
            $this->async_load_data($key);
        }
    }

    /**
     * 异步数据加载
     */
    private function async_load_data($key)
    {
        // 检查是否已缓存
        if ($this->get_cache($key) !== false) {
            return;
        }

        // 使用WordPress的wp_schedule_single_event进行异步处理
        if (!wp_next_scheduled('msd_async_load_data', [$key])) {
            wp_schedule_single_event(time() + 1, 'msd_async_load_data', [$key]);
        }
    }

    /**
     * 优化数据库查询
     */
    public function optimize_queries()
    {
        // 启用查询缓存
        add_filter('query', [$this, 'cache_database_query'], 10, 1);
        
        // 优化用户查询
        add_action('pre_get_users', [$this, 'optimize_user_queries']);
        
        // 优化站点查询
        add_filter('pre_get_sites', [$this, 'optimize_site_queries']);
    }

    /**
     * 缓存数据库查询
     */
    public function cache_database_query($query)
    {
        // 只缓存SELECT查询
        if (stripos(trim($query), 'SELECT') !== 0) {
            return $query;
        }

        $query_hash = md5($query);
        
        // 检查查询缓存
        if (isset($this->query_cache[$query_hash])) {
            $this->performance_stats['cache_hits']++;
            return $this->query_cache[$query_hash];
        }

        // 限制查询缓存大小
        if (count($this->query_cache) > 100) {
            $this->query_cache = array_slice($this->query_cache, -50, null, true);
        }

        $this->query_cache[$query_hash] = $query;
        $this->performance_stats['query_count']++;
        
        return $query;
    }

    /**
     * 优化用户查询
     */
    public function optimize_user_queries($query)
    {
        // 限制用户查询数量
        if (!isset($query->query_vars['number']) || $query->query_vars['number'] > 100) {
            $query->query_vars['number'] = 100;
        }

        // 只查询必要字段
        if (!isset($query->query_vars['fields'])) {
            $query->query_vars['fields'] = ['ID', 'user_login', 'user_email', 'display_name'];
        }
    }

    /**
     * 优化站点查询
     */
    public function optimize_site_queries($query)
    {
        // 检查是否为 WP_Site_Query 对象
        if (!($query instanceof WP_Site_Query)) {
            return $query;
        }

        // 获取查询变量
        $query_vars = $query->query_vars;
        
        // 限制站点查询数量
        if (!isset($query_vars['number']) || $query_vars['number'] > 100 || $query_vars['number'] === '') {
            $query->query_vars['number'] = 100;
        }

        return $query;
    }

    /**
     * 获取性能统计
     */
    public function get_performance_stats()
    {
        $this->performance_stats['memory_usage'] = memory_get_usage(true);
        $this->performance_stats['execution_time'] = microtime(true) - $this->performance_stats['execution_time'];
        $this->performance_stats['cache_hit_ratio'] = $this->calculate_cache_hit_ratio();
        
        return $this->performance_stats;
    }

    /**
     * 计算缓存命中率
     */
    private function calculate_cache_hit_ratio()
    {
        $total = $this->performance_stats['cache_hits'] + $this->performance_stats['cache_misses'];
        return $total > 0 ? round(($this->performance_stats['cache_hits'] / $total) * 100, 2) : 0;
    }

    /**
     * 内存管理
     */
    private function set_memory_cache($key, $data, $expiration)
    {
        if (!$this->is_memory_available()) {
            return false;
        }

        $this->cache_layers[self::CACHE_LAYER_MEMORY][$key] = [
            'data' => $data,
            'expires' => time() + $expiration
        ];

        return true;
    }

    /**
     * 检查内存是否可用
     */
    private function is_memory_available()
    {
        $current_usage = memory_get_usage(true);
        $available_memory = $this->memory_limit - $current_usage;
        
        // 保留20%的内存空间
        return $available_memory > ($this->memory_limit * 0.2);
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
     * 生成缓存键
     */
    private function generate_cache_key($key, $group)
    {
        return "msd_{$group}_{$key}";
    }

    /**
     * 清理过期的内存缓存
     */
    public function cleanup_memory()
    {
        $current_time = time();
        $cleaned_count = 0;
        
        foreach ($this->cache_layers[self::CACHE_LAYER_MEMORY] as $key => $cache_data) {
            if (isset($cache_data['expires']) && $cache_data['expires'] < $current_time) {
                unset($this->cache_layers[self::CACHE_LAYER_MEMORY][$key]);
                $cleaned_count++;
            }
        }
        
        // 如果内存使用率过高，强制清理一些缓存
        if (!$this->is_memory_available()) {
            $cache_keys = array_keys($this->cache_layers[self::CACHE_LAYER_MEMORY]);
            $keys_to_remove = array_slice($cache_keys, 0, min(10, count($cache_keys) / 2));
            
            foreach ($keys_to_remove as $key) {
                unset($this->cache_layers[self::CACHE_LAYER_MEMORY][$key]);
                $cleaned_count++;
            }
        }
        
        return $cleaned_count;
    }

    /**
     * 清理所有缓存
     */
    public function flush_all_caches()
    {
        // 清理内存缓存
        $this->cache_layers[self::CACHE_LAYER_MEMORY] = [];
        
        // 清理对象缓存
        if ($this->cache_layers[self::CACHE_LAYER_OBJECT]) {
            wp_cache_flush();
        }
        
        // 清理数据库缓存
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_msd_%'");
        
        // 清理查询缓存
        $this->query_cache = [];
        
        return true;
    }

    /**
     * 获取缓存统计信息
     */
    public function get_cache_stats()
    {
        $memory_cache_count = count($this->cache_layers[self::CACHE_LAYER_MEMORY]);
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        global $wpdb;
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_msd_%'"
        );

        return [
            'memory_cache_items' => $memory_cache_count,
            'transient_cache_items' => (int) $transient_count,
            'object_cache_enabled' => $this->cache_layers[self::CACHE_LAYER_OBJECT],
            'memory_usage' => size_format($memory_usage),
            'memory_peak' => size_format($memory_peak),
            'memory_limit' => size_format($this->memory_limit),
            'cache_hit_ratio' => $this->calculate_cache_hit_ratio(),
            'query_cache_size' => count($this->query_cache)
        ];
    }
}