<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Utilities\DataRetention;

class RetentionSettingsController extends Controller
{
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_RETENTION);

        $settings = DataRetention::getRetentionSettings();
        $defaults = [
            'aggregate_reports_retention_days' => $settings['aggregate_reports_retention_days'] ?? '',
            'forensic_reports_retention_days' => $settings['forensic_reports_retention_days'] ?? '',
            'tls_reports_retention_days' => $settings['tls_reports_retention_days'] ?? '',
        ];

        $this->data = [
            'settings' => $defaults,
        ];

        require __DIR__ . '/../Views/retention_settings.php';
    }

    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_RETENTION);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            $this->redirectBack();
            return;
        }

        $fields = [
            'aggregate_reports_retention_days',
            'forensic_reports_retention_days',
            'tls_reports_retention_days',
        ];

        $updates = [];
        foreach ($fields as $field) {
            $raw = trim((string) ($_POST[$field] ?? ''));
            if ($raw === '') {
                continue;
            }

            if (!ctype_digit($raw)) {
                MessageHelper::addMessage('Retention values must be non-negative integers.', 'error');
                $this->redirectBack();
                return;
            }

            $updates[$field] = $raw;
        }

        if (empty($updates)) {
            MessageHelper::addMessage('Please provide at least one retention value to update.', 'warning');
            $this->redirectBack();
            return;
        }

        $success = true;
        foreach ($updates as $name => $value) {
            if (!DataRetention::updateRetentionSetting($name, $value)) {
                $success = false;
            }
        }

        if ($success) {
            MessageHelper::addMessage('Retention settings updated successfully.', 'success');
        } else {
            MessageHelper::addMessage('One or more retention settings could not be updated.', 'error');
        }

        $this->redirectBack();
    }

    private function redirectBack(): void
    {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            return;
        }

        header('Location: /retention-settings');
        exit();
    }
}
