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

use App\Controllers\DomainGroupsController;
use App\Core\DatabaseManager;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use App\Models\DomainGroup;
use function TestHelpers\assertFalse;
use function TestHelpers\assertTrue;

$failures = 0;
$timestamp = time();

$session = SessionManager::getInstance();
$session->set('username', 'domain-group-viewer-' . $timestamp);
$session->set('user_role', 'viewer');
$_SESSION['username'] = $session->get('username');
$_SESSION['user_role'] = $session->get('user_role');

$db = DatabaseManager::getInstance();

// Seed domains
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', 'group-accessible-' . $timestamp . '.example');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleDomainId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', 'group-restricted-' . $timestamp . '.example');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedDomainId = (int) ($db->single()['id'] ?? 0);

// Seed groups
$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'Accessible Group ' . $timestamp);
$db->bind(':description', 'Accessible group for tests');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleGroupId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'Restricted Group ' . $timestamp);
$db->bind(':description', 'Restricted group for tests');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedGroupId = (int) ($db->single()['id'] ?? 0);

// Assign viewer access
$db->query('INSERT INTO user_domain_assignments (user_id, domain_id) VALUES (:user, :domain)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':domain', $accessibleDomainId);
$db->execute();

$db->query('INSERT INTO user_group_assignments (user_id, group_id) VALUES (:user, :group)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':group', $accessibleGroupId);
$db->execute();

// Unauthorized domain assignment should fail
assertFalse(
    DomainGroup::assignDomainToGroup($restrictedDomainId, $accessibleGroupId),
    'Users must not assign domains they cannot access.',
    $failures
);

// Unauthorized group assignment should fail
assertFalse(
    DomainGroup::assignDomainToGroup($accessibleDomainId, $restrictedGroupId),
    'Users must not assign domains to groups they cannot access.',
    $failures
);

// Authorized assignment should succeed
assertTrue(
    DomainGroup::assignDomainToGroup($accessibleDomainId, $accessibleGroupId),
    'Users should assign domains to accessible groups.',
    $failures
);

// Attempting to remove a restricted mapping should fail
$db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
$db->bind(':domain_id', $restrictedDomainId);
$db->bind(':group_id', $restrictedGroupId);
$db->execute();

assertFalse(
    DomainGroup::removeDomainFromGroup($restrictedDomainId, $restrictedGroupId),
    'Users must not remove assignments tied to restricted groups.',
    $failures
);

// Removing authorized assignment should succeed
assertTrue(
    DomainGroup::removeDomainFromGroup($accessibleDomainId, $accessibleGroupId),
    'Users should remove assignments they own.',
    $failures
);

// Controller validation for unauthorized assignment
MessageHelper::clearMessages();
$_POST = [
    'domain_id' => (string) $restrictedDomainId,
    'group_id' => (string) $accessibleGroupId,
];
$controller = new DomainGroupsController();
$assignMethod = new ReflectionMethod($controller, 'assignDomain');
$assignMethod->setAccessible(true);
$assignMethod->invoke($controller);
$messages = MessageHelper::getMessages();
$lastMessage = end($messages) ?: [];
assertTrue(
    ($lastMessage['type'] ?? '') === 'error',
    'Controller should report RBAC errors when assigning restricted domains.',
    $failures
);

// Controller validation for unauthorized removal
MessageHelper::clearMessages();
$_POST = [
    'domain_id' => (string) $restrictedDomainId,
    'group_id' => (string) $restrictedGroupId,
];
$removeMethod = new ReflectionMethod($controller, 'removeDomain');
$removeMethod->setAccessible(true);
$removeMethod->invoke($controller);
$messages = MessageHelper::getMessages();
$lastMessage = end($messages) ?: [];
assertTrue(
    ($lastMessage['type'] ?? '') === 'error',
    'Controller should report RBAC errors when removing restricted domains.',
    $failures
);

echo 'Domain group access control tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
