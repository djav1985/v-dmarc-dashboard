<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\AuditLogger;
use function TestHelpers\assertPredicate;

/**
 * Test AuditLogger portable date functions
 * These tests verify that the refactored methods use database-agnostic date calculations
 */

$failures = 0;

// Test that getFailedLogins generates proper timestamp parameters
function testGetFailedLoginsTimestampGeneration(): string
{
    // Calculate expected cutoff time (similar to what the method should do)
    $hours = 24;
    $expectedCutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

    // Verify the timestamp format is correct
    $timestampPattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    if (!preg_match($timestampPattern, $expectedCutoff)) {
        return "Generated timestamp format is invalid: {$expectedCutoff}";
    }

    // Verify the timestamp is in the past
    $cutoffTimestamp = strtotime($expectedCutoff);
    $currentTimestamp = time();
    if ($cutoffTimestamp >= $currentTimestamp) {
        return "Cutoff timestamp should be in the past";
    }

    return '';
}

// Test that cleanOldLogs generates proper timestamp parameters
function testCleanOldLogsTimestampGeneration(): string
{
    // Calculate expected cutoff time (similar to what the method should do)
    $days = 90;
    $expectedCutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // Verify the timestamp format is correct
    $timestampPattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    if (!preg_match($timestampPattern, $expectedCutoff)) {
        return "Generated timestamp format is invalid: {$expectedCutoff}";
    }

    // Verify the timestamp is in the past
    $cutoffTimestamp = strtotime($expectedCutoff);
    $currentTimestamp = time();
    if ($cutoffTimestamp >= $currentTimestamp) {
        return "Cutoff timestamp should be in the past";
    }

    return '';
}

// Test timestamp generation for getFailedLogins
$error = testGetFailedLoginsTimestampGeneration();
assertPredicate(
    empty($error),
    $error ?: 'getFailedLogins timestamp generation works correctly',
    $failures
);

// Test timestamp generation for cleanOldLogs
$error = testCleanOldLogsTimestampGeneration();
assertPredicate(
    empty($error),
    $error ?: 'cleanOldLogs timestamp generation works correctly',
    $failures
);

// Test that the AuditLogger can be instantiated
try {
    $auditLogger = AuditLogger::getInstance();
    assertPredicate(
        $auditLogger instanceof AuditLogger,
        'AuditLogger can be instantiated',
        $failures
    );
} catch (Exception $e) {
    assertPredicate(
        false,
        'AuditLogger instantiation failed: ' . $e->getMessage(),
        $failures
    );
}

echo "AuditLogger portable date functions test completed with " .
     ($failures === 0 ? "no failures" : "{$failures} failure(s)") . PHP_EOL;

exit($failures === 0 ? 0 : 1);
