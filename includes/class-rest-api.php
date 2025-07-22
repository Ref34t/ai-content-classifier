<?php
/**
 * Enhanced REST API with authentication and rate limiting
 */
class AICG_REST_API {
    
    private $namespace = 'aicg/v1';
    private $logger;
    private $cache;
    private $security;
    private $wp_filesystem;
    
    public function __construct() {
        $this->init_wp_filesystem();
        $this->logger = new AICG_Logger();
        $this->cache = new AICG_Cache();
        $this->security = new AICG_Security();
        
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_dispatch', array($this, 'pre_dispatch'), 10, 3);
    }
    
    /**
     * Initialize WP_Filesystem
     */
    private function init_wp_filesystem() {
        if (!function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        WP_Filesystem();
        global $wp_filesystem;
        $this->wp_filesystem = $wp_filesystem;
    }
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Content generation endpoint
        register_rest_route($this->namespace, '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_content'),
            'permission_callback' => array($this, 'check_generate_permission'),
            'args' => array(
                'prompt' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_prompt'),
                    'sanitize_callback' => array($this, 'sanitize_prompt')
                ),
                'content_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post',
                    'enum' => array('post', 'page', 'product', 'email', 'social')
                ),
                'seo_enabled' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true
                ),
                'model' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'gpt-3.5-turbo'
                ),
                'temperature' => array(
                    'required' => false,
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 2,
                    'default' => 0.7
                ),
                'max_tokens' => array(
                    'required' => false,
                    'type' => 'integer',
                    'minimum' => 100,
                    'maximum' => 8000,
                    'default' => 2000
                )
            )
        ));
        
        // Templates endpoint
        register_rest_route($this->namespace, '/templates', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_templates'),
                'permission_callback' => array($this, 'check_templates_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_template'),
                'permission_callback' => array($this, 'check_templates_permission'),
                'args' => array(
                    'name' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => array($this, 'validate_template_name')
                    ),
                    'prompt' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => array($this, 'validate_prompt')
                    ),
                    'content_type' => array(
                        'required' => false,
                        'type' => 'string',
                        'default' => 'post'
                    ),
                    'seo_enabled' => array(
                        'required' => false,
                        'type' => 'boolean',
                        'default' => true
                    )
                )
            )
        ));
        
        // Individual template endpoint
        register_rest_route($this->namespace, '/templates/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_template'),
                'permission_callback' => array($this, 'check_templates_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_template'),
                'permission_callback' => array($this, 'check_templates_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_template'),
                'permission_callback' => array($this, 'check_templates_permission')
            )
        ));
        
        // Usage stats endpoint
        register_rest_route($this->namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_stats_permission')
        ));
        
        // Cache management endpoint
        register_rest_route($this->namespace, '/cache', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_cache_stats'),
                'permission_callback' => array($this, 'check_admin_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'clear_cache'),
                'permission_callback' => array($this, 'check_admin_permission')
            )
        ));
        
        // Bulk operations endpoint
        register_rest_route($this->namespace, '/bulk', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_generate'),
            'permission_callback' => array($this, 'check_generate_permission'),
            'args' => array(
                'operations' => array(
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => array($this, 'validate_bulk_operations')
                )
            )
        ));
        
        // Health check endpoint
        register_rest_route($this->namespace, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Pre-dispatch middleware for rate limiting and logging
     */
    public function pre_dispatch($result, $server, $request) {
        $route = $request->get_route();
        
        // Only process our API routes
        if (strpos($route, '/aicg/v1/') !== 0) {
            return $result;
        }
        
        // Log API request
        $this->logger->info('API request: ' . $route, array(
            'method' => $request->get_method(),
            'params' => $request->get_params(),
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip()
        ));
        
        // Check rate limits
        $rate_limit_result = $this->check_rate_limit($request);
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }
        
        // Check API key if required
        $auth_result = $this->check_api_key_auth($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        return $result;
    }
    
    /**
     * Generate content endpoint
     */
    public function generate_content($request) {
        $params = $request->get_params();
        
        try {
            // Check cache first
            $cache_key = $this->cache->generate_cache_key($params['prompt'], $params);
            $cached_result = $this->cache->get($cache_key);
            
            if ($cached_result) {
                $this->logger->info('Content served from cache', array('cache_key' => $cache_key));
                return new WP_REST_Response(array(
                    'success' => true,
                    'data' => $cached_result,
                    'cached' => true
                ), 200);
            }
            
            // Generate new content
            $generator = AI_Content_Generator::get_instance();
            $result = $generator->generate_content(
                $params['prompt'],
                $params['content_type'],
                $params['seo_enabled']
            );
            
            if (is_wp_error($result)) {
                $this->logger->error('Content generation failed', array(
                    'error' => $result->get_error_message(),
                    'prompt' => $params['prompt']
                ));
                
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => $result->get_error_message()
                ), 400);
            }
            
            // Cache the result
            $this->cache->smart_cache($cache_key, $result, 'content', array(
                'content_type' => $params['content_type'],
                'prompt_length' => strlen($params['prompt'])
            ));
            
            $this->logger->info('Content generated successfully', array(
                'content_type' => $params['content_type'],
                'prompt_length' => strlen($params['prompt'])
            ));
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $result,
                'cached' => false
            ), 200);
            
        } catch (Exception $e) {
            $this->logger->error('Exception in generate_content', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('Internal server error', 'ai-content-classifier')
            ), 500);
        }
    }
    
    /**
     * Get templates endpoint
     */
    public function get_templates($request) {
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $search = $request->get_param('search');
        $content_type = $request->get_param('content_type');
        
        $offset = ($page - 1) * $per_page;
        $where_conditions = array();
        $where_values = array();
        
        if ($search) {
            $where_conditions[] = "(name LIKE %s OR prompt LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        if ($content_type) {
            $where_conditions[] = "content_type = %s";
            $where_values[] = $content_type;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aicg_templates 
            $where_clause 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        );
        
        $templates = $wpdb->get_results($query);
        
        // Get total count
        $count_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicg_templates $where_clause",
            $where_values
        );
        $total = $wpdb->get_var($count_query);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $templates,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page)
            )
        ), 200);
    }
    
    /**
     * Create template endpoint
     */
    public function create_template($request) {
        global $wpdb;
        
        $params = $request->get_params();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'aicg_templates',
            array(
                'name' => $params['name'],
                'prompt' => $params['prompt'],
                'content_type' => $params['content_type'],
                'seo_enabled' => $params['seo_enabled'] ? 1 : 0
            )
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('Failed to create template', 'ai-content-classifier')
            ), 400);
        }
        
        $template_id = $wpdb->insert_id;
        
        $this->logger->info('Template created via API', array(
            'template_id' => $template_id,
            'name' => $params['name']
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'id' => $template_id,
                'message' => __('Template created successfully', 'ai-content-classifier')
            )
        ), 201);
    }
    
    /**
     * Bulk generate endpoint
     */
    public function bulk_generate($request) {
        $operations = $request->get_param('operations');
        $results = array();
        
        foreach ($operations as $index => $operation) {
            try {
                $generator = AI_Content_Generator::get_instance();
                $result = $generator->generate_content(
                    $operation['prompt'],
                    $operation['content_type'] ?? 'post',
                    $operation['seo_enabled'] ?? true
                );
                
                if (is_wp_error($result)) {
                    $results[] = array(
                        'index' => $index,
                        'success' => false,
                        'error' => $result->get_error_message()
                    );
                } else {
                    $results[] = array(
                        'index' => $index,
                        'success' => true,
                        'data' => $result
                    );
                }
            } catch (Exception $e) {
                $results[] = array(
                    'index' => $index,
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }
        
        $this->logger->info('Bulk generation completed', array(
            'operations_count' => count($operations),
            'success_count' => count(array_filter($results, function($r) { return $r['success']; }))
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $results
        ), 200);
    }
    
    /**
     * Health check endpoint
     */
    public function health_check($request) {
        $health = array(
            'status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'version' => AICG_VERSION,
            'checks' => array()
        );
        
        // Check database connection
        global $wpdb;
        $health['checks']['database'] = $wpdb->get_var("SELECT 1") === '1';
        
        // Check OpenAI API key
        $storage = new AICG_Secure_Storage();
        $api_key = $storage->get_api_key();
        $health['checks']['api_key'] = !empty($api_key);
        
        // Check cache system
        $health['checks']['cache'] = $this->cache->test_encryption();
        
        // Check file permissions
        $health['checks']['file_permissions'] = $this->wp_filesystem->is_writable(wp_upload_dir()['basedir']);
        
        // Overall health
        $all_healthy = !in_array(false, $health['checks'], true);
        $health['status'] = $all_healthy ? 'healthy' : 'unhealthy';
        
        return new WP_REST_Response($health, $all_healthy ? 200 : 503);
    }
    
    /**
     * Check rate limit for API requests
     */
    private function check_rate_limit($request) {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        
        // Different limits for different endpoints
        $limits = array(
            '/generate' => 30, // 30 requests per hour
            '/bulk' => 5,      // 5 requests per hour
            '/templates' => 100 // 100 requests per hour
        );
        
        $route = $request->get_route();
        $limit = 60; // Default limit
        
        foreach ($limits as $endpoint => $endpoint_limit) {
            if (strpos($route, $endpoint) !== false) {
                $limit = $endpoint_limit;
                break;
            }
        }
        
        $transient_key = 'aicg_api_rate_limit_' . ($user_id ?: $ip);
        $requests = get_transient($transient_key) ?: array();
        
        // Clean old requests
        $current_time = time();
        $requests = array_filter($requests, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600; // 1 hour
        });
        
        if (count($requests) >= $limit) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        // Add current request
        $requests[] = $current_time;
        set_transient($transient_key, $requests, 3600);
        
        return true;
    }
    
    /**
     * Check API key authentication
     */
    private function check_api_key_auth($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (!$api_key) {
            return true; // Fall back to WordPress auth
        }
        
        // Validate API key format and check against stored keys
        $stored_keys = get_option('aicg_api_keys', array());
        
        if (!in_array($api_key, $stored_keys)) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Permission callbacks
     */
    public function check_generate_permission($request) {
        return current_user_can('edit_posts') || $this->check_api_key_auth($request) === true;
    }
    
    public function check_templates_permission($request) {
        return current_user_can('edit_posts') || $this->check_api_key_auth($request) === true;
    }
    
    public function check_stats_permission($request) {
        return current_user_can('edit_posts') || $this->check_api_key_auth($request) === true;
    }
    
    public function check_admin_permission($request) {
        return current_user_can('manage_options') || $this->check_api_key_auth($request) === true;
    }
    
    /**
     * Validation callbacks
     */
    public function validate_prompt($value) {
        if (empty(trim($value))) {
            return new WP_Error('invalid_prompt', 'Prompt cannot be empty');
        }
        
        if (strlen($value) > 5000) {
            return new WP_Error('prompt_too_long', 'Prompt is too long (max 5000 characters)');
        }
        
        return true;
    }
    
    public function validate_template_name($value) {
        if (empty(trim($value))) {
            return new WP_Error('invalid_name', 'Template name cannot be empty');
        }
        
        if (strlen($value) > 100) {
            return new WP_Error('name_too_long', 'Template name is too long (max 100 characters)');
        }
        
        return true;
    }
    
    public function validate_bulk_operations($value) {
        if (!is_array($value)) {
            return new WP_Error('invalid_operations', 'Operations must be an array');
        }
        
        if (count($value) > 10) {
            return new WP_Error('too_many_operations', 'Maximum 10 operations allowed');
        }
        
        return true;
    }
    
    /**
     * Sanitization callbacks
     */
    public function sanitize_prompt($value) {
        return $this->security->sanitize_prompt($value);
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}