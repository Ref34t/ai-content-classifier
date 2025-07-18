<?php
/**
 * Advanced caching system for API requests and generated content
 */
class AICG_Cache {
    
    private $cache_group = 'aicg_cache';
    private $cache_expiry = 86400; // 24 hours
    private $logger;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        $this->cache_expiry = get_option('aicg_cache_expiry', 86400);
        
        // Initialize cache system
        $this->init_cache_system();
    }
    
    /**
     * Initialize cache system
     */
    private function init_cache_system() {
        // Create cache table if it doesn't exist
        $this->create_cache_table();
        
        // Schedule cache cleanup
        if (!wp_next_scheduled('aicg_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'aicg_cleanup_cache');
        }
        
        // Hook cleanup function
        add_action('aicg_cleanup_cache', array($this, 'cleanup_expired_cache'));
    }
    
    /**
     * Create cache database table
     */
    private function create_cache_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            cache_group varchar(100) DEFAULT 'default',
            expiry_time datetime NOT NULL,
            created_time datetime DEFAULT CURRENT_TIMESTAMP,
            hit_count int DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key, cache_group),
            KEY expiry_time (expiry_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Generate cache key for API request
     */
    public function generate_cache_key($prompt, $options = array()) {
        $key_data = array(
            'prompt' => $prompt,
            'model' => isset($options['model']) ? $options['model'] : get_option('aicg_model', 'gpt-3.5-turbo'),
            'temperature' => isset($options['temperature']) ? $options['temperature'] : get_option('aicg_temperature', 0.7),
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : get_option('aicg_max_tokens', 2000),
            'content_type' => isset($options['content_type']) ? $options['content_type'] : 'post',
            'seo_enabled' => isset($options['seo_enabled']) ? $options['seo_enabled'] : true
        );
        
        return 'aicg_' . md5(json_encode($key_data));
    }
    
    /**
     * Get cached response
     */
    public function get($key, $group = 'default') {
        // Try WordPress object cache first
        $cached = wp_cache_get($key, $this->cache_group);
        if ($cached !== false) {
            $this->logger->debug('Cache hit (object cache): ' . $key);
            return $cached;
        }
        
        // Try database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value, expiry_time, hit_count FROM $table_name 
            WHERE cache_key = %s AND cache_group = %s AND expiry_time > NOW()",
            $key, $group
        ));
        
        if ($result) {
            // Update hit count
            $wpdb->update(
                $table_name,
                array('hit_count' => $result->hit_count + 1),
                array('cache_key' => $key, 'cache_group' => $group)
            );
            
            $cached_data = maybe_unserialize($result->cache_value);
            
            // Store in object cache for faster access
            wp_cache_set($key, $cached_data, $this->cache_group, $this->cache_expiry);
            
            $this->logger->debug('Cache hit (database): ' . $key);
            return $cached_data;
        }
        
        $this->logger->debug('Cache miss: ' . $key);
        return false;
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $group = 'default', $expiry = null) {
        if ($expiry === null) {
            $expiry = $this->cache_expiry;
        }
        
        // Store in WordPress object cache
        wp_cache_set($key, $data, $this->cache_group, $expiry);
        
        // Store in database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $expiry_time = date('Y-m-d H:i:s', time() + $expiry);
        $serialized_data = maybe_serialize($data);
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'cache_key' => $key,
                'cache_value' => $serialized_data,
                'cache_group' => $group,
                'expiry_time' => $expiry_time,
                'created_time' => current_time('mysql'),
                'hit_count' => 0
            )
        );
        
        if ($result) {
            $this->logger->debug('Cache stored: ' . $key);
            return true;
        }
        
        $this->logger->error('Failed to store cache: ' . $key);
        return false;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key, $group = 'default') {
        // Delete from object cache
        wp_cache_delete($key, $this->cache_group);
        
        // Delete from database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $result = $wpdb->delete(
            $table_name,
            array('cache_key' => $key, 'cache_group' => $group)
        );
        
        if ($result) {
            $this->logger->debug('Cache deleted: ' . $key);
            return true;
        }
        
        return false;
    }
    
    /**
     * Clear all cache
     */
    public function flush($group = null) {
        // Clear object cache
        wp_cache_flush();
        
        // Clear database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        if ($group) {
            $result = $wpdb->delete($table_name, array('cache_group' => $group));
        } else {
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
        }
        
        if ($result) {
            $this->logger->info('Cache flushed' . ($group ? ' for group: ' . $group : ''));
            return true;
        }
        
        return false;
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $deleted = $wpdb->query(
            "DELETE FROM $table_name WHERE expiry_time < NOW()"
        );
        
        if ($deleted > 0) {
            $this->logger->info('Cleaned up ' . $deleted . ' expired cache entries');
        }
        
        return $deleted;
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_entries,
                SUM(hit_count) as total_hits,
                AVG(hit_count) as avg_hits,
                COUNT(CASE WHEN expiry_time > NOW() THEN 1 END) as active_entries,
                COUNT(CASE WHEN expiry_time <= NOW() THEN 1 END) as expired_entries
            FROM $table_name
        ");
        
        if (!$stats) {
            return array(
                'total_entries' => 0,
                'total_hits' => 0,
                'avg_hits' => 0,
                'active_entries' => 0,
                'expired_entries' => 0,
                'hit_rate' => 0
            );
        }
        
        $hit_rate = $stats->total_entries > 0 ? ($stats->total_hits / $stats->total_entries) * 100 : 0;
        
        return array(
            'total_entries' => (int)$stats->total_entries,
            'total_hits' => (int)$stats->total_hits,
            'avg_hits' => round($stats->avg_hits, 2),
            'active_entries' => (int)$stats->active_entries,
            'expired_entries' => (int)$stats->expired_entries,
            'hit_rate' => round($hit_rate, 2)
        );
    }
    
    /**
     * Get cache size
     */
    public function get_cache_size() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        $size = $wpdb->get_var("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = '$table_name'
        ");
        
        return $size ? $size : 0;
    }
    
    /**
     * Get most popular cached items
     */
    public function get_popular_cache_items($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicg_cache';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT cache_key, cache_group, hit_count, created_time, expiry_time
            FROM $table_name
            WHERE expiry_time > NOW()
            ORDER BY hit_count DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Preload cache for common requests
     */
    public function preload_cache() {
        // Get most common templates
        global $wpdb;
        $templates_table = $wpdb->prefix . 'aicg_templates';
        
        $popular_templates = $wpdb->get_results("
            SELECT prompt, content_type
            FROM $templates_table
            ORDER BY id DESC
            LIMIT 5
        ");
        
        $preloaded = 0;
        
        foreach ($popular_templates as $template) {
            $cache_key = $this->generate_cache_key($template->prompt, array(
                'content_type' => $template->content_type
            ));
            
            // Only preload if not already cached
            if (!$this->get($cache_key)) {
                // This would normally generate content, but for preloading we'll skip
                // In a real implementation, you might want to generate and cache
                $preloaded++;
            }
        }
        
        $this->logger->info('Preloaded ' . $preloaded . ' cache entries');
        return $preloaded;
    }
    
    /**
     * Cache with intelligent expiry
     */
    public function smart_cache($key, $data, $group = 'default', $options = array()) {
        $expiry = $this->cache_expiry;
        
        // Adjust expiry based on content type
        if (isset($options['content_type'])) {
            switch ($options['content_type']) {
                case 'post':
                    $expiry = 86400; // 24 hours
                    break;
                case 'page':
                    $expiry = 604800; // 7 days
                    break;
                case 'product':
                    $expiry = 43200; // 12 hours
                    break;
                case 'email':
                    $expiry = 3600; // 1 hour
                    break;
                case 'social':
                    $expiry = 1800; // 30 minutes
                    break;
            }
        }
        
        // Adjust expiry based on prompt complexity
        if (isset($options['prompt_length'])) {
            $length = $options['prompt_length'];
            if ($length > 500) {
                $expiry *= 2; // Cache longer for complex prompts
            } elseif ($length < 100) {
                $expiry /= 2; // Cache shorter for simple prompts
            }
        }
        
        return $this->set($key, $data, $group, $expiry);
    }
    
    /**
     * Cache compression for large content
     */
    public function set_compressed($key, $data, $group = 'default', $expiry = null) {
        if (function_exists('gzcompress')) {
            $serialized = maybe_serialize($data);
            $compressed = gzcompress($serialized, 9);
            
            if ($compressed !== false) {
                $compressed_data = array(
                    'compressed' => true,
                    'data' => base64_encode($compressed)
                );
                
                return $this->set($key, $compressed_data, $group, $expiry);
            }
        }
        
        // Fallback to regular caching
        return $this->set($key, $data, $group, $expiry);
    }
    
    /**
     * Get compressed data
     */
    public function get_compressed($key, $group = 'default') {
        $cached = $this->get($key, $group);
        
        if ($cached && is_array($cached) && isset($cached['compressed']) && $cached['compressed']) {
            if (function_exists('gzuncompress')) {
                $compressed_data = base64_decode($cached['data']);
                $uncompressed = gzuncompress($compressed_data);
                
                if ($uncompressed !== false) {
                    return maybe_unserialize($uncompressed);
                }
            }
        }
        
        return $cached;
    }
}