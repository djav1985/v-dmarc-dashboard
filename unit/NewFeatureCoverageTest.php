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
use App\Models\PasswordReset;
use App\Models\Blacklist;
use App\Models\TlsReport;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;
use function TestHelpers\assertEquals;
use function TestHelpers\assertContains;

$failures = 0;

function insertUser(string $username, string $email): void
{
    $db = DatabaseManager::getInstance();
    $db->query('DELETE FROM users WHERE username = :username');
    $db->bind(':username', $username);
    $db->execute();

    $db->query('INSERT INTO users (username, password, role, first_name, last_name, email, admin, is_active) VALUES (:username, :password, :role, :first, :last, :email, 0, 1)');
    $db->bind(':username', $username);
    $db->bind(':password', password_hash('initialPassword123', PASSWORD_DEFAULT));
    $db->bind(':role', RBACManager::ROLE_VIEWER);
    $db->bind(':first', 'Test');
    $db->bind(':last', 'User');
    $db->bind(':email', $email);
    $db->execute();
}

function cleanupPasswordResets(string $username): void
{
    $db = DatabaseManager::getInstance();
    $db->query('DELETE FROM password_reset_tokens WHERE username = :username');
    $db->bind(':username', $username);
    $db->execute();
}

function insertTlsReport(int $domainId): int
{
    $db = DatabaseManager::getInstance();
    $timestamp = time();
    $db->query('INSERT INTO smtp_tls_reports (domain_id, org_name, contact_info, report_id, date_range_begin, date_range_end, raw_json, processed) VALUES (:domain_id, :org_name, :contact_info, :report_id, :begin, :end, :raw_json, 0)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Example Org');
    $db->bind(':contact_info', 'tls@example.com');
    $db->bind(':report_id', 'tls-' . $timestamp);
    $db->bind(':begin', $timestamp - 86400);
    $db->bind(':end', $timestamp);
    $db->bind(':raw_json', json_encode(['id' => 'tls-' . $timestamp]));
    $db->execute();

    $reportId = (int) $db->getLastInsertId();

    $db->query('INSERT INTO smtp_tls_policies (tls_report_id, policy_type, policy_string, policy_domain, mx_host, successful_session_count, failure_session_count) VALUES (:id, :type, :string, :domain, :mx, :success, :failure)');
    $db->bind(':id', $reportId);
    $db->bind(':type', 'sts');
    $db->bind(':string', 'enforce');
    $db->bind(':domain', 'mail.example.com');
    $db->bind(':mx', 'mx1.example.com');
    $db->bind(':success', 10);
    $db->bind(':failure', 2);
    $db->execute();

    return $reportId;
}

function cleanupTlsReport(int $reportId): void
{
    $db = DatabaseManager::getInstance();
    $db->query('DELETE FROM smtp_tls_policies WHERE tls_report_id = :id');
    $db->bind(':id', $reportId);
    $db->execute();

    $db->query('DELETE FROM smtp_tls_reports WHERE id = :id');
    $db->bind(':id', $reportId);
    $db->execute();
}

function insertDomainForTls(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

// Password reset token lifecycle
$username = 'reset_user_' . time();
$email = $username . '@example.com';
insertUser($username, $email);
cleanupPasswordResets($username);

$token = PasswordReset::createToken($username, $email);
assertContains(':', $token, 'Password reset token should combine selector and verifier.', $failures);

$record = PasswordReset::validateToken($token);
assertTrue(is_array($record), 'Password reset token should validate successfully.', $failures);
assertEquals($username, $record['username'] ?? null, 'Password reset token should resolve to the correct user.', $failures);

PasswordReset::consumeToken($record['selector']);
$afterConsume = PasswordReset::validateToken($token);
assertFalse(is_array($afterConsume), 'Consumed password reset tokens should no longer validate.', $failures);
cleanupPasswordResets($username);

// Blacklist management
$ipAddress = '203.0.113.' . rand(10, 200);
Blacklist::banIp($ipAddress);
$entries = Blacklist::getAll();
$found = false;
foreach ($entries as $entry) {
    if ($entry['ip_address'] === $ipAddress) {
        $found = (bool) $entry['blacklisted'];
        break;
    }
}
assertTrue($found, 'Ban IP should mark the address as blacklisted.', $failures);
Blacklist::unbanIp($ipAddress);
$entriesAfter = Blacklist::getAll();
$stillBlacklisted = false;
foreach ($entriesAfter as $entry) {
    if ($entry['ip_address'] === $ipAddress) {
        $stillBlacklisted = (bool) $entry['blacklisted'];
        break;
    }
}
assertFalse($stillBlacklisted, 'Unban IP should clear the blacklist flag.', $failures);

// TLS report accessibility
$_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
$domainId = insertDomainForTls('tls-' . time() . '.example');
$reportId = insertTlsReport($domainId);
$reports = TlsReport::getRecentReports(5);
assertTrue(count($reports) > 0, 'Recent TLS reports should return inserted data for administrators.', $failures);
$detail = TlsReport::getReportDetail($reportId);
assertTrue(is_array($detail) && !empty($detail['policies']), 'TLS report detail should include policy breakdown.', $failures);
cleanupTlsReport($reportId);
$dbCleanup = DatabaseManager::getInstance();
$dbCleanup->query('DELETE FROM domains WHERE id = :id');
$dbCleanup->bind(':id', $domainId);
$dbCleanup->execute();

$summary = sprintf('NewFeatureCoverageTest completed with %d failure(s).', $failures);
if ($failures > 0) {
    fwrite(STDERR, $summary . PHP_EOL);
    exit(1);
}

echo $summary . PHP_EOL;
