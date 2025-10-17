<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_MSD_404_Monitor
{
    private static $instance = null;
    private $table_name;
    private $queue_key = 'msd_404_queue';
    private $rate_limit_key = 'msd_404_rate_limit';
    private $rate_limit_duration = 300; // 5 minutes
    private $queue_batch_size = 20; // Write to DB every 20 entries
    private $cleanup_days = 30; // Auto-delete records older than 30 days

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . 'msd_404_log';
    }

    /**
     * Initialize hooks
     */
    public function init()
    {
        // Only hook if monitoring is enabled
        if (!$this->is_monitoring_enabled()) {
            return;
        }

        // Hook into template_redirect to catch 404s
        add_action('template_redirect', [$this, 'catch_404'], 999);
        
        // Schedule cleanup
        if (!wp_next_scheduled('msd_cleanup_404_log')) {
            wp_schedule_event(time(), 'daily', 'msd_cleanup_404_log');
        }
        add_action('msd_cleanup_404_log', [$this, 'cleanup_old_records']);
        
        // Process queue on shutdown
        add_action('shutdown', [$this, 'process_queue']);
    }

    /**
     * Check if monitoring is enabled
     */
    public function is_monitoring_enabled()
    {
        return (bool) get_site_option('msd_404_monitoring_enabled', false);
    }

    /**
     * Enable/disable monitoring
     */
    public function set_monitoring_enabled($enabled)
    {
        update_site_option('msd_404_monitoring_enabled', (bool) $enabled);
    }

    /**
     * Catch 404 errors
     */
    public function catch_404()
    {
        if (!is_404()) {
            return;
        }

        // Get request details
        $url = $this->get_current_url();
        $referer = wp_get_referer() ?: '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $ip_address = $this->get_client_ip();

        // Check rate limit
        if ($this->is_rate_limited($url)) {
            return;
        }

        // Add to queue
        $this->add_to_queue([
            'url' => $url,
            'referer' => $referer,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'timestamp' => current_time('mysql')
        ]);

        // Set rate limit
        $this->set_rate_limit($url);
    }

    /**
     * Get current request URL
     */
    private function get_current_url()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        
        return $protocol . '://' . $host . $uri;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }

    /**
     * Check if URL is rate limited
     */
    private function is_rate_limited($url)
    {
        $rate_limits = get_site_transient($this->rate_limit_key);
        
        if (!is_array($rate_limits)) {
            return false;
        }
        
        $url_hash = md5($url);
        return isset($rate_limits[$url_hash]);
    }

    /**
     * Set rate limit for URL
     */
    private function set_rate_limit($url)
    {
        $rate_limits = get_site_transient($this->rate_limit_key);
        
        if (!is_array($rate_limits)) {
            $rate_limits = [];
        }
        
        $url_hash = md5($url);
        $rate_limits[$url_hash] = time();
        
        // Clean up old entries (older than rate limit duration)
        $cutoff = time() - $this->rate_limit_duration;
        foreach ($rate_limits as $hash => $timestamp) {
            if ($timestamp < $cutoff) {
                unset($rate_limits[$hash]);
            }
        }
        
        set_site_transient($this->rate_limit_key, $rate_limits, $this->rate_limit_duration);
    }

    /**
     * Add entry to queue
     */
    private function add_to_queue($entry)
    {
        $queue = get_site_transient($this->queue_key);
        
        if (!is_array($queue)) {
            $queue = [];
        }
        
        $queue[] = $entry;
        
        set_site_transient($this->queue_key, $queue, HOUR_IN_SECONDS);
        
        // If queue is full, trigger processing
        if (count($queue) >= $this->queue_batch_size) {
            $this->process_queue();
        }
    }

    /**
     * Process queued entries and write to database
     */
    public function process_queue()
    {
        $queue = get_site_transient($this->queue_key);
        
        if (empty($queue) || !is_array($queue)) {
            return;
        }

        global $wpdb;
        
        // Prepare batch insert
        $values = [];
        $placeholders = [];
        
        foreach ($queue as $entry) {
            $placeholders[] = '(%s, %s, %s, %s, %s)';
            $values[] = $entry['url'];
            $values[] = $entry['referer'];
            $values[] = $entry['user_agent'];
            $values[] = $entry['ip_address'];
            $values[] = $entry['timestamp'];
        }
        
        $query = "INSERT INTO {$this->table_name} (url, referer, user_agent, ip_address, created_at) VALUES ";
        $query .= implode(', ', $placeholders);
        
        $result = $wpdb->query($wpdb->prepare($query, $values));
        
        if ($result !== false) {
            // Clear queue
            delete_site_transient($this->queue_key);
        }
    }

    /**
     * Get 404 statistics
     */
    public function get_statistics($limit = 20, $days = 30)
    {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Top 404 URLs
        $top_urls = $wpdb->get_results($wpdb->prepare(
            "SELECT url, COUNT(*) as count, MAX(created_at) as last_seen
            FROM {$this->table_name}
            WHERE created_at >= %s
            GROUP BY url
            ORDER BY count DESC
            LIMIT %d",
            $date_from,
            $limit
        ), ARRAY_A);
        
        // Total count
        $total_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            $date_from
        ));
        
        // Daily trend (last 7 days)
        $daily_trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
            FROM {$this->table_name}
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 7",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ), ARRAY_A);
        
        return [
            'top_urls' => $top_urls,
            'total_count' => (int) $total_count,
            'daily_trend' => array_reverse($daily_trend),
            'monitoring_enabled' => $this->is_monitoring_enabled()
        ];
    }

    /**
     * Get recent 404 entries
     */
    public function get_recent_entries($limit = 50)
    {
        global $wpdb;
        
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            ORDER BY created_at DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $entries;
    }

    /**
     * Clean up old records
     */
    public function cleanup_old_records()
    {
        global $wpdb;
        
        $date_before = date('Y-m-d H:i:s', strtotime("-{$this->cleanup_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $date_before
        ));
    }

    /**
     * Clear all 404 records
     */
    public function clear_all_records()
    {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        // Clear queue and rate limits
        delete_site_transient($this->queue_key);
        delete_site_transient($this->rate_limit_key);
        
        return $result !== false;
    }

    /**
     * Create database table
     */
    public static function create_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . 'msd_404_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            referer varchar(500) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY url_index (url(191)),
            KEY created_at_index (created_at)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop database table
     */
    public static function drop_table()
    {
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'msd_404_log';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
