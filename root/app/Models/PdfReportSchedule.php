<?php

namespace App\Models;

use App\Core\DatabaseManager;
use DateTimeImmutable;
use Throwable;

/**
 * Storage helper for scheduled PDF report automation.
 */
class PdfReportSchedule
{
    /**
     * Return all schedules along with template metadata and last generation details.
     */
    public static function getAllSchedules(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT
                prs.*, 
                prt.name AS template_name,
                prt.template_type,
                prg.generated_at AS last_generated_at,
                prg.filename AS last_filename,
                prg.file_path AS last_file_path,
                prg.id AS last_generation_id
            FROM pdf_report_schedules prs
            JOIN pdf_report_templates prt ON prs.template_id = prt.id
            LEFT JOIN pdf_report_generations prg ON prs.last_generation_id = prg.id
            ORDER BY prs.created_at DESC
        ');

        return $db->resultSet();
    }

    /**
     * Retrieve a single schedule row.
     */
    public static function find(int $id): ?array
    {
        $db = DatabaseManager::getInstance();

        $db->query('SELECT * FROM pdf_report_schedules WHERE id = :id');
        $db->bind(':id', $id);
        $result = $db->single();

        return $result ?: null;
    }

    /**
     * Persist a new schedule and return its identifier.
     *
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO pdf_report_schedules
            (name, template_id, title, frequency, recipients, domain_filter, group_filter, parameters,
             enabled, next_run_at, created_by)
            VALUES
            (:name, :template_id, :title, :frequency, :recipients, :domain_filter, :group_filter, :parameters,
             :enabled, :next_run_at, :created_by)
        ');

        $db->bind(':name', $data['name']);
        $db->bind(':template_id', $data['template_id']);
        $db->bind(':title', $data['title'] ?? $data['name']);
        $db->bind(':frequency', $data['frequency']);
        $db->bind(':recipients', json_encode($data['recipients'] ?? []));
        $db->bind(':domain_filter', $data['domain_filter'] ?? '');
        $db->bind(':group_filter', $data['group_filter'] ?? null);
        $db->bind(':parameters', json_encode($data['parameters'] ?? []));
        $db->bind(':enabled', $data['enabled'] ?? 1);
        $db->bind(':next_run_at', $data['next_run_at'] ?? null);
        $db->bind(':created_by', $data['created_by'] ?? null);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Update an existing schedule with provided attributes.
     *
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            UPDATE pdf_report_schedules
            SET
                name = :name,
                template_id = :template_id,
                title = :title,
                frequency = :frequency,
                recipients = :recipients,
                domain_filter = :domain_filter,
                group_filter = :group_filter,
                parameters = :parameters,
                enabled = :enabled,
                next_run_at = :next_run_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        $db->bind(':name', $data['name']);
        $db->bind(':template_id', $data['template_id']);
        $db->bind(':title', $data['title'] ?? $data['name']);
        $db->bind(':frequency', $data['frequency']);
        $db->bind(':recipients', json_encode($data['recipients'] ?? []));
        $db->bind(':domain_filter', $data['domain_filter'] ?? '');
        $db->bind(':group_filter', $data['group_filter'] ?? null);
        $db->bind(':parameters', json_encode($data['parameters'] ?? []));
        $db->bind(':enabled', $data['enabled'] ?? 1);
        $db->bind(':next_run_at', $data['next_run_at'] ?? null);
        $db->bind(':id', $id);
        $db->execute();
    }

    /**
     * Remove a schedule.
     */
    public static function delete(int $id): void
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM pdf_report_schedules WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();
    }

    /**
     * Toggle enabled flag for a schedule.
     */
    public static function setEnabled(int $id, bool $enabled): void
    {
        $db = DatabaseManager::getInstance();
        $db->query('UPDATE pdf_report_schedules SET enabled = :enabled WHERE id = :id');
        $db->bind(':enabled', $enabled ? 1 : 0);
        $db->bind(':id', $id);
        $db->execute();
    }

    /**
     * Compute and persist run metadata after an execution cycle.
     */
    public static function markRun(
        int $id,
        ?DateTimeImmutable $nextRun,
        bool $success,
        string $message,
        ?int $generationId
    ): void {
        $db = DatabaseManager::getInstance();

        $db->query('
            UPDATE pdf_report_schedules
            SET
                last_run_at = CURRENT_TIMESTAMP,
                next_run_at = :next_run_at,
                last_status = :status,
                last_error = :message,
                last_generation_id = :generation_id
            WHERE id = :id
        ');

        $db->bind(':next_run_at', $nextRun ? $nextRun->format('Y-m-d H:i:s') : null);
        $db->bind(':status', $success ? 'success' : 'failed');
        $db->bind(':message', $message);
        $db->bind(':generation_id', $generationId);
        $db->bind(':id', $id);
        $db->execute();
    }

    /**
     * Fetch all enabled schedules for evaluation.
     */
    public static function getEnabledSchedules(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT *
            FROM pdf_report_schedules
            WHERE enabled = 1
            ORDER BY
                CASE WHEN next_run_at IS NULL THEN 1 ELSE 0 END,
                next_run_at
        ');

        return $db->resultSet();
    }

    /**
     * Utility helper to parse a stored frequency descriptor.
     *
     * @return array{type:string,range_days:int,custom_days:?int}|null
     */
    public static function parseFrequency(string $frequency): ?array
    {
        $parts = explode(':', $frequency, 2);
        $type = $parts[0] ?? '';
        $custom = null;

        if ($type === 'custom' && isset($parts[1]) && is_numeric($parts[1])) {
            $custom = max(1, (int) $parts[1]);
        }

        return match ($type) {
            'daily' => ['type' => 'daily', 'range_days' => 1, 'custom_days' => null],
            'weekly' => ['type' => 'weekly', 'range_days' => 7, 'custom_days' => null],
            'monthly' => ['type' => 'monthly', 'range_days' => 30, 'custom_days' => null],
            'custom' => ['type' => 'custom', 'range_days' => $custom ?? 7, 'custom_days' => $custom],
            default => null,
        };
    }

    /**
     * Determine if a schedule should run at the provided reference time.
     */
    public static function isDue(array $schedule, DateTimeImmutable $reference, array $parsed): bool
    {
        if ((int) ($schedule['enabled'] ?? 0) !== 1) {
            return false;
        }

        if (!empty($schedule['next_run_at'])) {
            try {
                $next = new DateTimeImmutable($schedule['next_run_at']);
                if ($next <= $reference) {
                    return true;
                }
            } catch (Throwable $exception) {
                return true;
            }
        }

        if (!empty($schedule['last_run_at'])) {
            try {
                $last = new DateTimeImmutable($schedule['last_run_at']);
                $interval = PdfReportSchedulerHelper::buildInterval($parsed);
                return $last->add($interval) <= $reference;
            } catch (Throwable $exception) {
                return true;
            }
        }

        return true;
    }
}

/**
 * Internal helper used to share interval building without creating a new service dependency in the model.
 */
class PdfReportSchedulerHelper
{
    public static function buildInterval(array $parsed): \DateInterval
    {
        return match ($parsed['type']) {
            'daily' => new \DateInterval('P1D'),
            'weekly' => new \DateInterval('P7D'),
            'monthly' => new \DateInterval('P1M'),
            'custom' => new \DateInterval('P' . max(1, (int) ($parsed['custom_days'] ?? $parsed['range_days'])) . 'D'),
            default => new \DateInterval('P1D'),
        };
    }
}
