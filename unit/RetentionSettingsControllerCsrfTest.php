<?php

declare(strict_types=1);

// Define PHPUNIT_RUNNING to prevent header redirects
define('PHPUNIT_RUNNING', true);

// Start session first before any output
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Controllers\RetentionSettingsController;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;

$failures = 0;

echo "Starting RetentionSettingsController CSRF test..." . PHP_EOL;

// Set up test session with admin privileges to bypass RBAC
$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'test_admin';
$_SESSION['user_role'] = 'app_admin'; // Use app_admin role which has manage_retention permission
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Test 1: Valid CSRF token should not trigger error message
echo "Test 1: Valid CSRF token should not trigger error message..." . PHP_EOL;
$validToken = $_SESSION['csrf_token'];
$_POST = [
    'aggregate_reports_retention_days' => '30',
    'csrf_token' => $validToken,
];

unset($_SESSION['messages']); // Clear any previous messages
$controller = new RetentionSettingsController();

ob_start();
try {
    $controller->handleSubmission();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

// Check that no CSRF error message was added
$messages = $_SESSION['messages'] ?? [];
$csrfErrorFound = false;
foreach ($messages as $message) {
    $messageText = is_array($message) ? ($message['text'] ?? '') : $message;
    if (strpos($messageText, 'Invalid CSRF token') !== false) {
        $csrfErrorFound = true;
        break;
    }
}

assertFalse(
    $csrfErrorFound,
    'Valid CSRF token should not trigger error message',
    $failures
);

echo "Test 1 PASSED: Valid CSRF token was accepted." . PHP_EOL;

// Test 2: Invalid CSRF token should trigger error message
echo "Test 2: Invalid CSRF token should trigger error message..." . PHP_EOL;
$_POST = [
    'aggregate_reports_retention_days' => '30',
    'csrf_token' => 'invalid_token',
];

unset($_SESSION['messages']); // Clear messages
$controller = new RetentionSettingsController();

ob_start();
try {
    $controller->handleSubmission();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

// Check that CSRF error message was added
$messages = $_SESSION['messages'] ?? [];
$csrfErrorFound = false;
foreach ($messages as $message) {
    $messageText = is_array($message) ? ($message['text'] ?? '') : $message;
    if (strpos($messageText, 'Invalid CSRF token') !== false) {
        $csrfErrorFound = true;
        break;
    }
}

assertTrue(
    $csrfErrorFound,
    'Invalid CSRF token should trigger error message',
    $failures
);

echo "Test 2 PASSED: Invalid CSRF token was rejected." . PHP_EOL;

// Test 3: Missing CSRF token should also trigger error message
echo "Test 3: Missing CSRF token should trigger error message..." . PHP_EOL;
$_POST = [
    'aggregate_reports_retention_days' => '30',
    // No csrf_token in POST
];

unset($_SESSION['messages']); // Clear messages
$controller = new RetentionSettingsController();

ob_start();
try {
    $controller->handleSubmission();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

// Check that CSRF error message was added
$messages = $_SESSION['messages'] ?? [];
$csrfErrorFound = false;
foreach ($messages as $message) {
    $messageText = is_array($message) ? ($message['text'] ?? '') : $message;
    if (strpos($messageText, 'Invalid CSRF token') !== false) {
        $csrfErrorFound = true;
        break;
    }
}

assertTrue(
    $csrfErrorFound,
    'Missing CSRF token should trigger error message',
    $failures
);

echo "Test 3 PASSED: Missing CSRF token was rejected." . PHP_EOL;

echo "RetentionSettingsController CSRF validation tests completed with $failures failures." . PHP_EOL;
exit($failures === 0 ? 0 : 1);
