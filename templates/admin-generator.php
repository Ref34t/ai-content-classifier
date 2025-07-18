<?php
/**
 * Content generator admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get saved templates
global $wpdb;
$templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicg_templates ORDER BY name ASC");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="aicg-generator-wrapper">
        <div class="aicg-main-content">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Generate Content', 'ai-content-generator'); ?></h2>
                </div>
                <div class="inside">
                    <form id="aicg-generate-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="content_type"><?php _e('Content Type', 'ai-content-generator'); ?></label>
                                </th>
                                <td>
                                    <select name="content_type" id="content_type">
                                        <option value="post"><?php _e('Blog Post', 'ai-content-generator'); ?></option>
                                        <option value="page"><?php _e('Page', 'ai-content-generator'); ?></option>
                                        <option value="product"><?php _e('Product Description', 'ai-content-generator'); ?></option>
                                        <option value="email"><?php _e('Email', 'ai-content-generator'); ?></option>
                                        <option value="social"><?php _e('Social Media', 'ai-content-generator'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="template"><?php _e('Template', 'ai-content-generator'); ?></label>
                                </th>
                                <td>
                                    <select name="template" id="template">
                                        <option value=""><?php _e('-- Custom Prompt --', 'ai-content-generator'); ?></option>
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
                                    <label for="prompt"><?php _e('Prompt', 'ai-content-generator'); ?></label>
                                </th>
                                <td>
                                    <textarea name="prompt" 
                                              id="prompt" 
                                              rows="6" 
                                              class="large-text"
                                              placeholder="<?php esc_attr_e('Describe what content you want to generate...', 'ai-content-generator'); ?>"></textarea>
                                    <p class="description">
                                        <?php _e('Be specific about topic, tone, length, and any key points to include.', 'ai-content-generator'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Options', 'ai-content-generator'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="seo_enabled" id="seo_enabled" checked>
                                        <?php _e('Generate SEO metadata', 'ai-content-generator'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="auto_save" id="auto_save">
                                        <?php _e('Auto-save as draft', 'ai-content-generator'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="generate-btn">
                                <?php _e('Generate Content', 'ai-content-generator'); ?>
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
                        <h2><?php _e('Generated Content', 'ai-content-generator'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="result-content">
                            <!-- Generated content will be displayed here -->
                        </div>
                        
                        <div class="aicg-actions">
                            <button class="button button-primary" id="create-post-btn">
                                <?php _e('Create Post', 'ai-content-generator'); ?>
                            </button>
                            <button class="button" id="copy-content-btn">
                                <?php _e('Copy Content', 'ai-content-generator'); ?>
                            </button>
                            <button class="button" id="regenerate-btn">
                                <?php _e('Regenerate', 'ai-content-generator'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Results -->
                <div id="seo-results" class="postbox" style="display: none;">
                    <div class="postbox-header">
                        <h2><?php _e('SEO Metadata', 'ai-content-generator'); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Meta Description', 'ai-content-generator'); ?></th>
                                <td><span id="meta-description"></span></td>
                            </tr>
                            <tr>
                                <th><?php _e('Keywords', 'ai-content-generator'); ?></th>
                                <td><span id="keywords"></span></td>
                            </tr>
                            <tr>
                                <th><?php _e('Excerpt', 'ai-content-generator'); ?></th>
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
                    <h2><?php _e('Quick Tips', 'ai-content-generator'); ?></h2>
                </div>
                <div class="inside">
                    <h4><?php _e('Effective Prompts:', 'ai-content-generator'); ?></h4>
                    <ul>
                        <li><?php _e('Be specific about your topic', 'ai-content-generator'); ?></li>
                        <li><?php _e('Mention target audience', 'ai-content-generator'); ?></li>
                        <li><?php _e('Specify desired tone (professional, casual, etc.)', 'ai-content-generator'); ?></li>
                        <li><?php _e('Include key points to cover', 'ai-content-generator'); ?></li>
                        <li><?php _e('Mention desired length', 'ai-content-generator'); ?></li>
                    </ul>
                    
                    <h4><?php _e('Example Prompts:', 'ai-content-generator'); ?></h4>
                    <p><em>"Write a 1000-word blog post about the benefits of remote work for software developers. Include statistics, pros and cons, and tips for staying productive. Use a professional but conversational tone."</em></p>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Usage Stats', 'ai-content-generator'); ?></h2>
                </div>
                <div class="inside">
                    <?php
                    // Get today's usage stats for current user
                    $user_id = get_current_user_id();
                    $today_start = date('Y-m-d 00:00:00');
                    $today_end = date('Y-m-d 23:59:59');
                    
                    $usage_table = $wpdb->prefix . 'aicg_usage_log';
                    
                    // Check if table exists and get stats
                    if ($wpdb->get_var("SHOW TABLES LIKE '$usage_table'") == $usage_table && $user_id) {
                        $today_stats = $wpdb->get_row($wpdb->prepare(
                            "SELECT 
                                COUNT(*) as generations, 
                                COALESCE(SUM(cost), 0) as cost,
                                COALESCE(SUM(tokens_used), 0) as tokens
                            FROM $usage_table 
                            WHERE user_id = %d AND created_at BETWEEN %s AND %s",
                            $user_id,
                            $today_start,
                            $today_end
                        ));
                        
                        $generations = (int)$today_stats->generations;
                        $cost = (float)$today_stats->cost;
                        $tokens = (int)$today_stats->tokens;
                    } else {
                        $generations = 0;
                        $cost = 0.0;
                        $tokens = 0;
                    }
                    ?>
                    <p><?php _e('Content generated today:', 'ai-content-generator'); ?> <strong><?php echo $generations; ?></strong></p>
                    <p><?php _e('Estimated cost:', 'ai-content-generator'); ?> <strong>$<?php echo number_format($cost, 3); ?></strong></p>
                    <p><?php _e('Tokens used:', 'ai-content-generator'); ?> <strong><?php echo number_format($tokens); ?></strong></p>
                </div>
            </div>
        </div>
    </div>
</div>