<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Core\BrandingManager;
use App\Helpers\MessageHelper;

/**
 * Branding Settings Controller
 * Handles white-label branding configuration
 */
class BrandingController extends Controller
{
    public function handleRequest(): void
    {
        // Require settings management permission
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_SETTINGS);

        $branding = BrandingManager::getInstance();
        $settings = $branding->getAllSettings();

        $this->render('branding_settings', [
            'settings' => $settings
        ]);
    }

    public function handleSubmission(): void
    {
        // Require settings management permission
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_SETTINGS);

        $action = $_POST['action'] ?? '';
        $branding = BrandingManager::getInstance();

        switch ($action) {
            case 'update_settings':
                $this->updateSettings($branding);
                break;
            case 'upload_logo':
                $this->uploadLogo($branding);
                break;
            case 'reset_defaults':
                $this->resetToDefaults($branding);
                break;
            default:
                MessageHelper::addMessage('Invalid action specified.', 'error');
        }

        // Redirect back to settings page
        header('Location: /branding');
        exit();
    }

    private function updateSettings(BrandingManager $branding): void
    {
        $settings = [
            'app_name' => trim($_POST['app_name'] ?? ''),
            'primary_color' => trim($_POST['primary_color'] ?? ''),
            'secondary_color' => trim($_POST['secondary_color'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'footer_text' => trim($_POST['footer_text'] ?? ''),
            'theme_mode' => $_POST['theme_mode'] ?? 'light',
            'enable_custom_css' => isset($_POST['enable_custom_css']),
            'custom_css' => trim($_POST['custom_css'] ?? '')
        ];

        $success = true;
        foreach ($settings as $key => $value) {
            $type = ($key === 'enable_custom_css') ? 'boolean' : 'string';
            if ($key === 'custom_css') {
                $type = 'text';
            }
            
            if (!$branding->setSetting($key, $value, $type)) {
                $success = false;
            }
        }

        if ($success) {
            MessageHelper::addMessage('Branding settings updated successfully.', 'success');
        } else {
            MessageHelper::addMessage('Some settings could not be updated.', 'error');
        }
    }

    private function uploadLogo(BrandingManager $branding): void
    {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
            MessageHelper::addMessage('No logo file uploaded.', 'error');
            return;
        }

        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            MessageHelper::addMessage('File upload error occurred.', 'error');
            return;
        }

        $result = $branding->uploadLogo($_FILES['logo']);
        
        if ($result['success']) {
            MessageHelper::addMessage($result['message'], 'success');
        } else {
            MessageHelper::addMessage($result['message'], 'error');
        }
    }

    private function resetToDefaults(BrandingManager $branding): void
    {
        if ($branding->resetToDefaults()) {
            MessageHelper::addMessage('Branding settings reset to defaults successfully.', 'success');
        } else {
            MessageHelper::addMessage('Failed to reset branding settings.', 'error');
        }
    }
}