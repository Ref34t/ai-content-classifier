<?php
/**
 * Plugin Name: AI Content Classifier
 * Plugin URI: https://github.com/ref34t/ai-content-classifier
 * Description: Generate SEO-optimized WordPress content using OpenAI's GPT API
 * Version: 1.1.1
 * Author: Mo Khaled
 * Author URI: https://mokhaled.dev
 * License: GPL v2 or later
 * Text Domain: ai-content-classifier
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AICG_VERSION', '1.1.1');
define('AICG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICG_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Initialize autoloader
require_once AICG_PLUGIN_DIR . 'includes/class-autoloader.php';
$aicg_autoloader = new AICG_Autoloader();

// Preload critical classes
$aicg_autoloader->preload_critical_classes();

// Initialize the plugin with singleton pattern
function aicg_init() {
    static $initialized = false;
    
    if ($initialized) {
        return;
    }
    
    $plugin = AI_Content_Generator::get_instance();
    $plugin->run();
    
    // Initialize additional components
    new AICG_Admin_Bar();
    new AICG_REST_API();
    new AICG_Admin_Notices();
    new AICG_Bulk_Operations();
    new AICG_Template_Editor();
    
    $initialized = true;
}
add_action('plugins_loaded', 'aicg_init');

// Plugin action links
add_filter('plugin_action_links_' . AICG_PLUGIN_BASENAME, 'aicg_plugin_action_links');
function aicg_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=aicg-settings') . '">' . __('Settings', 'ai-content-classifier') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Plugin meta links
add_filter('plugin_row_meta', 'aicg_plugin_meta_links', 10, 2);
function aicg_plugin_meta_links($links, $file) {
    if ($file === AICG_PLUGIN_BASENAME) {
        $links[] = '<a href="' . admin_url('admin.php?page=ai-content-generator') . '">' . __('Generate Content', 'ai-content-classifier') . '</a>';
        $links[] = '<a href="https://github.com/ref34t/ai-content-classifier" target="_blank">' . __('GitHub', 'ai-content-classifier') . '</a>';
    }
    return $links;
}

// Activation hook
register_activation_hook(__FILE__, 'aicg_activate');
function aicg_activate() {
    // Create database tables if needed
    aicg_create_tables();
    
    // Set default options
    add_option('aicg_api_key', '');
    add_option('aicg_model', 'gpt-3.5-turbo');
    add_option('aicg_max_tokens', 2000);
    add_option('aicg_temperature', 0.7);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'aicg_deactivate');
function aicg_deactivate() {
    // Clean up scheduled tasks
    wp_clear_scheduled_hook('aicg_cleanup_temp_data');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Create database tables
function aicg_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Templates table
    $templates_table = $wpdb->prefix . 'aicg_templates';
    $sql_templates = "CREATE TABLE $templates_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        prompt text NOT NULL,
        content_type varchar(50) DEFAULT 'post',
        seo_enabled boolean DEFAULT true,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Usage log table
    $usage_table = $wpdb->prefix . 'aicg_usage_log';
    $sql_usage = "CREATE TABLE $usage_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        tokens_used int(11) NOT NULL DEFAULT 0,
        cost decimal(10,6) NOT NULL DEFAULT 0.000000,
        model varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Bulk operations queue table
    $bulk_table = $wpdb->prefix . 'aicg_bulk_queue';
    $sql_bulk = "CREATE TABLE $bulk_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        prompt text NOT NULL,
        content_type varchar(50) DEFAULT 'post',
        status varchar(20) DEFAULT 'pending',
        result text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        processed_at datetime NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    
    // Temporary data table
    $temp_table = $wpdb->prefix . 'aicg_temp_data';
    $sql_temp = "CREATE TABLE $temp_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        data_key varchar(255) NOT NULL,
        data_value longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY data_key (data_key),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_templates);
    dbDelta($sql_usage);
    dbDelta($sql_bulk);
    dbDelta($sql_temp);
}