<?php
/**
 * Content generator admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get saved templates with caching
$cache_key = 'aicg_all_templates_generator';
$templates = wp_cache_get($cache_key);

if ($templates === false) {
    global $wpdb;
    $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicg_templates ORDER BY name ASC");
    wp_cache_set($cache_key, $templates, '', 300); // Cache for 5 minutes
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="aicg-generator-wrapper">
        <div class="aicg-main-content">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Generate Content', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <form id="aicg-generate-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="content_type"><?php esc_html_e('Content Type', 'ai-content-classifier'); ?></label>
                                </th>
                                <td>
                                    <select name="content_type" id="content_type">
                                        <option value="post"><?php esc_html_e('Blog Post', 'ai-content-classifier'); ?></option>
                                        <option value="page"><?php esc_html_e('Page', 'ai-content-classifier'); ?></option>
                                        <option value="product"><?php esc_html_e('Product Description', 'ai-content-classifier'); ?></option>
                                        <option value="email"><?php esc_html_e('Email', 'ai-content-classifier'); ?></option>
                                        <option value="social"><?php esc_html_e('Social Media', 'ai-content-classifier'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="template"><?php esc_html_e('Template', 'ai-content-classifier'); ?></label>
                                </th>
                                <td>
                                    <select name="template" id="template">
                                        <option value=""><?php esc_html_e('-- Custom Prompt --', 'ai-content-classifier'); ?></option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo esc_attr($template->id); ?>" 
                                                    data-prompt="<?php echo esc_attr($template->prompt); ?>">
                                                <?php echo esc_html($template->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="prompt"><?php esc_html_e('Prompt', 'ai-content-classifier'); ?></label>
                                </th>
                                <td>
                                    <textarea name="prompt" 
                                              id="prompt" 
                                              rows="6" 
                                              class="large-text"
                                              placeholder="<?php esc_attr_e('Describe what content you want to generate...', 'ai-content-classifier'); ?>"></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Be specific about topic, tone, length, and any key points to include.', 'ai-content-classifier'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'ai-content-classifier'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="seo_enabled" id="seo_enabled" checked>
                                        <?php esc_html_e('Generate SEO metadata', 'ai-content-classifier'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="auto_save" id="auto_save">
                                        <?php esc_html_e('Auto-save as draft', 'ai-content-classifier'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="generate-btn">
                                <?php esc_html_e('Generate Content', 'ai-content-classifier'); ?>
                            </button>
                            <span class="spinner" style="float: none;"></span>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Results area -->
            <div id="aicg-results" style="display: none;">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('Generated Content', 'ai-content-classifier'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="result-content">
                            <!-- Generated content will be displayed here -->
                        </div>
                        
                        <div class="aicg-actions">
                            <button class="button button-primary" id="create-post-btn">
                                <?php esc_html_e('Create Post', 'ai-content-classifier'); ?>
                            </button>
                            <button class="button" id="copy-content-btn">
                                <?php esc_html_e('Copy Content', 'ai-content-classifier'); ?>
                            </button>
                            <button class="button" id="regenerate-btn">
                                <?php esc_html_e('Regenerate', 'ai-content-classifier'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Results -->
                <div id="seo-results" class="postbox" style="display: none;">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('SEO Metadata', 'ai-content-classifier'); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Meta Description', 'ai-content-classifier'); ?></th>
                                <td><span id="meta-description"></span></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Keywords', 'ai-content-classifier'); ?></th>
                                <td><span id="keywords"></span></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Excerpt', 'ai-content-classifier'); ?></th>
                                <td><span id="excerpt"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="aicg-sidebar">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Quick Tips', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <h4><?php esc_html_e('Effective Prompts:', 'ai-content-classifier'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Be specific about your topic', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Mention target audience', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Specify desired tone (professional, casual, etc.)', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Include key points to cover', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Mention desired length', 'ai-content-classifier'); ?></li>
                    </ul>
                    
                    <h4><?php esc_html_e('Example Prompts:', 'ai-content-classifier'); ?></h4>
                    <p><em>"Write a 1000-word blog post about the benefits of remote work for software developers. Include statistics, pros and cons, and tips for staying productive. Use a professional but conversational tone."</em></p>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Usage Stats', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <?php
                    // Get today's usage stats for current user
                    $user_id = get_current_user_id();
                    $today_start = gmdate('Y-m-d 00:00:00');
                    $today_end = gmdate('Y-m-d 23:59:59');
                    
                    $usage_table = $wpdb->prefix . 'aicg_usage_log';
                    
                    // Check if table exists and get stats with caching
                    $stats_cache_key = 'aicg_generator_stats_' . $user_id . '_' . gmdate('Y-m-d');
                    $today_stats = wp_cache_get($stats_cache_key);
                    
                    if ($today_stats === false && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usage_table)) == $usage_table && $user_id) {
                        $today_stats = $wpdb->get_row($wpdb->prepare(
                            "SELECT 
                                COUNT(*) as generations, 
                                COALESCE(SUM(cost), 0) as cost,
                                COALESCE(SUM(tokens_used), 0) as tokens
                            FROM {$wpdb->prefix}aicg_usage_log 
                            WHERE user_id = %d AND created_at BETWEEN %s AND %s",
                            $user_id,
                            $today_start,
                            $today_end
                        ));
                        wp_cache_set($stats_cache_key, $today_stats, '', 300); // Cache for 5 minutes
                    }
                    
                    if ($today_stats) {
                        $generations = (int)$today_stats->generations;
                        $cost = (float)$today_stats->cost;
                        $tokens = (int)$today_stats->tokens;
                    } else {
                        $generations = 0;
                        $cost = 0.0;
                        $tokens = 0;
                    }
                    ?>
                    <p><?php esc_html_e('Content generated today:', 'ai-content-classifier'); ?> <strong><?php echo esc_html($generations); ?></strong></p>
                    <p><?php esc_html_e('Estimated cost:', 'ai-content-classifier'); ?> <strong>$<?php echo number_format($cost, 3); ?></strong></p>
                    <p><?php esc_html_e('Tokens used:', 'ai-content-classifier'); ?> <strong><?php echo number_format($tokens); ?></strong></p>
                </div>
            </div>
        </div>
    </div>
</div>