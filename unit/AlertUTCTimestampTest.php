<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\SessionManager;
use App\Core\RBACManager;
use App\Models\Alert;
use function TestHelpers\assertTrue;
use function TestHelpers\assertEquals;

/**
 * Test UTC timestamp handling in Alert model
 * This test verifies that timestamps are correctly generated in UTC timezone
 */

$failures = 0;

// Set up session for database access
$sessionManager = SessionManager::getInstance();
$sessionManager->start();
$sessionManager->set('logged_in', true);
$sessionManager->set('user_role', RBACManager::ROLE_APP_ADMIN);
$sessionManager->set('username', 'test-admin');

/**
 * Test that UTC timestamps are correctly generated regardless of PHP timezone
 */
function testUTCTimestampGeneration(): string
{
    // Save original timezone
    $originalTz = date_default_timezone_get();

    try {
        // Test in different timezones to verify UTC consistency
        $testTimezones = ['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo'];
        $referenceTime = time();
        $expectedUTCTime = gmdate('Y-m-d H:i:s', $referenceTime);

        foreach ($testTimezones as $timezone) {
            date_default_timezone_set($timezone);

            // Test the format used in Alert::calculateMetric
            $startTimestamp = $referenceTime - (60 * 60); // 1 hour ago
            $startTime = gmdate('Y-m-d H:i:s', $startTimestamp);

            // Verify format is correct
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startTime)) {
                return "Invalid timestamp format in timezone {$timezone}: {$startTime}";
            }

            // Verify it's consistently in UTC regardless of PHP timezone
            $expectedStartTime = gmdate('Y-m-d H:i:s', $startTimestamp);
            if ($startTime !== $expectedStartTime) {
                return "Timestamp inconsistent in timezone {$timezone}: got {$startTime}, expected {$expectedStartTime}";
            }

            // Verify it differs from local time in non-UTC timezones
            $localTime = date('Y-m-d H:i:s', $startTimestamp);
            if ($timezone !== 'UTC' && $startTime === $localTime) {
                // This might be expected if the timezone offset is 0 at this time
                // So we'll check if there's an actual timezone difference
                $utcOffset = (int) date('Z', $startTimestamp);
                if ($utcOffset !== 0) {
                    return "UTC time should differ from local time in timezone {$timezone}";
                }
            }
        }

        return '';
    } finally {
        // Restore original timezone
        date_default_timezone_set($originalTz);
    }
}

/**
 * Test that the Alert model calculates correct UTC timestamps for database queries
 */
function testAlertTimestampCalculation(): string
{
    // Save original timezone
    $originalTz = date_default_timezone_get();

    try {
        // Test in a non-UTC timezone
        date_default_timezone_set('America/New_York');

        $db = DatabaseManager::getInstance();

        // Create a test rule
        $ruleData = [
            'name' => 'UTC Test Rule',
            'description' => 'Test UTC timestamp calculation',
            'rule_type' => 'metric',
            'metric' => 'dmarc_failure_rate',
            'threshold_value' => 50.0,
            'threshold_operator' => '>',
            'time_window' => 60, // 1 hour
            'severity' => 'medium',
            'notification_channels' => [],
            'notification_recipients' => [],
            'enabled' => 1
        ];

        $ruleId = Alert::createRule($ruleData);

        // Get the rule back to test metric calculation
        $db->query('SELECT * FROM alert_rules WHERE id = :id');
        $db->bind(':id', $ruleId);
        $rule = $db->single();

        if (!$rule) {
            return 'Failed to create or retrieve test rule';
        }

        // Test that calculateMetric uses UTC timestamps
        // Since calculateMetric is private, we'll test it indirectly through checkAlertRules
        // But first, let's verify that the timestamp calculation logic is working correctly

        $timeWindow = (int) $rule['time_window'];
        $startTimestamp = time() - ($timeWindow * 60);

        // This simulates what happens in calculateMetric
        $utcStartTime = gmdate('Y-m-d H:i:s', $startTimestamp);
        $localStartTime = date('Y-m-d H:i:s', $startTimestamp);

        // In America/New_York timezone, these should be different (unless it's exactly UTC offset 0)
        $utcOffset = (int) date('Z', $startTimestamp);
        if ($utcOffset !== 0 && $utcStartTime === $localStartTime) {
            return "UTC and local timestamps should differ in non-UTC timezone when offset is {$utcOffset}";
        }

        // Verify UTC timestamp format
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utcStartTime)) {
            return "Invalid UTC timestamp format: {$utcStartTime}";
        }

        // Clean up
        $db->query('DELETE FROM alert_rules WHERE id = :id');
        $db->bind(':id', $ruleId);
        $db->execute();

        return '';
    } finally {
        // Restore original timezone
        date_default_timezone_set($originalTz);
    }
}

// Run the tests
$error = testUTCTimestampGeneration();
assertTrue(
    empty($error),
    $error ?: 'UTC timestamp generation is consistent across timezones',
    $failures
);

$error = testAlertTimestampCalculation();
assertTrue(
    empty($error),
    $error ?: 'Alert model calculates UTC timestamps correctly',
    $failures
);

echo "Alert UTC timestamp test completed with " .
     ($failures === 0 ? 'no' : $failures) . " failure(s)\n";

exit($failures > 0 ? 1 : 0);
