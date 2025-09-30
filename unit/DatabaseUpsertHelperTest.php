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
use App\Models\Blacklist;
use App\Utilities\DataRetention;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

/**
 * Lightweight stub that avoids real database connections while recording issued statements.
 */
class DatabaseManagerUpsertStub extends DatabaseManager
{
    public array $queries = [];
    public array $bindings = [];
    public bool $executed = false;
    private string $driver;

    public function __construct(string $driver)
    {
        $this->driver = $driver;
    }

    public function setDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    public function query(string $sql): void
    {
        $this->queries[] = $sql;
        $this->bindings = [];
        $this->executed = false;
    }

    public function bind(string $param, $value, ?int $type = null): void
    {
        $this->bindings[ltrim($param, ':')] = $value;
    }

    public function execute(): bool
    {
        $this->executed = true;
        return true;
    }

    public function getDriverName(): string
    {
        return $this->driver;
    }
}

function normalize_sql(string $sql): string
{
    return preg_replace('/\s+/', ' ', trim($sql));
}

$failures = 0;

$timestamp = 1700000000;

$stub = new DatabaseManagerUpsertStub('pdo_sqlite');
$upsertSqlite = $stub->buildUpsertQuery(
    'ip_blacklist',
    [
        'ip_address' => '203.0.113.10',
        'login_attempts' => 0,
        'blacklisted' => true,
        'timestamp' => $timestamp,
    ],
    [
        'blacklisted' => DatabaseManager::useInsertValue('blacklisted'),
        'timestamp' => DatabaseManager::useInsertValue('timestamp'),
    ],
    'ip_address'
);

assertEquals(
    'INSERT INTO ip_blacklist (ip_address, login_attempts, blacklisted, timestamp) VALUES (:ip_address, :login_attempts, :blacklisted, :timestamp) ON CONFLICT (ip_address) DO UPDATE SET blacklisted = :blacklisted, timestamp = :timestamp',
    normalize_sql($upsertSqlite['sql']),
    'SQLite UPSERT query should include ON CONFLICT clause with placeholders reused.',
    $failures
);
assertEquals(
    [
        'ip_address' => '203.0.113.10',
        'login_attempts' => 0,
        'blacklisted' => true,
        'timestamp' => $timestamp,
    ],
    $upsertSqlite['bindings'],
    'SQLite UPSERT bindings should include insert placeholders.',
    $failures
);

$stub->setDriver('pdo_mysql');
$upsertMysql = $stub->buildUpsertQuery(
    'retention_settings',
    [
        'setting_name' => 'aggregate_reports_retention_days',
        'setting_value' => '7',
    ],
    [
        'setting_value' => DatabaseManager::useInsertValue('setting_value'),
        'updated_at' => DatabaseManager::rawExpression([
            'sqlite' => 'CURRENT_TIMESTAMP',
            'mysql' => 'NOW()',
        ]),
    ],
    'setting_name'
);

assertEquals(
    'INSERT INTO retention_settings (setting_name, setting_value) VALUES (:setting_name, :setting_value) ON DUPLICATE KEY UPDATE setting_value = :setting_value, updated_at = NOW()',
    normalize_sql($upsertMysql['sql']),
    'MySQL UPSERT query should include ON DUPLICATE KEY UPDATE clause with NOW().',
    $failures
);
assertEquals(
    [
        'setting_name' => 'aggregate_reports_retention_days',
        'setting_value' => '7',
    ],
    $upsertMysql['bindings'],
    'MySQL UPSERT bindings should include insert placeholders only.',
    $failures
);

DatabaseManager::setInstanceForTesting($stub);
$stub->setDriver('pdo_sqlite');

assertTrue(Blacklist::banIp('198.51.100.42'), 'banIp should return true when helper executes successfully.', $failures);
assertEquals(1, count($stub->queries), 'banIp should issue a single UPSERT query.', $failures);
assertEquals(
    'INSERT INTO ip_blacklist (ip_address, login_attempts, blacklisted, timestamp) VALUES (:ip_address, :login_attempts, :blacklisted, :timestamp) ON CONFLICT (ip_address) DO UPDATE SET blacklisted = :blacklisted, timestamp = :timestamp',
    normalize_sql($stub->queries[0]),
    'banIp should use the shared helper for SQLite syntax.',
    $failures
);
assertEquals('198.51.100.42', $stub->bindings['ip_address'] ?? null, 'banIp should bind the requested IP address.', $failures);
assertEquals(0, $stub->bindings['login_attempts'] ?? null, 'banIp should reset login attempts on insert.', $failures);
assertEquals(true, $stub->bindings['blacklisted'] ?? null, 'banIp should persist a TRUE blacklisted flag.', $failures);
assertTrue(
    isset($stub->bindings['timestamp']) && is_int($stub->bindings['timestamp']),
    'banIp should bind a numeric timestamp.',
    $failures
);
assertTrue($stub->executed, 'banIp should trigger execute on the stub.', $failures);

$stub->queries = [];
$stub->bindings = [];
$stub->executed = false;
$stub->setDriver('pdo_mysql');

assertTrue(
    DataRetention::updateRetentionSetting('tls_reports_retention_days', '14'),
    'updateRetentionSetting should return true when helper executes successfully.',
    $failures
);
assertEquals(1, count($stub->queries), 'updateRetentionSetting should issue a single UPSERT query.', $failures);
assertEquals(
    'INSERT INTO retention_settings (setting_name, setting_value) VALUES (:setting_name, :setting_value) ON DUPLICATE KEY UPDATE setting_value = :setting_value, updated_at = NOW()',
    normalize_sql($stub->queries[0]),
    'updateRetentionSetting should use the shared helper for MySQL syntax.',
    $failures
);
assertEquals('tls_reports_retention_days', $stub->bindings['setting_name'] ?? null, 'updateRetentionSetting should bind the setting name.', $failures);
assertEquals('14', $stub->bindings['setting_value'] ?? null, 'updateRetentionSetting should bind the new setting value.', $failures);
assertTrue($stub->executed, 'updateRetentionSetting should trigger execute on the stub.', $failures);

DatabaseManager::setInstanceForTesting(null);

echo 'Database UPSERT helper coverage completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
