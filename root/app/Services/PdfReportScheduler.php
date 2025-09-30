<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

namespace App\Services;

use App\Core\Mailer;
use App\Models\PdfReport;
use App\Models\PdfReportSchedule;
use App\Models\PdfReportSchedulerHelper;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Coordinates execution of scheduled PDF report exports and notifications.
 */
class PdfReportScheduler
{
    /**
     * Evaluate enabled schedules and execute those due for generation.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function processDueSchedules(?DateTimeImmutable $now = null): array
    {
        $reference = $now ?? new DateTimeImmutable('now');
        $schedules = PdfReportSchedule::getEnabledSchedules();
        $results = [];

        foreach ($schedules as $schedule) {
            $parsed = PdfReportSchedule::parseFrequency($schedule['frequency'] ?? '');
            if ($parsed === null) {
                continue;
            }

            if (!PdfReportSchedule::isDue($schedule, $reference, $parsed)) {
                continue;
            }

            $results[] = self::executeSchedule($schedule, $reference, $parsed);
        }

        return $results;
    }

    public static function runScheduleNow(int $scheduleId, ?DateTimeImmutable $now = null): ?array
    {
        $schedule = PdfReportSchedule::find($scheduleId);
        if (!$schedule) {
            return null;
        }

        $parsed = PdfReportSchedule::parseFrequency($schedule['frequency'] ?? '');
        if ($parsed === null) {
            throw new RuntimeException('Schedule has an invalid cadence definition.');
        }

        return self::executeSchedule($schedule, $now ?? new DateTimeImmutable('now'), $parsed);
    }

    /**
     * Break down JSON encoded recipient field.
     *
     * @return array<int,string>
     */
    private static function parseRecipients(string $encoded): array
    {
        $decoded = json_decode($encoded, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $decoded), static function ($value): bool {
            return $value !== '';
        }));
    }

    /**
     * Determine the reporting window for a schedule execution.
     *
     * @param array<string,mixed> $schedule
     * @param array<string,mixed> $parsed
     *
     * @return array{0:string,1:string}
     */
    private static function determinePeriod(array $schedule, DateTimeImmutable $now, array $parsed): array
    {
        $endDate = $now->format('Y-m-d');

        $cadenceFloor = $parsed['type'] === 'monthly'
            ? $now->sub(new DateInterval('P1M'))
            : $now->sub(new DateInterval('P' . max(0, $parsed['range_days'] - 1) . 'D'));

        if (!empty($schedule['last_run_at'])) {
            try {
                $lastRun = new DateTimeImmutable($schedule['last_run_at']);
                $start = $lastRun->add(new DateInterval('P1D'));
                if ($start < $cadenceFloor) {
                    $start = $cadenceFloor;
                }
                if ($start > $now) {
                    $start = $now;
                }

                return [$start->format('Y-m-d'), $endDate];
            } catch (Throwable $exception) {
                // fall through to default handling
            }
        }

        return [$cadenceFloor->format('Y-m-d'), $endDate];
    }

    /**
     * Calculate the next execution time.
     *
     * @param array<string,mixed> $schedule
     * @param array<string,mixed> $parsed
     */
    private static function computeNextRun(array $schedule, DateTimeImmutable $now, array $parsed): ?DateTimeImmutable
    {
        $interval = PdfReportSchedulerHelper::buildInterval($parsed);

        if (!empty($schedule['next_run_at'])) {
            try {
                $existing = new DateTimeImmutable($schedule['next_run_at']);
                if ($existing <= $now) {
                    return $now->add($interval);
                }

                return $existing;
            } catch (Throwable $exception) {
                // continue to default path
            }
        }

        if (!empty($schedule['last_run_at'])) {
            try {
                $last = new DateTimeImmutable($schedule['last_run_at']);
                return $last->add($interval);
            } catch (Throwable $exception) {
                // default to now
            }
        }

        return $now->add($interval);
    }

    /**
     * Perform generation and delivery for a single schedule instance.
     *
     * @param array<string,mixed> $schedule
     * @param array<string,mixed> $parsed
     *
     * @return array<string,mixed>
     */
    private static function executeSchedule(array $schedule, DateTimeImmutable $reference, array $parsed): array
    {
        [$startDate, $endDate] = self::determinePeriod($schedule, $reference, $parsed);
        $recipients = self::parseRecipients($schedule['recipients'] ?? '[]');
        $title = $schedule['title'] ?? $schedule['name'] ?? 'DMARC Report';
        $success = false;
        $message = '';
        $generationId = null;

        try {
            if (empty($recipients)) {
                throw new RuntimeException('No recipients configured for scheduled PDF report.');
            }

            $reportData = PdfReport::generateReportData(
                (int) $schedule['template_id'],
                $startDate,
                $endDate,
                $schedule['domain_filter'] ?? '',
                isset($schedule['group_filter']) ? (int) $schedule['group_filter'] : null
            );

            if (empty($reportData)) {
                throw new RuntimeException('Report template returned no data for the requested period.');
            }

            $generation = PdfReportService::generatePdf(
                $reportData,
                $title,
                [
                    'output_directory' => defined('PDF_REPORT_STORAGE_PATH') ? PDF_REPORT_STORAGE_PATH : null,
                    'prefix' => 'schedule-' . ($schedule['id'] ?? 'report'),
                ]
            );

            $generationId = PdfReport::logGeneration([
                'template_id' => (int) $schedule['template_id'],
                'filename' => $generation['filename'],
                'file_path' => $generation['relative_path'],
                'title' => $title,
                'date_range_start' => $startDate,
                'date_range_end' => $endDate,
                'domain_filter' => $schedule['domain_filter'] ?? '',
                'group_filter' => $schedule['group_filter'] ?? null,
                'parameters' => [
                    'schedule_id' => $schedule['id'] ?? null,
                    'schedule' => $schedule,
                ],
                'file_size' => $generation['size'],
                'generated_by' => $schedule['created_by'] ?? 'Scheduler',
                'schedule_id' => $schedule['id'] ?? null,
            ]);

            $subject = sprintf('Scheduled DMARC PDF: %s (%s - %s)', $title, $startDate, $endDate);

            $failedRecipients = [];
            foreach ($recipients as $recipient) {
                $sent = Mailer::sendTemplate(
                    $recipient,
                    $subject,
                    'pdf_report_notification',
                    [
                        'subject' => $subject,
                        'schedule' => $schedule,
                        'period_start' => $startDate,
                        'period_end' => $endDate,
                        'generation' => [
                            'id' => $generationId,
                            'filename' => $generation['filename'],
                            'file_path' => $generation['relative_path'],
                            'size' => $generation['size'],
                        ],
                    ]
                );

                if (!$sent) {
                    $failedRecipients[] = $recipient;
                }
            }

            if (!empty($failedRecipients)) {
                throw new RuntimeException('Failed recipients: ' . implode(', ', $failedRecipients));
            }

            $success = true;
            $message = 'Report generated and delivered to recipients.';
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
        }

        $nextRun = self::computeNextRun($schedule, $reference, $parsed);
        PdfReportSchedule::markRun($schedule['id'], $nextRun, $success, $message, $success ? $generationId : null);

        return [
            'schedule_id' => (int) $schedule['id'],
            'success' => $success,
            'message' => $message,
            'generation_id' => $generationId,
            'next_run' => $nextRun ? $nextRun->format('Y-m-d H:i:s') : null,
        ];
    }
}
