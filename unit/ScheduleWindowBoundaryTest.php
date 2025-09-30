<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

$autoloadPath = __DIR__ . '/../root/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    require __DIR__ . '/../root/app/Services/PdfReportScheduler.php';
    require __DIR__ . '/../root/app/Services/EmailDigestService.php';
}
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Services\EmailDigestService;
use App\Services\PdfReportScheduler;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

/**
 * Invoke a private static method for testing via reflection.
 *
 * @param class-string $class
 * @param string $method
 * @param array<int, mixed> $arguments
 *
 * @return mixed
 */
function invoke_private_static(string $class, string $method, array $arguments = [])
{
    $reflection = new ReflectionClass($class);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs(null, $arguments);
}

$failures = 0;

// Ensure PDF schedules advance from the day after the prior run while staying within the cadence window.
$dailyParsed = ['type' => 'daily', 'range_days' => 1, 'custom_days' => null];
$dailySchedule = ['last_run_at' => '2024-04-14 08:00:00'];
$dailyNow = new DateTimeImmutable('2024-04-15 09:30:00');
$dailyPeriod = invoke_private_static(PdfReportScheduler::class, 'determinePeriod', [$dailySchedule, $dailyNow, $dailyParsed]);
assertEquals('2024-04-15', $dailyPeriod[0], 'Daily scheduler should begin the day after the last run.', $failures);
assertEquals('2024-04-15', $dailyPeriod[1], 'Daily scheduler should end on the current day.', $failures);

$weeklyParsed = ['type' => 'weekly', 'range_days' => 7, 'custom_days' => null];
$weeklySchedule = ['last_run_at' => '2024-03-01 00:00:00'];
$weeklyNow = new DateTimeImmutable('2024-04-15 12:00:00');
$weeklyPeriod = invoke_private_static(PdfReportScheduler::class, 'determinePeriod', [$weeklySchedule, $weeklyNow, $weeklyParsed]);
assertEquals('2024-04-09', $weeklyPeriod[0], 'Weekly scheduler should clamp start within the cadence window.', $failures);
assertEquals('2024-04-15', $weeklyPeriod[1], 'Weekly scheduler end date should match the evaluation date.', $failures);

// Email digests should mirror the same boundary behaviour when resuming from a prior send.
$digestSchedule = ['last_sent' => '2024-04-10 10:45:00'];
$digestNow = new DateTimeImmutable('2024-04-12 07:00:00');
$digestPeriod = invoke_private_static(EmailDigestService::class, 'determinePeriod', [$digestSchedule, $digestNow, $weeklyParsed]);
assertEquals('2024-04-11', $digestPeriod[0], 'Email digest should start on the day after the last dispatch.', $failures);
assertEquals('2024-04-12', $digestPeriod[1], 'Email digest should end on the evaluation day.', $failures);

$staleDigestSchedule = ['last_sent' => '2023-12-01 05:00:00'];
$staleDigestNow = new DateTimeImmutable('2024-04-15 00:00:00');
$staleDigestPeriod = invoke_private_static(EmailDigestService::class, 'determinePeriod', [$staleDigestSchedule, $staleDigestNow, $weeklyParsed]);
assertEquals('2024-04-09', $staleDigestPeriod[0], 'Stale digests should clamp to the cadence window when catching up.', $failures);
assertEquals('2024-04-15', $staleDigestPeriod[1], 'Stale digest end date should match the evaluation day.', $failures);

try {
    $invalidDigestSchedule = ['last_sent' => 'not-a-date'];
    $invalidDigestPeriod = invoke_private_static(EmailDigestService::class, 'determinePeriod', [$invalidDigestSchedule, $digestNow, $weeklyParsed]);
    assertTrue(is_array($invalidDigestPeriod), 'Invalid timestamps should fall back to cadence defaults.', $failures);
    assertEquals('2024-04-06', $invalidDigestPeriod[0], 'Invalid last_sent should use cadence fallback start.', $failures);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unexpected exception while testing invalid digest timestamps: ' . $exception->getMessage() . PHP_EOL);
    $failures++;
}

echo 'Schedule window boundary tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
