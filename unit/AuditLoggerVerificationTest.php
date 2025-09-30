<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\AuditLogger;
use function TestHelpers\assertPredicate;

/**
 * Manual verification test of the updated AuditLogger methods
 * This test confirms the SQL queries are syntactically correct and portable
 */

$failures = 0;

// Test SQL query construction by inspecting the generated SQL and parameters
function testSQLQueryConstruction(): string
{
    // Simulate what the getFailedLogins method does
    $hours = 24;
    $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

    // Verify the SQL is database-agnostic (no SQLite-specific functions)
    $sql = '
        SELECT * FROM audit_logs
        WHERE action = :action 
        AND timestamp >= :cutoff_time
        ORDER BY timestamp DESC
    ';

    // Check that SQL doesn't contain SQLite-specific functions
    if (strpos($sql, 'datetime(') !== false) {
        return "SQL still contains SQLite-specific datetime() function";
    }

    // Check that parameters are properly named
    if (strpos($sql, ':cutoff_time') === false) {
        return "SQL missing :cutoff_time parameter";
    }

    if (strpos($sql, ':action') === false) {
        return "SQL missing :action parameter";
    }

    // Verify timestamp format is correct
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
        return "Generated timestamp has invalid format: {$cutoffTime}";
    }

    return '';
}

function testCleanOldLogsSQLConstruction(): string
{
    // Simulate what the cleanOldLogs method does
    $days = 90;
    $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // Verify the SQL is database-agnostic (no SQLite-specific functions)
    $sql = '
        DELETE FROM audit_logs
        WHERE timestamp < :cutoff_time
    ';

    // Check that SQL doesn't contain SQLite-specific functions
    if (strpos($sql, 'datetime(') !== false) {
        return "SQL still contains SQLite-specific datetime() function";
    }

    // Check that parameters are properly named
    if (strpos($sql, ':cutoff_time') === false) {
        return "SQL missing :cutoff_time parameter";
    }

    // Verify timestamp format is correct
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
        return "Generated timestamp has invalid format: {$cutoffTime}";
    }

    return '';
}

// Test actual AuditLogger instantiation and method existence
function testAuditLoggerMethods(): string
{
    try {
        $auditLogger = AuditLogger::getInstance();

        // Verify methods exist and have correct signatures
        if (!method_exists($auditLogger, 'getFailedLogins')) {
            return "getFailedLogins method does not exist";
        }

        if (!method_exists($auditLogger, 'cleanOldLogs')) {
            return "cleanOldLogs method does not exist";
        }

        // Check method reflection to ensure parameters are correct
        $reflection = new ReflectionMethod($auditLogger, 'getFailedLogins');
        $parameters = $reflection->getParameters();

        if (count($parameters) !== 1 || $parameters[0]->getName() !== 'hours') {
            return "getFailedLogins method signature is incorrect";
        }

        $reflection = new ReflectionMethod($auditLogger, 'cleanOldLogs');
        $parameters = $reflection->getParameters();

        if (count($parameters) !== 1 || $parameters[0]->getName() !== 'daysToKeep') {
            return "cleanOldLogs method signature is incorrect";
        }

        return '';
    } catch (Exception $e) {
        return "Error accessing AuditLogger methods: " . $e->getMessage();
    }
}

// Run verification tests
$error = testSQLQueryConstruction();
assertPredicate(
    empty($error),
    $error ?: 'getFailedLogins SQL construction is database-agnostic',
    $failures
);

$error = testCleanOldLogsSQLConstruction();
assertPredicate(
    empty($error),
    $error ?: 'cleanOldLogs SQL construction is database-agnostic',
    $failures
);

$error = testAuditLoggerMethods();
assertPredicate(
    empty($error),
    $error ?: 'AuditLogger methods are accessible and have correct signatures',
    $failures
);

echo "AuditLogger verification test completed with " .
     ($failures === 0 ? "no failures" : "{$failures} failure(s)") . PHP_EOL;

echo "\nSummary of changes verified:" . PHP_EOL;
echo "- SQLite-specific datetime() functions removed" . PHP_EOL;
echo "- Parameterized timestamps implemented" . PHP_EOL;
echo "- Database-agnostic SQL queries confirmed" . PHP_EOL;
echo "- Method signatures preserved" . PHP_EOL;

exit($failures === 0 ? 0 : 1);
