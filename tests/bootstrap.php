<?php
/**
 * PHPUnit bootstrap file
 */

// First, check if we're loaded from within WordPress.
if (!defined('ABSPATH')) {
    // If not, we need to load WordPress.
    // This is a simplified example. A real-world scenario would be more complex.
    $wp_load_path = realpath(__DIR__ . '../../../../../../wp-load.php');
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        echo "WordPress environment not found. Please configure the path to wp-load.php\n";
        exit(1);
    }
}

// Load the plugin
require_once dirname(__DIR__) . '/ai-content-generator.php';

