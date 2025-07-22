<?php
/**
 * Admin bar integration for quick access to AI Content Classifier
 */
class AICG_Admin_Bar {
    
    private $logger;
    private $cache;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        $this->cache = new AICG_Cache();
        
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_admin_bar_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_bar_scripts'));
        
        // AJAX handlers for admin bar actions
        add_action('wp_ajax_aicg_quick_generate', array($this, 'ajax_quick_generate'));
        add_action('wp_ajax_aicg_admin_bar_stats', array($this, 'ajax_admin_bar_stats'));
    }
    
    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        // Check if API key is configured
        $storage = new AICG_Secure_Storage();
        $api_key = $storage->get_api_key();
        
        if (empty($api_key)) {
            // API key not configured, don't show admin bar menu
            return;
        }
        
        // Get current usage stats
        $stats = $this->get_quick_stats();
        
        // Main menu item
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-main',
            'title' => '<span class="ab-icon dashicons-edit-large"></span>' . __('AI Content', 'ai-content-classifier'),
            'href' => admin_url('admin.php?page=ai-content-generator'),
            'meta' => array(
                'title' => __('AI Content Classifier', 'ai-content-classifier')
            )
        ));
        
        // Quick generate submenu
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-quick-generate',
            'parent' => 'aicg-main',
            'title' => '🚀 ' . __('Quick Generate', 'ai-content-classifier'),
            'href' => '#',
            'meta' => array(
                'title' => __('Generate content quickly', 'ai-content-classifier'),
                'class' => 'aicg-quick-generate-trigger'
            )
        ));
        
        // Templates submenu
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-templates',
            'parent' => 'aicg-main',
            'title' => '📋 ' . __('Templates', 'ai-content-classifier'),
            'href' => admin_url('admin.php?page=aicg-templates'),
            'meta' => array(
                'title' => __('Manage templates', 'ai-content-classifier')
            )
        ));
        
        // Recent templates
        $recent_templates = $this->get_recent_templates(3);
        if (!empty($recent_templates)) {
            foreach ($recent_templates as $template) {
                $wp_admin_bar->add_node(array(
                    'id' => 'aicg-template-' . $template->id,
                    'parent' => 'aicg-templates',
                    'title' => '• ' . esc_html($template->name),
                    'href' => admin_url('admin.php?page=ai-content-generator&template=' . $template->id),
                    'meta' => array(
                        'title' => __('Use template: ', 'ai-content-classifier') . esc_attr($template->name)
                    )
                ));
            }
        }
        
        // Bulk operations
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-bulk',
            'parent' => 'aicg-main',
            'title' => '📦 ' . __('Bulk Operations', 'ai-content-classifier'),
            'href' => admin_url('admin.php?page=aicg-bulk'),
            'meta' => array(
                'title' => __('Bulk content generation', 'ai-content-classifier')
            )
        ));
        
        // Stats submenu
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-stats',
            'parent' => 'aicg-main',
            'title' => '📊 ' . __('Usage Stats', 'ai-content-classifier'),
            'href' => '#',
            'meta' => array(
                'title' => __('View usage statistics', 'ai-content-classifier'),
                'class' => 'aicg-stats-trigger'
            )
        ));
        
        // Usage summary
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-usage-summary',
            'parent' => 'aicg-stats',
            'title' => sprintf(
                /* translators: %1$d: number of generations today, %2$s: cost amount */
                __('Today: %1$d generations | Cost: $%2$s', 'ai-content-classifier'),
                $stats['today_generations'],
                number_format($stats['today_cost'], 3)
            ),
            'href' => admin_url('admin.php?page=aicg-stats'),
            'meta' => array(
                'title' => __('View detailed statistics', 'ai-content-classifier')
            )
        ));
        
        // Cache status
        $cache_stats = array('total_hits' => 0, 'hit_rate' => 0.0);
        try {
            if (class_exists('AICG_Cache')) {
                $cache_stats = $this->cache->get_cache_stats();
            }
        } catch (Exception $e) {
            // Ignore cache errors
        }
        
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-cache-status',
            'parent' => 'aicg-stats',
            'title' => sprintf(
                /* translators: %1$d: number of cache hits, %2$.1f: hit rate percentage */
                __('Cache: %1$d hits (%2$.1f%%)', 'ai-content-classifier'),
                $cache_stats['total_hits'],
                $cache_stats['hit_rate']
            ),
            'href' => admin_url('admin.php?page=aicg-cache'),
            'meta' => array(
                'title' => __('Cache performance', 'ai-content-classifier')
            )
        ));
        
        // Settings
        $wp_admin_bar->add_node(array(
            'id' => 'aicg-settings',
            'parent' => 'aicg-main',
            'title' => '⚙️ ' . __('Settings', 'ai-content-classifier'),
            'href' => admin_url('admin.php?page=aicg-settings'),
            'meta' => array(
                'title' => __('Plugin settings', 'ai-content-classifier')
            )
        ));
        
        // Quick actions for current post (if editing)
        if (is_admin() && isset($_GET['post']) && current_user_can('edit_post', sanitize_text_field(wp_unslash($_GET['post'])))) {
            $post_id = intval(sanitize_text_field(wp_unslash($_GET['post'])));
            $post = get_post($post_id);
            
            if ($post) {
                $wp_admin_bar->add_node(array(
                    'id' => 'aicg-enhance-post',
                    'parent' => 'aicg-main',
                    'title' => '✨ ' . __('Enhance This Post', 'ai-content-classifier'),
                    'href' => '#',
                    'meta' => array(
                        'title' => __('Enhance current post with AI', 'ai-content-classifier'),
                        'class' => 'aicg-enhance-post-trigger',
                        'data-post-id' => $post_id
                    )
                ));
            }
        }
    }
    
    /**
     * Enqueue admin bar scripts
     */
    public function enqueue_admin_bar_scripts() {
        if (!is_admin_bar_showing() || !current_user_can('edit_posts')) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Add inline script for admin bar functionality
        $script = "
        jQuery(document).ready(function($) {
            // Quick generate modal
            $('.aicg-quick-generate-trigger').on('click', function(e) {
                e.preventDefault();
                aicg_show_quick_generate_modal();
            });
            
            // Stats modal
            $('.aicg-stats-trigger').on('click', function(e) {
                e.preventDefault();
                aicg_show_stats_modal();
            });
            
            // Enhance post
            $('.aicg-enhance-post-trigger').on('click', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                aicg_enhance_post(postId);
            });
            
            // Add notification badge for pending bulk operations
            aicg_check_bulk_operations();
        });
        
        function aicg_show_quick_generate_modal() {
            var modal = $('<div id=\"aicg-quick-modal\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;\"></div>');
            
            var content = $('<div style=\"background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%;\"></div>');
            content.html('<h2>" . __('Quick Generate', 'ai-content-classifier') . "</h2><form id=\"aicg-quick-form\"><textarea id=\"aicg-quick-prompt\" placeholder=\"" . __('Enter your prompt here...', 'ai-content-classifier') . "\" style=\"width: 100%; height: 100px; margin-bottom: 10px;\"></textarea><br><button type=\"submit\">" . __('Generate', 'ai-content-classifier') . "</button> <button type=\"button\" onclick=\"$(\\\"#aicg-quick-modal\\\").remove()\">" . __('Cancel', 'ai-content-classifier') . "</button></form>');
            
            modal.append(content);
            $('body').append(modal);
            
            $('#aicg-quick-form').on('submit', function(e) {
                e.preventDefault();
                var prompt = $('#aicg-quick-prompt').val();
                if (prompt.trim()) {
                    aicg_quick_generate(prompt);
                }
            });
        }
        
        function aicg_quick_generate(prompt) {
            $.post(ajaxurl, {
                action: 'aicg_quick_generate',
                prompt: prompt,
                nonce: '" . wp_create_nonce('aicg_quick_generate') . "'
            }).done(function(response) {
                if (response.success) {
                    alert('" . __('Content generated successfully!', 'ai-content-classifier') . "');
                    $('#aicg-quick-modal').remove();
                } else {
                    alert('" . __('Error: ', 'ai-content-classifier') . "' + response.data);
                }
            });
        }
        
        function aicg_show_stats_modal() {
            $.post(ajaxurl, {
                action: 'aicg_admin_bar_stats',
                nonce: '" . wp_create_nonce('aicg_admin_bar_stats') . "'
            }).done(function(response) {
                if (response.success) {
                    var stats = response.data;
                    var modal = $('<div id=\"aicg-stats-modal\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;\"></div>');
                    
                    var content = $('<div style=\"background: white; padding: 20px; border-radius: 8px; max-width: 400px; width: 90%;\"></div>');
                    content.html('<h2>" . __('Usage Stats', 'ai-content-classifier') . "</h2><ul><li>" . __('Today: ', 'ai-content-classifier') . "' + stats.today_generations + ' " . __('generations', 'ai-content-classifier') . "</li><li>" . __('This week: ', 'ai-content-classifier') . "' + stats.week_generations + ' " . __('generations', 'ai-content-classifier') . "</li><li>" . __('Total cost: $', 'ai-content-classifier') . "' + stats.total_cost + '</li><li>" . __('Cache hits: ', 'ai-content-classifier') . "' + stats.cache_hits + '</li></ul><button onclick=\"$(\\\"#aicg-stats-modal\\\").remove()\">" . __('Close', 'ai-content-classifier') . "</button>');
                    
                    modal.append(content);
                    $('body').append(modal);
                }
            });
        }
        
        function aicg_enhance_post(postId) {
            if (confirm('" . __('This will enhance the current post with AI-generated content. Continue?', 'ai-content-classifier') . "')) {
                // Implementation would go here
                alert('" . __('Post enhancement feature coming soon!', 'ai-content-classifier') . "');
            }
        }
        
        function aicg_check_bulk_operations() {
            // Check for pending bulk operations and show notification
            // This would poll the server for active bulk operations
        }
        ";
        
        wp_add_inline_script('jquery', $script);
        
        // Add admin bar CSS
        wp_add_inline_style('admin-bar', '
            #wpadminbar .aicg-notification-badge {
                background: #ff6b6b;
                color: white;
                border-radius: 50%;
                padding: 2px 6px;
                font-size: 10px;
                margin-left: 5px;
            }
            
            #wpadminbar .aicg-quick-stats {
                font-size: 11px;
                opacity: 0.8;
            }
            
            #aicg-quick-modal textarea {
                font-family: monospace;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
            }
            
            #aicg-quick-modal button {
                background: #0073aa;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
                margin-right: 5px;
            }
            
            #aicg-quick-modal button:hover {
                background: #005a87;
            }
        ');
    }
    
    /**
     * AJAX handler for quick generate
     */
    public function ajax_quick_generate() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'aicg_quick_generate')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions', 'ai-content-classifier'));
        }
        
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt is required', 'ai-content-classifier'));
        }
        
        try {
            $generator = AI_Content_Generator::get_instance();
            $result = $generator->generate_content($prompt, 'post', true);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            // Store in user meta for quick access
            $user_id = get_current_user_id();
            $quick_results = get_user_meta($user_id, 'aicg_quick_results', true);
            
            if (!is_array($quick_results)) {
                $quick_results = array();
            }
            
            array_unshift($quick_results, array(
                'prompt' => $prompt,
                'result' => $result,
                'timestamp' => time()
            ));
            
            // Keep only last 5 results
            $quick_results = array_slice($quick_results, 0, 5);
            update_user_meta($user_id, 'aicg_quick_results', $quick_results);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for admin bar stats
     */
    public function ajax_admin_bar_stats() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'aicg_admin_bar_stats')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        $stats = $this->get_detailed_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get quick stats for admin bar
     */
    private function get_quick_stats() {
        $user_id = get_current_user_id();
        
        // Get today's usage
        $today_start = gmdate('Y-m-d 00:00:00');
        $today_end = gmdate('Y-m-d 23:59:59');
        
        global $wpdb;
        $usage_table = $wpdb->prefix . 'aicg_usage_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$usage_table'") != $usage_table) {
            return array(
                'today_generations' => 0,
                'today_cost' => 0.0
            );
        }
        
        // If user is not logged in, show 0 stats
        if (!$user_id) {
            return array(
                'today_generations' => 0,
                'today_cost' => 0.0
            );
        }
        
        $today_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as generations, COALESCE(SUM(cost), 0) as cost
            FROM $usage_table 
            WHERE user_id = %d AND created_at BETWEEN %s AND %s",
            $user_id,
            $today_start,
            $today_end
        ));
        
        return array(
            'today_generations' => (int)$today_stats->generations,
            'today_cost' => (float)$today_stats->cost
        );
    }
    
    /**
     * Get detailed stats for modal
     */
    private function get_detailed_stats() {
        $user_id = get_current_user_id();
        
        global $wpdb;
        $usage_table = $wpdb->prefix . 'aicg_usage_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$usage_table'") != $usage_table) {
            return array(
                'today_generations' => 0,
                'today_cost' => '0.000',
                'week_generations' => 0,
                'week_cost' => '0.000',
                'total_generations' => 0,
                'total_cost' => '0.000',
                'cache_hits' => 0,
                'cache_hit_rate' => 0.0
            );
        }
        
        // Today's stats
        $today_start = gmdate('Y-m-d 00:00:00');
        $today_end = gmdate('Y-m-d 23:59:59');
        
        $today_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as generations, COALESCE(SUM(cost), 0) as cost
            FROM $usage_table 
            WHERE user_id = %d AND created_at BETWEEN %s AND %s",
            $user_id,
            $today_start,
            $today_end
        ));
        
        // Week's stats
        $week_start = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
        
        $week_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as generations, COALESCE(SUM(cost), 0) as cost
            FROM $usage_table 
            WHERE user_id = %d AND created_at >= %s",
            $user_id,
            $week_start
        ));
        
        // Total stats
        $total_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as generations, COALESCE(SUM(cost), 0) as cost
            FROM $usage_table 
            WHERE user_id = %d",
            $user_id
        ));
        
        // Cache stats (default values if cache not available)
        $cache_stats = array(
            'total_hits' => 0,
            'hit_rate' => 0.0
        );
        
        if (class_exists('AICG_Cache')) {
            try {
                $cache_stats = $this->cache->get_cache_stats();
            } catch (Exception $e) {
                // Ignore cache errors
            }
        }
        
        return array(
            'today_generations' => (int)$today_stats->generations,
            'today_cost' => number_format((float)$today_stats->cost, 3),
            'week_generations' => (int)$week_stats->generations,
            'week_cost' => number_format((float)$week_stats->cost, 3),
            'total_generations' => (int)$total_stats->generations,
            'total_cost' => number_format((float)$total_stats->cost, 3),
            'cache_hits' => $cache_stats['total_hits'],
            'cache_hit_rate' => $cache_stats['hit_rate']
        );
    }
    
    /**
     * Get recent templates
     */
    private function get_recent_templates($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}aicg_templates 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links, $file) {
        if ($file === AICG_PLUGIN_BASENAME) {
            $settings_link = '<a href="' . admin_url('admin.php?page=aicg-settings') . '">' . __('Settings', 'ai-content-classifier') . '</a>';
            $generate_link = '<a href="' . admin_url('admin.php?page=ai-content-generator') . '">' . __('Generate', 'ai-content-classifier') . '</a>';
            
            array_unshift($links, $settings_link, $generate_link);
        }
        
        return $links;
    }
    
    /**
     * Add plugin meta links
     */
    public function add_plugin_meta_links($links, $file) {
        if ($file === AICG_PLUGIN_BASENAME) {
            $links[] = '<a href="https://github.com/yourusername/ai-content-generator" target="_blank">' . __('GitHub', 'ai-content-classifier') . '</a>';
            $links[] = '<a href="https://wordpress.org/support/plugin/ai-content-generator" target="_blank">' . __('Support', 'ai-content-classifier') . '</a>';
            $links[] = '<a href="https://docs.example.com/ai-content-generator" target="_blank">' . __('Documentation', 'ai-content-classifier') . '</a>';
        }
        
        return $links;
    }
    
    /**
     * Check if bulk operations are running
     */
    private function get_bulk_operations_status() {
        global $wpdb;
        
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicg_bulk_queue 
            WHERE status IN ('pending', 'processing')"
        );
        
        return (int)$pending_count;
    }
    
    /**
     * Add contextual help
     */
    public function add_contextual_help($contextual_help, $screen_id, $screen) {
        if (strpos($screen_id, 'aicg') !== false) {
            $screen->add_help_tab(array(
                'id' => 'aicg-quick-help',
                'title' => __('Quick Help', 'ai-content-classifier'),
                'content' => '
                    <h3>' . __('AI Content Classifier', 'ai-content-classifier') . '</h3>
                    <p>' . __('Use the admin bar menu for quick access to:', 'ai-content-classifier') . '</p>
                    <ul>
                        <li><strong>' . __('Quick Generate:', 'ai-content-classifier') . '</strong> ' . __('Generate content with a simple prompt', 'ai-content-classifier') . '</li>
                        <li><strong>' . __('Templates:', 'ai-content-classifier') . '</strong> ' . __('Access your saved templates', 'ai-content-classifier') . '</li>
                        <li><strong>' . __('Usage Stats:', 'ai-content-classifier') . '</strong> ' . __('View your API usage and costs', 'ai-content-classifier') . '</li>
                        <li><strong>' . __('Settings:', 'ai-content-classifier') . '</strong> ' . __('Configure the plugin', 'ai-content-classifier') . '</li>
                    </ul>
                '
            ));
        }
        
        return $contextual_help;
    }
}