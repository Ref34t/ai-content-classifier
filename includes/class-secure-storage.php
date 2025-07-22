<?php
/**
 * Secure storage for sensitive data like API keys
 */
class AICG_Secure_Storage {
    
    private $encryption_key;
    private $logger;
    
    public function __construct() {
        $this->logger = new AICG_Logger();
        $this->encryption_key = $this->get_or_create_encryption_key();
    }
    
    /**
     * Store encrypted API key
     */
    public function store_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        // Validate API key format
        if (!$this->validate_api_key_format($api_key)) {
            $this->logger->error(__('Invalid API key format provided', 'ai-content-classifier'));
            return false;
        }
        
        // Encrypt the API key
        $encrypted_key = $this->encrypt_data($api_key);
        
        if ($encrypted_key === false) {
            $this->logger->error(__('Failed to encrypt API key', 'ai-content-classifier'));
            return false;
        }
        
        // Store encrypted key
        $result = update_option('aicg_api_key_encrypted', $encrypted_key);
        
        if ($result) {
            $this->logger->info(__('API key stored successfully (encrypted)', 'ai-content-classifier'));
            // Remove any old unencrypted keys
            delete_option('aicg_api_key');
        } else {
            $this->logger->error(__('Failed to store encrypted API key', 'ai-content-classifier'));
        }
        
        return $result;
    }
    
    /**
     * Retrieve and decrypt API key
     */
    public function get_api_key() {
        // First try to get encrypted key
        $encrypted_key = get_option('aicg_api_key_encrypted');
        
        if ($encrypted_key) {
            $decrypted_key = $this->decrypt_data($encrypted_key);
            
            if ($decrypted_key === false) {
                $this->logger->error(__('Failed to decrypt API key', 'ai-content-classifier'));
                return false;
            }
            
            return $decrypted_key;
        }
        
        // Fallback to old unencrypted key (for migration)
        $old_key = get_option('aicg_api_key');
        if ($old_key) {
            $this->logger->warning(__('Found unencrypted API key, migrating to encrypted storage', 'ai-content-classifier'));
            
            // Migrate to encrypted storage
            if ($this->store_api_key($old_key)) {
                delete_option('aicg_api_key');
                return $old_key;
            }
        }
        
        return false;
    }
    
    /**
     * Validate API key format
     */
    private function validate_api_key_format($api_key) {
        // OpenAI API keys start with 'sk-' and are 51 characters long
        if (!preg_match('/^sk-[A-Za-z0-9]{48}$/', $api_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            $this->logger->warning('OpenSSL not available, cannot encrypt data');
            return false;
        }
        
        try {
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
            
            if ($encrypted === false) {
                $this->logger->error('OpenSSL encryption failed');
                return false;
            }
            
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            $this->logger->error('Encryption error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    private function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            $this->logger->warning('OpenSSL not available, cannot decrypt data');
            return false;
        }
        
        try {
            $data = base64_decode($encrypted_data);
            if ($data === false) {
                $this->logger->error('Invalid base64 encoded data');
                return false;
            }
            
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
            
            if ($decrypted === false) {
                $this->logger->error('OpenSSL decryption failed');
                return false;
            }
            
            return $decrypted;
        } catch (Exception $e) {
            $this->logger->error('Decryption error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or create encryption key
     */
    private function get_or_create_encryption_key() {
        $key = get_option('aicg_encryption_key');
        
        if (!$key) {
            // Generate a new encryption key
            $key = $this->generate_encryption_key();
            
            if ($key) {
                update_option('aicg_encryption_key', $key);
                $this->logger->info('New encryption key generated');
            } else {
                $this->logger->critical('Failed to generate encryption key');
                return false;
            }
        }
        
        return $key;
    }
    
    /**
     * Generate a secure encryption key
     */
    private function generate_encryption_key() {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $key = openssl_random_pseudo_bytes(32);
            return base64_encode($key);
        } elseif (function_exists('random_bytes')) {
            $key = random_bytes(32);
            return base64_encode($key);
        } else {
            // Fallback to WordPress function
            $key = wp_generate_password(32, true, true);
            return base64_encode($key);
        }
    }
    
    /**
     * Rotate encryption key (for security)
     */
    public function rotate_encryption_key() {
        $old_key = $this->encryption_key;
        $new_key = $this->generate_encryption_key();
        
        if (!$new_key) {
            $this->logger->error('Failed to generate new encryption key');
            return false;
        }
        
        // Get current API key with old key
        $api_key = $this->get_api_key();
        
        if (!$api_key) {
            $this->logger->warning('No API key found during key rotation');
            return false;
        }
        
        // Update encryption key
        $this->encryption_key = $new_key;
        update_option('aicg_encryption_key', $new_key);
        
        // Re-encrypt API key with new key
        $result = $this->store_api_key($api_key);
        
        if ($result) {
            $this->logger->info('Encryption key rotated successfully');
        } else {
            $this->logger->error('Failed to re-encrypt API key with new key');
            // Restore old key
            $this->encryption_key = $old_key;
            update_option('aicg_encryption_key', $old_key);
        }
        
        return $result;
    }
    
    /**
     * Clear all encrypted data
     */
    public function clear_encrypted_data() {
        delete_option('aicg_api_key_encrypted');
        delete_option('aicg_api_key');
        
        $this->logger->info('All encrypted data cleared');
    }
    
    /**
     * Test encryption/decryption
     */
    public function test_encryption() {
        $test_data = 'test_encryption_' . wp_generate_password(12, false);
        
        $encrypted = $this->encrypt_data($test_data);
        if ($encrypted === false) {
            return false;
        }
        
        $decrypted = $this->decrypt_data($encrypted);
        if ($decrypted === false) {
            return false;
        }
        
        return $decrypted === $test_data;
    }
    
    /**
     * Get encryption status
     */
    public function get_encryption_status() {
        $status = array(
            'encryption_available' => function_exists('openssl_encrypt'),
            'key_exists' => !empty($this->encryption_key),
            'test_passed' => $this->test_encryption(),
            'api_key_encrypted' => !empty(get_option('aicg_api_key_encrypted')),
            'old_key_exists' => !empty(get_option('aicg_api_key'))
        );
        
        $status['fully_encrypted'] = $status['encryption_available'] && 
                                    $status['key_exists'] && 
                                    $status['test_passed'] && 
                                    $status['api_key_encrypted'] && 
                                    !$status['old_key_exists'];
        
        return $status;
    }
    
    /**
     * Migrate old unencrypted data
     */
    public function migrate_unencrypted_data() {
        $migrated = 0;
        
        // Migrate API key
        $old_api_key = get_option('aicg_api_key');
        if ($old_api_key) {
            if ($this->store_api_key($old_api_key)) {
                delete_option('aicg_api_key');
                $migrated++;
                $this->logger->info('Migrated API key to encrypted storage');
            }
        }
        
        // Add migration for other sensitive data here in the future
        
        return $migrated;
    }
}