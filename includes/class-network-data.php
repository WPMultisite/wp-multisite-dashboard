<?php

if (!defined("ABSPATH")) {
    exit();
}

class WP_MSD_Network_Data
{
    private $wpdb;
    private $cache_group = "msd_network_data";
    private $cache_timeout = 3600;
    private $performance_manager;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->performance_manager = WP_MSD_Performance_Manager::get_instance();
    }

    public function get_total_sites()
    {
        $cache_key = "total_sites_count";
        $cached = $this->performance_manager->get_cache($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        // 优化查询：只计数，不获取完整对象
        $count = get_sites([
            "count" => true,
            "archived" => 0,
            "spam" => 0,
            "deleted" => 0
        ]);
        
        $this->performance_manager->set_cache(
            $cache_key, 
            $count, 
            WP_MSD_Performance_Manager::CACHE_LONG, 
            $this->cache_group
        );

        return $count;
    }

    public function get_total_users()
    {
        $cache_key = "total_users_count";
        $cached = $this->performance_manager->get_cache($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        // 优化查询：使用更高效的计数方式
        if (function_exists('get_user_count')) {
            $count = get_user_count();
        } else {
            // 备用方案：直接数据库查询
            $user_count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT ID) FROM {$this->wpdb->users} WHERE user_status = %d",
                    0
                )
            );
            $count = intval($user_count);
        }

        $this->performance_manager->set_cache(
            $cache_key, 
            $count, 
            WP_MSD_Performance_Manager::CACHE_LONG, 
            $this->cache_group
        );

        return $count;
    }

    public function get_total_posts()
    {
        $cache_key = "total_posts_count";
        $cached = $this->performance_manager->get_cache($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        // 优化：使用单个数据库查询而不是循环切换博客
        $total_posts = $this->get_network_post_count('post');
        
        $this->performance_manager->set_cache(
            $cache_key, 
            $total_posts, 
            WP_MSD_Performance_Manager::CACHE_EXTENDED, 
            $this->cache_group
        );
        
        return $total_posts;
    }

    public function get_total_pages()
    {
        $cache_key = "total_pages_count";
        $cached = $this->performance_manager->get_cache($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        // 优化：使用单个数据库查询而不是循环切换博客
        $total_pages = $this->get_network_post_count('page');
        
        $this->performance_manager->set_cache(
            $cache_key, 
            $total_pages, 
            WP_MSD_Performance_Manager::CACHE_EXTENDED, 
            $this->cache_group
        );
        
        return $total_pages;
    }

    public function get_multisite_configuration()
    {
        $cache_key = "multisite_configuration";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $config = [
            "installation_type" => is_subdomain_install()
                ? "subdomain"
                : "subdirectory",
            "installation_type_label" => is_subdomain_install()
                ? __("Subdomain Installation", "wp-multisite-dashboard")
                : __("Subdirectory Installation", "wp-multisite-dashboard"),
            "domain_current_site" => DOMAIN_CURRENT_SITE,
            "path_current_site" => PATH_CURRENT_SITE,
            "site_id_current_site" => SITE_ID_CURRENT_SITE,
            "blog_id_current_site" => BLOG_ID_CURRENT_SITE,
            "multisite_enabled" => true,
            "cookie_domain" => defined("COOKIE_DOMAIN") ? COOKIE_DOMAIN : "",
        ];

        $this->set_cache($cache_key, $config, 7200);
        return $config;
    }

    public function get_network_information()
    {
        $cache_key = "network_information";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wp_version;

        $info = [
            "network_name" => get_network_option(null, "site_name"),
            "network_admin_email" => get_network_option(null, "admin_email"),
            "registration" => get_network_option(null, "registration"),
            "registration_label" => $this->get_registration_label(
                get_network_option(null, "registration")
            ),
            "blog_upload_space" => get_space_allowed(),
            "blog_upload_space_formatted" => get_space_allowed() . " MB",
            "fileupload_maxk" => get_network_option(null, "fileupload_maxk"),
            "fileupload_maxk_formatted" => size_format(
                get_network_option(null, "fileupload_maxk") * 1024
            ),
            "illegal_names" => get_network_option(null, "illegal_names"),
            "limited_email_domains" => get_network_option(
                null,
                "limited_email_domains"
            ),
            "banned_email_domains" => get_network_option(
                null,
                "banned_email_domains"
            ),
            "welcome_email" => get_network_option(null, "welcome_email"),
            "first_post" => get_network_option(null, "first_post"),
            "first_page" => get_network_option(null, "first_page"),
            "first_comment" => get_network_option(null, "first_comment"),
            "first_comment_url" => get_network_option(
                null,
                "first_comment_url"
            ),
            "first_comment_author" => get_network_option(
                null,
                "first_comment_author"
            ),
            "welcome_user_email" => get_network_option(
                null,
                "welcome_user_email"
            ),
            "default_language" => get_network_option(null, "WPLANG") ?: "en_US",
            "wp_version" => $wp_version,
            "active_sitewide_plugins_count" => count(
                get_site_option("active_sitewide_plugins", [])
            ),
            "allowed_themes_count" => count(
                get_site_option("allowedthemes", [])
            ),
        ];

        $this->set_cache($cache_key, $info, 3600);
        return $info;
    }

    private function get_registration_label($registration)
    {
        $labels = [
            "none" => __("Registration disabled", "wp-multisite-dashboard"),
            "user" => __("User registration only", "wp-multisite-dashboard"),
            "blog" => __("Site registration only", "wp-multisite-dashboard"),
            "all" => __("User and site registration", "wp-multisite-dashboard"),
        ];
        return $labels[$registration] ??
            __("Unknown", "wp-multisite-dashboard");
    }

    public function get_recent_network_activity($limit = 10)
    {
        $cache_key = "recent_network_activity_{$limit}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sites = get_sites([
            "number" => 20,
            "orderby" => "last_updated",
            "order" => "DESC",
        ]);
        $activities = [];

        foreach ($sites as $site) {
            $blog_id = $site->blog_id;
            switch_to_blog($blog_id);

            $recent_posts = get_posts([
                "numberposts" => 3,
                "post_status" => "publish",
                "orderby" => "date",
                "order" => "DESC",
            ]);

            $recent_pages = get_posts([
                "numberposts" => 2,
                "post_type" => "page",
                "post_status" => "publish",
                "orderby" => "date",
                "order" => "DESC",
            ]);

            $site_name = get_bloginfo("name");
            $site_url = get_site_url();
            $admin_url = get_admin_url();

            foreach ($recent_posts as $post) {
                $activities[] = [
                    "type" => "post",
                    "type_label" => __("Post", "wp-multisite-dashboard"),
                    "title" => $post->post_title,
                    "content" => wp_trim_words($post->post_content, 15),
                    "author" => get_the_author_meta(
                        "display_name",
                        $post->post_author
                    ),
                    "date" => $post->post_date,
                    "date_human" =>
                        human_time_diff(strtotime($post->post_date)) .
                        " " .
                        __("ago", "wp-multisite-dashboard"),
                    "site_name" => $site_name,
                    "site_url" => $site_url,
                    "edit_url" =>
                        $admin_url .
                        "post.php?post=" .
                        $post->ID .
                        "&action=edit",
                    "view_url" => get_permalink($post->ID),
                    "blog_id" => $blog_id,
                    "timestamp" => strtotime($post->post_date),
                ];
            }

            foreach ($recent_pages as $page) {
                $activities[] = [
                    "type" => "page",
                    "type_label" => __("Page", "wp-multisite-dashboard"),
                    "title" => $page->post_title,
                    "content" => wp_trim_words($page->post_content, 15),
                    "author" => get_the_author_meta(
                        "display_name",
                        $page->post_author
                    ),
                    "date" => $page->post_date,
                    "date_human" =>
                        human_time_diff(strtotime($page->post_date)) .
                        " " .
                        __("ago", "wp-multisite-dashboard"),
                    "site_name" => $site_name,
                    "site_url" => $site_url,
                    "edit_url" =>
                        $admin_url .
                        "post.php?post=" .
                        $page->ID .
                        "&action=edit",
                    "view_url" => get_permalink($page->ID),
                    "blog_id" => $blog_id,
                    "timestamp" => strtotime($page->post_date),
                ];
            }

            restore_current_blog();
        }

        usort($activities, function ($a, $b) {
            return $b["timestamp"] - $a["timestamp"];
        });

        $activities = array_slice($activities, 0, $limit);

        $this->set_cache($cache_key, $activities, 1800);
        return $activities;
    }

    public function get_total_storage_used()
    {
        $cache_key = "total_storage";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $storage_data = $this->get_storage_usage_data();
        $total_bytes = 0;

        foreach ($storage_data["sites"] as $site) {
            $total_bytes += $site["storage_bytes"];
        }

        $formatted_storage = size_format($total_bytes);
        $this->set_cache($cache_key, $formatted_storage, 3600);

        return $formatted_storage;
    }

    public function get_storage_usage_data($limit = 10)
    {
        $cache_key = "storage_usage_data_{$limit}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sites = get_sites(["number" => 100]);
        $storage_data = [
            "total_bytes" => 0,
            "total_formatted" => "0 B",
            "sites" => [],
            "summary" => [
                "sites_analyzed" => 0,
                "average_per_site" => 0,
                "largest_site" => null,
                "storage_limit" => get_space_allowed(),
            ],
        ];

        $site_storage = [];
        $total_bytes = 0;

        foreach ($sites as $site) {
            $blog_id = $site->blog_id;
            $storage_bytes = $this->get_site_storage_usage($blog_id);

            if ($storage_bytes > 0) {
                $storage_limit_mb = get_space_allowed();
                $usage_percentage =
                    $storage_limit_mb > 0
                        ? ($storage_bytes / (1024 * 1024) / $storage_limit_mb) *
                            100
                        : 0;

                $site_info = [
                    "blog_id" => $blog_id,
                    "name" => $this->get_site_name($blog_id),
                    "domain" => $site->domain . $site->path,
                    "storage_bytes" => $storage_bytes,
                    "storage_formatted" => size_format($storage_bytes),
                    "storage_limit_mb" => $storage_limit_mb,
                    "usage_percentage" => round($usage_percentage, 1),
                    "status" => $this->get_storage_status_from_percentage(
                        $usage_percentage
                    ),
                    "admin_url" => get_admin_url($blog_id),
                ];

                $site_storage[] = $site_info;
                $total_bytes += $storage_bytes;
            }
        }

        usort($site_storage, function ($a, $b) {
            return $b["storage_bytes"] <=> $a["storage_bytes"];
        });

        $storage_data["sites"] = array_slice($site_storage, 0, $limit);
        $storage_data["total_bytes"] = $total_bytes;
        $storage_data["total_formatted"] = size_format($total_bytes);
        $storage_data["summary"]["sites_analyzed"] = count($site_storage);
        $storage_data["summary"]["average_per_site"] =
            count($site_storage) > 0 ? $total_bytes / count($site_storage) : 0;
        $storage_data["summary"]["largest_site"] = !empty($site_storage)
            ? $site_storage[0]
            : null;

        $this->set_cache($cache_key, $storage_data, 3600);
        return $storage_data;
    }

    private function get_storage_status_from_percentage($percentage)
    {
        if ($percentage > 90) {
            return "critical";
        } elseif ($percentage > 75) {
            return "warning";
        }
        return "good";
    }

    public function get_overall_network_status()
    {
        $cache_key = "network_status";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $total_sites = $this->get_total_sites();

        $status_data = [
            "status" => "healthy",
            "message" => __(
                "All systems operating normally",
                "wp-multisite-dashboard"
            ),
        ];

        if ($total_sites == 0) {
            $status_data = [
                "status" => "warning",
                "message" => __("No sites found", "wp-multisite-dashboard"),
            ];
        }

        $this->set_cache($cache_key, $status_data, 900);
        return $status_data;
    }

    public function get_recent_active_sites($limit = 5)
    {
        $cache_key = "recent_sites_{$limit}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sites = $this->fetch_active_sites($limit * 2);
        $site_data = [];

        foreach ($sites as $site) {
            $blog_id = $site->blog_id;

            $site_info = [
                "blog_id" => $blog_id,
                "name" => $this->get_site_name($blog_id),
                "domain" => $site->domain . $site->path,
                "users" => $this->get_site_user_count($blog_id),
                "last_activity" => $this->get_site_last_activity($blog_id),
                "admin_url" => get_admin_url($blog_id),
                "view_url" => get_site_url($blog_id),
                "status" => $this->get_site_status($blog_id),
                "favicon" => $this->get_site_favicon($blog_id),
            ];

            $site_info["last_activity_human"] = $site_info["last_activity"]
                ? human_time_diff(strtotime($site_info["last_activity"])) .
                    " " .
                    __("ago", "wp-multisite-dashboard")
                : __("No recent activity", "wp-multisite-dashboard");

            $site_data[] = $site_info;
        }

        usort($site_data, function ($a, $b) {
            if (empty($a["last_activity"])) {
                return 1;
            }
            if (empty($b["last_activity"])) {
                return -1;
            }
            return strtotime($b["last_activity"]) -
                strtotime($a["last_activity"]);
        });

        $recent_sites = array_slice($site_data, 0, $limit);
        $this->set_cache($cache_key, $recent_sites, 1800);

        return $recent_sites;
    }

    public function get_network_settings_overview()
    {
        $cache_key = "network_settings_overview";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wp_version;

        $settings_data = [
            "network_info" => [
                "network_name" => get_network_option(null, "site_name"),
                "network_admin_email" => get_network_option(
                    null,
                    "admin_email"
                ),
                "registration_allowed" => get_network_option(
                    null,
                    "registration"
                ),
                "subdomain_install" => is_subdomain_install(),
                "max_upload_size" => size_format(wp_max_upload_size()),
                "blog_upload_space" => get_space_allowed(),
                "file_upload_max_size" => size_format(wp_max_upload_size()),
                "max_execution_time" => ini_get("max_execution_time"),
                "memory_limit" => ini_get("memory_limit"),
            ],
            "theme_plugin_settings" => [
                "network_active_plugins" => count(
                    get_site_option("active_sitewide_plugins", [])
                ),
                "network_themes" => $this->count_network_themes(),
                "plugin_auto_updates" => $this->check_plugin_auto_updates(),
                "theme_auto_updates" => $this->check_theme_auto_updates(),
            ],
            "quick_actions" => [
                "network_settings_url" => network_admin_url("settings.php"),
                "network_sites_url" => network_admin_url("sites.php"),
                "network_users_url" => network_admin_url("users.php"),
                "network_themes_url" => network_admin_url("themes.php"),
                "network_plugins_url" => network_admin_url("plugins.php"),
                "network_updates_url" => network_admin_url("update-core.php"),
            ],
            "system_status" => [
                "wordpress_version" => $wp_version,
                "php_version" => phpversion(),
                "mysql_version" => $this->wpdb->db_version(),
                "multisite_enabled" => true,
                "last_updated" => current_time("mysql"),
            ],
        ];

        $this->set_cache($cache_key, $settings_data, 1800);
        return $settings_data;
    }

    private function count_network_themes()
    {
        $themes = wp_get_themes(["allowed" => "network"]);
        return count($themes);
    }

    private function check_plugin_auto_updates()
    {
        return get_site_option("auto_update_plugins", []);
    }

    private function check_theme_auto_updates()
    {
        return get_site_option("auto_update_themes", []);
    }

    private function get_site_favicon($blog_id)
    {
        $cache_key = "site_favicon_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        switch_to_blog($blog_id);

        $favicon_url = "";

        $site_icon_id = get_option("site_icon");
        if ($site_icon_id) {
            $favicon_url = wp_get_attachment_image_url($site_icon_id, "full");
        }

        if (empty($favicon_url)) {
            $custom_favicon = get_option("blog_public")
                ? get_site_icon_url()
                : "";
            if ($custom_favicon) {
                $favicon_url = $custom_favicon;
            }
        }

        if (empty($favicon_url)) {
            $site_url = get_site_url();
            $favicon_url = $site_url . "/favicon.ico";

            $response = wp_remote_head($favicon_url, [
                "timeout" => 5,
            ]);

            if (
                is_wp_error($response) ||
                wp_remote_retrieve_response_code($response) !== 200
            ) {
                $favicon_url = includes_url("images/w-logo-blue.png");
            }
        }

        restore_current_blog();

        wp_cache_set($cache_key, $favicon_url, $this->cache_group, 3600);
        return $favicon_url;
    }

    private function fetch_active_sites($limit)
    {
        return get_sites([
            "number" => $limit,
            "orderby" => "last_updated",
            "order" => "DESC",
            "public" => null,
            "archived" => 0,
            "mature" => 0,
            "spam" => 0,
            "deleted" => 0,
        ]);
    }

    public function get_top_storage_sites($limit = 5)
    {
        $storage_data = $this->get_storage_usage_data($limit);
        return array_slice($storage_data["sites"], 0, $limit);
    }

    private function get_site_storage_usage($blog_id)
    {
        $cache_key = "storage_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $upload_dir = $this->get_site_upload_dir($blog_id);
        $size_bytes = 0;

        if (is_dir($upload_dir)) {
            $size_bytes = $this->calculate_directory_size($upload_dir);
        }

        wp_cache_set($cache_key, $size_bytes, $this->cache_group, 3600);
        return $size_bytes;
    }

    private function get_site_upload_dir($blog_id)
    {
        // Always switch to target blog to get its own uploads basedir reliably
        // This avoids brittle string replacements and works for both subdomain and subdirectory installs
        switch_to_blog($blog_id);
        $upload_dir = wp_upload_dir();
        $basedir = isset($upload_dir['basedir']) ? $upload_dir['basedir'] : '';
        restore_current_blog();
        return $basedir;
    }

    private function calculate_directory_size($directory)
    {
        $size = 0;
        $file_count = 0;
        $max_files = 5000;

        try {
            if (!is_readable($directory)) {
                return 0;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $size += $file->getSize();
                    $file_count++;

                    if ($file_count > $max_files) {
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("MSD Storage calculation error: " . $e->getMessage());
            return 0;
        }

        return $size;
    }

    private function get_site_name($blog_id)
    {
        $cache_key = "site_name_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $site_details = get_blog_details($blog_id);
        $name = $site_details
            ? $site_details->blogname
            : __("Unknown Site", "wp-multisite-dashboard");

        wp_cache_set($cache_key, $name, $this->cache_group, 3600);
        return $name;
    }

    private function get_site_user_count($blog_id)
    {
        $cache_key = "user_count_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $count = count_users()["total_users"];

        if ($blog_id > 1) {
            $users = get_users(["blog_id" => $blog_id, "fields" => "ID"]);
            $count = count($users);
        }

        wp_cache_set($cache_key, $count, $this->cache_group, 1800);
        return $count;
    }

    private function get_site_last_activity($blog_id)
    {
        $cache_key = "last_activity_{$blog_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        switch_to_blog($blog_id);

        $last_post = get_posts([
            "numberposts" => 1,
            "post_status" => "publish",
            "orderby" => "date",
            "order" => "DESC",
            "fields" => "post_date",
        ]);

        $last_comment = get_comments([
            "number" => 1,
            "status" => "approve",
            "orderby" => "comment_date",
            "order" => "DESC",
            "fields" => "comment_date",
        ]);

        restore_current_blog();

        $dates = array_filter([
            $last_post ? $last_post[0]->post_date : null,
            $last_comment ? $last_comment[0]->comment_date : null,
        ]);

        $last_activity = empty($dates) ? null : max($dates);

        wp_cache_set($cache_key, $last_activity, $this->cache_group, 1800);
        return $last_activity;
    }

    private function get_site_status($blog_id)
    {
        $last_activity = $this->get_site_last_activity($blog_id);

        if (empty($last_activity)) {
            return "inactive";
        }

        $days_inactive =
            (current_time("timestamp") - strtotime($last_activity)) /
            DAY_IN_SECONDS;

        if ($days_inactive > 90) {
            return "inactive";
        } elseif ($days_inactive > 30) {
            return "warning";
        }

        return "active";
    }

    public function log_activity(
        $site_id,
        $activity_type,
        $description,
        $severity = "low",
        $user_id = null
    ) {
        $table_name = $this->wpdb->base_prefix . "msd_activity_log";

        $result = $this->wpdb->insert(
            $table_name,
            [
                "site_id" => intval($site_id),
                "user_id" => $user_id ?: get_current_user_id(),
                "activity_type" => sanitize_text_field($activity_type),
                "description" => sanitize_text_field($description),
                "severity" => in_array($severity, [
                    "low",
                    "medium",
                    "high",
                    "critical",
                ])
                    ? $severity
                    : "low",
                "ip_address" => $this->get_client_ip(),
                "user_agent" => substr(
                    $_SERVER["HTTP_USER_AGENT"] ?? "",
                    0,
                    500
                ),
                "created_at" => current_time("mysql"),
            ],
            ["%d", "%d", "%s", "%s", "%s", "%s", "%s", "%s"]
        );

        if (mt_rand(1, 100) <= 5) {
            $this->cleanup_old_activity_logs();
        }

        return $result !== false;
    }

    private function get_client_ip()
    {
        $ip_headers = [
            "HTTP_CF_CONNECTING_IP",
            "HTTP_CLIENT_IP",
            "HTTP_X_FORWARDED_FOR",
            "HTTP_X_FORWARDED",
            "HTTP_X_CLUSTER_CLIENT_IP",
            "HTTP_FORWARDED_FOR",
            "HTTP_FORWARDED",
            "REMOTE_ADDR",
        ];

        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(",", $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (
                    filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    )
                ) {
                    return $ip;
                }
            }
        }

        return $_SERVER["REMOTE_ADDR"] ?? "";
    }

    private function cleanup_old_activity_logs()
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->base_prefix}msd_activity_log
                WHERE created_at < %s",
                date("Y-m-d H:i:s", strtotime("-60 days"))
            )
        );
    }

    public function create_activity_log_table()
    {
        $table_name = $this->wpdb->base_prefix . "msd_activity_log";
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            description text NOT NULL,
            severity enum('low','medium','high','critical') DEFAULT 'low',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY severity (severity),
            KEY created_at (created_at),
            KEY site_activity (site_id, created_at)
        ) $charset_collate;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }

    public function clear_all_caches()
    {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            // Fallback: best-effort cleanup without group flush
        }

        $transients = [
            "msd_total_sites",
            "msd_total_users",
            "msd_total_posts",
            "msd_total_pages",
            "msd_total_storage",
            "msd_storage_usage_data",
            "msd_network_status",
            "msd_network_settings_overview",
            "msd_multisite_configuration",
            "msd_network_information",
            "msd_recent_network_activity",
        ];

        foreach ($transients as $transient) {
            delete_site_transient($transient);
        }

        return true;
    }

    public function clear_widget_cache($widget = null)
    {
        if ($widget) {
            $cache_keys = [
                "network_overview" => [
                    "total_posts",
                    "total_pages",
                    "multisite_configuration",
                    "network_information",
                    "network_status",
                ],
                "site_list" => ["recent_sites_5"],
                "storage_data" => ["storage_usage_data_5"],
                "network_settings" => ["network_settings_overview"],
                "user_management" => ["recent_users_data"],
                "last_edits" => ["recent_network_activity_10"],
                "contact_info" => ["contact_info"],
            ];

            if (isset($cache_keys[$widget])) {
                foreach ($cache_keys[$widget] as $key) {
                    $this->delete_cache($key);
                }
            }
        } else {
            $this->clear_all_caches();
        }

        return true;
    }

    private function get_cache($key)
    {
        // 向后兼容的缓存获取方法
        return $this->performance_manager->get_cache($key, $this->cache_group);
    }

    private function set_cache($key, $value, $expiration = null)
    {
        // 向后兼容的缓存设置方法
        $expiration = $expiration ?: $this->cache_timeout;
        return $this->performance_manager->set_cache($key, $value, $expiration, $this->cache_group);
    }

    private function delete_cache($key)
    {
        return delete_site_transient("msd_{$key}");
    }

    /**
     * 优化的网络文章计数方法
     * 使用单个SQL查询替代多次博客切换
     */
    private function get_network_post_count($post_type = 'post')
    {
        // 获取所有活跃站点的blog_id
        $sites = get_sites([
            'fields' => 'ids',
            'archived' => 0,
            'spam' => 0,
            'deleted' => 0,
            'number' => 1000 // 限制站点数量以避免内存问题
        ]);

        if (empty($sites)) {
            return 0;
        }

        $total_count = 0;
        $batch_size = 50; // 批量处理以优化内存使用
        $site_batches = array_chunk($sites, $batch_size);

        foreach ($site_batches as $batch) {
            $batch_count = $this->get_batch_post_count($batch, $post_type);
            $total_count += $batch_count;
            
            // 检查内存使用情况
            $current_usage = memory_get_usage(true);
            $memory_limit = $this->get_memory_limit_bytes();
            if ($current_usage > ($memory_limit * 0.8)) {
                error_log('MSD: Memory limit reached during post count calculation');
                break;
            }
        }

        return $total_count;
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
     * 批量获取文章计数
     */
    private function get_batch_post_count($site_ids, $post_type)
    {
        if (empty($site_ids)) {
            return 0;
        }

        $total_count = 0;
        
        // 为每个站点构建表名
        foreach ($site_ids as $blog_id) {
            $table_name = $blog_id == 1 ? 
                $this->wpdb->posts : 
                $this->wpdb->get_blog_prefix($blog_id) . 'posts';
            
            // 检查表是否存在
            $table_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            
            if ($table_exists) {
                $count = $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} 
                         WHERE post_type = %s AND post_status = 'publish'",
                        $post_type
                    )
                );
                $total_count += intval($count);
            }
        }

        return $total_count;
    }

    public function get_network_stats_summary()
    {
        return [
            "total_sites" => $this->get_total_sites(),
            "total_users" => $this->get_total_users(),
            "total_posts" => $this->get_total_posts(),
            "total_pages" => $this->get_total_pages(),
            "total_storage" => $this->get_total_storage_used(),
            "last_updated" => current_time("mysql"),
        ];
    }
}
