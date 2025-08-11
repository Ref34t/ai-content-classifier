<?php
/**
 * OpenAI API Client
 */
class AICG_OpenAI_Client {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model;
    private $max_tokens;
    private $temperature;
    
    public function __construct()
    {
        $this->api_key = get_option('aicg_api_key');
        $this->model = get_option('aicg_model', 'gpt-3.5-turbo');
        $this->max_tokens = get_option('aicg_max_tokens', 2000);
        $this->temperature = get_option('aicg_temperature', 0.7);
    }
    
    /**
     * Generate completion from OpenAI
     */
    public function generate_completion($prompt, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured', 'ai-content-classifier'));
        }
        
        // Merge options with defaults
        $options = wp_parse_args($options, array(
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional content writer specializing in WordPress content creation.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        ));
        
        // Prepare request with proper type casting
        $request_body = array(
            'model' => (string) $options['model'],
            'messages' => $options['messages'],
            'max_tokens' => (int) $options['max_tokens'],
            'temperature' => (float) $options['temperature'],
            'response_format' => array('type' => 'json_object') // Request JSON response
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 60
        ));
        
        // Handle errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : 'Unknown API error';
            
            return new WP_Error('api_error', $error_message);
        }
        
        // Parse response
        $data = json_decode($response_body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', __('Invalid response from OpenAI API', 'ai-content-classifier'));
        }
        
        $content = $data['choices'][0]['message']['content'];
        
        // Log usage statistics if available
        if (isset($data['usage'])) {
            $this->log_usage_data($data['usage'], $options['model']);
        } else {
            // Fallback: Estimate usage and log anyway
            $estimated_tokens = $this->estimate_tokens($content);
            
            $fallback_usage = array(
                'total_tokens' => $estimated_tokens,
                'prompt_tokens' => intval($estimated_tokens * 0.3), // Rough estimate
                'completion_tokens' => intval($estimated_tokens * 0.7) // Rough estimate
            );
            
            $this->log_usage_data($fallback_usage, $options['model']);
        }
        
        // If we requested JSON format, try to parse it
        if (isset($request_body['response_format']) && $request_body['response_format']['type'] === 'json_object') {
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        return $content;
    }
    
    /**
     * Generate embeddings for content
     */
    public function generate_embeddings($text) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured', 'ai-content-classifier'));
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'text-embedding-ada-002',
                'input' => $text
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (!isset($data['data'][0]['embedding'])) {
            return new WP_Error('invalid_response', __('Invalid embeddings response', 'ai-content-classifier'));
        }
        
        return $data['data'][0]['embedding'];
    }
    
    /**
     * Validate API key
     */
    public function validate_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->api_key;
        }
        
        if (empty($api_key)) {
            return false;
        }
        
        // Validate API key format first (OpenAI keys start with sk- and are at least 40 chars)
        if (!preg_match('/^sk-[A-Za-z0-9_-]{40,}$/', $api_key)) {
            return false;
        }
        
        // Use models endpoint instead of chat completions to avoid generating logs
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }
    
    /**
     * Get available models
     */
    public function get_available_models() {
        return array(
            'gpt-3.5-turbo' => __('GPT-3.5 Turbo (Fast & Affordable)', 'ai-content-classifier'),
            'gpt-3.5-turbo-16k' => __('GPT-3.5 Turbo 16K (Longer Context)', 'ai-content-classifier'),
            'gpt-4' => __('GPT-4 (Most Capable)', 'ai-content-classifier'),
            'gpt-4-turbo-preview' => __('GPT-4 Turbo (Latest)', 'ai-content-classifier'),
        );
    }
    
    /**
     * Estimate tokens for text
     */
    public function estimate_tokens($text) {
        // Rough estimation: 1 token ≈ 4 characters
        return ceil(strlen($text) / 4);
    }
    
    /**
     * Calculate cost estimate
     */
    public function calculate_cost($tokens, $model = null) {
        if ($model === null) {
            $model = $this->model;
        }
        
        // Pricing per 1K tokens (as of 2024)
        $pricing = array(
            'gpt-3.5-turbo' => array('input' => 0.0005, 'output' => 0.0015),
            'gpt-3.5-turbo-16k' => array('input' => 0.001, 'output' => 0.002),
            'gpt-4' => array('input' => 0.03, 'output' => 0.06),
            'gpt-4-turbo-preview' => array('input' => 0.01, 'output' => 0.03),
        );
        
        if (!isset($pricing[$model])) {
            return 0;
        }
        
        // Assume 50/50 input/output ratio
        $input_tokens = $tokens / 2;
        $output_tokens = $tokens / 2;
        
        $cost = ($input_tokens / 1000 * $pricing[$model]['input']) + 
                ($output_tokens / 1000 * $pricing[$model]['output']);
        
        return round($cost, 4);
    }
    
    /**
     * Log usage data from OpenAI API response
     */
    private function log_usage_data($usage_data, $model) {
        $user_id = get_current_user_id();
        
        // Extract token usage
        $total_tokens = isset($usage_data['total_tokens']) ? $usage_data['total_tokens'] : 0;
        $prompt_tokens = isset($usage_data['prompt_tokens']) ? $usage_data['prompt_tokens'] : 0;
        $completion_tokens = isset($usage_data['completion_tokens']) ? $usage_data['completion_tokens'] : 0;
        
        // Calculate cost based on actual usage
        $cost = $this->calculate_cost_from_usage($prompt_tokens, $completion_tokens, $model);
        
        // Log the usage
        $security = new AICG_Security();
        $security->log_usage($user_id, $total_tokens, $cost, $model);
    }
    
    /**
     * Calculate cost from actual token usage
     */
    private function calculate_cost_from_usage($prompt_tokens, $completion_tokens, $model) {
        // Pricing per 1K tokens (as of 2024)
        $pricing = array(
            'gpt-3.5-turbo' => array('input' => 0.0005, 'output' => 0.0015),
            'gpt-3.5-turbo-16k' => array('input' => 0.001, 'output' => 0.002),
            'gpt-4' => array('input' => 0.03, 'output' => 0.06),
            'gpt-4-turbo-preview' => array('input' => 0.01, 'output' => 0.03),
        );
        
        if (!isset($pricing[$model])) {
            return 0;
        }
        
        $input_cost = ($prompt_tokens / 1000) * $pricing[$model]['input'];
        $output_cost = ($completion_tokens / 1000) * $pricing[$model]['output'];
        
        return round($input_cost + $output_cost, 6);
    }
}