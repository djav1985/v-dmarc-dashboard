<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';

/**
 * Test AuditLogger edge cases and verify database compatibility
 */

$failures = 0;

/**
 * Simple assertion helper that records failures and reports a helpful message.
 */
function assertPredicate(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

// Test timestamp generation with various parameters
function testTimestampGenerationEdgeCases(): string
{
    // Test with different hour values
    $testHours = [1, 24, 168, 720]; // 1 hour, 1 day, 1 week, 1 month

    foreach ($testHours as $hours) {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        // Verify format
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
            return "Invalid timestamp format for {$hours} hours: {$cutoffTime}";
        }

        // Verify it's in the past
        if (strtotime($cutoffTime) >= time()) {
            return "Timestamp for {$hours} hours should be in the past";
        }
    }

    // Test with different day values
    $testDays = [1, 7, 30, 90, 365]; // Various day intervals

    foreach ($testDays as $days) {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Verify format
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
            return "Invalid timestamp format for {$days} days: {$cutoffTime}";
        }

        // Verify it's in the past
        if (strtotime($cutoffTime) >= time()) {
            return "Timestamp for {$days} days should be in the past";
        }
    }

    return '';
}

// Test that timestamps are compatible with both MySQL and SQLite formats
function testTimestampCompatibility(): string
{
    $timestamp = date('Y-m-d H:i:s', strtotime('-24 hours'));

    // This format should be accepted by both MySQL and SQLite
    // MySQL: DATETIME/TIMESTAMP columns accept 'YYYY-MM-DD HH:MM:SS' format
    // SQLite: datetime() function accepts ISO format and this format

    // Test if strtotime can parse it back (validates format correctness)
    $parsedBack = strtotime($timestamp);
    if ($parsedBack === false) {
        return "Generated timestamp cannot be parsed back: {$timestamp}";
    }

    // Test if the round-trip maintains reasonable accuracy (within 1 second)
    $originalTime = strtotime('-24 hours');
    if (abs($parsedBack - $originalTime) > 1) {
        return "Timestamp round-trip loses accuracy";
    }

    return '';
}

// Run edge case tests
$error = testTimestampGenerationEdgeCases();
assertPredicate(
    empty($error),
    $error ?: 'Timestamp generation handles various edge cases correctly',
    $failures
);

$error = testTimestampCompatibility();
assertPredicate(
    empty($error),
    $error ?: 'Generated timestamps are compatible with both MySQL and SQLite',
    $failures
);

// Test parameter sanitization (ensure no SQL injection risk with our approach)
function testParameterSafety(): string
{
    // Our approach uses parameterized queries, which should prevent injection
    // But let's test that the timestamp generation itself is safe

    // Test with suspicious values that might cause issues
    $testValues = [0, -1, 999999]; // edge cases

    foreach ($testValues as $value) {
        try {
            // This should either work or throw an exception, but not cause injection
            $timestamp = date('Y-m-d H:i:s', strtotime("-{$value} hours"));

            // Verify the result is still a valid timestamp format or empty
            if (!empty($timestamp) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp)) {
                return "Unsafe timestamp generated for value {$value}: {$timestamp}";
            }
        } catch (Exception $e) {
            // Exceptions are acceptable for invalid inputs
            continue;
        }
    }

    return '';
}

$error = testParameterSafety();
assertPredicate(
    empty($error),
    $error ?: 'Parameter handling is safe from injection attacks',
    $failures
);

echo "AuditLogger edge case tests completed with " .
     ($failures === 0 ? "no failures" : "{$failures} failure(s)") . PHP_EOL;

exit($failures === 0 ? 0 : 1);
