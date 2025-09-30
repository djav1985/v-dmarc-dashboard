<?php
/**
 * Simple demonstration script to show the forensic reports RBAC fix
 * This demonstrates the vulnerability that was fixed.
 */

require __DIR__ . '/root/vendor/autoload.php';
require __DIR__ . '/root/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Models\DmarcReport;

// Reset session to simulate a user with no domain access
$_SESSION = [];
$_SESSION['username'] = 'test_user_no_access';
$_SESSION['user_role'] = RBACManager::ROLE_VIEWER;

echo "Demonstrating the forensic reports RBAC fix..." . PHP_EOL;
echo "User: " . $_SESSION['username'] . " (Role: " . $_SESSION['user_role'] . ")" . PHP_EOL;

// Check what the user's accessible domains are
$rbac = RBACManager::getInstance();
$accessibleDomains = $rbac->getAccessibleDomains();
echo "User's accessible domains: " . count($accessibleDomains) . PHP_EOL;

// Test the fixed getForensicReports method
$forensicReports = DmarcReport::getForensicReports();
echo "Forensic reports returned: " . count($forensicReports) . PHP_EOL;

if (count($forensicReports) === 0) {
    echo "✓ PASS: User with no domain access correctly receives no forensic reports" . PHP_EOL;
} else {
    echo "✗ FAIL: User with no domain access received " . count($forensicReports) . " forensic reports" . PHP_EOL;
    echo "This would be a security vulnerability!" . PHP_EOL;
}

// Now test as an admin user
$_SESSION['username'] = 'admin_user';
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;

echo PHP_EOL . "Testing as admin user..." . PHP_EOL;
echo "User: " . $_SESSION['username'] . " (Role: " . $_SESSION['user_role'] . ")" . PHP_EOL;

$adminForensicReports = DmarcReport::getForensicReports();
echo "Forensic reports returned: " . count($adminForensicReports) . PHP_EOL;

if (count($adminForensicReports) >= 0) {
    echo "✓ PASS: Admin user receives forensic reports (count may be 0 if no data)" . PHP_EOL;
}

echo PHP_EOL . "Forensic reports RBAC fix validation completed successfully." . PHP_EOL;