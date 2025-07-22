<?php
/**
 * Template editing functionality with version control
 */
class AICG_Template_Editor {
    
    private $logger;
    private $notices;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        $this->notices = new AICG_Admin_Notices();
        
        add_action('wp_ajax_aicg_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aicg_load_template', array($this, 'ajax_load_template'));
        add_action('wp_ajax_aicg_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aicg_duplicate_template', array($this, 'ajax_duplicate_template'));
        add_action('wp_ajax_aicg_preview_template', array($this, 'ajax_preview_template'));
        add_action('wp_ajax_aicg_validate_template', array($this, 'ajax_validate_template'));
        add_action('wp_ajax_aicg_template_history', array($this, 'ajax_template_history'));
        add_action('wp_ajax_aicg_restore_template', array($this, 'ajax_restore_template'));
        
        // Create template versions table
        $this->create_template_versions_table();
    }
    
    /**
     * Create template versions table for version control
     */
    private function create_template_versions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aicg_template_versions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            version_number int(11) NOT NULL DEFAULT 1,
            name varchar(255) NOT NULL,
            prompt text NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'post',
            seo_enabled tinyint(1) NOT NULL DEFAULT 1,
            variables longtext,
            metadata longtext,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            change_log text,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY version_number (version_number),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX handler for saving template
     */
    public function ajax_save_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_save_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions', 'ai-content-classifier'));
        }
        
        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['name']);
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $seo_enabled = isset($_POST['seo_enabled']) ? 1 : 0;
        $variables = json_decode(stripslashes($_POST['variables']), true);
        $change_log = sanitize_textarea_field($_POST['change_log']);
        
        // Validate template
        $validation_result = $this->validate_template($name, $prompt, $content_type);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Save template
        $result = $this->save_template($template_id, $name, $prompt, $content_type, $seo_enabled, $variables, $change_log);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'template_id' => $result['template_id'],
            'version_number' => $result['version_number'],
            'message' => __('Template saved successfully', 'ai-content-classifier')
        ));
    }
    
    /**
     * AJAX handler for loading template
     */
    public function ajax_load_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_load_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        $template_id = intval($_POST['template_id']);
        $version_number = isset($_POST['version_number']) ? intval($_POST['version_number']) : null;
        
        $template = $this->load_template($template_id, $version_number);
        
        if (!$template) {
            wp_send_json_error(__('Template not found', 'ai-content-classifier'));
        }
        
        wp_send_json_success($template);
    }
    
    /**
     * AJAX handler for deleting template
     */
    public function ajax_delete_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_delete_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions', 'ai-content-classifier'));
        }
        
        $template_id = intval($_POST['template_id']);
        
        $result = $this->delete_template($template_id);
        
        if ($result) {
            wp_send_json_success(__('Template deleted successfully', 'ai-content-classifier'));
        } else {
            wp_send_json_error(__('Failed to delete template', 'ai-content-classifier'));
        }
    }
    
    /**
     * AJAX handler for duplicating template
     */
    public function ajax_duplicate_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_duplicate_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions', 'ai-content-classifier'));
        }
        
        $template_id = intval($_POST['template_id']);
        $new_name = sanitize_text_field($_POST['new_name']);
        
        $result = $this->duplicate_template($template_id, $new_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'template_id' => $result,
            'message' => __('Template duplicated successfully', 'ai-content-classifier')
        ));
    }
    
    /**
     * AJAX handler for template preview
     */
    public function ajax_preview_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_preview_template')) {
            wp_die('Security check failed');
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $variables = json_decode(stripslashes($_POST['variables']), true);
        
        $preview = $this->preview_template($prompt, $content_type, $variables);
        
        wp_send_json_success($preview);
    }
    
    /**
     * AJAX handler for template validation
     */
    public function ajax_validate_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_validate_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        $name = sanitize_text_field($_POST['name']);
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $content_type = sanitize_text_field($_POST['content_type']);
        
        $validation_result = $this->validate_template($name, $prompt, $content_type);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        wp_send_json_success(__('Template is valid', 'ai-content-classifier'));
    }
    
    /**
     * AJAX handler for template history
     */
    public function ajax_template_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_template_history')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']);
        
        $history = $this->get_template_history($template_id);
        
        wp_send_json_success($history);
    }
    
    /**
     * AJAX handler for restoring template version
     */
    public function ajax_restore_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'aicg_restore_template')) {
            wp_die(esc_html__('Security check failed', 'ai-content-classifier'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions', 'ai-content-classifier'));
        }
        
        $template_id = intval($_POST['template_id']);
        $version_number = intval($_POST['version_number']);
        
        $result = $this->restore_template_version($template_id, $version_number);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Template version restored successfully', 'ai-content-classifier'));
    }
    
    /**
     * Save template with version control
     */
    public function save_template($template_id, $name, $prompt, $content_type, $seo_enabled, $variables = null, $change_log = '') {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $is_new = $template_id === 0;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            if ($is_new) {
                // Create new template
                $result = $wpdb->insert(
                    $wpdb->prefix . 'aicg_templates',
                    array(
                        'name' => $name,
                        'prompt' => $prompt,
                        'content_type' => $content_type,
                        'seo_enabled' => $seo_enabled,
                        'created_at' => current_time('mysql')
                    )
                );
                
                if (!$result) {
                    throw new Exception('Failed to create template');
                }
                
                $template_id = $wpdb->insert_id;
                $version_number = 1;
                
            } else {
                // Update existing template
                $result = $wpdb->update(
                    $wpdb->prefix . 'aicg_templates',
                    array(
                        'name' => $name,
                        'prompt' => $prompt,
                        'content_type' => $content_type,
                        'seo_enabled' => $seo_enabled
                    ),
                    array('id' => $template_id)
                );
                
                if ($result === false) {
                    throw new Exception('Failed to update template');
                }
                
                // Get next version number
                $version_number = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$wpdb->prefix}aicg_template_versions WHERE template_id = %d",
                    $template_id
                ));
                
                // Deactivate previous versions
                $wpdb->update(
                    $wpdb->prefix . 'aicg_template_versions',
                    array('is_active' => 0),
                    array('template_id' => $template_id)
                );
            }
            
            // Create version record
            $version_data = array(
                'template_id' => $template_id,
                'version_number' => $version_number,
                'name' => $name,
                'prompt' => $prompt,
                'content_type' => $content_type,
                'seo_enabled' => $seo_enabled,
                'variables' => $variables ? json_encode($variables) : null,
                'created_by' => $user_id,
                'change_log' => $change_log,
                'is_active' => 1
            );
            
            $result = $wpdb->insert($wpdb->prefix . 'aicg_template_versions', $version_data);
            
            if (!$result) {
                throw new Exception('Failed to create version record');
            }
            
            $wpdb->query('COMMIT');
            
            $this->logger->info('Template saved', array(
                'template_id' => $template_id,
                'version_number' => $version_number,
                'name' => $name,
                'is_new' => $is_new
            ));
            
            return array(
                'template_id' => $template_id,
                'version_number' => $version_number
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            $this->logger->error('Failed to save template', array(
                'error' => $e->getMessage(),
                'template_id' => $template_id,
                'name' => $name
            ));
            
            return new WP_Error('save_failed', $e->getMessage());
        }
    }
    
    /**
     * Load template data
     */
    public function load_template($template_id, $version_number = null) {
        global $wpdb;
        
        if ($version_number) {
            // Load specific version
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aicg_template_versions WHERE template_id = %d AND version_number = %d",
                $template_id,
                $version_number
            ));
        } else {
            // Load current version
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT tv.*, t.id as template_id 
                FROM {$wpdb->prefix}aicg_template_versions tv
                JOIN {$wpdb->prefix}aicg_templates t ON tv.template_id = t.id
                WHERE tv.template_id = %d AND tv.is_active = 1",
                $template_id
            ));
        }
        
        if (!$template) {
            return false;
        }
        
        // Parse variables
        $variables = json_decode($template->variables, true);
        
        return array(
            'id' => $template->template_id,
            'name' => $template->name,
            'prompt' => $template->prompt,
            'content_type' => $template->content_type,
            'seo_enabled' => (bool)$template->seo_enabled,
            'variables' => $variables ?: array(),
            'version_number' => $template->version_number,
            'created_by' => $template->created_by,
            'created_at' => $template->created_at,
            'change_log' => $template->change_log
        );
    }
    
    /**
     * Delete template and all versions
     */
    public function delete_template($template_id) {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete versions
            $wpdb->delete(
                $wpdb->prefix . 'aicg_template_versions',
                array('template_id' => $template_id)
            );
            
            // Delete template
            $result = $wpdb->delete(
                $wpdb->prefix . 'aicg_templates',
                array('id' => $template_id)
            );
            
            if (!$result) {
                throw new Exception('Failed to delete template');
            }
            
            $wpdb->query('COMMIT');
            
            $this->logger->info('Template deleted', array('template_id' => $template_id));
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            $this->logger->error('Failed to delete template', array(
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ));
            
            return false;
        }
    }
    
    /**
     * Duplicate template
     */
    public function duplicate_template($template_id, $new_name) {
        $template = $this->load_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', 'Template not found');
        }
        
        // Create duplicate
        $result = $this->save_template(
            0, // New template
            $new_name,
            $template['prompt'],
            $template['content_type'],
            $template['seo_enabled'],
            $template['variables'],
            'Duplicated from template #' . $template_id
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result['template_id'];
    }
    
    /**
     * Preview template with variables
     */
    public function preview_template($prompt, $content_type, $variables = array()) {
        // Replace variables in prompt
        $processed_prompt = $this->replace_variables($prompt, $variables);
        
        // Extract variables from prompt
        $extracted_variables = $this->extract_variables($prompt);
        
        return array(
            'original_prompt' => $prompt,
            'processed_prompt' => $processed_prompt,
            'variables' => $extracted_variables,
            'content_type' => $content_type,
            'estimated_tokens' => $this->estimate_tokens($processed_prompt),
            'estimated_cost' => $this->estimate_cost($processed_prompt)
        );
    }
    
    /**
     * Validate template
     */
    public function validate_template($name, $prompt, $content_type) {
        $errors = array();
        
        // Validate name
        if (empty(trim($name))) {
            $errors[] = 'Template name is required';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Template name is too long (max 255 characters)';
        }
        
        // Validate prompt
        if (empty(trim($prompt))) {
            $errors[] = 'Template prompt is required';
        } elseif (strlen($prompt) > 10000) {
            $errors[] = 'Template prompt is too long (max 10,000 characters)';
        }
        
        // Validate content type
        $valid_types = array('post', 'page', 'product', 'email', 'social');
        if (!in_array($content_type, $valid_types)) {
            $errors[] = 'Invalid content type';
        }
        
        // Check for valid variables
        $variables = $this->extract_variables($prompt);
        foreach ($variables as $variable) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable)) {
                $errors[] = "Invalid variable name: {$variable}";
            }
        }
        
        // Check for dangerous content
        $dangerous_patterns = array(
            '/\beval\s*\(/i',
            '/\bexec\s*\(/i',
            '/\bsystem\s*\(/i',
            '/<script[^>]*>/i',
            '/javascript:/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $prompt)) {
                $errors[] = 'Template contains potentially dangerous content';
                break;
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Get template history
     */
    public function get_template_history($template_id) {
        global $wpdb;
        
        $versions = $wpdb->get_results($wpdb->prepare(
            "SELECT tv.*, u.display_name as created_by_name
            FROM {$wpdb->prefix}aicg_template_versions tv
            LEFT JOIN {$wpdb->users} u ON tv.created_by = u.ID
            WHERE tv.template_id = %d
            ORDER BY tv.version_number DESC",
            $template_id
        ));
        
        $history = array();
        
        foreach ($versions as $version) {
            $history[] = array(
                'version_number' => $version->version_number,
                'created_by' => $version->created_by_name,
                'created_at' => $version->created_at,
                'change_log' => $version->change_log,
                'is_active' => (bool)$version->is_active
            );
        }
        
        return $history;
    }
    
    /**
     * Restore template version
     */
    public function restore_template_version($template_id, $version_number) {
        global $wpdb;
        
        // Get version data
        $version = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aicg_template_versions WHERE template_id = %d AND version_number = %d",
            $template_id,
            $version_number
        ));
        
        if (!$version) {
            return new WP_Error('version_not_found', 'Version not found');
        }
        
        // Save as new version
        $result = $this->save_template(
            $template_id,
            $version->name,
            $version->prompt,
            $version->content_type,
            $version->seo_enabled,
            json_decode($version->variables, true),
            "Restored from version {$version_number}"
        );
        
        return $result;
    }
    
    /**
     * Replace variables in prompt
     */
    private function replace_variables($prompt, $variables = array()) {
        if (empty($variables)) {
            return $prompt;
        }
        
        foreach ($variables as $key => $value) {
            $prompt = str_replace('[' . strtoupper($key) . ']', $value, $prompt);
        }
        
        return $prompt;
    }
    
    /**
     * Extract variables from prompt
     */
    private function extract_variables($prompt) {
        preg_match_all('/\[([A-Z_][A-Z0-9_]*)\]/', $prompt, $matches);
        
        return array_unique($matches[1]);
    }
    
    /**
     * Estimate tokens for prompt
     */
    private function estimate_tokens($prompt) {
        // Rough estimation: 1 token ≈ 4 characters
        return ceil(strlen($prompt) / 4);
    }
    
    /**
     * Estimate cost for prompt
     */
    private function estimate_cost($prompt) {
        $tokens = $this->estimate_tokens($prompt);
        $model = get_option('aicg_model', 'gpt-3.5-turbo');
        
        $pricing = array(
            'gpt-3.5-turbo' => 0.000002,
            'gpt-4' => 0.00006,
            'gpt-4-turbo-preview' => 0.00004
        );
        
        $cost_per_token = isset($pricing[$model]) ? $pricing[$model] : 0.000002;
        
        return round($tokens * $cost_per_token, 6);
    }
    
    /**
     * Get template usage statistics
     */
    public function get_template_usage_stats($template_id) {
        global $wpdb;
        
        // This would require tracking template usage in generation logs
        // For now, return placeholder data
        return array(
            'template_id' => $template_id,
            'total_uses' => 0,
            'last_used' => null,
            'success_rate' => 0
        );
    }
    
    /**
     * Export template
     */
    public function export_template($template_id) {
        $template = $this->load_template($template_id);
        
        if (!$template) {
            return false;
        }
        
        $export_data = array(
            'name' => $template['name'],
            'prompt' => $template['prompt'],
            'content_type' => $template['content_type'],
            'seo_enabled' => $template['seo_enabled'],
            'variables' => $template['variables'],
            'exported_at' => current_time('mysql'),
            'exported_by' => get_current_user_id(),
            'version' => '1.0'
        );
        
        return $export_data;
    }
    
    /**
     * Import template
     */
    public function import_template($import_data) {
        if (!isset($import_data['name']) || !isset($import_data['prompt'])) {
            return new WP_Error('invalid_import', 'Invalid import data');
        }
        
        $name = sanitize_text_field($import_data['name']);
        $prompt = sanitize_textarea_field($import_data['prompt']);
        $content_type = sanitize_text_field($import_data['content_type'] ?? 'post');
        $seo_enabled = isset($import_data['seo_enabled']) ? (bool)$import_data['seo_enabled'] : true;
        $variables = isset($import_data['variables']) ? $import_data['variables'] : array();
        
        $result = $this->save_template(
            0, // New template
            $name . ' (Imported)',
            $prompt,
            $content_type,
            $seo_enabled,
            $variables,
            'Imported template'
        );
        
        return $result;
    }
}