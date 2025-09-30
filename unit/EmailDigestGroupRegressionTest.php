<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Models\EmailDigest;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

function emailDigestRegressionInsertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function emailDigestRegressionInsertGroup(string $groupName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $groupName);
    $db->bind(':description', 'Email digest regression coverage group');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function emailDigestRegressionAssignDomain(int $domainId, int $groupId): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

function emailDigestRegressionInsertReport(
    int $domainId,
    int $rangeStart,
    int $rangeEnd,
    string $identifier
): int {
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Digest Regression Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $identifier);
    $db->bind(':start', $rangeStart);
    $db->bind(':end', $rangeEnd);
    $db->bind(':received', date('Y-m-d H:i:s', $rangeEnd));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function emailDigestRegressionInsertRecord(
    int $reportId,
    string $sourceIp,
    int $count,
    string $disposition,
    string $spf,
    string $dkim
): void {
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', $sourceIp);
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim', $dkim);
    $db->bind(':spf', $spf);
    $db->bind(':header_from', 'example.com');
    $db->bind(':envelope_from', 'sender@example.com');
    $db->bind(':envelope_to', 'recipient@example.com');
    $db->execute();
}

function emailDigestRegressionCleanup(): void
{
    $db = DatabaseManager::getInstance();
    $pattern = 'digest-dup-%';

    $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id IN (SELECT id FROM dmarc_aggregate_reports WHERE domain_id IN (SELECT id FROM domains WHERE domain LIKE :pattern))');
    $db->bind(':pattern', $pattern);
    $db->execute();

    $db->query('DELETE FROM dmarc_aggregate_reports WHERE domain_id IN (SELECT id FROM domains WHERE domain LIKE :pattern)');
    $db->bind(':pattern', $pattern);
    $db->execute();

    $db->query('DELETE FROM domain_group_assignments WHERE domain_id IN (SELECT id FROM domains WHERE domain LIKE :pattern)');
    $db->bind(':pattern', $pattern);
    $db->execute();

    $db->query('DELETE FROM domains WHERE domain LIKE :pattern');
    $db->bind(':pattern', $pattern);
    $db->execute();

    $db->query('DELETE FROM domain_groups WHERE name LIKE :group_pattern');
    $db->bind(':group_pattern', 'Digest Regression %');
    $db->execute();

    $db->query('DELETE FROM email_digest_schedules WHERE name LIKE :schedule_pattern');
    $db->bind(':schedule_pattern', 'Digest Regression%');
    $db->execute();
}

$timestamp = time();
emailDigestRegressionCleanup();
$rangeStart = $timestamp - 3600;
$rangeEnd = $timestamp;
$startDate = date('Y-m-d', $rangeStart);
$endDate = date('Y-m-d', $rangeEnd);
$startTimestamp = strtotime($startDate);
$endTimestamp = strtotime($endDate . ' 23:59:59');

$domainName = 'digest-dup-' . $timestamp . '.example';
$domainId = emailDigestRegressionInsertDomain($domainName);

$groupAId = emailDigestRegressionInsertGroup('Digest Regression A ' . $timestamp);
$groupBId = emailDigestRegressionInsertGroup('Digest Regression B ' . $timestamp);

emailDigestRegressionAssignDomain($domainId, $groupAId);
emailDigestRegressionAssignDomain($domainId, $groupBId);

$reportId = emailDigestRegressionInsertReport($domainId, $rangeStart, $rangeEnd, 'digest-regression-report-' . $timestamp);
emailDigestRegressionInsertRecord($reportId, '198.51.100.25', 5, 'none', 'pass', 'pass');
emailDigestRegressionInsertRecord($reportId, '198.51.100.25', 3, 'reject', 'fail', 'fail');

$scheduleId = EmailDigest::createSchedule([
    'name' => 'Digest Regression ' . $timestamp,
    'frequency' => 'daily',
    'recipients' => ['digest-regression@example.com'],
    'domain_filter' => $domainName,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', $rangeEnd),
]);

$digestData = EmailDigest::generateDigestData($scheduleId, $startDate, $endDate);

assertTrue(!empty($digestData), 'Digest data should be generated for seeded schedule', $failures);
$summary = $digestData['summary'] ?? [];
assertEquals(1, (int) ($summary['domain_count'] ?? 0), 'Summary should include exactly one domain', $failures);
assertEquals(1, (int) ($summary['report_count'] ?? 0), 'Summary should include exactly one report', $failures);
assertEquals(8, (int) ($summary['total_volume'] ?? 0), 'Summary total volume should match raw record counts', $failures);
assertEquals(5, (int) ($summary['passed_count'] ?? 0), 'Summary should report accurate pass volume', $failures);
assertEquals(3, (int) ($summary['rejected_count'] ?? 0), 'Summary should report accurate reject volume', $failures);

$domains = $digestData['domains'] ?? [];
assertCountEquals(1, $domains, 'Domain breakdown should include a single domain entry', $failures);
$domainBreakdown = $domains[0] ?? [];
assertEquals($domainName, $domainBreakdown['domain'] ?? '', 'Domain breakdown should return the filtered domain', $failures);
assertEquals(8, (int) ($domainBreakdown['total_volume'] ?? 0), 'Domain breakdown should avoid duplicate aggregation', $failures);

$threats = $digestData['threats'] ?? [];
assertCountEquals(1, $threats, 'Threat aggregation should produce one source IP row', $failures);
$threat = $threats[0] ?? [];
assertEquals(3, (int) ($threat['threat_volume'] ?? 0), 'Threat aggregation should respect raw reject volume', $failures);
assertEquals(1, (int) ($threat['affected_domains'] ?? 0), 'Threat aggregation should count the affected domain once', $failures);

$globalScheduleId = EmailDigest::createSchedule([
    'name' => 'Digest Regression Global ' . $timestamp,
    'frequency' => 'daily',
    'recipients' => ['digest-regression-global@example.com'],
    'domain_filter' => '',
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', $rangeEnd),
]);

$globalDigestData = EmailDigest::generateDigestData($globalScheduleId, $startDate, $endDate);
assertTrue(!empty($globalDigestData), 'Global digest data should be generated without a group filter', $failures);
$globalSummary = $globalDigestData['summary'] ?? [];

$db = DatabaseManager::getInstance();
$db->query('
    SELECT COALESCE(SUM(dmar.count), 0) AS total_volume
    FROM dmarc_aggregate_reports dar
    LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
    WHERE dar.date_range_begin >= :start_date
    AND dar.date_range_end <= :end_date
');
$db->bind(':start_date', $startTimestamp);
$db->bind(':end_date', $endTimestamp);
$rawTotals = $db->single();
$rawTotalVolume = (int) ($rawTotals['total_volume'] ?? 0);
assertEquals(
    $rawTotalVolume,
    (int) ($globalSummary['total_volume'] ?? 0),
    'Global summary should reflect raw total volume without duplication',
    $failures
);

$globalDomains = $globalDigestData['domains'] ?? [];
$matchedDomains = array_values(array_filter(
    $globalDomains,
    static function (array $domainRow) use ($domainName): bool {
        return ($domainRow['domain'] ?? '') === $domainName;
    }
));
assertTrue(!empty($matchedDomains), 'Global digest should include the seeded domain', $failures);
$matchedDomain = $matchedDomains[0];
assertEquals(8, (int) ($matchedDomain['total_volume'] ?? 0), 'Global domain aggregation should match raw counts', $failures);

$globalThreats = $globalDigestData['threats'] ?? [];
$matchedThreats = array_values(array_filter(
    $globalThreats,
    static function (array $threatRow): bool {
        return ($threatRow['source_ip'] ?? '') === '198.51.100.25';
    }
));
assertTrue(!empty($matchedThreats), 'Global threat aggregation should include the seeded source IP', $failures);
$matchedThreat = $matchedThreats[0];
assertEquals(3, (int) ($matchedThreat['threat_volume'] ?? 0), 'Global threat aggregation should avoid duplicate counts', $failures);
assertEquals(1, (int) ($matchedThreat['affected_domains'] ?? 0), 'Global threat aggregation should count the domain once', $failures);

echo 'EmailDigest group assignment duplication regression completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
