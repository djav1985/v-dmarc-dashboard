<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

namespace App\Services;

use App\Core\Mailer;
use App\Models\EmailDigest;
use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Coordinates scheduled email digest generation and delivery.
 */
class EmailDigestService
{
    /**
     * Process all enabled digest schedules that are due to run.
     *
     * @param DateTimeImmutable|null $now Optional time reference for testing.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function processDueDigests(?DateTimeImmutable $now = null): array
    {
        $referenceTime = $now ?? new DateTimeImmutable('now');
        $schedules = EmailDigest::getEnabledSchedules();
        $results = [];

        foreach ($schedules as $schedule) {
            $parsedFrequency = self::parseFrequency($schedule['frequency'] ?? '');
            if ($parsedFrequency === null) {
                continue;
            }

            if (!self::isScheduleDue($schedule, $referenceTime, $parsedFrequency)) {
                continue;
            }

            [$startDate, $endDate] = self::determinePeriod($schedule, $referenceTime, $parsedFrequency);
            $digestData = EmailDigest::generateDigestData((int) $schedule['id'], $startDate, $endDate);

            $recipients = json_decode($schedule['recipients'] ?? '[]', true) ?? [];
            $subject = self::buildSubject($schedule['name'] ?? 'DMARC Digest', $startDate, $endDate);
            $template = self::resolveTemplate($parsedFrequency['type']);
            $nextRun = self::computeNextRun($schedule, $referenceTime, $parsedFrequency);

            $success = true;
            $failedRecipients = [];
            $errorMessage = '';

            if (empty($digestData)) {
                $success = false;
                $errorMessage = 'No data available for the selected reporting period';
            } elseif (empty($recipients)) {
                $success = false;
                $errorMessage = 'No recipients configured for this digest schedule';
            } else {
                foreach ($recipients as $recipient) {
                    $sent = Mailer::sendTemplate(
                        $recipient,
                        $subject,
                        $template,
                        [
                            'subject' => $subject,
                            'digest' => $digestData,
                            'schedule' => $schedule,
                            'range_days' => $parsedFrequency['range_days'],
                        ]
                    );

                    if (!$sent) {
                        $success = false;
                        $failedRecipients[] = $recipient;
                    }
                }

                if (!empty($failedRecipients)) {
                    $errorMessage = 'Failed recipients: ' . implode(', ', $failedRecipients);
                }
            }

            EmailDigest::logDigestSend(
                (int) $schedule['id'],
                $recipients,
                $subject,
                $startDate,
                $endDate,
                $success,
                $errorMessage
            );

            $nextRunValue = $nextRun ? $nextRun->format('Y-m-d H:i:s') : null;

            EmailDigest::updateLastSent(
                (int) $schedule['id'],
                $nextRunValue,
                $success
            );

            $results[] = [
                'schedule_id' => (int) $schedule['id'],
                'success' => $success,
                'recipients' => $recipients,
                'failed_recipients' => $failedRecipients,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_run' => $nextRunValue,
                'message' => $errorMessage,
            ];
        }

        return $results;
    }

    /**
     * Break down a stored frequency string.
     */
    private static function parseFrequency(string $frequency): ?array
    {
        $parts = explode(':', $frequency, 2);
        $type = $parts[0] ?? '';
        $customDays = null;

        if ($type === 'custom' && isset($parts[1]) && is_numeric($parts[1])) {
            $customDays = max(1, (int) $parts[1]);
        }

        $rangeDays = match ($type) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'custom' => $customDays ?? 7,
            default => null,
        };

        if ($rangeDays === null) {
            return null;
        }

        return [
            'type' => $type,
            'custom_days' => $customDays,
            'range_days' => $rangeDays,
        ];
    }

    /**
     * Determine whether a schedule should run at the current time.
     */
    private static function isScheduleDue(array $schedule, DateTimeImmutable $now, array $parsed): bool
    {
        if ((int) ($schedule['enabled'] ?? 0) !== 1) {
            return false;
        }

        if (!empty($schedule['next_scheduled'])) {
            try {
                $next = new DateTimeImmutable($schedule['next_scheduled']);
                return $next <= $now;
            } catch (Throwable $exception) {
                // If parsing fails, treat it as due immediately.
                return true;
            }
        }

        if (!empty($schedule['last_sent'])) {
            try {
                $lastSent = new DateTimeImmutable($schedule['last_sent']);
            } catch (Throwable $exception) {
                return true;
            }

            $interval = self::buildInterval($parsed);
            return $lastSent->add($interval) <= $now;
        }

        // No prior runs or scheduling information — run immediately.
        return true;
    }

    /**
     * Compute the next scheduled runtime for a digest after the current execution.
     */
    private static function computeNextRun(array $schedule, DateTimeImmutable $now, array $parsed): ?DateTimeImmutable
    {
        $interval = self::buildInterval($parsed);

        if (!empty($schedule['next_scheduled'])) {
            try {
                $existing = new DateTimeImmutable($schedule['next_scheduled']);
                if ($existing <= $now) {
                    return $now->add($interval);
                }
                return $existing->add($interval);
            } catch (Throwable $exception) {
                // Fall through to default scheduling below.
            }
        }

        if (!empty($schedule['last_sent'])) {
            try {
                $lastSent = new DateTimeImmutable($schedule['last_sent']);
                return $lastSent->add($interval);
            } catch (Throwable $exception) {
                // Default to now-based scheduling
            }
        }

        return $now->add($interval);
    }

    /**
     * Determine the reporting window for a digest run.
     */
    private static function determinePeriod(array $schedule, DateTimeImmutable $now, array $parsed): array
    {
        $endDate = $now->format('Y-m-d');
        $cadenceFloor = self::defaultStartReference($now, $parsed);

        if (!empty($schedule['last_sent'])) {
            try {
                $lastSent = new DateTimeImmutable($schedule['last_sent']);
                $startReference = $lastSent->add(new DateInterval('P1D'));
                if ($startReference < $cadenceFloor) {
                    $startReference = $cadenceFloor;
                }
            } catch (Throwable $exception) {
                $startReference = $cadenceFloor;
            }
        } else {
            $startReference = $cadenceFloor;
        }

        if ($startReference > $now) {
            $startReference = $now;
        }

        return [$startReference->format('Y-m-d'), $endDate];
    }

    /**
     * Resolve the template filename for a digest frequency.
     */
    private static function resolveTemplate(string $type): string
    {
        return match ($type) {
            'daily' => 'digest_daily',
            'weekly' => 'digest_weekly',
            'monthly' => 'digest_monthly',
            'custom' => 'digest_custom',
            default => 'digest_weekly',
        };
    }

    /**
     * Build the next-run subject line.
     */
    private static function buildSubject(string $name, string $startDate, string $endDate): string
    {
        return sprintf('DMARC Digest: %s (%s - %s)', $name, $startDate, $endDate);
    }

    /**
     * Translate parsed frequency metadata into a DateInterval.
     */
    private static function buildInterval(array $parsed): DateInterval
    {
        return match ($parsed['type']) {
            'daily' => new DateInterval('P1D'),
            'weekly' => new DateInterval('P7D'),
            'monthly' => new DateInterval('P1M'),
            'custom' => new DateInterval('P' . max(1, (int) ($parsed['custom_days'] ?? $parsed['range_days'])) . 'D'),
            default => new DateInterval('P1D'),
        };
    }

    /**
     * Fallback start-date reference when none is stored.
     */
    private static function defaultStartReference(DateTimeImmutable $now, array $parsed): DateTimeImmutable
    {
        if ($parsed['type'] === 'monthly') {
            return $now->sub(new DateInterval('P1M'));
        }

        $days = max(0, $parsed['range_days'] - 1);
        return $now->sub(new DateInterval('P' . $days . 'D'));
    }
}
