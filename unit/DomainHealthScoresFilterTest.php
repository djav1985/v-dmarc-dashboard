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
use App\Models\Analytics;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;

$failures = 0;

function domainHealthFilterInsertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function domainHealthFilterInsertGroup(string $name): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'Domain health filter coverage');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function domainHealthFilterAssignDomain(int $domainId, int $groupId): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

function domainHealthFilterInsertReport(int $domainId, int $startTimestamp, int $endTimestamp, string $identifier): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Domain Health Filter Org');
    $db->bind(':email', 'filter@example.com');
    $db->bind(':report_id', $identifier);
    $db->bind(':start', $startTimestamp);
    $db->bind(':end', $endTimestamp);
    $db->bind(':received', date('Y-m-d H:i:s', $endTimestamp));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function domainHealthFilterInsertRecord(int $reportId, int $count, string $disposition = 'none'): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', '198.51.100.42');
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim', $disposition === 'none' ? 'pass' : 'fail');
    $db->bind(':spf', $disposition === 'none' ? 'pass' : 'fail');
    $db->bind(':header_from', 'example.org');
    $db->bind(':envelope_from', 'sender@example.org');
    $db->bind(':envelope_to', 'recipient@example.org');
    $db->execute();
}

$db = DatabaseManager::getInstance();
$timestamp = time();

$startDate = date('Y-m-d', $timestamp - 3600);
$endDate = date('Y-m-d', $timestamp);

$groupAId = domainHealthFilterInsertGroup('Domain Health Filter Group A ' . $timestamp);
$groupBId = domainHealthFilterInsertGroup('Domain Health Filter Group B ' . $timestamp);

$domainA = 'domain-a-' . $timestamp . '.example';
$domainB = 'domain-b-' . $timestamp . '.example';
$domainC = 'domain-c-' . $timestamp . '.example';

$domainAId = domainHealthFilterInsertDomain($domainA);
$domainBId = domainHealthFilterInsertDomain($domainB);
$domainCId = domainHealthFilterInsertDomain($domainC);

domainHealthFilterAssignDomain($domainAId, $groupAId);
domainHealthFilterAssignDomain($domainBId, $groupAId);
domainHealthFilterAssignDomain($domainCId, $groupBId);

$rangeStart = strtotime($startDate . ' 00:00:00');
$rangeEnd = strtotime($endDate . ' 23:59:59');

$reportA = domainHealthFilterInsertReport($domainAId, $rangeStart, $rangeEnd, 'domain-a-report-' . $timestamp);
domainHealthFilterInsertRecord($reportA, 12, 'none');

domainHealthFilterInsertRecord($reportA, 3, 'reject');

$reportB = domainHealthFilterInsertReport($domainBId, $rangeStart, $rangeEnd, 'domain-b-report-' . $timestamp);
domainHealthFilterInsertRecord($reportB, 5, 'none');
domainHealthFilterInsertRecord($reportB, 2, 'quarantine');

$reportC = domainHealthFilterInsertReport($domainCId, $rangeStart, $rangeEnd, 'domain-c-report-' . $timestamp);
domainHealthFilterInsertRecord($reportC, 8, 'none');

domainHealthFilterInsertRecord($reportC, 1, 'reject');

$groupResults = Analytics::getDomainHealthScores($startDate, $endDate, $groupAId);
assertCountEquals(2, $groupResults, 'Group-scoped results should include both in-group domains', $failures);

$groupDomains = array_map(static fn(array $row): string => (string) ($row['domain'] ?? ''), $groupResults);
sort($groupDomains);
assertEquals([$domainA, $domainB], $groupDomains, 'Group results should not include out-of-group domains', $failures);

$groupFiltered = Analytics::getDomainHealthScores($startDate, $endDate, $groupAId, $domainA);
assertCountEquals(1, $groupFiltered, 'Domain filter should narrow the scoped result set', $failures);
if (!empty($groupFiltered)) {
    assertEquals($domainA, (string) ($groupFiltered[0]['domain'] ?? ''), 'Domain filter should return only the requested domain', $failures);
}

$groupFilteredMissing = Analytics::getDomainHealthScores($startDate, $endDate, $groupAId, $domainC);
assertCountEquals(0, $groupFilteredMissing, 'Filtering to an out-of-group domain should return no rows', $failures);

$globalFiltered = Analytics::getDomainHealthScores($startDate, $endDate, null, $domainC);
assertCountEquals(1, $globalFiltered, 'Global filtering should surface a matching domain even without a group scope', $failures);
if (!empty($globalFiltered)) {
    assertEquals($domainC, (string) ($globalFiltered[0]['domain'] ?? ''), 'Global filtering should match the requested domain', $failures);
}

echo "Domain health filter coverage completed with " . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
