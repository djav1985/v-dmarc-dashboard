<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\SessionManager;
use App\Models\Alert;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$session = SessionManager::getInstance();
$session->start();
$session->set('logged_in', true);
$session->set('user_role', RBACManager::ROLE_APP_ADMIN);
$session->set('username', 'alert-spf-group-regression');

$db = DatabaseManager::getInstance();

function alertSpfRegressionInsertDomain(DatabaseManager $db, string $domain): int
{
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domain);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function alertSpfRegressionInsertGroup(DatabaseManager $db, string $name): int
{
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'Automated alert SPF regression test group');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function alertSpfRegressionAssignDomainToGroup(DatabaseManager $db, int $domainId, int $groupId): void
{
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

function alertSpfRegressionInsertReport(DatabaseManager $db, int $domainId, string $reportId, string $receivedAt): int
{
    $receivedTimestamp = strtotime($receivedAt);

    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Alert Metric Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $reportId);
    $db->bind(':start', $receivedTimestamp - 600);
    $db->bind(':end', $receivedTimestamp);
    $db->bind(':received', $receivedAt);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function alertSpfRegressionInsertRecord(DatabaseManager $db, int $reportId, int $count): void
{
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', '198.51.100.45');
    $db->bind(':count', $count);
    $db->bind(':disposition', 'reject');
    $db->bind(':dkim_result', 'fail');
    $db->bind(':spf_result', 'fail');
    $db->bind(':header_from', 'example.com');
    $db->bind(':envelope_from', 'alerts@example.com');
    $db->bind(':envelope_to', 'postmaster@example.com');
    $db->execute();
}

function alertSpfRegressionCalculateMetric(array $rule): float
{
    $reflector = new ReflectionClass(Alert::class);
    $method = $reflector->getMethod('calculateMetric');
    $method->setAccessible(true);

    return (float) $method->invoke(null, $rule);
}

$windowMinutes = 180;
$receivedTime = date('Y-m-d H:i:s', time() - 60);

$domainName = 'alert-spf-regression-' . uniqid() . '.example';
$groupPrimary = 'Alert SPF Regression Group ' . uniqid();
$groupSecondary = 'Alert SPF Regression Secondary ' . uniqid();

$domainId = alertSpfRegressionInsertDomain($db, $domainName);
assertTrue($domainId > 0, 'Domain should be inserted for SPF regression metric test.', $failures);

$groupPrimaryId = alertSpfRegressionInsertGroup($db, $groupPrimary);
$groupSecondaryId = alertSpfRegressionInsertGroup($db, $groupSecondary);

assertTrue($groupPrimaryId > 0 && $groupSecondaryId > 0, 'Test groups should be inserted successfully.', $failures);

if ($domainId > 0 && $groupPrimaryId > 0 && $groupSecondaryId > 0) {
    alertSpfRegressionAssignDomainToGroup($db, $domainId, $groupPrimaryId);
    alertSpfRegressionAssignDomainToGroup($db, $domainId, $groupSecondaryId);

    $reportId = alertSpfRegressionInsertReport($db, $domainId, 'alert-spf-regression-' . uniqid(), $receivedTime);
    alertSpfRegressionInsertRecord($db, $reportId, 7);

    $baseRule = [
        'metric' => 'spf_failures',
        'time_window' => $windowMinutes,
        'domain_filter' => $domainName,
    ];

    $unfilteredRule = array_merge($baseRule, ['group_filter' => null]);
    $groupFilteredRule = array_merge($baseRule, ['group_filter' => $groupPrimaryId]);

    $unfilteredValue = alertSpfRegressionCalculateMetric($unfilteredRule);
    $filteredValue = alertSpfRegressionCalculateMetric($groupFilteredRule);

    assertEquals(7.0, $unfilteredValue, 'Unfiltered SPF failure metric should equal the actual report count without duplication.', $failures);
    assertEquals(7.0, $filteredValue, 'Group-filtered SPF failure metric should still report the actual count.', $failures);

    // Clean up inserted data.
    $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
    $db->bind(':report_id', $reportId);
    $db->execute();

    $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
    $db->bind(':report_id', $reportId);
    $db->execute();

    $db->query('DELETE FROM domain_group_assignments WHERE domain_id = :domain_id');
    $db->bind(':domain_id', $domainId);
    $db->execute();
}

$db->query('DELETE FROM domain_groups WHERE id IN (:group_primary, :group_secondary)');
$db->bind(':group_primary', $groupPrimaryId ?? 0);
$db->bind(':group_secondary', $groupSecondaryId ?? 0);
$db->execute();

$db->query('DELETE FROM domains WHERE id = :domain_id');
$db->bind(':domain_id', $domainId ?? 0);
$db->execute();

echo 'Alert SPF failure group regression test completed with ' . ($failures === 0 ? 'no' : $failures) . " failure(s)\n";

exit($failures > 0 ? 1 : 0);

