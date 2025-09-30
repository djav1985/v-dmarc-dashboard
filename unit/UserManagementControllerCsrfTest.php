<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Controllers\UserManagementController;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;
use function TestHelpers\assertContains;

$failures = 0;

// Set up test session with admin privileges to bypass RBAC
$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'test_admin';
$_SESSION['user_role'] = 'app_admin'; // Use app_admin role which has manage_users permission
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Test 1: Valid CSRF token should not trigger error message
$validToken = $_SESSION['csrf_token'];
$_POST = [
    'action' => 'invalid_action', // Use invalid action to avoid actual processing
    'csrf_token' => $validToken,
];

unset($_SESSION['messages']); // Clear any previous messages
$controller = new UserManagementController();

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

// Test 2: Invalid CSRF token should trigger error message
$_POST = [
    'action' => 'create_user',
    'csrf_token' => 'invalid_token',
];

unset($_SESSION['messages']); // Clear messages
$controller = new UserManagementController();

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

// Test 3: Missing CSRF token should trigger error message
$_POST = [
    'action' => 'create_user',
    // No csrf_token field
];

unset($_SESSION['messages']); // Clear messages
$controller = new UserManagementController();

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

echo "CSRF validation tests completed with $failures failures." . PHP_EOL;
exit($failures === 0 ? 0 : 1);
