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
use App\Models\Users;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$db = DatabaseManager::getInstance();

$username = 'user-default-role-' . bin2hex(random_bytes(4));

$userCreated = Users::createUser([
    'username' => $username,
    'password' => password_hash('secret', PASSWORD_BCRYPT),
    'first_name' => 'Viewer',
    'last_name' => 'Default',
    'email' => $username . '@example.com',
]);

assertTrue($userCreated, 'User creation without explicit role should succeed.', $failures);

$db->query('SELECT role, admin FROM users WHERE username = :username');
$db->bind(':username', $username);
$row = $db->single() ?: [];

assertEquals(RBACManager::ROLE_VIEWER, $row['role'] ?? null, 'Stored role should default to viewer.', $failures);
assertEquals(0, (int) ($row['admin'] ?? -1), 'Admin flag should remain disabled for viewer default.', $failures);

echo 'Users model default role test completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
