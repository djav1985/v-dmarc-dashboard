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
use App\Models\PdfReport;
use function TestHelpers\assertContains;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertPredicate;
use function TestHelpers\assertTrue;

$failures = 0;

function pdfReportGroupInsertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function pdfReportGroupInsertGroup(string $name): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'PDF report group filter test');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function pdfReportGroupAssignDomain(int $domainId, int $groupId): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':group_id', $groupId);
    $db->execute();
}

function pdfReportGroupInsertReport(int $domainId, int $startTimestamp, int $endTimestamp, string $identifier): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Group Filter Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $identifier);
    $db->bind(':start', $startTimestamp);
    $db->bind(':end', $endTimestamp);
    $db->bind(':received', date('Y-m-d H:i:s', $endTimestamp));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function pdfReportGroupInsertRecord(
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

$timestamp = time();
$rangeStart = $timestamp - 3600;
$rangeEnd = $timestamp;
$startDate = date('Y-m-d', $rangeStart);
$endDate = date('Y-m-d', $rangeEnd);

$groupAId = pdfReportGroupInsertGroup('Group A ' . $timestamp);
$groupBId = pdfReportGroupInsertGroup('Group B ' . $timestamp);

$domainA1 = 'group-a-1-' . $timestamp . '.example';
$domainA2 = 'group-a-2-' . $timestamp . '.example';
$domainB1 = 'group-b-1-' . $timestamp . '.example';

$domainA1Id = pdfReportGroupInsertDomain($domainA1);
$domainA2Id = pdfReportGroupInsertDomain($domainA2);
$domainB1Id = pdfReportGroupInsertDomain($domainB1);

pdfReportGroupAssignDomain($domainA1Id, $groupAId);
pdfReportGroupAssignDomain($domainA2Id, $groupAId);
pdfReportGroupAssignDomain($domainB1Id, $groupBId);

$reportA1Id = pdfReportGroupInsertReport($domainA1Id, $rangeStart, $rangeEnd, 'report-a1-' . $timestamp);
pdfReportGroupInsertRecord($reportA1Id, '192.0.2.1', 5, 'none', 'pass', 'pass');
pdfReportGroupInsertRecord($reportA1Id, '198.51.100.1', 3, 'reject', 'fail', 'fail');

$reportA2Id = pdfReportGroupInsertReport($domainA2Id, $rangeStart, $rangeEnd, 'report-a2-' . $timestamp);
pdfReportGroupInsertRecord($reportA2Id, '192.0.2.2', 2, 'none', 'pass', 'pass');

$reportB1Id = pdfReportGroupInsertReport($domainB1Id, $rangeStart, $rangeEnd, 'report-b1-' . $timestamp);
pdfReportGroupInsertRecord($reportB1Id, '198.51.100.2', 11, 'quarantine', 'pass', 'fail');

$db = DatabaseManager::getInstance();
$templateSections = json_encode([
    'summary',
    'domain_health',
    'top_threats',
    'compliance_status',
    'detailed_analytics',
    'volume_trends',
    'authentication_breakdown',
    'recommendations',
]);

$db->query('INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES (:name, :description, :type, :sections)');
$db->bind(':name', 'Group Filter Template ' . $timestamp);
$db->bind(':description', 'Ensures PDF analytics respect group filters');
$db->bind(':type', 'integration-test');
$db->bind(':sections', $templateSections);
$db->execute();

$db->query('SELECT last_insert_rowid() as id');
$templateRow = $db->single();
$templateId = (int) ($templateRow['id'] ?? 0);

$reportData = PdfReport::generateReportData($templateId, $startDate, $endDate, '', $groupAId);
$sections = $reportData['sections'] ?? [];

assertPredicate(isset($sections['summary']), 'Summary section should be generated', $failures);
$summary = $sections['summary'] ?? [];
assertEquals(2, (int) ($summary['domain_count'] ?? -1), 'Summary should count only group domains', $failures);
assertEquals(10, (int) ($summary['total_volume'] ?? -1), 'Summary volume should exclude out-of-group traffic', $failures);
assertEquals(2, (int) ($summary['report_count'] ?? -1), 'Summary report count should include only group reports', $failures);
assertEquals(3, (int) ($summary['unique_ips'] ?? -1), 'Summary should only count IPs from filtered domains', $failures);
assertEquals(7, (int) ($summary['passed_count'] ?? -1), 'Summary pass count should reflect filtered data', $failures);
assertEquals(0, (int) ($summary['quarantined_count'] ?? -1), 'Summary should not include quarantined mail outside the group', $failures);
assertEquals(3, (int) ($summary['rejected_count'] ?? -1), 'Summary reject count should only include filtered domains', $failures);
assertEquals(70.0, round((float) ($summary['pass_rate'] ?? 0.0), 1), 'Summary pass rate should be computed from filtered data', $failures);

$domainHealth = $sections['domain_health'] ?? [];
assertCountEquals(2, $domainHealth, 'Domain health should include both in-group domains', $failures);
$healthDomains = array_map(static fn($row) => $row['domain'] ?? '', $domainHealth);
sort($healthDomains);
assertEquals([$domainA1, $domainA2], $healthDomains, 'Domain health should only contain group domains', $failures);

$topThreats = $sections['top_threats'] ?? [];
assertCountEquals(1, $topThreats, 'Top threats should only include in-group IPs', $failures);
if (!empty($topThreats)) {
    assertEquals('198.51.100.1', $topThreats[0]['source_ip'] ?? '', 'Threat list should use in-group threat IP', $failures);
    assertTrue(strpos($topThreats[0]['affected_domains'] ?? '', $domainB1) === false, 'Threat list should not reference out-of-group domains', $failures);
}

$compliance = $sections['compliance_status'] ?? [];
assertTrue(!empty($compliance), 'Compliance timeline should be generated', $failures);
if (!empty($compliance)) {
    $firstCompliance = $compliance[0];
    assertEquals(70.0, round((float) ($firstCompliance['dmarc_compliance'] ?? 0.0), 1), 'Compliance should reflect filtered DMARC rate', $failures);
}

$detailed = $sections['detailed_analytics'] ?? [];
$trendRows = $detailed['trends'] ?? [];
assertTrue(!empty($trendRows), 'Detailed analytics should include trend data', $failures);
if (!empty($trendRows)) {
    $firstTrend = $trendRows[0];
    assertEquals(10, (int) ($firstTrend['total_volume'] ?? -1), 'Trend volume should exclude out-of-group reports', $failures);
    assertEquals(2, (int) ($firstTrend['report_count'] ?? -1), 'Trend report count should respect group filter', $failures);
}

$volumeTrends = $sections['volume_trends'] ?? [];
assertTrue(!empty($volumeTrends), 'Volume trends should be generated', $failures);
if (!empty($volumeTrends)) {
    $firstVolumeTrend = $volumeTrends[0];
    assertEquals(10, (int) ($firstVolumeTrend['total_volume'] ?? -1), 'Volume trends should only include in-group mail', $failures);
}

$singleDomainReport = PdfReport::generateReportData($templateId, $startDate, $endDate, $domainA1, null);
$singleDomainThreats = $singleDomainReport['sections']['top_threats'] ?? [];
assertCountEquals(1, $singleDomainThreats, 'Domain-filtered report should only list threats for the selected domain', $failures);
if (!empty($singleDomainThreats)) {
    $singleThreat = $singleDomainThreats[0];
    assertEquals('198.51.100.1', $singleThreat['source_ip'] ?? '', 'Domain-filtered threats should include the matching IP', $failures);
    assertTrue(strpos($singleThreat['affected_domains'] ?? '', $domainA2) === false, 'Domain-filtered threats should not mention other domains', $failures);
}

$breakdown = $sections['authentication_breakdown'] ?? [];
assertCountEquals(2, $breakdown, 'Authentication breakdown should ignore out-of-group combinations', $failures);
foreach ($breakdown as $row) {
    assertTrue(($row['disposition'] ?? '') !== 'quarantine', 'Breakdown should not include quarantined mail from other groups', $failures);
}

$recommendations = $sections['recommendations'] ?? [];
assertTrue(!empty($recommendations), 'Recommendations should be generated from filtered data', $failures);
foreach ($recommendations as $recommendation) {
    if (isset($recommendation['domain'])) {
        assertTrue($recommendation['domain'] !== $domainB1, 'Recommendations should not include out-of-group domains', $failures);
    }
    if (isset($recommendation['details']) && is_array($recommendation['details'])) {
        $detailsJson = json_encode($recommendation['details']);
        assertTrue(strpos($detailsJson, $domainB1) === false, 'Recommendation details should not reference out-of-group domains', $failures);
    }
}

assertContains((string) $groupAId, json_encode($reportData), 'Report metadata should record the requested group filter', $failures);

echo "PdfReport group filter coverage completed with " . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
