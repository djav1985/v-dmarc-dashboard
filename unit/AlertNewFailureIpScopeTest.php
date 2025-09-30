<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\SessionManager;
use App\Core\RBACManager;
use App\Models\Alert;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$session = SessionManager::getInstance();
$session->start();
$session->set('logged_in', true);
$session->set('user_role', RBACManager::ROLE_APP_ADMIN);
$session->set('username', 'alert-new-ip-scope');

$db = DatabaseManager::getInstance();

function alertMetricInsertDomain(DatabaseManager $db, string $domain): int
{
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domain);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function alertMetricInsertGroup(DatabaseManager $db, string $name): int
{
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'Automated alert metric test group');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function alertMetricAssignDomainToGroup(DatabaseManager $db, int $domainId, int $groupId): void
{
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

function alertMetricInsertReport(DatabaseManager $db, int $domainId, string $reportId, string $receivedAt): int
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

function alertMetricInsertRecord(DatabaseManager $db, int $reportId, string $ip, int $count): void
{
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', $ip);
    $db->bind(':count', $count);
    $db->bind(':disposition', 'reject');
    $db->bind(':dkim_result', 'fail');
    $db->bind(':spf_result', 'fail');
    $db->bind(':header_from', 'example.com');
    $db->bind(':envelope_from', 'noreply@example.com');
    $db->bind(':envelope_to', 'postmaster@example.com');
    $db->execute();
}

function calculateNewFailureIpsMetric(array $rule): float
{
    $reflector = new ReflectionClass(Alert::class);
    $method = $reflector->getMethod('calculateMetric');
    $method->setAccessible(true);

    return (float) $method->invoke(null, $rule);
}

$windowMinutes = 120;
$now = time();
$historicalCutoff = $now - (($windowMinutes * 60) + 3600);
$historicalReceived = date('Y-m-d H:i:s', $historicalCutoff);
$currentReceived = date('Y-m-d H:i:s', $now);
$sharedIp = '198.51.100.25';

$domainA = 'alert-metric-a-' . uniqid() . '.example';
$domainB = 'alert-metric-b-' . uniqid() . '.example';
$groupAName = 'Alert Metric Group ' . uniqid();
$groupBName = 'Alert Metric Other Group ' . uniqid();

$domainAId = alertMetricInsertDomain($db, $domainA);
$domainBId = alertMetricInsertDomain($db, $domainB);
$groupAId = alertMetricInsertGroup($db, $groupAName);
$groupBId = alertMetricInsertGroup($db, $groupBName);

alertMetricAssignDomainToGroup($db, $domainAId, $groupAId);
alertMetricAssignDomainToGroup($db, $domainBId, $groupBId);

$historicalReportId = alertMetricInsertReport($db, $domainBId, 'historical-' . uniqid(), $historicalReceived);
alertMetricInsertRecord($db, $historicalReportId, $sharedIp, 5);

$currentReportId = alertMetricInsertReport($db, $domainAId, 'current-' . uniqid(), $currentReceived);
alertMetricInsertRecord($db, $currentReportId, $sharedIp, 3);

$ruleId = Alert::createRule([
    'name' => 'New Failure IP Scope ' . uniqid(),
    'description' => 'Ensure scoped domain/group checks ignore other domains.',
    'rule_type' => 'metric',
    'metric' => 'new_failure_ips',
    'threshold_value' => 0.0,
    'threshold_operator' => '>',
    'time_window' => $windowMinutes,
    'domain_filter' => $domainA,
    'group_filter' => $groupAId,
    'severity' => 'medium',
    'notification_channels' => [],
    'notification_recipients' => [],
    'enabled' => 1,
]);

assertTrue($ruleId > 0, 'Alert rule should be created successfully for metric test.', $failures);

$db->query('SELECT * FROM alert_rules WHERE id = :id');
$db->bind(':id', $ruleId);
$rule = $db->single();

assertTrue(!empty($rule), 'Created alert rule should be retrievable for metric calculation.', $failures);

if (!empty($rule)) {
    $metricValue = calculateNewFailureIpsMetric($rule);

    assertEquals(1.0, $metricValue, 'New failure IP metric should count the scoped domain record only.', $failures);
}

// Clean up inserted data to keep the test database consistent for other cases.
$db->query('DELETE FROM alert_rules WHERE id = :id');
$db->bind(':id', $ruleId);
$db->execute();

$db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
$db->bind(':report_id', $historicalReportId);
$db->execute();

$db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
$db->bind(':report_id', $currentReportId);
$db->execute();

$db->query('DELETE FROM dmarc_aggregate_reports WHERE id IN (:historical_id, :current_id)');
$db->bind(':historical_id', $historicalReportId);
$db->bind(':current_id', $currentReportId);
$db->execute();

$db->query('DELETE FROM domain_group_assignments WHERE domain_id IN (:domain_a, :domain_b)');
$db->bind(':domain_a', $domainAId);
$db->bind(':domain_b', $domainBId);
$db->execute();

$db->query('DELETE FROM domain_groups WHERE id IN (:group_a, :group_b)');
$db->bind(':group_a', $groupAId);
$db->bind(':group_b', $groupBId);
$db->execute();

$db->query('DELETE FROM domains WHERE id IN (:domain_a, :domain_b)');
$db->bind(':domain_a', $domainAId);
$db->bind(':domain_b', $domainBId);
$db->execute();

echo 'Alert new failure IP scope test completed with ' . ($failures === 0 ? 'no' : $failures) . " failure(s)\n";

exit($failures > 0 ? 1 : 0);
