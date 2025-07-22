<?php
/**
 * Admin menu and interface
 */
class AICG_Admin_Menu {
    
    private $plugin_name;
    private $version;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Only add menu if not already added
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        // Main menu
        add_menu_page(
            __('AI Content Classifier', 'ai-content-classifier'),
            __('AI Content', 'ai-content-classifier'),
            'edit_posts',
            'ai-content-classifier',
            array($this, 'display_generator_page'),
            'dashicons-edit-large',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'ai-content-classifier',
            __('Templates', 'ai-content-classifier'),
            __('Templates', 'ai-content-classifier'),
            'edit_posts',
            'aicg-templates',
            array($this, 'display_templates_page')
        );
        
        add_submenu_page(
            'ai-content-classifier',
            __('Settings', 'ai-content-classifier'),
            __('Settings', 'ai-content-classifier'),
            'manage_options',
            'aicg-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'ai-content-classifier') === false && strpos($hook, 'aicg-') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            AICG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version
        );
        
        // JavaScript
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            AICG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-element', 'wp-components'),
            $this->version,
            true
        );
        
        // Localize script
        wp_localize_script($this->plugin_name . '-admin', 'aicg', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aicg_generate_nonce'),
            'strings' => array(
                'generating' => __('Generating content...', 'ai-content-classifier'),
                'error' => __('An error occurred', 'ai-content-classifier'),
                'success' => __('Content generated successfully!', 'ai-content-classifier')
            )
        ));
    }
    
    /**
     * Display generator page
     */
    public function display_generator_page() {
        include AICG_PLUGIN_DIR . 'templates/admin-generator.php';
    }
    
    /**
     * Display templates page
     */
    public function display_templates_page() {
        include AICG_PLUGIN_DIR . 'templates/admin-templates.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        include AICG_PLUGIN_DIR . 'templates/admin-settings.php';
    }
}