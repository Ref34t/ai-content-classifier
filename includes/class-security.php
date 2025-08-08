<?php
/**
 * Security and optimization features
 */
class AICG_Security {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Rate limiting
        add_action('wp_ajax_aicg_generate_content', array($this, 'check_rate_limit'), 5);
        
        // Input sanitization
        add_filter('aicg_sanitize_prompt', array($this, 'sanitize_prompt'));
        
        // Content filtering
        add_filter('aicg_filter_content', array($this, 'filter_generated_content'));
        
        // Security headers removed to prevent header conflicts
        
        // Cleanup old data
        add_action('aicg_cleanup_temp_data', array($this, 'cleanup_old_data'));
        
        // Schedule cleanup if not scheduled
        if (!wp_next_scheduled('aicg_cleanup_temp_data')) {
            wp_schedule_event(time(), 'daily', 'aicg_cleanup_temp_data');
        }
    }
    
    /**
     * Rate limiting for API calls
     */
    public function check_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'aicg_rate_limit_' . $user_id;
        
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            $requests = array();
        }
        
        // Allow 100 requests per hour for testing
        $rate_limit = 100;
        $time_window = 3600; // 1 hour
        
        // Clean old requests
        $current_time = time();
        $requests = array_filter($requests, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
        
        // Check if rate limit exceeded
        if (count($requests) >= $rate_limit) {
            wp_die(json_encode(array(
                'success' => false,
                'data' => __('Rate limit exceeded. Please try again later.', 'ai-content-classifier')
            )));
        }
        
        // Add current request
        $requests[] = $current_time;
        set_transient($transient_key, $requests, $time_window);
    }
    
    /**
     * Sanitize user prompts
     */
    public function sanitize_prompt($prompt) {
        // Remove potentially dangerous content
        $prompt = wp_strip_all_tags($prompt);
        $prompt = wp_kses_post($prompt);
        
        // Remove suspicious patterns
        $dangerous_patterns = array(
            '/\b(eval|exec|system|shell_exec|passthru|file_get_contents|curl|wget)\b/i',
            '/\b(script|javascript|vbscript|onload|onerror|onclick)\b/i',
            '/[<>]/',
            '/\{\{.*?\}\}/',
            '/\$\{.*?\}/',
        );
        
        foreach ($dangerous_patterns as $pattern) {
            $prompt = preg_replace($pattern, '', $prompt);
        }
        
        // Limit length
        $prompt = wp_trim_words($prompt, 500);
        
        return $prompt;
    }
    
    /**
     * Filter generated content for safety
     */
    public function filter_generated_content($content) {
        // Remove potential XSS
        $content = wp_kses_post($content);
        
        // Remove suspicious scripts
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/javascript:/i', '', $content);
        $content = preg_replace('/on\w+\s*=/i', '', $content);
        
        // Remove potential malicious links
        $content = preg_replace_callback('/<a\s+[^>]*href\s*=\s*["\\](["\\]+)[^>]*>/i', function($matches) {
            $url = $matches[1];
            
            // Check if URL is safe
            if ($this->is_safe_url($url)) {
                return $matches[0];
            }
            
            return '<a href="#" data-blocked-url="' . esc_attr($url) . '">';
        }, $content);
        
        return $content;
    }
    
    /**
     * Check if URL is safe
     */
    private function is_safe_url($url) {
        // Allow internal URLs
        if (strpos($url, home_url()) === 0) {
            return true;
        }

        // Check for valid protocols
        if (!in_array(wp_parse_url($url, PHP_URL_SCHEME), array('http', 'https'))) {
            return false;
        }

        // Allow common safe domains
        $safe_domains = array(
            'wordpress.org',
            'wikipedia.org',
            'github.com',
            'stackoverflow.com',
            'google.com',
            'youtube.com',
            'vimeo.com',
            'twitter.com',
            'facebook.com',
            'linkedin.com',
            'instagram.com',
            'pinterest.com',
            'amazon.com',
            'apple.com',
            'microsoft.com',
        );

        $host = wp_parse_url($url, PHP_URL_HOST);

        foreach ($safe_domains as $safe_domain) {
            if (substr($host, -strlen($safe_domain) - 1) === '.' . $safe_domain || $host === $safe_domain) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only on our plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'ai-content-classifier') === false) {
            return;
        }
        
        // Add CSP header
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
        
        // Add other security headers
        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Check cache to avoid running cleanup too frequently
        $cache_key = 'aicg_last_cleanup';
        $last_cleanup = wp_cache_get($cache_key);
        
        // Only run cleanup once per hour
        if ($last_cleanup && (time() - $last_cleanup) < 3600) {
            return;
        }
        
        // Clean up old temporary data (older than 7 days)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $deleted_temp = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aicg_temp_data WHERE created_at < %s",
            gmdate('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        // Clean up old usage logs (older than 30 days)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $deleted_logs = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aicg_usage_log WHERE created_at < %s",
            gmdate('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Cache the cleanup timestamp
        wp_cache_set($cache_key, time(), '', 3600);
        
        // Log cleanup results
        if ($deleted_temp > 0 || $deleted_logs > 0) {
            $logger = new AICG_Logger();
            $logger->info("AICG Security Cleanup: Removed {$deleted_temp} temp records and {$deleted_logs} usage logs");
        }
    }

    /**
     * Log usage for monitoring
     */
    public function log_usage($user_id, $tokens_used, $cost, $model) {
        global $wpdb;

        $usage_table = $wpdb->prefix . 'aicg_usage_log';

        // Cache table existence check to avoid repeated SHOW TABLES queries
        $table_exists_key = 'aicg_usage_table_exists';
        $table_exists = wp_cache_get($table_exists_key);

        if ($table_exists === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_table)) == $usage_table;
            wp_cache_set($table_exists_key, $table_exists, '', 300); // Cache for 5 minutes
        }

        if (!$table_exists) {
            // Try to create the table if it doesn't exist
            aicg_create_tables();
            // Invalidate cache after table creation attempt
            wp_cache_delete($table_exists_key);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_table)) == $usage_table;
            wp_cache_set($table_exists_key, $table_exists, '', 300);
        }

        // Only insert if table exists or was created successfully
        if ($table_exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $usage_table,
                array(
                    'user_id' => $user_id,
                    'tokens_used' => $tokens_used,
                    'cost' => $cost,
                    'model' => $model,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%f', '%s', '%s')
            );
        }
    }

    /**
     * Get usage statistics
     */
    public function get_usage_stats( $user_id = null, $days = 30 ) {
        global $wpdb;

        $table_name = esc_sql( $wpdb->prefix . 'aicg_usage_log' );
        $date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // Generate cache key
        $cache_key = 'aicg_usage_stats_' . ( $user_id ?: 'all' ) . '_' . $days;

        if ( false !== ( $cached_stats = wp_cache_get( $cache_key ) ) ) {
            return $cached_stats;
        }

        if ( $user_id ) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) AS total_requests,
                    SUM(tokens_used) AS total_tokens,
                    SUM(cost) AS total_cost,
                    AVG(tokens_used) AS avg_tokens_per_request
             FROM {$table_name}
             WHERE created_at >= %s
             AND user_id = %d",
                $date_limit,
                $user_id
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) AS total_requests,
                    SUM(tokens_used) AS total_tokens,
                    SUM(cost) AS total_cost,
                    AVG(tokens_used) AS avg_tokens_per_request
             FROM {$table_name}
             WHERE created_at >= %s",
                $date_limit
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $stats = $wpdb->get_row( $query );

        wp_cache_set( $cache_key, $stats, '', 300 );

        return $stats;
    }
    
    /**
     * Check if user has permission for action
     */
    public function check_permission($action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        switch ($action) {
            case 'generate_content':
                return user_can($user_id, 'edit_posts');
                
            case 'manage_templates':
                return user_can($user_id, 'edit_posts');
                
            case 'manage_settings':
                return user_can($user_id, 'manage_options');
                
            case 'view_usage_stats':
                return user_can($user_id, 'edit_posts');
                
            default:
                return false;
        }
    }
    
    /**
     * Validate API key format
     */
    public function validate_api_key_format($api_key) {
        // OpenAI API keys start with 'sk-' and are 51 characters long
        if (!preg_match('/^sk-[A-Za-z0-9]{48}$/', $api_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return $data; // Fallback if OpenSSL not available
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return $encrypted_data; // Fallback if OpenSSL not available
        }
        
        $data = base64_decode($encrypted_data);
        $key = $this->get_encryption_key();
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key() {
        $key = get_option('aicg_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('aicg_encryption_key', $key);
        }
        
        return $key;
    }
}