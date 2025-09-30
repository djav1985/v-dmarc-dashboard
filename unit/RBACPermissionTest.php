<?php
declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Controllers\UploadController;
use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\DmarcReport;

function assertTrue(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function assertFalse(bool $condition, string $message, int &$failures): void
{
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function assertEquals($expected, $actual, string $message, int &$failures): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . PHP_EOL);
        $failures++;
    }
}

function assertCountEquals(int $expected, array $actual, string $message, int &$failures): void
{
    if (count($actual) !== $expected) {
        fwrite(STDERR, $message . ' Expected count ' . $expected . ' got ' . count($actual) . PHP_EOL);
        $failures++;
    }
}

function resetSession(): void
{
    $_SESSION = [];
}

function insertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function insertDomainGroup(string $name, string $description): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', $description);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function setupScopedData(): array
{
    $timestamp = time();
    $username = 'scoped_user_' . $timestamp;
    $accessibleDomainName = 'scoped-allowed-' . $timestamp . '.example';
    $restrictedDomainName = 'scoped-denied-' . $timestamp . '.example';
    $unassignedDomainName = 'scoped-unassigned-' . $timestamp . '.example';

    $accessibleDomainId = insertDomain($accessibleDomainName);
    $restrictedDomainId = insertDomain($restrictedDomainName);
    $unassignedDomainId = insertDomain($unassignedDomainName);

    $accessibleGroupId = insertDomainGroup('Scoped Group ' . $timestamp, 'Test group');
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $accessibleDomainId);
    $db->bind(':group_id', $accessibleGroupId);
    $db->execute();

    $restrictedGroupId = insertDomainGroup('Restricted Group ' . $timestamp, 'Test group');
    $db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
    $db->bind(':domain_id', $restrictedDomainId);
    $db->bind(':group_id', $restrictedGroupId);
    $db->execute();

    $accessibleReportId = insertAggregateReport($accessibleDomainId, 'accessible-report-' . $timestamp);
    insertAggregateRecord($accessibleReportId, '198.51.100.10', 5, 'none');

    $restrictedReportId = insertAggregateReport($restrictedDomainId, 'restricted-report-' . $timestamp);
    insertAggregateRecord($restrictedReportId, '203.0.113.50', 7, 'reject');

    $db->query('INSERT INTO user_domain_assignments (user_id, domain_id, assigned_by) VALUES (:user_id, :domain_id, :assigned_by)');
    $db->bind(':user_id', $username);
    $db->bind(':domain_id', $accessibleDomainId);
    $db->bind(':assigned_by', 'test-suite');
    $db->execute();

    $db->query('INSERT INTO user_group_assignments (user_id, group_id) VALUES (:user_id, :group_id)');
    $db->bind(':user_id', $username);
    $db->bind(':group_id', $accessibleGroupId);
    $db->execute();

    return [
        'username' => $username,
        'domains' => [
            'accessible' => ['id' => $accessibleDomainId, 'name' => $accessibleDomainName],
            'restricted' => ['id' => $restrictedDomainId, 'name' => $restrictedDomainName],
            'unassigned' => ['id' => $unassignedDomainId, 'name' => $unassignedDomainName],
        ],
        'groups' => [
            'accessible' => $accessibleGroupId,
            'restricted' => $restrictedGroupId,
        ],
        'reports' => [
            'accessible' => $accessibleReportId,
            'restricted' => $restrictedReportId,
        ],
    ];
}

function insertAggregateReport(int $domainId, string $reportIdentifier): int
{
    $db = DatabaseManager::getInstance();
    $rangeStart = time() - 86400;
    $rangeEnd = time();

    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, extra_contact_info, report_id, date_range_begin, date_range_end, raw_xml) VALUES (:domain_id, :org_name, :email, NULL, :report_id, :date_range_begin, :date_range_end, NULL)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Test Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $reportIdentifier);
    $db->bind(':date_range_begin', $rangeStart);
    $db->bind(':date_range_end', $rangeEnd);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

function insertAggregateRecord(int $reportId, string $sourceIp, int $count, string $disposition): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', $sourceIp);
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim_result', 'pass');
    $db->bind(':spf_result', 'pass');
    $db->bind(':header_from', 'example.com');
    $db->bind(':envelope_from', 'mail@example.com');
    $db->bind(':envelope_to', 'postmaster@example.com');
    $db->execute();
}

function cleanupScopedData(array $data): void
{
    $db = DatabaseManager::getInstance();

    $db->query('DELETE FROM user_domain_assignments WHERE user_id = :user_id');
    $db->bind(':user_id', $data['username']);
    $db->execute();

    $db->query('DELETE FROM user_group_assignments WHERE user_id = :user_id');
    $db->bind(':user_id', $data['username']);
    $db->execute();

    foreach ($data['reports'] as $reportId) {
        $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
        $db->bind(':report_id', $reportId);
        $db->execute();

        $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
        $db->bind(':report_id', $reportId);
        $db->execute();
    }

    $db->query('DELETE FROM domain_group_assignments WHERE group_id IN (:group_a, :group_b)');
    $db->bind(':group_a', $data['groups']['accessible']);
    $db->bind(':group_b', $data['groups']['restricted']);
    $db->execute();

    $db->query('DELETE FROM domain_groups WHERE id IN (:group_a, :group_b)');
    $db->bind(':group_a', $data['groups']['accessible']);
    $db->bind(':group_b', $data['groups']['restricted']);
    $db->execute();

    foreach ($data['domains'] as $domain) {
        $db->query('DELETE FROM domains WHERE id = :domain_id');
        $db->bind(':domain_id', $domain['id']);
        $db->execute();
    }
}

$failures = 0;

// Test 1: viewer role should be denied upload permission
resetSession();
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = RBACManager::ROLE_VIEWER;
$_SESSION['username'] = 'viewer_user';

$unauthorizedController = new class extends UploadController {
    public bool $renderCalled = false;
    protected function render(string $view, array $data = []): void
    {
        $this->renderCalled = true;
    }
};

$exceptionCaught = false;
try {
    $unauthorizedController->handleRequest();
} catch (RuntimeException $e) {
    $exceptionCaught = true;
    assertTrue(strpos($e->getMessage(), RBACManager::PERM_UPLOAD_REPORTS) !== false, 'Unauthorized upload access should mention required permission', $failures);
}

assertTrue($exceptionCaught, 'Unauthorized upload access should throw runtime exception during tests', $failures);
assertFalse($unauthorizedController->renderCalled, 'Render should not execute for unauthorized upload access', $failures);

// Test 2: app admin should be able to load the upload screen
resetSession();
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$_SESSION['username'] = 'admin_user';

$authorizedController = new class extends UploadController {
    public bool $renderCalled = false;
    protected function render(string $view, array $data = []): void
    {
        $this->renderCalled = true;
    }
};

try {
    $authorizedController->handleRequest();
} catch (RuntimeException $e) {
    assertTrue(false, 'Admin upload access should not throw exception', $failures);
}

assertTrue($authorizedController->renderCalled, 'Upload controller should render for authorized user', $failures);

// Test 3: Scoped domain admin should only see assigned domains and groups
$scopedData = setupScopedData();

try {
    resetSession();
    $_SESSION['logged_in'] = true;
    $_SESSION['user_role'] = RBACManager::ROLE_DOMAIN_ADMIN;
    $_SESSION['username'] = $scopedData['username'];

    $groups = DomainGroup::getAllGroups();
    assertCountEquals(1, $groups, 'Scoped user should see only one accessible group', $failures);
    assertEquals($scopedData['groups']['accessible'], (int) ($groups[0]['id'] ?? 0), 'Scoped user should see assigned group ID', $failures);

    $domains = Domain::getAllDomains();
    assertCountEquals(1, $domains, 'Scoped user should see only assigned domain', $failures);
    assertEquals($scopedData['domains']['accessible']['name'], $domains[0]['domain'] ?? '', 'Accessible domain should match assigned domain', $failures);

    $unassigned = DomainGroup::getUnassignedDomains();
    assertCountEquals(0, $unassigned, 'Scoped user should not see unassigned domains they lack access to', $failures);

    $reports = DmarcReport::getFilteredReports(['limit' => 10, 'offset' => 0]);
    assertCountEquals(1, $reports, 'Scoped user should receive reports only for accessible domains', $failures);
    assertEquals($scopedData['domains']['accessible']['name'], $reports[0]['domain'] ?? '', 'Report domain should match accessible domain', $failures);

    $restrictedReports = DmarcReport::getFilteredReports(['domain' => $scopedData['domains']['restricted']['name']]);
    assertCountEquals(0, $restrictedReports, 'Scoped user should not retrieve reports for restricted domain filter', $failures);

    $restrictedDetails = DmarcReport::getReportDetails($scopedData['reports']['restricted']);
    assertTrue($restrictedDetails === null, 'Scoped user should not access restricted report details', $failures);

    $restrictedRecords = DmarcReport::getAggregateRecords($scopedData['reports']['restricted']);
    assertCountEquals(0, $restrictedRecords, 'Scoped user should not access restricted report records', $failures);
} finally {
    cleanupScopedData($scopedData);
}

echo "RBAC permission tests completed with $failures failures." . PHP_EOL;
exit($failures === 0 ? 0 : 1);

