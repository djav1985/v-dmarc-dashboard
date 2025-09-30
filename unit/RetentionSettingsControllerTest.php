<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Controllers\RetentionSettingsController;
use App\Core\RBACManager;
use App\Core\SessionManager;
use App\Utilities\DataRetention;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$session = SessionManager::getInstance();
$session->start();
$session->set('logged_in', true);
$session->set('user_role', RBACManager::ROLE_APP_ADMIN);
$session->set('username', 'retention-admin');

$fields = [
    'aggregate_reports_retention_days' => '45',
    'forensic_reports_retention_days' => '30',
    'tls_reports_retention_days' => '15',
];

$failures = 0;
$originalSettings = DataRetention::getRetentionSettings();

try {
    $_POST = array_merge($fields, ['csrf_token' => 'test']);

    $controller = new RetentionSettingsController();
    $controller->handleSubmission();

    $updated = DataRetention::getRetentionSettings();
    foreach ($fields as $key => $value) {
        assertEquals($value, $updated[$key] ?? null, 'Retention controller should update ' . $key, $failures);
    }

    assertTrue(isset($updated['aggregate_reports_retention_days']), 'Updated settings should be persisted.', $failures);
} finally {
    foreach ($fields as $key => $value) {
        $restore = $originalSettings[$key] ?? null;
        if ($restore !== null) {
            DataRetention::updateRetentionSetting($key, (string) $restore);
        }
    }
    $_POST = [];
}

echo "Retention settings controller tests completed.\n";
exit($failures === 0 ? 0 : 1);
