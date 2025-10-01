<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\SessionManager;
use App\Utilities\DataRetention;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$sessionManager = SessionManager::getInstance();
$sessionManager->start();
$sessionManager->set('logged_in', true);
$sessionManager->set('user_role', RBACManager::ROLE_APP_ADMIN);
$sessionManager->set('username', 'test-admin');

$db = DatabaseManager::getInstance();

$uniqueId = bin2hex(random_bytes(6));
$domainName = 'batch-test-' . $uniqueId . '.example';
$domainId = null;
$reportIds = [];

try {
    // Insert test domain
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $domainResult = $db->single();
    $domainId = (int) ($domainResult['id'] ?? 0);

    assertTrue($domainId > 0, 'Domain should be created for batching tests.', $failures);

    // Set retention to 5 days
    DataRetention::updateRetentionSetting('aggregate_reports_retention_days', '5');

    // Create reports that are older than 5 days (should be deleted)
    $now = time();
    $oldEnd = $now - (10 * 24 * 60 * 60); // 10 days ago
    $oldBegin = $oldEnd - (2 * 60 * 60);

    // Create 1200 old reports to test batching (3 batches of 500 each with batch size 500)
    $reportCount = 1200;
    echo "Creating $reportCount old reports to test batching...\n";

    for ($i = 0; $i < $reportCount; $i++) {
        $db->query('
            INSERT INTO dmarc_aggregate_reports
                (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at)
            VALUES
                (:domain_id, :org_name, :email, :report_id, :date_range_begin, :date_range_end, :received_at)
        ');
        $db->bind(':domain_id', $domainId);
        $db->bind(':org_name', 'Test Org ' . $i);
        $db->bind(':email', 'test' . $i . '@example.com');
        $db->bind(':report_id', 'old-report-' . $uniqueId . '-' . $i);
        $db->bind(':date_range_begin', $oldBegin);
        $db->bind(':date_range_end', $oldEnd);
        $db->bind(':received_at', date('Y-m-d H:i:s', $oldEnd));
        $db->execute();

        $db->query('SELECT last_insert_rowid() as id');
        $row = $db->single();
        $reportId = (int) ($row['id'] ?? 0);
        $reportIds[] = $reportId;

        // Add 2 records per report
        for ($j = 0; $j < 2; $j++) {
            $db->query('
                INSERT INTO dmarc_aggregate_records
                    (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to)
                VALUES
                    (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)
            ');
            $db->bind(':report_id', $reportId);
            $db->bind(':source_ip', '192.0.2.' . (($i + $j) % 255));
            $db->bind(':count', 5);
            $db->bind(':disposition', 'reject');
            $db->bind(':dkim_result', 'pass');
            $db->bind(':spf_result', 'pass');
            $db->bind(':header_from', $domainName);
            $db->bind(':envelope_from', $domainName);
            $db->bind(':envelope_to', 'user@' . $domainName);
            $db->execute();
        }
    }

    echo "Created $reportCount reports with " . ($reportCount * 2) . " records.\n";

    // Verify reports exist before cleanup
    $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_reports WHERE domain_id = :domain_id');
    $db->bind(':domain_id', $domainId);
    $beforeReports = $db->single();
    $beforeReportCount = (int) ($beforeReports['count'] ?? 0);
    assertEquals($reportCount, $beforeReportCount, 'All test reports should exist before cleanup.', $failures);

    $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_records WHERE report_id IN (SELECT id FROM dmarc_aggregate_reports WHERE domain_id = :domain_id)');
    $db->bind(':domain_id', $domainId);
    $beforeRecords = $db->single();
    $beforeRecordCount = (int) ($beforeRecords['count'] ?? 0);
    assertEquals($reportCount * 2, $beforeRecordCount, 'All test records should exist before cleanup.', $failures);

    // Run cleanup - this should handle the 1200 reports in batches
    echo "Running cleanup with batching...\n";
    $results = DataRetention::cleanupOldReports();

    // Verify cleanup results - note: there might be other old reports in the database
    // so we just verify that at least our reports were deleted
    assertTrue(
        !isset($results['errors']) || empty($results['errors']),
        'Cleanup should complete without errors. Errors: ' . json_encode($results['errors'] ?? []),
        $failures
    );
    assertTrue(
        $results['aggregate_reports_deleted'] >= $reportCount,
        'At least ' . $reportCount . ' reports should be deleted (may include other old reports). Got: ' . 
        ($results['aggregate_reports_deleted'] ?? 0),
        $failures
    );
    assertTrue(
        $results['aggregate_records_deleted'] >= $reportCount * 2,
        'At least ' . ($reportCount * 2) . ' records should be deleted (may include other old records). Got: ' . 
        ($results['aggregate_records_deleted'] ?? 0),
        $failures
    );

    // Verify reports are deleted
    $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_reports WHERE domain_id = :domain_id');
    $db->bind(':domain_id', $domainId);
    $afterReports = $db->single();
    $afterReportCount = (int) ($afterReports['count'] ?? 0);
    assertEquals(0, $afterReportCount, 'All old reports should be deleted after cleanup.', $failures);

    $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_records WHERE report_id IN (SELECT id FROM dmarc_aggregate_reports WHERE domain_id = :domain_id)');
    $db->bind(':domain_id', $domainId);
    $afterRecords = $db->single();
    $afterRecordCount = (int) ($afterRecords['count'] ?? 0);
    assertEquals(0, $afterRecordCount, 'All old records should be deleted after cleanup.', $failures);

    echo "Successfully cleaned up $reportCount reports across multiple batches.\n";
} finally {
    // Clean up test data
    if (!empty($reportIds)) {
        echo "Cleaning up test data...\n";
        // Delete in batches to avoid parameter limits during cleanup
        $batches = array_chunk($reportIds, 500);
        foreach ($batches as $batch) {
            if (!empty($batch)) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                try {
                    $db->query("DELETE FROM dmarc_aggregate_records WHERE report_id IN ($placeholders)");
                    foreach ($batch as $idx => $id) {
                        $db->bind(':param' . $idx, $id);
                    }
                    $db->execute();
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }

                try {
                    $db->query("DELETE FROM dmarc_aggregate_reports WHERE id IN ($placeholders)");
                    foreach ($batch as $idx => $id) {
                        $db->bind(':param' . $idx, $id);
                    }
                    $db->execute();
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }
    }

    if ($domainId) {
        $db->query('DELETE FROM domains WHERE id = :domain_id');
        $db->bind(':domain_id', $domainId);
        $db->execute();
    }

    $db->query('DELETE FROM retention_settings WHERE setting_name = :name');
    $db->bind(':name', 'aggregate_reports_retention_days');
    $db->execute();
}

echo 'Data retention batching tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
