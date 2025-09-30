<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Controllers\ReportsManagementController;
use App\Helpers\MessageHelper;
use function TestHelpers\assertFalse;
use function TestHelpers\assertTrue;

$failures = 0;

$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'reports-admin';
$_SESSION['user_role'] = 'app_admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));

MessageHelper::clearMessages();
$_POST = [
    'action' => 'noop',
    'csrf_token' => $_SESSION['csrf_token'],
];

$controller = new ReportsManagementController();
ob_start();
try {
    $controller->handleSubmission();
} catch (\Exception $exception) {
    // Expected during redirect
}
ob_end_clean();

$messages = MessageHelper::getMessages();
$foundError = false;
foreach ($messages as $message) {
    $text = is_array($message) ? ($message['text'] ?? '') : $message;
    if (strpos($text, 'Invalid CSRF token') !== false) {
        $foundError = true;
        break;
    }
}

assertFalse($foundError, 'Valid CSRF submissions should not generate an error message.', $failures);

MessageHelper::clearMessages();
$_POST = [
    'action' => 'noop',
    'csrf_token' => 'incorrect',
];

ob_start();
try {
    $controller->handleSubmission();
} catch (\Exception $exception) {
    // Expected redirect
}
ob_end_clean();

$messages = MessageHelper::getMessages();
$foundError = false;
foreach ($messages as $message) {
    $text = is_array($message) ? ($message['text'] ?? '') : $message;
    if (strpos($text, 'Invalid CSRF token') !== false) {
        $foundError = true;
        break;
    }
}

assertTrue($foundError, 'Invalid CSRF submissions should be rejected.', $failures);

echo 'Reports management CSRF tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
