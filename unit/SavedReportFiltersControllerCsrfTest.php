<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

// Suppress warnings about headers being already sent during testing
error_reporting(E_ALL & ~E_WARNING);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

use App\Controllers\SavedReportFiltersController;
use App\Core\RBACManager;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;

$failures = 0;

// Set up test session with appropriate privileges
$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'test_user';
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Test 1: store() with valid CSRF token should not trigger CSRF error
$validToken = $_SESSION['csrf_token'];
$_POST = [
    'csrf_token' => $validToken,
    'name' => 'Test Filter',
    'filters_json' => '[]',
];

unset($_SESSION['messages']);
$controller = new SavedReportFiltersController();

ob_start();
try {
    $controller->store();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

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
    'store() with valid CSRF token should not trigger error message',
    $failures
);

// Test 2: store() with invalid CSRF token should trigger error
$_POST = [
    'csrf_token' => 'invalid_token',
    'name' => 'Test Filter',
    'filters_json' => '[]',
];

unset($_SESSION['messages']);
$controller = new SavedReportFiltersController();

ob_start();
try {
    $controller->store();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

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
    'store() with invalid CSRF token should trigger error message',
    $failures
);

// Test 3: update() with invalid CSRF token should trigger error
$_POST = [
    'csrf_token' => 'invalid_token',
    'name' => 'Updated Filter',
];

unset($_SESSION['messages']);
$controller = new SavedReportFiltersController();

ob_start();
try {
    $controller->update(1);
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

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
    'update() with invalid CSRF token should trigger error message',
    $failures
);

// Test 4: delete() with invalid CSRF token should trigger error
$_POST = [
    'csrf_token' => 'invalid_token',
];

unset($_SESSION['messages']);
$controller = new SavedReportFiltersController();

ob_start();
try {
    $controller->delete(1);
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

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
    'delete() with invalid CSRF token should trigger error message',
    $failures
);

// Test 5: Missing CSRF token should trigger error
$_POST = [
    'name' => 'Test Filter',
    // No csrf_token field
];

unset($_SESSION['messages']);
$controller = new SavedReportFiltersController();

ob_start();
try {
    $controller->store();
} catch (Exception $e) {
    // Expected due to header redirect
}
ob_end_clean();

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

fwrite(STDOUT, "SavedReportFiltersController CSRF validation tests completed with $failures failures." . PHP_EOL);
exit($failures === 0 ? 0 : 1);
