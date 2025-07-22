<?php
/**
 * Settings page template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Settings are initialized in the main plugin class

// Handle form submission
if (isset($_POST['submit'])) {
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'ai-content-classifier') . '</p></div>';
}

// Test API connection
$api_key = get_option('aicg_api_key');
$api_status = 'not-configured';

if (!empty($api_key)) {
    $client = new OpenAI_Client();
    if ($client->validate_api_key($api_key)) {
        $api_status = 'connected';
    } else {
        $api_status = 'error';
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($api_status === 'error'): ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('API connection failed. Please check your API key.', 'ai-content-classifier'); ?></p>
        </div>
    <?php elseif ($api_status === 'connected'): ?>
        <div class="notice notice-success">
            <p><?php esc_html_e('API connection successful!', 'ai-content-classifier'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('aicg_settings');
        do_settings_sections('aicg_settings');
        submit_button();
        ?>
    </form>
    
    <div class="aicg-settings-section">
        <h2><?php esc_html_e('Getting Started', 'ai-content-classifier'); ?></h2>
        <ol>
            <li><?php esc_html_e('Sign up for an OpenAI account at', 'ai-content-classifier'); ?> <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></li>
            <li><?php esc_html_e('Create an API key in your OpenAI dashboard', 'ai-content-classifier'); ?></li>
            <li><?php esc_html_e('Add billing information to your OpenAI account', 'ai-content-classifier'); ?></li>
            <li><?php esc_html_e('Paste your API key above and save settings', 'ai-content-classifier'); ?></li>
            <li><?php esc_html_e('Start generating content!', 'ai-content-classifier'); ?></li>
        </ol>
        
        <h3><?php esc_html_e('Cost Information', 'ai-content-classifier'); ?></h3>
        <p><?php esc_html_e('OpenAI charges per token used. Approximate costs:', 'ai-content-classifier'); ?></p>
        <ul>
            <li>GPT-3.5 Turbo: ~$0.002 per 1,000 tokens</li>
            <li>GPT-4: ~$0.06 per 1,000 tokens</li>
            <li>1 token ≈ 0.75 words</li>
        </ul>
        <p><?php esc_html_e('A typical blog post (1,000 words) costs $0.003-0.08 to generate.', 'ai-content-classifier'); ?></p>
    </div>
</div>

<script>
// Initialize settings page
jQuery(document).ready(function($) {
    // Update cost estimate when settings change
    $('#aicg_model, #aicg_max_tokens').on('change input', function() {
        updateCostEstimate();
    });
    
    // Update cost estimate on page load
    updateCostEstimate();
    
    function updateCostEstimate() {
        const model = $('#aicg_model').val();
        const maxTokens = parseInt($('#aicg_max_tokens').val()) || 2000;
        
        const costPerToken = {
            'gpt-3.5-turbo': 0.000002,
            'gpt-3.5-turbo-16k': 0.000003,
            'gpt-4': 0.00006,
            'gpt-4-turbo-preview': 0.00004
        };
        
        const cost = (costPerToken[model] || 0.000002) * maxTokens;
        const costFormatted = '$' + cost.toFixed(4);
        
        // Add cost info to model field if it exists
        const modelField = $('#aicg_model').closest('td');
        let costInfo = modelField.find('.cost-info');
        if (costInfo.length === 0) {
            costInfo = $('<p class="cost-info description"></p>');
            modelField.append(costInfo);
        }
        costInfo.text('Estimated cost per generation: ' + costFormatted);
    }
});
</script>