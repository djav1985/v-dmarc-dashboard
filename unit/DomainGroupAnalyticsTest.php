<?php

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Models\DomainGroup;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

/**
 * Insert a test domain and return its ID.
 */
function analyticsInsertDomain(DatabaseManager $db, string $domain): int
{
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domain);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

/**
 * Insert a test domain group and return its ID.
 */
function analyticsInsertGroup(DatabaseManager $db, string $name): int
{
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'Analytics test group');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

/**
 * Link a domain to a group.
 */
function analyticsAssignDomainToGroup(DatabaseManager $db, int $domainId, int $groupId): void
{
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

/**
 * Insert a DMARC aggregate report for analytics testing.
 */
function analyticsInsertAggregateReport(
    DatabaseManager $db,
    int $domainId,
    int $rangeStart,
    int $rangeEnd,
    string $reportId
): int {
    $db->query('
        INSERT INTO dmarc_aggregate_reports (
            domain_id,
            org_name,
            email,
            extra_contact_info,
            report_id,
            date_range_begin,
            date_range_end,
            raw_xml
        ) VALUES (
            :domain_id,
            :org_name,
            :email,
            NULL,
            :report_id,
            :date_range_begin,
            :date_range_end,
            NULL
        )
    ');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Analytics Org');
    $db->bind(':email', 'analytics@example.com');
    $db->bind(':report_id', $reportId);
    $db->bind(':date_range_begin', $rangeStart);
    $db->bind(':date_range_end', $rangeEnd);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

/**
 * Insert an aggregate record row for a report.
 */
function analyticsInsertAggregateRecord(
    DatabaseManager $db,
    int $reportId,
    string $disposition,
    int $count
): void {
    $db->query('
        INSERT INTO dmarc_aggregate_records (
            report_id,
            source_ip,
            count,
            disposition,
            dkim_result,
            spf_result,
            header_from,
            envelope_from,
            envelope_to
        ) VALUES (
            :report_id,
            :source_ip,
            :count,
            :disposition,
            :dkim_result,
            :spf_result,
            :header_from,
            :envelope_from,
            :envelope_to
        )
    ');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', '198.51.100.42');
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim_result', 'pass');
    $db->bind(':spf_result', 'pass');
    $db->bind(':header_from', 'example.test');
    $db->bind(':envelope_from', 'mailer@example.test');
    $db->bind(':envelope_to', 'recipient@example.test');
    $db->execute();
}

$failures = 0;

$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$_SESSION['username'] = 'analytics_test_user';

$db = DatabaseManager::getInstance();
$timestamp = time();

$startDate = date('Y-m-d', strtotime('-1 day'));
$endDate = date('Y-m-d');

$groupRecords = [
    'groups' => [],
    'domains' => [],
    'reports' => [],
];

try {
    $groupWithoutTrafficId = analyticsInsertGroup($db, 'Analytics No Traffic ' . $timestamp);
    $groupRecords['groups'][] = $groupWithoutTrafficId;

    $noTrafficDomainId = analyticsInsertDomain($db, 'no-traffic-' . $timestamp . '.example');
    $groupRecords['domains'][] = $noTrafficDomainId;
    analyticsAssignDomainToGroup($db, $noTrafficDomainId, $groupWithoutTrafficId);

    $pastRangeEnd = strtotime($startDate) - 3600;
    $pastRangeStart = $pastRangeEnd - 3600;
    $outOfRangeReportId = analyticsInsertAggregateReport(
        $db,
        $noTrafficDomainId,
        $pastRangeStart,
        $pastRangeEnd,
        'out-of-range-' . $timestamp
    );
    $groupRecords['reports'][] = $outOfRangeReportId;

    $groupWithTrafficId = analyticsInsertGroup($db, 'Analytics With Traffic ' . $timestamp);
    $groupRecords['groups'][] = $groupWithTrafficId;

    $activeDomainId = analyticsInsertDomain($db, 'active-' . $timestamp . '.example');
    $groupRecords['domains'][] = $activeDomainId;
    analyticsAssignDomainToGroup($db, $activeDomainId, $groupWithTrafficId);

    $rangeStart = strtotime($startDate);
    $rangeEnd = strtotime($endDate . ' 12:00:00');
    $inRangeReportId = analyticsInsertAggregateReport(
        $db,
        $activeDomainId,
        $rangeStart,
        $rangeEnd,
        'in-range-' . $timestamp
    );
    $groupRecords['reports'][] = $inRangeReportId;

    analyticsInsertAggregateRecord($db, $inRangeReportId, 'none', 8);
    analyticsInsertAggregateRecord($db, $inRangeReportId, 'reject', 2);

    $analytics = DomainGroup::getGroupAnalytics($startDate, $endDate);

    assertTrue(is_array($analytics), 'Analytics result should be an array', $failures);
    assertTrue(
        count($analytics) >= 2,
        'Analytics should include inserted groups',
        $failures
    );

    $analyticsByGroup = [];
    foreach ($analytics as $row) {
        $analyticsByGroup[(int) ($row['id'] ?? 0)] = $row;
    }

    assertTrue(
        isset($analyticsByGroup[$groupWithoutTrafficId]),
        'Group without traffic should be returned',
        $failures
    );
    $noTrafficSummary = $analyticsByGroup[$groupWithoutTrafficId];

    assertEquals(
        0,
        (int) ($noTrafficSummary['report_count'] ?? -1),
        'Report count should be zero without in-range traffic',
        $failures
    );
    assertEquals(
        0,
        (int) ($noTrafficSummary['total_volume'] ?? -1),
        'Total volume should be zero without traffic',
        $failures
    );
    assertEquals(
        0,
        (int) ($noTrafficSummary['passed_count'] ?? -1),
        'Passed count should be zero without traffic',
        $failures
    );
    assertEquals(
        0,
        (int) ($noTrafficSummary['quarantined_count'] ?? -1),
        'Quarantined count should be zero without traffic',
        $failures
    );
    assertEquals(
        0,
        (int) ($noTrafficSummary['rejected_count'] ?? -1),
        'Rejected count should be zero without traffic',
        $failures
    );
    assertEquals(
        0.0,
        (float) ($noTrafficSummary['pass_rate'] ?? -1),
        'Pass rate should be zero without traffic',
        $failures
    );

    assertTrue(
        isset($analyticsByGroup[$groupWithTrafficId]),
        'Group with traffic should be returned',
        $failures
    );
    $trafficSummary = $analyticsByGroup[$groupWithTrafficId];

    assertEquals(
        1,
        (int) ($trafficSummary['report_count'] ?? -1),
        'Report count should reflect in-range reports',
        $failures
    );
    assertEquals(
        10,
        (int) ($trafficSummary['total_volume'] ?? -1),
        'Total volume should sum record counts',
        $failures
    );
    assertEquals(
        8,
        (int) ($trafficSummary['passed_count'] ?? -1),
        'Passed count should sum none dispositions',
        $failures
    );
    assertEquals(
        0,
        (int) ($trafficSummary['quarantined_count'] ?? -1),
        'Quarantined count should default to zero',
        $failures
    );
    assertEquals(
        2,
        (int) ($trafficSummary['rejected_count'] ?? -1),
        'Rejected count should sum reject dispositions',
        $failures
    );
    assertEquals(
        80.0,
        (float) ($trafficSummary['pass_rate'] ?? -1),
        'Pass rate should guard against zero division and compute correctly',
        $failures
    );
} finally {
    foreach (array_reverse($groupRecords['reports']) as $reportId) {
        if ($reportId > 0) {
            $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();

            $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();
        }
    }

    foreach ($groupRecords['domains'] as $domainId) {
        if ($domainId > 0) {
            $db->query('DELETE FROM domain_group_assignments WHERE domain_id = :domain_id');
            $db->bind(':domain_id', $domainId);
            $db->execute();

            $db->query('DELETE FROM domains WHERE id = :domain_id');
            $db->bind(':domain_id', $domainId);
            $db->execute();
        }
    }

    foreach ($groupRecords['groups'] as $groupId) {
        if ($groupId > 0) {
            $db->query('DELETE FROM domain_groups WHERE id = :group_id');
            $db->bind(':group_id', $groupId);
            $db->execute();
        }
    }
}

$summary = 'Domain group analytics tests completed with ' . $failures . ' failures.';

if ($failures > 0) {
    fwrite(STDERR, $summary . PHP_EOL);
    exit(1);
}

echo $summary . PHP_EOL;
