<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Test to simulate cron.php digest processing
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../root/config.php';
require_once __DIR__ . '/../root/vendor/autoload.php';

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\Mailer;
use App\Models\EmailDigest;
use App\Services\EmailDigestService;

echo "Simulating cron.php hourly digest processing...\n";
echo "======================================\n\n";

// Important: No session is started (simulates background job)
// No $_SESSION variables are set (no user context)

// Setup test data (normally this would be done by users via web interface)
// For testing, we temporarily start a session to create schedule
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$_SESSION['username'] = 'admin';

$db = DatabaseManager::getInstance();

// Create test domain
$testDomain = 'cron-test-' . time() . '.example.com';
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $testDomain);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$domainResult = $db->single();
$domainId = (int) ($domainResult['id'] ?? 0);

// Create test report
$now = time();
$db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
$db->bind(':domain_id', $domainId);
$db->bind(':org_name', 'Test Org');
$db->bind(':email', 'reports@example.com');
$db->bind(':report_id', 'cron-test-' . time());
$db->bind(':start', $now - 86400);
$db->bind(':end', $now);
$db->bind(':received', date('Y-m-d H:i:s', $now));
$db->execute();

// Create test records
$reportId = (int) $db->getLastInsertId();
$db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to)');
$db->bind(':report_id', $reportId);
$db->bind(':source_ip', '192.0.2.1');
$db->bind(':count', 10);
$db->bind(':disposition', 'none');
$db->bind(':dkim', 'pass');
$db->bind(':spf', 'pass');
$db->bind(':header_from', $testDomain);
$db->bind(':envelope_from', 'noreply@' . $testDomain);
$db->bind(':envelope_to', 'postmaster@' . $testDomain);
$db->execute();

// Create digest schedule (due now)
$scheduleId = EmailDigest::createSchedule([
    'name' => 'Cron Test Digest',
    'frequency' => 'daily',
    'recipients' => ['test@example.com'],
    'domain_filter' => $testDomain,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', time() - 60), // Due 1 minute ago
]);

echo "Created test schedule #$scheduleId for domain $testDomain\n\n";

// Now simulate cron.php behavior: destroy session
session_destroy();
$_SESSION = [];

echo "Session cleared (simulating background job context)\n";
echo "User context: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NONE') . "\n\n";

// Mock email sending to capture attempts
$sentEmails = [];
Mailer::setTransportOverride(static function (string $to, string $subject) use (&$sentEmails): bool {
    $sentEmails[] = ['to' => $to, 'subject' => $subject];
    return true;
});

// This is what cron.php does
echo "Processing due digests...\n";
$digestResults = EmailDigestService::processDueDigests();

echo "\nResults:\n";
echo "- Processed " . count($digestResults) . " digest schedule(s)\n";

foreach ($digestResults as $result) {
    $status = $result['success'] ? '✓ SUCCESS' : '✗ FAILED';
    echo "\nSchedule #{$result['schedule_id']}: $status\n";
    echo "  Recipients: " . implode(', ', $result['recipients'] ?? []) . "\n";
    echo "  Period: {$result['start_date']} to {$result['end_date']}\n";
    echo "  Next run: {$result['next_run']}\n";
    if (!$result['success']) {
        echo "  Error: {$result['message']}\n";
    }
}

echo "\nEmails sent: " . count($sentEmails) . "\n";
foreach ($sentEmails as $email) {
    echo "  - To: {$email['to']}, Subject: {$email['subject']}\n";
}

// Verify the fix worked
if (empty($digestResults)) {
    echo "\n✗ FAIL: No digests were processed (RBAC blocking issue)\n";
    exit(1);
}

$successCount = 0;
foreach ($digestResults as $result) {
    if ($result['success']) {
        $successCount++;
    }
}

if ($successCount === 0) {
    echo "\n✗ FAIL: All digests failed\n";
    exit(1);
}

echo "\n✓ SUCCESS: Background job processed digest(s) without user session\n";
exit(0);
