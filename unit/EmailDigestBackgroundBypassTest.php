<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Models\EmailDigest;
use App\Services\EmailDigestService;

// Test: Verify generateDigestData works with bypassRbac flag when no session
echo "Testing EmailDigest background job bypass...\n";

// Set admin session temporarily to create test data
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$_SESSION['username'] = 'test-admin';

$db = DatabaseManager::getInstance();

// Insert a test domain
$testDomain = 'test-bg-' . time() . '.example.com';
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $testDomain);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$domainResult = $db->single();
$domainId = (int) ($domainResult['id'] ?? 0);

// Insert a DMARC report
$now = time();
$db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
$db->bind(':domain_id', $domainId);
$db->bind(':org_name', 'Test Org');
$db->bind(':email', 'reports@example.com');
$db->bind(':report_id', 'bg-test-' . time());
$db->bind(':start', $now - 86400);
$db->bind(':end', $now);
$db->bind(':received', date('Y-m-d H:i:s', $now));
$db->execute();

// Create a digest schedule
$scheduleId = EmailDigest::createSchedule([
    'name' => 'Background Test Digest',
    'frequency' => 'daily',
    'recipients' => ['test@example.com'],
    'domain_filter' => $testDomain,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', time() - 60),
]);

echo "Created schedule ID: $scheduleId for domain: $testDomain\n";

// Clear session to simulate background job
$_SESSION = [];

// Test without bypass flag - should return empty
$startDate = date('Y-m-d', $now - 86400);
$endDate = date('Y-m-d', $now);

$digestDataWithoutBypass = EmailDigest::generateDigestData($scheduleId, $startDate, $endDate, false);
if (empty($digestDataWithoutBypass)) {
    echo "✓ Without bypass flag: Returns empty (expected when no session)\n";
} else {
    echo "✗ Without bypass flag: Should return empty but got data\n";
    exit(1);
}

// Test with bypass flag - should return data
$digestDataWithBypass = EmailDigest::generateDigestData($scheduleId, $startDate, $endDate, true);
if (!empty($digestDataWithBypass)) {
    echo "✓ With bypass flag: Returns data (expected for background jobs)\n";
    echo "  - Found " . count($digestDataWithBypass['domains'] ?? []) . " domain(s) in digest\n";
} else {
    echo "✗ With bypass flag: Should return data but got empty\n";
    exit(1);
}

echo "\nBackground job bypass test passed!\n";
exit(0);
