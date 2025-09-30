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
use App\Models\EmailDigest;
use function TestHelpers\assertFalse;
use function TestHelpers\assertTrue;

$failures = 0;

$db = DatabaseManager::getInstance();
$timestamp = time();

$accessibleDomain = 'digest-access-' . $timestamp . '.example';
$restrictedDomain = 'digest-restricted-' . $timestamp . '.example';

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $accessibleDomain);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleDomainId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $restrictedDomain);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedDomainId = (int) ($db->single()['id'] ?? 0);

// Accessible group
$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'Digest Group ' . $timestamp);
$db->bind(':description', 'Digest access group');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleGroupId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
$db->bind(':domain_id', $accessibleDomainId);
$db->bind(':group_id', $accessibleGroupId);
$db->execute();

$_SESSION['username'] = 'digest-viewer-' . $timestamp;
$_SESSION['user_role'] = 'viewer';

$db->query('INSERT INTO user_domain_assignments (user_id, domain_id) VALUES (:user, :domain)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':domain', $accessibleDomainId);
$db->execute();

$db->query('INSERT INTO user_group_assignments (user_id, group_id) VALUES (:user, :group)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':group', $accessibleGroupId);
$db->execute();

$accessibleScheduleId = EmailDigest::createSchedule([
    'name' => 'Digest Accessible ' . $timestamp,
    'frequency' => 'weekly',
    'recipients' => ['digest@example.com'],
    'domain_filter' => $accessibleDomain,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => null,
    'created_by' => $_SESSION['username'],
]);

assertTrue($accessibleScheduleId > 0, 'Accessible digest schedule should be created successfully.', $failures);

// Create schedule tied to accessible group
$groupScheduleId = EmailDigest::createSchedule([
    'name' => 'Group Digest ' . $timestamp,
    'frequency' => 'weekly',
    'recipients' => ['group@example.com'],
    'domain_filter' => '',
    'group_filter' => $accessibleGroupId,
    'enabled' => 1,
    'next_scheduled' => null,
    'created_by' => $_SESSION['username'],
]);

assertTrue($groupScheduleId > 0, 'Digest schedules for accessible groups should be allowed.', $failures);

$ownerScheduleId = EmailDigest::createSchedule([
    'name' => 'Owner Digest ' . $timestamp,
    'frequency' => 'weekly',
    'recipients' => ['owner@example.com'],
    'domain_filter' => '',
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => null,
    'created_by' => $_SESSION['username'],
]);

assertTrue($ownerScheduleId > 0, 'Users should be able to create personal digests without filters.', $failures);

// Restricted schedule inserted manually to bypass guard for test coverage
$db->query('INSERT INTO email_digest_schedules (name, frequency, recipients, domain_filter, group_filter, enabled, next_scheduled) VALUES (:name, :frequency, :recipients, :domain_filter, :group_filter, 1, NULL)');
$db->bind(':name', 'Restricted Digest ' . $timestamp);
$db->bind(':frequency', 'weekly');
$db->bind(':recipients', json_encode(['restricted@example.com']));
$db->bind(':domain_filter', $restrictedDomain);
$db->bind(':group_filter', null);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedScheduleId = (int) ($db->single()['id'] ?? 0);

$schedules = EmailDigest::getAllSchedules();
$retrievedIds = array_map(static fn(array $row) => (int) ($row['id'] ?? 0), $schedules);

assertTrue(in_array($accessibleScheduleId, $retrievedIds, true), 'Accessible domain schedule should be listed.', $failures);
assertTrue(in_array($groupScheduleId, $retrievedIds, true), 'Accessible group schedule should be listed.', $failures);
assertTrue(in_array($ownerScheduleId, $retrievedIds, true), 'Viewer should see their own unfiltered digests.', $failures);
assertFalse(in_array($restrictedScheduleId, $retrievedIds, true), 'Schedules for unauthorized domains must be hidden.', $failures);

$restrictedDigest = EmailDigest::generateDigestData($restrictedScheduleId, date('Y-m-d'), date('Y-m-d'));
assertTrue(empty($restrictedDigest), 'Generating data for unauthorized schedules should return empty results.', $failures);

assertTrue(EmailDigest::setEnabled($accessibleScheduleId, false), 'Authorized schedules should toggle successfully.', $failures);
assertTrue(EmailDigest::setEnabled($ownerScheduleId, true), 'Owners should be able to enable their personal digests.', $failures);
assertFalse(EmailDigest::setEnabled($restrictedScheduleId, true), 'Unauthorized schedules must not toggle.', $failures);

echo 'Email digest schedule access tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
