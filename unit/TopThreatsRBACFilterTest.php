<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Models\Analytics;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;

$failures = 0;

function topThreatsInsertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function topThreatsInsertReport(int $domainId, int $startTimestamp, int $endTimestamp, string $identifier): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Top Threats Test Org');
    $db->bind(':email', 'threats@example.com');
    $db->bind(':report_id', $identifier);
    $db->bind(':start', $startTimestamp);
    $db->bind(':end', $endTimestamp);
    $db->bind(':received', date('Y-m-d H:i:s', $endTimestamp));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function topThreatsInsertRecord(int $reportId, string $sourceIp, int $count, string $disposition = 'reject'): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', $sourceIp);
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim', 'fail');
    $db->bind(':spf', 'fail');
    $db->bind(':header_from', 'threat.example');
    $db->bind(':envelope_from', 'sender@threat.example');
    $db->bind(':envelope_to', 'recipient@example.org');
    $db->execute();
}

$db = DatabaseManager::getInstance();
$timestamp = time();

$startDate = date('Y-m-d', $timestamp - 3600);
$endDate = date('Y-m-d', $timestamp);

$domainA = 'threat-domain-a-' . $timestamp . '.example';
$domainB = 'threat-domain-b-' . $timestamp . '.example';

$domainAId = topThreatsInsertDomain($domainA);
$domainBId = topThreatsInsertDomain($domainB);

$rangeStart = strtotime($startDate . ' 00:00:00');
$rangeEnd = strtotime($endDate . ' 23:59:59');

// Create reports with threatening IPs
$reportA = topThreatsInsertReport($domainAId, $rangeStart, $rangeEnd, 'threat-report-a-' . $timestamp);
topThreatsInsertRecord($reportA, '192.0.2.100', 15, 'reject');
topThreatsInsertRecord($reportA, '192.0.2.101', 8, 'quarantine');

$reportB = topThreatsInsertReport($domainBId, $rangeStart, $rangeEnd, 'threat-report-b-' . $timestamp);
topThreatsInsertRecord($reportB, '192.0.2.200', 20, 'reject');
topThreatsInsertRecord($reportB, '192.0.2.201', 5, 'quarantine');

// Test as app admin - should see all threats
$_SESSION['username'] = 'threat_admin_' . $timestamp;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;

$adminResults = Analytics::getTopThreats($startDate, $endDate, 10);
assertCountEquals(4, $adminResults, 'Admin should see all threat IPs from all domains', $failures);

// Test as viewer with access to only domain A - should see only threats from domain A
$_SESSION['username'] = 'threat_viewer_' . $timestamp;
$_SESSION['user_role'] = RBACManager::ROLE_VIEWER;

$db->query('INSERT INTO user_domain_assignments (user_id, domain_id) VALUES (:user_id, :domain_id)');
$db->bind(':user_id', $_SESSION['username']);
$db->bind(':domain_id', $domainAId);
$db->execute();

$viewerResults = Analytics::getTopThreats($startDate, $endDate, 10);
assertCountEquals(2, $viewerResults, 'Viewer should only see threats from authorized domain', $failures);

$viewerIps = array_map(static fn(array $row): string => (string) ($row['source_ip'] ?? ''), $viewerResults);
sort($viewerIps);
assertEquals(['192.0.2.100', '192.0.2.101'], $viewerIps, 'Viewer should only see IPs from domain A', $failures);

// Test as viewer with no domain access - should see no threats
$db->query('DELETE FROM user_domain_assignments WHERE user_id = :user_id');
$db->bind(':user_id', $_SESSION['username']);
$db->execute();

$noAccessResults = Analytics::getTopThreats($startDate, $endDate, 10);
assertCountEquals(0, $noAccessResults, 'Viewer with no domain access should see no threats', $failures);

// Test with domain filter - should bypass RBAC for that specific domain
$_SESSION['username'] = 'threat_admin_' . $timestamp;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;

$filteredResults = Analytics::getTopThreats($startDate, $endDate, 10, null, $domainB);
assertCountEquals(2, $filteredResults, 'Domain filter should return only threats from that domain', $failures);

$filteredIps = array_map(static fn(array $row): string => (string) ($row['source_ip'] ?? ''), $filteredResults);
sort($filteredIps);
assertEquals(['192.0.2.200', '192.0.2.201'], $filteredIps, 'Filtered results should only include IPs from domain B', $failures);

unset($_SESSION['username'], $_SESSION['user_role']);

echo "Top threats RBAC filter coverage completed with " . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
