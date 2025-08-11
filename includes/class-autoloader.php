<?php
/**
 * Autoloader for AI Content Classifier plugin classes
 */
class AICG_Autoloader {
    
    private $class_map = array();
    private $base_dir;
    
    public function __construct() {
        $this->base_dir = AICG_PLUGIN_DIR;
        $this->init_class_map();
        $this->register();
    }
    
    /**
     * Initialize class map
     */
    private function init_class_map() {
        $this->class_map = array(
            // Core classes
            'AI_Content_Generator' => 'includes/class-ai-content-generator.php',
            'AICG_OpenAI_Client' => 'includes/class-openai-client.php',
            'AICG_Security' => 'includes/class-security.php',
            'AICG_Logger' => 'includes/class-logger.php',
            'AICG_Cache' => 'includes/class-cache.php',
            'AICG_Secure_Storage' => 'includes/class-secure-storage.php',
            
            // Admin classes
            'AICG_Admin_Menu' => 'admin/class-admin-menu.php',
            'AICG_Settings' => 'admin/class-settings.php',
            'AICG_Admin_Notices' => 'admin/class-admin-notices.php',
            'AICG_Bulk_Operations' => 'admin/class-bulk-operations.php',
            
            // API classes
            'AICG_REST_API' => 'includes/class-rest-api.php',
            'AICG_API_Auth' => 'includes/class-api-auth.php',
            
            // Utility classes
            'AICG_Template_Editor' => 'includes/class-template-editor.php',
            'AICG_Admin_Bar' => 'includes/class-admin-bar.php',
            'AICG_Performance' => 'includes/class-performance.php',
            'AICG_Multisite' => 'includes/class-multisite.php',
            
            // Queue and background processing
            'AICG_Queue' => 'includes/class-queue.php',
            'AICG_Background_Process' => 'includes/class-background-process.php',
            
            // Content processing
            'AICG_Content_Processor' => 'includes/class-content-processor.php',
            'AICG_SEO_Optimizer' => 'includes/class-seo-optimizer.php',
            'AICG_Content_Filter' => 'includes/class-content-filter.php',
            
            // Import/Export
            'AICG_Import_Export' => 'includes/class-import-export.php',
            'AICG_Backup' => 'includes/class-backup.php',
            
            // Analytics and reporting
            'AICG_Analytics' => 'includes/class-analytics.php',
            'AICG_Reports' => 'includes/class-reports.php',
            
            // Integration classes
            'AICG_Gutenberg_Integration' => 'includes/class-gutenberg-integration.php',
            'AICG_Classic_Editor_Integration' => 'includes/class-classic-editor-integration.php',
            'AICG_Elementor_Integration' => 'includes/class-elementor-integration.php',
            
            // Migration and compatibility
            'AICG_Migration' => 'includes/class-migration.php',
            'AICG_Compatibility' => 'includes/class-compatibility.php'
        );
    }
    
    /**
     * Register autoloader
     */
    public function register() {
        spl_autoload_register(array($this, 'load_class'));
    }
    
    /**
     * Unregister autoloader
     */
    public function unregister() {
        spl_autoload_unregister(array($this, 'load_class'));
    }
    
    /**
     * Load class file
     */
    public function load_class($class_name) {
        // Check if class is in our map
        if (isset($this->class_map[$class_name])) {
            $file_path = $this->base_dir . $this->class_map[$class_name];
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return true;
            }
        }
        
        // Try PSR-4 style loading for AICG_ prefixed classes
        if (strpos($class_name, 'AICG_') === 0) {
            $class_file = $this->convert_class_to_file($class_name);
            $file_path = $this->base_dir . 'includes/' . $class_file;
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return true;
            }
            
            // Try admin directory
            $file_path = $this->base_dir . 'admin/' . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Convert class name to file name
     */
    private function convert_class_to_file($class_name) {
        // Remove AICG_ prefix
        $class_name = str_replace('AICG_', '', $class_name);
        
        // Convert to lowercase and replace underscores with hyphens
        $file_name = strtolower(str_replace('_', '-', $class_name));
        
        return 'class-' . $file_name . '.php';
    }
    
    /**
     * Add class to map
     */
    public function add_class($class_name, $file_path) {
        $this->class_map[$class_name] = $file_path;
    }
    
    /**
     * Remove class from map
     */
    public function remove_class($class_name) {
        if (isset($this->class_map[$class_name])) {
            unset($this->class_map[$class_name]);
        }
    }
    
    /**
     * Get class map
     */
    public function get_class_map() {
        return $this->class_map;
    }
    
    /**
     * Check if class is loadable
     */
    public function is_class_loadable($class_name) {
        if (isset($this->class_map[$class_name])) {
            return file_exists($this->base_dir . $this->class_map[$class_name]);
        }
        
        // Check PSR-4 style
        if (strpos($class_name, 'AICG_') === 0) {
            $class_file = $this->convert_class_to_file($class_name);
            return file_exists($this->base_dir . 'includes/' . $class_file) ||
                   file_exists($this->base_dir . 'admin/' . $class_file);
        }
        
        return false;
    }
    
    /**
     * Load all classes in a directory
     */
    public function load_directory($directory) {
        $dir_path = $this->base_dir . $directory;
        
        if (!is_dir($dir_path)) {
            return false;
        }
        
        $files = glob($dir_path . '/*.php');
        $loaded = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                require_once $file;
                $loaded++;
            }
        }
        
        return $loaded;
    }
    
    /**
     * Preload critical classes
     */
    public function preload_critical_classes() {
        $critical_classes = array(
            'AICG_Logger',
            'AICG_Security',
            'AICG_Cache',
            'AICG_Secure_Storage'
        );
        
        $loaded = 0;
        foreach ($critical_classes as $class_name) {
            if ($this->load_class($class_name)) {
                $loaded++;
            }
        }
        
        return $loaded;
    }
    
    /**
     * Get missing classes
     */
    public function get_missing_classes() {
        $missing = array();
        
        foreach ($this->class_map as $class_name => $file_path) {
            if (!file_exists($this->base_dir . $file_path)) {
                $missing[] = array(
                    'class' => $class_name,
                    'file' => $file_path
                );
            }
        }
        
        return $missing;
    }
    
    /**
     * Validate all classes
     */
    public function validate_classes() {
        $results = array(
            'total' => count($this->class_map),
            'loaded' => 0,
            'missing' => 0,
            'errors' => array()
        );
        
        foreach ($this->class_map as $class_name => $file_path) {
            $full_path = $this->base_dir . $file_path;
            
            if (!file_exists($full_path)) {
                $results['missing']++;
                $results['errors'][] = "File not found: $file_path";
                continue;
            }
            
            // Try to load the class
            try {
                require_once $full_path;
                if (class_exists($class_name)) {
                    $results['loaded']++;
                } else {
                    $results['errors'][] = "Class not found in file: $class_name in $file_path";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Error loading $class_name: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Generate class map from directory structure
     */
    public function generate_class_map() {
        $directories = array('includes', 'admin');
        $generated_map = array();
        
        foreach ($directories as $directory) {
            $dir_path = $this->base_dir . $directory;
            
            if (!is_dir($dir_path)) {
                continue;
            }
            
            $files = glob($dir_path . '/class-*.php');
            
            foreach ($files as $file) {
                $relative_path = str_replace($this->base_dir, '', $file);
                $class_name = $this->file_to_class_name(basename($file));
                
                if ($class_name) {
                    $generated_map[$class_name] = $relative_path;
                }
            }
        }
        
        return $generated_map;
    }
    
    /**
     * Convert file name to class name
     */
    private function file_to_class_name($file_name) {
        // Remove class- prefix and .php extension
        $name = str_replace(array('class-', '.php'), '', $file_name);
        
        // Convert hyphens to underscores and capitalize
        $parts = explode('-', $name);
        $class_parts = array();
        
        foreach ($parts as $part) {
            $class_parts[] = ucfirst($part);
        }
        
        return 'AICG_' . implode('_', $class_parts);
    }
    
    /**
     * Cache class map for performance
     */
    public function cache_class_map() {
        $cache_key = 'aicg_class_map_' . md5(serialize($this->class_map));
        wp_cache_set($cache_key, $this->class_map, 'aicg_autoloader', 3600);
    }
    
    /**
     * Load cached class map
     */
    public function load_cached_class_map() {
        $cache_key = 'aicg_class_map_' . md5(serialize($this->class_map));
        $cached_map = wp_cache_get($cache_key, 'aicg_autoloader');
        
        if ($cached_map) {
            $this->class_map = $cached_map;
            return true;
        }
        
        return false;
    }
}