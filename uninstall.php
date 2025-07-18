<?php
/**
 * Uninstall script for AI Content Generator plugin
 * 
 * This file is called when the plugin is deleted from WordPress admin
 * It removes all plugin data including options, database tables, and cached data
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - ensure this is a legitimate uninstall
if (!current_user_can('activate_plugins')) {
    exit;
}

// Delete all plugin options
delete_option('aicg_api_key');
delete_option('aicg_model');
delete_option('aicg_max_tokens');
delete_option('aicg_temperature');
delete_option('aicg_default_language');
delete_option('aicg_encryption_key');
delete_option('aicg_plugin_version');
delete_option('aicg_activation_date');
delete_option('aicg_usage_stats');

// Delete multisite options if applicable
if (is_multisite()) {
    delete_site_option('aicg_api_key');
    delete_site_option('aicg_model');
    delete_site_option('aicg_max_tokens');
    delete_site_option('aicg_temperature');
    delete_site_option('aicg_default_language');
    delete_site_option('aicg_encryption_key');
    delete_site_option('aicg_plugin_version');
    delete_site_option('aicg_activation_date');
    delete_site_option('aicg_usage_stats');
}

// Remove database tables
global $wpdb;

// List of tables to remove
$tables = array(
    $wpdb->prefix . 'aicg_templates',
    $wpdb->prefix . 'aicg_usage_log',
    $wpdb->prefix . 'aicg_temp_data',
    $wpdb->prefix . 'aicg_cache',
    $wpdb->prefix . 'aicg_queue'
);

// Drop each table
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Clear all transients related to the plugin
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aicg_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aicg_%'");

// Clear all user meta related to the plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'aicg_%'");

// Remove scheduled hooks
wp_clear_scheduled_hook('aicg_cleanup_temp_data');
wp_clear_scheduled_hook('aicg_cleanup_cache');
wp_clear_scheduled_hook('aicg_process_queue');
wp_clear_scheduled_hook('aicg_update_usage_stats');

// Remove all post meta related to the plugin
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'aicg_%'");

// Clear object cache
wp_cache_flush();

// Remove uploaded files and directories
$upload_dir = wp_upload_dir();
$plugin_upload_dir = $upload_dir['basedir'] . '/ai-content-generator';

if (is_dir($plugin_upload_dir)) {
    // Function to recursively remove directory
    function aicg_remove_directory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        aicg_remove_directory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    aicg_remove_directory($plugin_upload_dir);
}

// Remove custom capabilities if any were added
$roles = wp_roles();
if ($roles) {
    foreach ($roles->roles as $role_name => $role_info) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('aicg_manage_templates');
            $role->remove_cap('aicg_generate_content');
            $role->remove_cap('aicg_view_usage');
            $role->remove_cap('aicg_manage_settings');
        }
    }
}

// Log the uninstall action
error_log('AI Content Generator plugin uninstalled successfully at ' . current_time('mysql'));

// Final cleanup - remove any remaining plugin traces
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aicg_%'");

// Clear rewrite rules
flush_rewrite_rules();