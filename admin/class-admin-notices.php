<?php
/**
 * Admin notices system for better user feedback
 */
class AICG_Admin_Notices {
    
    private $notices = array();
    private $logger;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        
        add_action('admin_notices', array($this, 'display_notices'));
        add_action('wp_ajax_aicg_dismiss_notice', array($this, 'ajax_dismiss_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Load stored notices
        $this->load_notices();
    }
    
    /**
     * Add a notice
     */
    public function add_notice($id, $message, $type = 'info', $dismissible = true, $capability = 'edit_posts') {
        $notice = array(
            'id' => $id,
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'capability' => $capability,
            'timestamp' => time(),
            'screen' => $this->get_current_screen(),
            'user_id' => get_current_user_id()
        );
        
        $this->notices[$id] = $notice;
        $this->save_notices();
        
        $this->logger->info('Admin notice added', array(
            'notice_id' => $id,
            'type' => $type,
            'message' => $message
        ));
    }
    
    /**
     * Add success notice
     */
    public function add_success($id, $message, $dismissible = true) {
        $this->add_notice($id, $message, 'success', $dismissible);
    }
    
    /**
     * Add error notice
     */
    public function add_error($id, $message, $dismissible = true) {
        $this->add_notice($id, $message, 'error', $dismissible);
    }
    
    /**
     * Add warning notice
     */
    public function add_warning($id, $message, $dismissible = true) {
        $this->add_notice($id, $message, 'warning', $dismissible);
    }
    
    /**
     * Add info notice
     */
    public function add_info($id, $message, $dismissible = true) {
        $this->add_notice($id, $message, 'info', $dismissible);
    }
    
    /**
     * Add temporary notice (auto-expires)
     */
    public function add_temporary($id, $message, $type = 'info', $duration = 300) {
        $this->add_notice($id, $message, $type, true);
        
        // Set expiration
        $this->notices[$id]['expires'] = time() + $duration;
        $this->save_notices();
    }
    
    /**
     * Remove a notice
     */
    public function remove_notice($id) {
        if (isset($this->notices[$id])) {
            unset($this->notices[$id]);
            $this->save_notices();
            
            $this->logger->debug('Admin notice removed', array('notice_id' => $id));
        }
    }
    
    /**
     * Display notices
     */
    public function display_notices() {
        $current_screen = get_current_screen();
        $current_user_id = get_current_user_id();
        
        // Clean up expired notices
        $this->cleanup_expired_notices();
        
        foreach ($this->notices as $notice) {
            // Check capability
            if (!current_user_can($notice['capability'])) {
                continue;
            }
            
            // Check if notice is dismissed for current user
            if ($this->is_notice_dismissed($notice['id'], $current_user_id)) {
                continue;
            }
            
            // Check screen restriction
            if (!empty($notice['screen']) && $current_screen && $notice['screen'] !== $current_screen->id) {
                continue;
            }
            
            $this->render_notice($notice);
        }
    }
    
    /**
     * Render individual notice
     */
    private function render_notice($notice) {
        $classes = array('notice', 'notice-' . $notice['type']);
        
        if ($notice['dismissible']) {
            $classes[] = 'is-dismissible';
        }
        
        $class_attr = implode(' ', $classes);
        $data_attrs = '';
        
        if ($notice['dismissible']) {
            $data_attrs = 'data-notice-id="' . esc_attr($notice['id']) . '"';
        }
        
        ?>
        <div class="<?php echo esc_attr($class_attr); ?>" <?php echo $data_attrs; ?>>
            <p>
                <?php echo wp_kses_post($notice['message']); ?>
                <?php if (isset($notice['timestamp'])): ?>
                    <span class="notice-time" style="font-size: 0.9em; color: #666; margin-left: 10px;">
                        <?php echo human_time_diff($notice['timestamp']); ?> ago
                    </span>
                <?php endif; ?>
            </p>
            
            <?php if ($notice['dismissible']): ?>
                <button type="button" class="notice-dismiss aicg-notice-dismiss" 
                        data-notice-id="<?php echo esc_attr($notice['id']); ?>">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_dismiss_notice')) {
            wp_die('Security check failed');
        }
        
        $notice_id = sanitize_text_field($_POST['notice_id']);
        $user_id = get_current_user_id();
        
        $this->dismiss_notice($notice_id, $user_id);
        
        wp_send_json_success('Notice dismissed');
    }
    
    /**
     * Dismiss notice for user
     */
    private function dismiss_notice($notice_id, $user_id) {
        $dismissed = get_user_meta($user_id, 'aicg_dismissed_notices', true);
        
        if (!is_array($dismissed)) {
            $dismissed = array();
        }
        
        $dismissed[$notice_id] = time();
        update_user_meta($user_id, 'aicg_dismissed_notices', $dismissed);
        
        $this->logger->debug('Notice dismissed', array(
            'notice_id' => $notice_id,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Check if notice is dismissed
     */
    private function is_notice_dismissed($notice_id, $user_id) {
        $dismissed = get_user_meta($user_id, 'aicg_dismissed_notices', true);
        
        return is_array($dismissed) && isset($dismissed[$notice_id]);
    }
    
    /**
     * Enqueue scripts for notices
     */
    public function enqueue_scripts($hook) {
        // Only load on admin pages
        if (!is_admin()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Add inline script for notice dismissal
        $script = "
        jQuery(document).ready(function($) {
            $(document).on('click', '.aicg-notice-dismiss', function(e) {
                e.preventDefault();
                
                var noticeId = $(this).data('notice-id');
                var notice = $(this).closest('.notice');
                
                $.post(ajaxurl, {
                    action: 'aicg_dismiss_notice',
                    notice_id: noticeId,
                    nonce: '" . wp_create_nonce('aicg_dismiss_notice') . "'
                }).done(function() {
                    notice.fadeOut();
                });
            });
            
            // Auto-dismiss temporary notices
            $('.notice[data-expires]').each(function() {
                var notice = $(this);
                var expires = parseInt(notice.data('expires')) * 1000;
                var now = Date.now();
                
                if (expires > now) {
                    setTimeout(function() {
                        notice.fadeOut();
                    }, expires - now);
                }
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * Load stored notices
     */
    private function load_notices() {
        $stored_notices = get_option('aicg_admin_notices', array());
        
        if (is_array($stored_notices)) {
            $this->notices = $stored_notices;
        }
    }
    
    /**
     * Save notices to database
     */
    private function save_notices() {
        update_option('aicg_admin_notices', $this->notices);
    }
    
    /**
     * Cleanup expired notices
     */
    private function cleanup_expired_notices() {
        $current_time = time();
        $cleaned = false;
        
        foreach ($this->notices as $id => $notice) {
            if (isset($notice['expires']) && $notice['expires'] < $current_time) {
                unset($this->notices[$id]);
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            $this->save_notices();
        }
    }
    
    /**
     * Get current screen ID
     */
    private function get_current_screen() {
        $screen = get_current_screen();
        return $screen ? $screen->id : null;
    }
    
    /**
     * Add plugin activation notice
     */
    public function add_activation_notice() {
        $this->add_success(
            'aicg_activated',
            'AI Content Generator has been activated successfully! <a href="' . admin_url('admin.php?page=aicg-settings') . '">Configure your OpenAI API key</a> to get started.',
            true
        );
    }
    
    /**
     * Add API key missing notice
     */
    public function add_api_key_missing_notice() {
        $storage = new AICG_Secure_Storage();
        $api_key = $storage->get_api_key();
        
        if (empty($api_key)) {
            $this->add_warning(
                'aicg_api_key_missing',
                'AI Content Generator requires an OpenAI API key to function. <a href="' . admin_url('admin.php?page=aicg-settings') . '">Configure your API key</a>.',
                true
            );
        }
    }
    
    /**
     * Add rate limit warning
     */
    public function add_rate_limit_warning($limit_type = 'general') {
        $messages = array(
            'general' => 'You are approaching your API rate limit. Consider upgrading your plan or waiting before making more requests.',
            'hourly' => 'You have reached your hourly rate limit. Please wait before making more requests.',
            'daily' => 'You have reached your daily rate limit. Please wait until tomorrow or upgrade your plan.'
        );
        
        $message = isset($messages[$limit_type]) ? $messages[$limit_type] : $messages['general'];
        
        $this->add_warning('aicg_rate_limit_' . $limit_type, $message, true);
    }
    
    /**
     * Add generation success notice
     */
    public function add_generation_success($content_type, $words_count = null) {
        $message = "Content generated successfully!";
        
        if ($words_count) {
            $message .= " Generated {$words_count} words of {$content_type} content.";
        }
        
        $this->add_temporary('aicg_generation_success', $message, 'success', 10);
    }
    
    /**
     * Add generation error notice
     */
    public function add_generation_error($error_message) {
        $this->add_error(
            'aicg_generation_error',
            'Content generation failed: ' . $error_message,
            true
        );
    }
    
    /**
     * Add template saved notice
     */
    public function add_template_saved($template_name) {
        $this->add_temporary(
            'aicg_template_saved',
            "Template '{$template_name}' has been saved successfully!",
            'success',
            10
        );
    }
    
    /**
     * Add maintenance notice
     */
    public function add_maintenance_notice($message, $start_time = null, $end_time = null) {
        $notice_message = $message;
        
        if ($start_time && $end_time) {
            $notice_message .= " Scheduled from " . date('Y-m-d H:i', $start_time) . " to " . date('Y-m-d H:i', $end_time) . ".";
        }
        
        $this->add_warning('aicg_maintenance', $notice_message, false);
    }
    
    /**
     * Add update notice
     */
    public function add_update_notice($version, $changelog_url = null) {
        $message = "AI Content Generator has been updated to version {$version}.";
        
        if ($changelog_url) {
            $message .= " <a href='{$changelog_url}' target='_blank'>View changelog</a>.";
        }
        
        $this->add_info('aicg_updated_' . $version, $message, true);
    }
    
    /**
     * Add quota warning
     */
    public function add_quota_warning($usage_percent) {
        if ($usage_percent >= 90) {
            $type = 'error';
            $message = 'You have used ' . $usage_percent . '% of your API quota. Please upgrade your plan to continue using the service.';
        } elseif ($usage_percent >= 75) {
            $type = 'warning';
            $message = 'You have used ' . $usage_percent . '% of your API quota. Consider upgrading your plan.';
        } else {
            return; // No warning needed
        }
        
        $this->add_notice('aicg_quota_warning', $message, $type, true);
    }
    
    /**
     * Clear all notices
     */
    public function clear_all_notices() {
        $this->notices = array();
        $this->save_notices();
        
        $this->logger->info('All admin notices cleared');
    }
    
    /**
     * Clear notices for specific user
     */
    public function clear_user_notices($user_id) {
        delete_user_meta($user_id, 'aicg_dismissed_notices');
        
        $this->logger->info('User notices cleared', array('user_id' => $user_id));
    }
    
    /**
     * Get notice statistics
     */
    public function get_notice_stats() {
        $stats = array(
            'total_notices' => count($this->notices),
            'by_type' => array(),
            'dismissed_count' => 0
        );
        
        foreach ($this->notices as $notice) {
            $type = $notice['type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }
        
        // Count dismissed notices across all users
        global $wpdb;
        $dismissed_meta = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'aicg_dismissed_notices'"
        );
        
        foreach ($dismissed_meta as $meta) {
            $dismissed = maybe_unserialize($meta->meta_value);
            if (is_array($dismissed)) {
                $stats['dismissed_count'] += count($dismissed);
            }
        }
        
        return $stats;
    }
}