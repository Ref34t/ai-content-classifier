<?php
/**
 * Comprehensive error logging system for AI Content Generator
 */
class AICG_Logger {
    
    private $log_file;
    private $log_level;
    private $max_log_size;
    private $max_log_files;
    
    // Log levels
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;
    
    private $log_levels = array(
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    );
    
    public function __construct() {
        $this->log_level = defined('WP_DEBUG') && WP_DEBUG ? self::DEBUG : self::ERROR;
        $this->max_log_size = 5 * 1024 * 1024; // 5MB
        $this->max_log_files = 5;
        
        // Set log file path
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ai-content-generator/logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Add .htaccess to protect logs
            $htaccess_file = $log_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "deny from all\n");
            }
        }
        
        $this->log_file = $log_dir . '/aicg-' . date('Y-m-d') . '.log';
        
        // Initialize logging
        $this->info('Logger initialized');
    }
    
    /**
     * Log emergency message
     */
    public function emergency($message, $context = array()) {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log alert message
     */
    public function alert($message, $context = array()) {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = array()) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = array()) {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = array()) {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log notice message
     */
    public function notice($message, $context = array()) {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = array()) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = array()) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Main logging method
     */
    public function log($level, $message, $context = array()) {
        // Check if we should log this level
        if ($level > $this->log_level) {
            return;
        }
        
        // Rotate log file if necessary
        $this->rotate_log_if_needed();
        
        // Format the log entry
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Write to file
        $this->write_to_file($log_entry);
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
        
        // For critical errors, also send email notification
        if ($level <= self::CRITICAL) {
            $this->send_critical_notification($level, $message, $context);
        }
    }
    
    /**
     * Format log entry
     */
    private function format_log_entry($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $level_name = $this->log_levels[$level];
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        // Build context string
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        // Build log entry
        $log_entry = sprintf(
            "[%s] %s: %s | User: %d | IP: %s | Memory: %s/%s%s\n",
            $timestamp,
            $level_name,
            $message,
            $user_id,
            $ip_address,
            $this->format_bytes($memory_usage),
            $this->format_bytes($memory_peak),
            $context_str
        );
        
        return $log_entry;
    }
    
    /**
     * Write to log file
     */
    private function write_to_file($log_entry) {
        // Use file locking to prevent corruption
        $fp = fopen($this->log_file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $log_entry);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
    
    /**
     * Rotate log file if it's too large
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $file_size = filesize($this->log_file);
        if ($file_size > $this->max_log_size) {
            $this->rotate_log();
        }
    }
    
    /**
     * Rotate log files
     */
    private function rotate_log() {
        $log_dir = dirname($this->log_file);
        $base_name = basename($this->log_file, '.log');
        
        // Move existing rotated files
        for ($i = $this->max_log_files - 1; $i >= 1; $i--) {
            $old_file = $log_dir . '/' . $base_name . '.' . $i . '.log';
            $new_file = $log_dir . '/' . $base_name . '.' . ($i + 1) . '.log';
            
            if (file_exists($old_file)) {
                if ($i == $this->max_log_files - 1) {
                    unlink($old_file); // Delete oldest file
                } else {
                    rename($old_file, $new_file);
                }
            }
        }
        
        // Move current file to .1
        $rotated_file = $log_dir . '/' . $base_name . '.1.log';
        rename($this->log_file, $rotated_file);
        
        // Compress old log file
        if (function_exists('gzencode')) {
            $content = file_get_contents($rotated_file);
            $compressed = gzencode($content);
            file_put_contents($rotated_file . '.gz', $compressed);
            unlink($rotated_file);
        }
    }
    
    /**
     * Send critical error notification
     */
    private function send_critical_notification($level, $message, $context) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        // Don't spam - only send one notification per hour for the same error
        $transient_key = 'aicg_critical_notification_' . md5($message);
        if (get_transient($transient_key)) {
            return;
        }
        
        set_transient($transient_key, true, HOUR_IN_SECONDS);
        
        $subject = sprintf('[%s] AI Content Generator Critical Error', get_bloginfo('name'));
        $body = sprintf(
            "A critical error occurred in the AI Content Generator plugin:\n\n" .
            "Level: %s\n" .
            "Message: %s\n" .
            "Time: %s\n" .
            "Site: %s\n" .
            "User: %d\n" .
            "IP: %s\n\n" .
            "Context: %s\n\n" .
            "Please check the plugin logs for more details.",
            $this->log_levels[$level],
            $message,
            date('Y-m-d H:i:s'),
            home_url(),
            get_current_user_id(),
            $this->get_client_ip(),
            json_encode($context, JSON_PRETTY_PRINT)
        );
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Format bytes for human reading
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Get log entries for admin viewing
     */
    public function get_log_entries($limit = 100, $level = null) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array();
        
        // Parse log entries
        foreach (array_reverse($lines) as $line) {
            if (count($entries) >= $limit) {
                break;
            }
            
            $entry = $this->parse_log_entry($line);
            if ($entry && ($level === null || $entry['level'] === $level)) {
                $entries[] = $entry;
            }
        }
        
        return $entries;
    }
    
    /**
     * Parse a log entry line
     */
    private function parse_log_entry($line) {
        $pattern = '/\[([^\]]+)\] ([A-Z]+): (.+?) \| User: (\d+) \| IP: ([^\s]+) \| Memory: ([^\/]+)\/([^\s]+)(.*)/';
        
        if (preg_match($pattern, $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3],
                'user_id' => intval($matches[4]),
                'ip' => $matches[5],
                'memory_usage' => $matches[6],
                'memory_peak' => $matches[7],
                'context' => isset($matches[8]) ? $matches[8] : ''
            );
        }
        
        return null;
    }
    
    /**
     * Clear log files
     */
    public function clear_logs() {
        $log_dir = dirname($this->log_file);
        $files = glob($log_dir . '/*.log*');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        $this->info('Log files cleared');
    }
    
    /**
     * Get log statistics
     */
    public function get_log_stats() {
        if (!file_exists($this->log_file)) {
            return array(
                'total_entries' => 0,
                'file_size' => 0,
                'last_entry' => null
            );
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $file_size = filesize($this->log_file);
        $last_line = end($lines);
        
        return array(
            'total_entries' => count($lines),
            'file_size' => $this->format_bytes($file_size),
            'last_entry' => $this->parse_log_entry($last_line)
        );
    }
}