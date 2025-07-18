<?php
/**
 * Bulk operations for content generation
 */
class AICG_Bulk_Operations {
    
    private $logger;
    private $cache;
    private $generator;
    private $notices;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        $this->cache = new AICG_Cache();
        $this->generator = AI_Content_Generator::get_instance();
        $this->notices = new AICG_Admin_Notices();
        
        add_action('wp_ajax_aicg_bulk_generate', array($this, 'ajax_bulk_generate'));
        add_action('wp_ajax_aicg_bulk_status', array($this, 'ajax_bulk_status'));
        add_action('wp_ajax_aicg_bulk_cancel', array($this, 'ajax_bulk_cancel'));
        
        // Schedule bulk processing
        add_action('aicg_process_bulk_queue', array($this, 'process_bulk_queue'));
        
        // Initialize bulk queue table
        $this->create_bulk_queue_table();
    }
    
    /**
     * Create bulk queue table
     */
    private function create_bulk_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id varchar(32) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            operation_type varchar(50) NOT NULL DEFAULT 'generate',
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 10,
            prompt text NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'post',
            seo_enabled tinyint(1) NOT NULL DEFAULT 1,
            model varchar(50) NOT NULL DEFAULT 'gpt-3.5-turbo',
            temperature decimal(2,1) NOT NULL DEFAULT 0.7,
            max_tokens int(11) NOT NULL DEFAULT 2000,
            result longtext,
            error_message text,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime NULL,
            completed_at datetime NULL,
            INDEX batch_id (batch_id),
            INDEX status (status),
            INDEX user_id (user_id),
            INDEX priority (priority),
            INDEX created_at (created_at),
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX handler for bulk generation
     */
    public function ajax_bulk_generate() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_bulk_generate')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $operations = json_decode(stripslashes($_POST['operations']), true);
        
        if (!is_array($operations) || empty($operations)) {
            wp_send_json_error('Invalid operations data');
        }
        
        if (count($operations) > 50) {
            wp_send_json_error('Maximum 50 operations allowed per batch');
        }
        
        $batch_id = $this->create_bulk_batch($operations);
        
        if (!$batch_id) {
            wp_send_json_error('Failed to create bulk batch');
        }
        
        // Schedule immediate processing
        wp_schedule_single_event(time(), 'aicg_process_bulk_queue', array($batch_id));
        
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'message' => 'Bulk generation started',
            'operations_count' => count($operations)
        ));
    }
    
    /**
     * AJAX handler for bulk status
     */
    public function ajax_bulk_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_bulk_status')) {
            wp_die('Security check failed');
        }
        
        $batch_id = sanitize_text_field($_POST['batch_id']);
        $status = $this->get_batch_status($batch_id);
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX handler for bulk cancellation
     */
    public function ajax_bulk_cancel() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_bulk_cancel')) {
            wp_die('Security check failed');
        }
        
        $batch_id = sanitize_text_field($_POST['batch_id']);
        $result = $this->cancel_batch($batch_id);
        
        if ($result) {
            wp_send_json_success('Batch cancelled successfully');
        } else {
            wp_send_json_error('Failed to cancel batch');
        }
    }
    
    /**
     * Create bulk batch
     */
    public function create_bulk_batch($operations) {
        global $wpdb;
        
        $batch_id = wp_generate_uuid4();
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($operations as $index => $operation) {
                $data = array(
                    'batch_id' => $batch_id,
                    'user_id' => $user_id,
                    'operation_type' => 'generate',
                    'status' => 'pending',
                    'priority' => isset($operation['priority']) ? intval($operation['priority']) : 10,
                    'prompt' => sanitize_textarea_field($operation['prompt']),
                    'content_type' => sanitize_text_field($operation['content_type'] ?? 'post'),
                    'seo_enabled' => isset($operation['seo_enabled']) ? (int)$operation['seo_enabled'] : 1,
                    'model' => sanitize_text_field($operation['model'] ?? get_option('aicg_model', 'gpt-3.5-turbo')),
                    'temperature' => floatval($operation['temperature'] ?? get_option('aicg_temperature', 0.7)),
                    'max_tokens' => intval($operation['max_tokens'] ?? get_option('aicg_max_tokens', 2000))
                );
                
                $result = $wpdb->insert($table_name, $data);
                
                if (!$result) {
                    throw new Exception('Failed to insert operation: ' . $wpdb->last_error);
                }
            }
            
            $wpdb->query('COMMIT');
            
            $this->logger->info('Bulk batch created', array(
                'batch_id' => $batch_id,
                'operations_count' => count($operations),
                'user_id' => $user_id
            ));
            
            return $batch_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            $this->logger->error('Failed to create bulk batch', array(
                'error' => $e->getMessage(),
                'operations_count' => count($operations)
            ));
            
            return false;
        }
    }
    
    /**
     * Process bulk queue
     */
    public function process_bulk_queue($batch_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        // Get pending operations
        $where_clause = "status = 'pending' AND attempts < max_attempts";
        $params = array();
        
        if ($batch_id) {
            $where_clause .= " AND batch_id = %s";
            $params[] = $batch_id;
        }
        
        $operations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where_clause ORDER BY priority ASC, created_at ASC LIMIT 10",
            $params
        ));
        
        if (empty($operations)) {
            return;
        }
        
        $this->logger->info('Processing bulk queue', array(
            'batch_id' => $batch_id,
            'operations_count' => count($operations)
        ));
        
        foreach ($operations as $operation) {
            $this->process_single_operation($operation);
        }
        
        // Schedule next processing if there are more pending operations
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'pending' AND attempts < max_attempts",
            $params
        ));
        
        if ($remaining > 0) {
            wp_schedule_single_event(time() + 30, 'aicg_process_bulk_queue', array($batch_id));
        }
    }
    
    /**
     * Process single operation
     */
    private function process_single_operation($operation) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array(
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $operation->attempts + 1
            ),
            array('id' => $operation->id)
        );
        
        try {
            // Generate content
            $result = $this->generator->generate_content(
                $operation->prompt,
                $operation->content_type,
                $operation->seo_enabled
            );
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Update with success
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'result' => maybe_serialize($result),
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $operation->id)
            );
            
            $this->logger->info('Bulk operation completed', array(
                'operation_id' => $operation->id,
                'batch_id' => $operation->batch_id
            ));
            
        } catch (Exception $e) {
            $status = $operation->attempts >= $operation->max_attempts ? 'failed' : 'pending';
            
            $wpdb->update(
                $table_name,
                array(
                    'status' => $status,
                    'error_message' => $e->getMessage()
                ),
                array('id' => $operation->id)
            );
            
            $this->logger->error('Bulk operation failed', array(
                'operation_id' => $operation->id,
                'batch_id' => $operation->batch_id,
                'error' => $e->getMessage(),
                'attempts' => $operation->attempts + 1
            ));
        }
    }
    
    /**
     * Get batch status
     */
    public function get_batch_status($batch_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                MIN(created_at) as created_at,
                MAX(completed_at) as completed_at
            FROM $table_name 
            WHERE batch_id = %s",
            $batch_id
        ));
        
        if (!$stats) {
            return array(
                'exists' => false,
                'error' => 'Batch not found'
            );
        }
        
        $progress = $stats->total > 0 ? (($stats->completed + $stats->failed) / $stats->total) * 100 : 0;
        
        $status = 'in_progress';
        if ($stats->pending == 0 && $stats->processing == 0) {
            $status = $stats->failed > 0 ? 'completed_with_errors' : 'completed';
        } elseif ($stats->cancelled > 0) {
            $status = 'cancelled';
        }
        
        return array(
            'exists' => true,
            'batch_id' => $batch_id,
            'status' => $status,
            'progress' => round($progress, 1),
            'total' => (int)$stats->total,
            'pending' => (int)$stats->pending,
            'processing' => (int)$stats->processing,
            'completed' => (int)$stats->completed,
            'failed' => (int)$stats->failed,
            'cancelled' => (int)$stats->cancelled,
            'created_at' => $stats->created_at,
            'completed_at' => $stats->completed_at
        );
    }
    
    /**
     * Cancel batch
     */
    public function cancel_batch($batch_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'cancelled'),
            array(
                'batch_id' => $batch_id,
                'status' => 'pending'
            )
        );
        
        if ($result !== false) {
            $this->logger->info('Batch cancelled', array('batch_id' => $batch_id));
            return true;
        }
        
        return false;
    }
    
    /**
     * Get batch results
     */
    public function get_batch_results($batch_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $operations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, prompt, content_type, status, result, error_message, created_at, completed_at
            FROM $table_name 
            WHERE batch_id = %s 
            ORDER BY id ASC",
            $batch_id
        ));
        
        $results = array();
        
        foreach ($operations as $operation) {
            $result_data = array(
                'id' => $operation->id,
                'prompt' => $operation->prompt,
                'content_type' => $operation->content_type,
                'status' => $operation->status,
                'created_at' => $operation->created_at,
                'completed_at' => $operation->completed_at
            );
            
            if ($operation->status === 'completed' && $operation->result) {
                $result_data['result'] = maybe_unserialize($operation->result);
            }
            
            if ($operation->status === 'failed' && $operation->error_message) {
                $result_data['error'] = $operation->error_message;
            }
            
            $results[] = $result_data;
        }
        
        return $results;
    }
    
    /**
     * Get user's bulk batches
     */
    public function get_user_batches($user_id = null, $limit = 10) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $batches = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                batch_id,
                COUNT(*) as total_operations,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_operations,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_operations,
                MIN(created_at) as created_at,
                MAX(completed_at) as completed_at
            FROM $table_name 
            WHERE user_id = %d 
            GROUP BY batch_id 
            ORDER BY created_at DESC 
            LIMIT %d",
            $user_id,
            $limit
        ));
        
        $formatted_batches = array();
        
        foreach ($batches as $batch) {
            $progress = $batch->total_operations > 0 ? 
                (($batch->completed_operations + $batch->failed_operations) / $batch->total_operations) * 100 : 0;
            
            $status = 'in_progress';
            if ($batch->completed_operations + $batch->failed_operations == $batch->total_operations) {
                $status = $batch->failed_operations > 0 ? 'completed_with_errors' : 'completed';
            }
            
            $formatted_batches[] = array(
                'batch_id' => $batch->batch_id,
                'status' => $status,
                'progress' => round($progress, 1),
                'total_operations' => (int)$batch->total_operations,
                'completed_operations' => (int)$batch->completed_operations,
                'failed_operations' => (int)$batch->failed_operations,
                'created_at' => $batch->created_at,
                'completed_at' => $batch->completed_at
            );
        }
        
        return $formatted_batches;
    }
    
    /**
     * Cleanup old batches
     */
    public function cleanup_old_batches($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($deleted > 0) {
            $this->logger->info('Cleaned up old bulk batches', array('deleted_count' => $deleted));
        }
        
        return $deleted;
    }
    
    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_operations,
                COUNT(DISTINCT batch_id) as total_batches,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_operations,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_operations,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_operations,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_operations,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_processing_time
            FROM $table_name 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return array(
            'total_operations' => (int)$stats->total_operations,
            'total_batches' => (int)$stats->total_batches,
            'pending_operations' => (int)$stats->pending_operations,
            'processing_operations' => (int)$stats->processing_operations,
            'completed_operations' => (int)$stats->completed_operations,
            'failed_operations' => (int)$stats->failed_operations,
            'avg_processing_time' => round($stats->avg_processing_time, 2),
            'success_rate' => $stats->total_operations > 0 ? 
                round(($stats->completed_operations / $stats->total_operations) * 100, 2) : 0
        );
    }
    
    /**
     * Retry failed operations
     */
    public function retry_failed_operations($batch_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_bulk_queue';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null
            ),
            array(
                'batch_id' => $batch_id,
                'status' => 'failed'
            )
        );
        
        if ($result !== false) {
            // Schedule processing
            wp_schedule_single_event(time(), 'aicg_process_bulk_queue', array($batch_id));
            
            $this->logger->info('Retrying failed operations', array(
                'batch_id' => $batch_id,
                'operations_count' => $result
            ));
            
            return $result;
        }
        
        return false;
    }
}