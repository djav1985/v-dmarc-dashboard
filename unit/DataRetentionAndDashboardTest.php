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
use App\Models\DmarcReport;
use App\Utilities\DataRetention;
use function TestHelpers\assertCountEquals;
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
$testSettingName = 'retention_test_' . $uniqueId;
$domainName = 'sqlite-filter-' . $uniqueId . '.example';
$domainId = null;
$reportIds = [];

try {
    // Verify retention setting upsert works on current driver (SQLite during tests).
    DataRetention::updateRetentionSetting($testSettingName, '10');
    $db->query('SELECT setting_value, updated_at FROM retention_settings WHERE setting_name = :name');
    $db->bind(':name', $testSettingName);
    $firstSetting = $db->single();

    assertTrue($firstSetting !== null, 'Retention setting should be inserted when missing.', $failures);
    assertEquals('10', $firstSetting['setting_value'] ?? null, 'Inserted setting value should match requested value.', $failures);

    sleep(1);

    DataRetention::updateRetentionSetting($testSettingName, '15');
    $db->query('SELECT setting_value, updated_at FROM retention_settings WHERE setting_name = :name');
    $db->bind(':name', $testSettingName);
    $secondSetting = $db->single();

    assertEquals('15', $secondSetting['setting_value'] ?? null, 'Upsert should update the stored retention value.', $failures);
    if ($firstSetting && $secondSetting) {
        assertTrue(
            ($secondSetting['updated_at'] ?? '') !== ($firstSetting['updated_at'] ?? ''),
            'Upsert should refresh the updated_at timestamp.',
            $failures
        );
    }

    // Insert domain for dashboard/filter verification.
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $domainResult = $db->single();
    $domainId = (int) ($domainResult['id'] ?? 0);

    assertTrue($domainId > 0, 'Domain should be created for reporting tests.', $failures);

    $now = time();
    $recentEnd = $now - (24 * 60 * 60); // 1 day ago
    $recentBegin = $recentEnd - (2 * 60 * 60);
    $oldEnd = $now - (12 * 24 * 60 * 60); // 12 days ago
    $oldBegin = $oldEnd - (2 * 60 * 60);

    $reportsToCreate = [
        [
            'label' => 'recent',
            'report_id' => 'recent-report-' . $uniqueId,
            'begin' => $recentBegin,
            'end' => $recentEnd,
        ],
        [
            'label' => 'old',
            'report_id' => 'old-report-' . $uniqueId,
            'begin' => $oldBegin,
            'end' => $oldEnd,
        ],
    ];

    foreach ($reportsToCreate as $reportData) {
        $db->query('
            INSERT INTO dmarc_aggregate_reports
                (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at)
            VALUES
                (:domain_id, :org_name, :email, :report_id, :date_range_begin, :date_range_end, :received_at)
        ');
        $db->bind(':domain_id', $domainId);
        $db->bind(':org_name', ucfirst($reportData['label']) . ' Org');
        $db->bind(':email', $reportData['label'] . '@example.com');
        $db->bind(':report_id', $reportData['report_id']);
        $db->bind(':date_range_begin', $reportData['begin']);
        $db->bind(':date_range_end', $reportData['end']);
        $db->bind(':received_at', date('Y-m-d H:i:s', $reportData['end']));
        $db->execute();

        $db->query('SELECT last_insert_rowid() as id');
        $row = $db->single();
        $reportId = (int) ($row['id'] ?? 0);
        $reportIds[$reportData['label']] = $reportId;

        assertTrue($reportId > 0, 'Aggregate report should insert successfully for ' . $reportData['label'] . ' data.', $failures);

        $db->query('
            INSERT INTO dmarc_aggregate_records
                (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to)
            VALUES
                (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)
        ');
        $db->bind(':report_id', $reportId);
        $db->bind(':source_ip', '192.0.2.' . ($reportData['label'] === 'recent' ? '1' : '2'));
        $db->bind(':count', 5);
        $db->bind(':disposition', $reportData['label'] === 'recent' ? 'none' : 'reject');
        $db->bind(':dkim_result', 'pass');
        $db->bind(':spf_result', 'pass');
        $db->bind(':header_from', $domainName);
        $db->bind(':envelope_from', $domainName);
        $db->bind(':envelope_to', 'user@' . $domainName);
        $db->execute();
    }

    // Dashboard summary should only include the recent report within the 7-day window.
    $summary = DmarcReport::getDashboardSummary(7);
    $matchingDomains = array_values(array_filter(
        $summary,
        static fn(array $row): bool => ($row['domain'] ?? '') === $domainName
    ));

    assertCountEquals(1, $matchingDomains, 'Dashboard summary should include the test domain once.', $failures);
    if (!empty($matchingDomains)) {
        $reportCount = (int) ($matchingDomains[0]['report_count'] ?? 0);
        $lastReportDate = (int) ($matchingDomains[0]['last_report_date'] ?? 0);
        assertEquals(1, $reportCount, 'Only the recent report should be counted within 7 days.', $failures);
        assertEquals($recentEnd, $lastReportDate, 'Last report date should reflect the most recent aggregate report.', $failures);
    }

    // Date range filters should operate on numeric timestamps for report listing.
    $recentFilter = [
        'domain' => $domainName,
        'date_from' => date('Y-m-d', strtotime('-5 days')),
        'limit' => 10,
        'offset' => 0,
    ];
    $recentReports = DmarcReport::getFilteredReports($recentFilter);
    assertCountEquals(1, $recentReports, 'Date-from filter should exclude older reports.', $failures);
    assertEquals(
        $reportsToCreate[0]['report_id'],
        $recentReports[0]['report_id'] ?? null,
        'Recent report should satisfy the lower bound filter.',
        $failures
    );

    $recentCount = DmarcReport::getFilteredReportsCount($recentFilter);
    assertEquals(1, $recentCount, 'Filtered report count should match filtered results.', $failures);

    $oldFilter = [
        'domain' => $domainName,
        'date_to' => date('Y-m-d', strtotime('-10 days')),
        'limit' => 10,
        'offset' => 0,
    ];
    $oldReports = DmarcReport::getFilteredReports($oldFilter);
    assertCountEquals(1, $oldReports, 'Date-to filter should exclude recent reports.', $failures);
    assertEquals(
        $reportsToCreate[1]['report_id'],
        $oldReports[0]['report_id'] ?? null,
        'Old report should satisfy the upper bound filter.',
        $failures
    );

    $oldCount = DmarcReport::getFilteredReportsCount($oldFilter);
    assertEquals(1, $oldCount, 'Filtered report count should respect the upper bound filter.', $failures);
} finally {
    // Clean up inserted data to avoid leaking state between tests.
    if (!empty($reportIds)) {
        foreach ($reportIds as $reportId) {
            if ($reportId > 0) {
                $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
                $db->bind(':report_id', $reportId);
                $db->execute();

                $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
                $db->bind(':report_id', $reportId);
                $db->execute();
            }
        }
    }

    if ($domainId) {
        $db->query('DELETE FROM domains WHERE id = :domain_id');
        $db->bind(':domain_id', $domainId);
        $db->execute();
    }

    $db->query('DELETE FROM retention_settings WHERE setting_name = :name');
    $db->bind(':name', $testSettingName);
    $db->execute();
}

echo 'Data retention and dashboard tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
