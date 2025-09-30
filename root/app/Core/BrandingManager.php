<?php

namespace App\Core;

use App\Core\DatabaseManager;
use App\Core\AuditLogger;

/**
 * Branding and Settings Manager
 * Handles white-label branding and application configuration
 */
class BrandingManager
{
    private static ?BrandingManager $instance = null;
    private array $settings = [];
    private bool $loaded = false;

    private function __construct()
    {
    }

    public static function getInstance(): BrandingManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load all settings from database
     */
    private function loadSettings(): void
    {
        if ($this->loaded) {
            return;
        }

        try {
            $db = DatabaseManager::getInstance();
            $db->query('SELECT setting_key, setting_value, setting_type FROM app_settings');
            $results = $db->resultSet();

            foreach ($results as $setting) {
                $value = $setting['setting_value'];

                // Convert based on type
                switch ($setting['setting_type']) {
                    case 'boolean':
                        $value = (bool) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }

                $this->settings[$setting['setting_key']] = $value;
            }

            $this->loaded = true;
        } catch (\Exception $e) {
            // Set defaults if database fails
            $this->setDefaults();
        }
    }

    /**
     * Set default values
     */
    private function setDefaults(): void
    {
        $this->settings = [
            'app_name' => 'DMARC Dashboard',
            'app_logo_url' => '',
            'primary_color' => '#5755d9',
            'secondary_color' => '#f1f3f4',
            'company_name' => '',
            'footer_text' => '',
            'theme_mode' => 'light',
            'enable_custom_css' => false,
            'custom_css' => ''
        ];
        $this->loaded = true;
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $this->loadSettings();
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $key, $value, string $type = 'string', ?string $description = null): bool
    {
        try {
            $oldValue = $this->getSetting($key);

            $db = DatabaseManager::getInstance();

            // Check if setting exists
            $db->query('SELECT 1 FROM app_settings WHERE setting_key = :key');
            $db->bind(':key', $key);
            $exists = $db->single();

            $session = SessionManager::getInstance();
            $username = $session->get('username');

            if ($exists) {
                // Update existing setting
                $db->query('
                    UPDATE app_settings 
                    SET setting_value = :value, setting_type = :type, updated_by = :username, updated_at = CURRENT_TIMESTAMP
                    WHERE setting_key = :key
                ');
            } else {
                // Insert new setting
                $db->query('
                    INSERT INTO app_settings (setting_key, setting_value, setting_type, description, updated_by)
                    VALUES (:key, :value, :type, :description, :username)
                ');
                $db->bind(':description', $description);
            }

            $db->bind(':key', $key);
            $db->bind(':value', is_array($value) ? json_encode($value) : (string) $value);
            $db->bind(':type', $type);
            $db->bind(':username', $username);

            $result = $db->execute();

            if ($result) {
                // Update local cache
                $this->settings[$key] = $value;

                // Log the change
                AuditLogger::getInstance()->logSettingsChange($key, $oldValue, $value);
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all settings
     */
    public function getAllSettings(): array
    {
        $this->loadSettings();
        return $this->settings;
    }

    /**
     * Get branding-specific settings for templates
     */
    public function getBrandingVars(): array
    {
        return [
            'app_name' => $this->getSetting('app_name', 'DMARC Dashboard'),
            'app_logo_url' => $this->getSetting('app_logo_url', ''),
            'primary_color' => $this->getSetting('primary_color', '#5755d9'),
            'secondary_color' => $this->getSetting('secondary_color', '#f1f3f4'),
            'company_name' => $this->getSetting('company_name', ''),
            'footer_text' => $this->getSetting('footer_text', ''),
            'theme_mode' => $this->getSetting('theme_mode', 'light')
        ];
    }

    /**
     * Generate custom CSS based on settings
     */
    public function getCustomCSS(): string
    {
        $css = '';

        $primaryColor = $this->getSetting('primary_color', '#5755d9');
        $secondaryColor = $this->getSetting('secondary_color', '#f1f3f4');

        // Generate CSS variables for theming
        $css .= ":root {\n";
        $css .= "  --primary-color: $primaryColor;\n";
        $css .= "  --secondary-color: $secondaryColor;\n";
        $css .= "}\n\n";

        // Override Spectre.css primary color
        $css .= ".btn-primary {\n";
        $css .= "  background-color: var(--primary-color);\n";
        $css .= "  border-color: var(--primary-color);\n";
        $css .= "}\n\n";

        $css .= ".btn-primary:hover {\n";
        $css .= "  background-color: color-mix(in srgb, var(--primary-color) 85%, black);\n";
        $css .= "  border-color: color-mix(in srgb, var(--primary-color) 85%, black);\n";
        $css .= "}\n\n";

        // Links and accents
        $css .= "a, .text-primary {\n";
        $css .= "  color: var(--primary-color);\n";
        $css .= "}\n\n";

        // Form focus states
        $css .= ".form-input:focus, .form-select:focus {\n";
        $css .= "  border-color: var(--primary-color);\n";
        $css .= "  box-shadow: 0 0 0 0.1rem color-mix(in srgb, var(--primary-color) 25%, transparent);\n";
        $css .= "}\n\n";

        // Add custom CSS if enabled
        if ($this->getSetting('enable_custom_css', false)) {
            $customCSS = $this->getSetting('custom_css', '');
            if (!empty($customCSS)) {
                $css .= "\n/* Custom CSS */\n";
                $css .= $customCSS . "\n";
            }
        }

        return $css;
    }

    /**
     * Upload and handle logo file
     */
    public function uploadLogo(array $fileData): array
    {
        $result = ['success' => false, 'message' => '', 'url' => ''];

        try {
            // Validate file
            if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
                throw new \Exception('No valid file uploaded');
            }

            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileData['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
            }

            // Check file size (max 2MB)
            if ($fileData['size'] > 2 * 1024 * 1024) {
                throw new \Exception('File too large. Maximum size is 2MB.');
            }

            // Generate unique filename
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $extension;
            $uploadDir = __DIR__ . '/../../public/assets/images/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadPath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
                throw new \Exception('Failed to save uploaded file');
            }

            // Update setting
            $logoUrl = '/assets/images/' . $filename;
            $this->setSetting('app_logo_url', $logoUrl);

            $result['success'] = true;
            $result['url'] = $logoUrl;
            $result['message'] = 'Logo uploaded successfully';
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Reset branding to defaults
     */
    public function resetToDefaults(): bool
    {
        $defaults = [
            'app_name' => 'DMARC Dashboard',
            'app_logo_url' => '',
            'primary_color' => '#5755d9',
            'secondary_color' => '#f1f3f4',
            'company_name' => '',
            'footer_text' => '',
            'theme_mode' => 'light',
            'enable_custom_css' => false,
            'custom_css' => ''
        ];

        $success = true;
        foreach ($defaults as $key => $value) {
            if (!$this->setSetting($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }
}
