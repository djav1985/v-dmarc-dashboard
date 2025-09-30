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
use App\Models\DmarcReport;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;
use function TestHelpers\assertEquals;
use function TestHelpers\assertCountEquals;

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

function insertForensicReport(int $domainId): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_forensic_reports (domain_id, arrival_date, source_ip, authentication_results, raw_message) VALUES (:domain_id, :arrival_date, :source_ip, :auth_results, :raw_message)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':arrival_date', time());
    $db->bind(':source_ip', '192.168.1.100');
    $db->bind(':auth_results', 'dmarc=fail reason="policy"');
    $db->bind(':raw_message', 'Subject: Test Message\n\nTest forensic report message');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();

    return (int) ($result['id'] ?? 0);
}

echo "Starting forensic reports RBAC tests..." . PHP_EOL;

$failures = 0;

// Initialize database connection
$db = DatabaseManager::getInstance();

// Test: User with no domain access should receive empty forensic reports
resetSession();
$_SESSION['username'] = 'test_user_no_access';
$_SESSION['user_role'] = RBACManager::ROLE_VIEWER;

// Create test domains and forensic reports
$timestamp = time();
$domain1Id = insertDomain('test-domain-1-' . $timestamp . '.com');
$domain2Id = insertDomain('test-domain-2-' . $timestamp . '.com');
$forensicReport1Id = insertForensicReport($domain1Id);
$forensicReport2Id = insertForensicReport($domain2Id);

try {
    // Test that user with no domain assignments gets no forensic reports
    $reports = DmarcReport::getForensicReports();
    assertCountEquals(0, $reports, 'User with no domain access should receive no forensic reports', $failures);
    
    // Test with specific domain ID that user cannot access
    $reportsForDomain1 = DmarcReport::getForensicReports($domain1Id);
    assertCountEquals(0, $reportsForDomain1, 'User with no domain access should receive no reports for specific domain', $failures);
    
    echo "✓ User with no domain access correctly receives empty forensic reports" . PHP_EOL;
    
} finally {
    // Cleanup: Remove test data
    $db->query('DELETE FROM dmarc_forensic_reports WHERE domain_id IN (:domain1, :domain2)');
    $db->bind(':domain1', $domain1Id);
    $db->bind(':domain2', $domain2Id);
    $db->execute();
    
    $db->query('DELETE FROM domains WHERE id IN (:domain1, :domain2)');
    $db->bind(':domain1', $domain1Id);
    $db->bind(':domain2', $domain2Id);
    $db->execute();
}

// Test: App admin should still see all reports
resetSession();
$_SESSION['username'] = 'admin_user';
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;

// Create test domains and forensic reports for admin test
$timestamp2 = time() + 1;
$domain3Id = insertDomain('admin-test-domain-' . $timestamp2 . '.com');
$forensicReport3Id = insertForensicReport($domain3Id);

try {
    // Test that admin user gets all forensic reports
    $adminReports = DmarcReport::getForensicReports();
    assertTrue(count($adminReports) >= 1, 'Admin user should receive forensic reports', $failures);
    
    echo "✓ App admin correctly receives forensic reports" . PHP_EOL;
    
} finally {
    // Cleanup: Remove test data
    $db->query('DELETE FROM dmarc_forensic_reports WHERE domain_id = :domain3');
    $db->bind(':domain3', $domain3Id);
    $db->execute();
    
    $db->query('DELETE FROM domains WHERE id = :domain3');
    $db->bind(':domain3', $domain3Id);
    $db->execute();
}

resetSession();

echo "Forensic reports RBAC tests completed with $failures failures." . PHP_EOL;
exit($failures === 0 ? 0 : 1);