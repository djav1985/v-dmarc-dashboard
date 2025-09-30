<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: TestHelpers.php
 * Description: Shared test assertion helpers to avoid function redeclaration conflicts.
 */

declare(strict_types=1);

/**
 * All assertion helpers are placed in the TestHelpers namespace to prevent
 * "Cannot redeclare function" fatal errors when PHPUnit loads multiple test
 * files in the same process.
 */

namespace TestHelpers;

/**
 * Simple assertion helper that records failures and reports a helpful message.
 */
function assertTrue(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function assertFalse(bool $condition, string $message, int &$failures): void
{
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function assertEquals($expected, $actual, string $message, int &$failures): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . ' Expected ' . var_export($expected, true) .
            ' got ' . var_export($actual, true) . PHP_EOL
        );
        $failures++;
    }
}

function assertCountEquals(int $expected, array $actual, string $message, int &$failures): void
{
    if (count($actual) !== $expected) {
        fwrite(STDERR, $message . ' Expected count ' . $expected . ' got ' . count($actual) . PHP_EOL);
        $failures++;
    }
}

function assertContains(string $needle, string $haystack, string $message, int &$failures): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function assertEqual(string $expected, string $actual, string $message, int &$failures): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . " Expected: '$expected', Got: '$actual'" . PHP_EOL);
        $failures++;
    }
}

function assertPredicate(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}