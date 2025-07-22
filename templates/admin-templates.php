<?php
/**
 * Templates management page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get templates
global $wpdb;
$templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicg_templates ORDER BY name ASC");

// Handle form submissions
if (isset($_POST['action'])) {
    $action = sanitize_text_field(wp_unslash($_POST['action']));
    switch ($action) {
        case 'create_template':
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aicg_template_nonce')) {
                $name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
                $prompt = isset($_POST['template_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['template_prompt'])) : '';
                $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'post';
                $seo_enabled = isset($_POST['seo_enabled']) ? 1 : 0;
                
                if (empty($name) || empty($prompt)) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Template name and prompt are required.', 'ai-content-classifier') . '</p></div>';
                    break;
                }
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'aicg_templates',
                    array(
                        'name' => $name,
                        'prompt' => $prompt,
                        'content_type' => $content_type,
                        'seo_enabled' => $seo_enabled
                    )
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Template created successfully!', 'ai-content-classifier') . '</p></div>';
                    // Refresh templates list
                    $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicg_templates ORDER BY name ASC");
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create template.', 'ai-content-classifier') . '</p></div>';
                }
            }
            break;
            
        case 'delete_template':
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aicg_template_nonce')) {
                $template_id = isset($_POST['template_id']) ? intval(wp_unslash($_POST['template_id'])) : 0;
                
                if ($template_id <= 0) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid template ID.', 'ai-content-classifier') . '</p></div>';
                    break;
                }
                $result = $wpdb->delete(
                    $wpdb->prefix . 'aicg_templates',
                    array('id' => $template_id)
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Template deleted successfully!', 'ai-content-classifier') . '</p></div>';
                    // Refresh templates list
                    $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicg_templates ORDER BY name ASC");
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete template.', 'ai-content-classifier') . '</p></div>';
                }
            }
            break;
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="aicg-templates-wrapper">
        <div class="aicg-main-content">
            <!-- Create new template -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Create New Template', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="create_template">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('aicg_template_nonce')); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template_name"><?php esc_html_e('Template Name', 'ai-content-classifier'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="template_name" 
                                           name="template_name" 
                                           class="regular-text" 
                                           required 
                                           placeholder="<?php esc_attr_e('e.g., Blog Post - How To Guide', 'ai-content-classifier'); ?>" />
                                </td>
                            </tr>
                            
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
                                    <label for="template_prompt"><?php esc_html_e('Prompt Template', 'ai-content-classifier'); ?></label>
                                </th>
                                <td>
                                    <textarea name="template_prompt" 
                                              id="template_prompt" 
                                              rows="8" 
                                              class="large-text" 
                                              required
                                              placeholder="<?php esc_attr_e('Write a comprehensive guide about [TOPIC]. Include step-by-step instructions, tips, and examples. Target audience: [AUDIENCE]. Tone: [TONE]. Length: [LENGTH] words.', 'ai-content-classifier'); ?>"></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Use placeholders like [TOPIC], [AUDIENCE], [TONE], [LENGTH] that can be replaced when using the template.', 'ai-content-classifier'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'ai-content-classifier'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="seo_enabled" value="1" checked>
                                        <?php esc_html_e('Enable SEO optimization by default', 'ai-content-classifier'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Create Template', 'ai-content-classifier'); ?>">
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Existing templates -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Saved Templates', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <?php if (empty($templates)): ?>
                        <p><?php esc_html_e('No templates created yet. Create your first template above!', 'ai-content-classifier'); ?></p>
                    <?php else: ?>
                        <div class="aicg-templates-list">
                            <?php foreach ($templates as $template): ?>
                                <div class="template-item">
                                    <h3><?php echo esc_html($template->name); ?></h3>
                                    <p><strong><?php esc_html_e('Type:', 'ai-content-classifier'); ?></strong> <?php echo esc_html(ucfirst($template->content_type)); ?></p>
                                    <p><strong><?php esc_html_e('SEO Enabled:', 'ai-content-classifier'); ?></strong> <?php echo $template->seo_enabled ? esc_html__('Yes', 'ai-content-classifier') : esc_html__('No', 'ai-content-classifier'); ?></p>
                                    <p><strong><?php esc_html_e('Prompt:', 'ai-content-classifier'); ?></strong></p>
                                    <div class="template-prompt">
                                        <?php echo nl2br(esc_html(wp_trim_words($template->prompt, 50))); ?>
                                        <?php if (str_word_count($template->prompt) > 50): ?>
                                            <button type="button" class="button button-small toggle-full-prompt" data-template-id="<?php echo esc_attr($template->id); ?>">
                                                <?php esc_html_e('Show Full Prompt', 'ai-content-classifier'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="template-full-prompt" id="full-prompt-<?php echo esc_attr($template->id); ?>" style="display: none;">
                                        <p><?php echo nl2br(esc_html($template->prompt)); ?></p>
                                    </div>
                                    
                                    <div class="template-actions">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-content-generator&template=' . $template->id); ?>" 
                                           class="button button-primary">
                                            <?php esc_html_e('Use Template', 'ai-content-classifier'); ?>
                                        </a>
                                        <button type="button" class="button edit-template" data-template-id="<?php echo esc_attr($template->id); ?>">
                                            <?php esc_html_e('Edit', 'ai-content-classifier'); ?>
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this template?', 'ai-content-classifier'); ?>');">
                                            <input type="hidden" name="action" value="delete_template">
                                            <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
                                            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('aicg_template_nonce')); ?>">
                                            <input type="submit" class="button button-link-delete" value="<?php esc_attr_e('Delete', 'ai-content-classifier'); ?>">
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="aicg-sidebar">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Template Tips', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <h4><?php esc_html_e('Creating Good Templates:', 'ai-content-classifier'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Use clear, specific instructions', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Include placeholders for customization', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Specify tone and style requirements', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Mention target audience', 'ai-content-classifier'); ?></li>
                        <li><?php esc_html_e('Include formatting guidelines', 'ai-content-classifier'); ?></li>
                    </ul>
                    
                    <h4><?php esc_html_e('Useful Placeholders:', 'ai-content-classifier'); ?></h4>
                    <ul>
                        <li><code>[TOPIC]</code> - Main subject</li>
                        <li><code>[AUDIENCE]</code> - Target readers</li>
                        <li><code>[TONE]</code> - Writing style</li>
                        <li><code>[LENGTH]</code> - Word count</li>
                        <li><code>[KEYWORDS]</code> - SEO keywords</li>
                    </ul>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Example Templates', 'ai-content-classifier'); ?></h2>
                </div>
                <div class="inside">
                    <h4><?php esc_html_e('Blog Post Template:', 'ai-content-classifier'); ?></h4>
                    <p><em>"Write a comprehensive blog post about [TOPIC]. Include an engaging introduction, 3-5 main sections with examples, and a conclusion with actionable takeaways. Target audience: [AUDIENCE]. Tone: informative yet conversational. Length: 1500 words."</em></p>
                    
                    <h4><?php esc_html_e('Product Description:', 'ai-content-classifier'); ?></h4>
                    <p><em>"Create a compelling product description for [PRODUCT]. Highlight key features, benefits, and unique selling points. Include technical specifications and use cases. Target customers: [AUDIENCE]. Tone: persuasive and professional. Length: 300 words."</em></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle full prompt display
    $('.toggle-full-prompt').on('click', function() {
        const templateId = $(this).data('template-id');
        const fullPrompt = $('#full-prompt-' + templateId);
        
        if (fullPrompt.is(':visible')) {
            fullPrompt.hide();
            $(this).text('<?php esc_html_e('Show Full Prompt', 'ai-content-classifier'); ?>');
        } else {
            fullPrompt.show();
            $(this).text('<?php esc_html_e('Hide Full Prompt', 'ai-content-classifier'); ?>');
        }
    });
    
    // Edit template functionality (simplified)
    $('.edit-template').on('click', function() {
        alert('<?php esc_html_e('Edit functionality coming soon!', 'ai-content-classifier'); ?>');
    });
});
</script>