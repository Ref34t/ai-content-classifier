<?php
/**
 * Settings management
 */
class AICG_Settings {
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings
        register_setting('aicg_settings', 'aicg_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('aicg_settings', 'aicg_model', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo'
        ));
        
        register_setting('aicg_settings', 'aicg_max_tokens', array(
            'sanitize_callback' => 'absint',
            'default' => 2000
        ));
        
        register_setting('aicg_settings', 'aicg_temperature', array(
            'sanitize_callback' => array($this, 'sanitize_temperature'),
            'default' => 0.7
        ));
        
        register_setting('aicg_settings', 'aicg_default_language', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'en'
        ));
        
        // Add settings sections
        add_settings_section(
            'aicg_api_settings',
            __('API Settings', 'ai-content-classifier'),
            array($this, 'api_settings_callback'),
            'aicg_settings'
        );
        
        add_settings_section(
            'aicg_generation_settings',
            __('Content Generation Settings', 'ai-content-classifier'),
            array($this, 'generation_settings_callback'),
            'aicg_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'aicg_api_key',
            __('OpenAI API Key', 'ai-content-classifier'),
            array($this, 'api_key_field_callback'),
            'aicg_settings',
            'aicg_api_settings'
        );
        
        add_settings_field(
            'aicg_model',
            __('AI Model', 'ai-content-classifier'),
            array($this, 'model_field_callback'),
            'aicg_settings',
            'aicg_api_settings'
        );
        
        add_settings_field(
            'aicg_max_tokens',
            __('Max Tokens', 'ai-content-classifier'),
            array($this, 'max_tokens_field_callback'),
            'aicg_settings',
            'aicg_generation_settings'
        );
        
        add_settings_field(
            'aicg_temperature',
            __('Temperature (Creativity)', 'ai-content-classifier'),
            array($this, 'temperature_field_callback'),
            'aicg_settings',
            'aicg_generation_settings'
        );
        
        add_settings_field(
            'aicg_default_language',
            __('Default Language', 'ai-content-classifier'),
            array($this, 'language_field_callback'),
            'aicg_settings',
            'aicg_generation_settings'
        );
    }
    
    /**
     * Sanitize temperature value
     */
    public function sanitize_temperature($value) {
        $value = floatval($value);
        return max(0, min(2, $value)); // Clamp between 0 and 2
    }
    
    /**
     * Section callbacks
     */
    public function api_settings_callback() {
        echo '<p>' . __('Configure your OpenAI API settings.', 'ai-content-classifier') . '</p>';
    }
    
    public function generation_settings_callback() {
        echo '<p>' . __('Customize how content is generated.', 'ai-content-classifier') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function api_key_field_callback() {
        $value = get_option('aicg_api_key');
        ?>
        <input type="password" 
               id="aicg_api_key" 
               name="aicg_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="sk-..." />
        <p class="description">
            <?php _e('Get your API key from', 'ai-content-classifier'); ?> 
            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>
        </p>
        <?php
    }
    
    public function model_field_callback() {
        $value = get_option('aicg_model', 'gpt-3.5-turbo');
        $client = new OpenAI_Client();
        $models = $client->get_available_models();
        ?>
        <select id="aicg_model" name="aicg_model">
            <?php foreach ($models as $model_id => $model_name): ?>
                <option value="<?php echo esc_attr($model_id); ?>" 
                        <?php selected($value, $model_id); ?>>
                    <?php echo esc_html($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Choose the AI model to use for content generation.', 'ai-content-classifier'); ?>
        </p>
        <?php
    }
    
    public function max_tokens_field_callback() {
        $value = get_option('aicg_max_tokens', 2000);
        ?>
        <input type="number" 
               id="aicg_max_tokens" 
               name="aicg_max_tokens" 
               value="<?php echo esc_attr($value); ?>" 
               min="100" 
               max="8000" 
               step="100" />
        <p class="description">
            <?php _e('Maximum number of tokens to generate (1 token ≈ 0.75 words).', 'ai-content-classifier'); ?>
        </p>
        <?php
    }
    
    public function temperature_field_callback() {
        $value = get_option('aicg_temperature', 0.7);
        ?>
        <input type="range" 
               id="aicg_temperature" 
               name="aicg_temperature" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               max="2" 
               step="0.1" />
        <span id="temp_value"><?php echo esc_html($value); ?></span>
        <p class="description">
            <?php _e('Lower values = more focused, higher values = more creative.', 'ai-content-classifier'); ?>
        </p>
        <?php
    }
    
    public function language_field_callback() {
        $value = get_option('aicg_default_language', 'en');
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese'
        );
        ?>
        <select id="aicg_default_language" name="aicg_default_language">
            <?php foreach ($languages as $code => $name): ?>
                <option value="<?php echo esc_attr($code); ?>" 
                        <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Default language for content generation.', 'ai-content-classifier'); ?>
        </p>
        <?php
    }
}