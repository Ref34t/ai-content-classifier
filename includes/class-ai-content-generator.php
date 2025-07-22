<?php
/**
 * Main plugin class
 */
class AI_Content_Generator {
    
    private $loader;
    private $plugin_name;
    private $version;
    private $openai_client;
    private static $instance = null;
    private static $hooks_registered = false;
    
    public function __construct() {
        $this->plugin_name = 'ai-content-classifier';
        $this->version = AICG_VERSION;
        
        $this->load_dependencies();
        
        // Only register admin hooks once
        if (!self::$hooks_registered) {
            $this->define_admin_hooks();
            $this->define_public_hooks();
            self::$hooks_registered = true;
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load_dependencies() {
        // Initialize OpenAI client
        $this->openai_client = new OpenAI_Client();
    }
    
    private function define_admin_hooks() {
        $admin_menu = new AICG_Admin_Menu($this->plugin_name, $this->version);
        
        add_action('admin_menu', array($admin_menu, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($admin_menu, 'enqueue_scripts'));
        
        // Initialize settings
        new AICG_Settings();
        
        // Initialize admin bar
        new AICG_Admin_Bar();
        
        // AJAX handlers
        add_action('wp_ajax_aicg_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_aicg_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aicg_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aicg_create_post', array($this, 'ajax_create_post'));
    }
    
    private function define_public_hooks() {
        // Add REST API endpoints if needed
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public function run() {
        // Plugin is ready to run
    }
    
    /**
     * AJAX handler for content generation
     */
    public function ajax_generate_content() {
        // Verify nonce and user capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'aicg_generate_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error(__('Security check failed.', 'ai-content-classifier'), 403);
            return;
        }

        // Sanitize and retrieve parameters
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
        $seo_enabled = isset($_POST['seo_enabled']) && wp_unslash($_POST['seo_enabled']) === 'true';

        // Generate content using the new unified method
        $response = $this->handle_content_generation($prompt, $content_type, $seo_enabled);

        // Send JSON response
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message(), 400);
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Generate content using OpenAI API
     */
    public function generate_content($prompt, $content_type = 'post', $seo_enabled = true) {
        // Build the full prompt
        $full_prompt = $this->build_prompt($prompt, $content_type, $seo_enabled);
        
        // Call OpenAI API
        $response = $this->openai_client->generate_completion($full_prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response
        $content = $this->parse_ai_response($response, $seo_enabled);
        
        // Filter the generated content for security
        $security = new AICG_Security();
        $content['content'] = $security->filter_generated_content($content['content']);
        
        return $content;
    }
    
    /**
     * Build a comprehensive prompt for AI
     */
    private function build_prompt($base_prompt, $content_type, $seo_enabled) {
        /* translators: %s: content type (e.g., blog post, page, etc.) */
        $prompt = sprintf(__("You are a professional content writer creating %s content for WordPress.\n\n", 'ai-content-classifier'), $content_type);
        $prompt .= __("Instructions:\n", 'ai-content-classifier');
        $prompt .= __("- Write engaging, well-structured content\n", 'ai-content-classifier');
        $prompt .= __("- Use proper HTML formatting (headings, paragraphs, lists)\n", 'ai-content-classifier');
        $prompt .= __("- Make content scannable and easy to read\n", 'ai-content-classifier');
        
        if ($seo_enabled) {
            $prompt .= __("- Optimize for SEO with relevant keywords\n", 'ai-content-classifier');
            $prompt .= __("- Include a meta description (max 160 characters)\n", 'ai-content-classifier');
            $prompt .= __("- Suggest 3-5 focus keywords\n", 'ai-content-classifier');
            $prompt .= __("- Create an SEO-friendly title\n", 'ai-content-classifier');
        }
        
        $prompt .= "\n" . __('Content request: ', 'ai-content-classifier') . $base_prompt;
        $prompt .= __("\n\nFormat your response as JSON with the following structure:\n", 'ai-content-classifier');
        $prompt .= "{\n";
        $prompt .= '  "title": "' . __('Article title', 'ai-content-classifier') . '",\n';
        $prompt .= '  "content": "' . __('Full HTML content', 'ai-content-classifier') . '",\n';
        
        if ($seo_enabled) {
            $prompt .= '  "meta_description": "' . __('SEO meta description', 'ai-content-classifier') . '",\n';
            $prompt .= '  "keywords": ["keyword1", "keyword2", "keyword3"],\n';
            $prompt .= '  "excerpt": "' . __('Brief excerpt for listings', 'ai-content-classifier') . '"\n';
        }
        
        $prompt .= "}\n";
        
        return $prompt;
    }
    
    /**
     * Parse AI response and extract structured data
     */
    private function parse_ai_response($response_body, $seo_enabled) {
        // Check if response is already parsed (array) or needs parsing (string)
        if (is_array($response_body)) {
            $data = $response_body;
        } else {
            // Attempt to decode the JSON response
            $data = json_decode($response_body, true);
        }

        // Check for JSON errors or incomplete data
        if (!is_array($data) || !isset($data['title']) || !isset($data['content'])) {
            // If JSON fails, try to extract content with regex as a fallback
            return $this->fallback_parse_response($response_body, $seo_enabled);
        }

        return $data;
    }

    /**
     * Fallback parser for non-JSON AI responses.
     */
    private function fallback_parse_response($response_body, $seo_enabled) {
        // Convert array to string if needed for parsing
        $content_string = is_array($response_body) ? json_encode($response_body) : $response_body;
        
        $data = [
            'title' => $this->extract_title($content_string),
            'content' => $this->clean_content($content_string),
        ];

        if ($seo_enabled) {
            $data['meta_description'] = $this->extract_meta_description($content_string);
            $data['keywords'] = $this->extract_keywords($content_string);
            $data['excerpt'] = wp_trim_words($data['content'], 55);
        }

        return $data;
    }
    
    /**
     * Helper methods for content extraction
     */
    private function extract_title($content) {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return wp_strip_all_tags($matches[1]);
        }
        return __('Untitled', 'ai-content-classifier');
    }
    
    private function clean_content($content) {
        // Remove any JSON formatting if present
        $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
        
        // Basic content cleaning
        $content = wp_kses_post($content);
        
        return $content;
    }
    
    private function extract_meta_description($content) {
        // Try to find meta description in content
        if (preg_match('/meta.?description[:\s]+([^\n]{20,160})/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return wp_trim_words($content, 20);
    }
    
    private function extract_keywords($content) {
        // Basic keyword extraction
        $keywords = array();
        if (preg_match_all('/keywords?[:\s]+([^\n]+)/i', $content, $matches)) {
            $keyword_string = $matches[1][0];
            $keywords = array_map('trim', explode(',', $keyword_string));
        }
        return array_slice($keywords, 0, 5);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('aicg/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_content'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST API endpoint for content generation
     */
    public function rest_generate_content(WP_REST_Request $request) {
        // Permission check is handled by the REST API route definition

        // Sanitize and retrieve parameters
        $prompt = sanitize_textarea_field($request->get_param('prompt'));
        $content_type = sanitize_text_field($request->get_param('content_type')) ?: 'post';
        $seo_enabled = $request->get_param('seo_enabled') !== null ? rest_sanitize_boolean($request->get_param('seo_enabled')) : true;

        // Generate content using the new unified method
        $response = $this->handle_content_generation($prompt, $content_type, $seo_enabled);

        // Return REST response
        if (is_wp_error($response)) {
            return new WP_REST_Response(['error' => $response->get_error_message()], 400);
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Unified method to handle content generation.
     */
    private function handle_content_generation($prompt, $content_type, $seo_enabled) {
        if (empty($prompt)) {
            return new WP_Error('prompt_empty', __('Prompt cannot be empty.', 'ai-content-classifier'));
        }

        // Build the prompt
        $full_prompt = $this->build_prompt($prompt, $content_type, $seo_enabled);

        // Call the OpenAI API
        $api_response = $this->openai_client->generate_completion($full_prompt);

        if (is_wp_error($api_response)) {
            return $api_response;
        }

        // Parse the response
        return $this->parse_ai_response($api_response, $seo_enabled);
    }
    
    /**
     * AJAX handler for saving templates
     */
    public function ajax_save_template() {
        // Verify nonce and user capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'aicg_generate_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error(__('Security check failed.', 'ai-content-classifier'), 403);
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        
        // Save template logic would go here
        wp_send_json_success(__('Template saved successfully!', 'ai-content-classifier'));
    }
    
    /**
     * AJAX handler for deleting templates
     */
    public function ajax_delete_template() {
        // Verify nonce and user capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'aicg_generate_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error(__('Security check failed.', 'ai-content-classifier'), 403);
            return;
        }
        
        $id = isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0;
        
        // Delete template logic would go here
        wp_send_json_success(__('Template deleted successfully!', 'ai-content-classifier'));
    }
    
    /**
     * AJAX handler for creating posts
     */
    public function ajax_create_post() {
        // Verify nonce and user capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'aicg_generate_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error(__('Security check failed.', 'ai-content-classifier'), 403);
            return;
        }
        
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $excerpt = isset($_POST['excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['excerpt'])) : '';
        $meta_description = isset($_POST['meta_description']) ? sanitize_text_field(wp_unslash($_POST['meta_description'])) : '';
        $keywords = isset($_POST['keywords']) ? array_map('sanitize_text_field', wp_unslash($_POST['keywords'])) : array(); // Array of keywords
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'post';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'draft';
        
        // Create post data
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => $content_type === 'page' ? 'page' : 'post',
            'post_author' => get_current_user_id()
        );
        
        // Insert the post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
            return;
        }
        
        // Add meta data
        if (!empty($meta_description)) {
            update_post_meta($post_id, 'aicg_meta_description', $meta_description);
        }
        
        if (!empty($keywords) && is_array($keywords)) {
            update_post_meta($post_id, 'aicg_keywords', $keywords);
        }
        
        // Mark as AI generated
        update_post_meta($post_id, 'aicg_generated', true);
        update_post_meta($post_id, 'aicg_generated_date', current_time('mysql'));
        
        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
            'message' => __('Post created successfully!', 'ai-content-classifier')
        ));
    }
}