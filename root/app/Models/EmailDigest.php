<?php

namespace App\Models;

use App\Core\DatabaseManager;
use DateTimeImmutable;

/**
 * Email Digest model for managing automated email reports
 */
class EmailDigest
{
    /**
     * Get all digest schedules
     *
     * @return array
     */
    public static function getAllSchedules(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                eds.*,
                dg.name as group_name,
                COUNT(edl.id) as sent_count
            FROM email_digest_schedules eds
            LEFT JOIN domain_groups dg ON eds.group_filter = dg.id
            LEFT JOIN email_digest_logs edl ON eds.id = edl.schedule_id
            GROUP BY eds.id
            ORDER BY eds.created_at DESC
        ');

        return $db->resultSet();
    }

    /**
     * Create a new digest schedule
     *
     * @param array $data
     * @return int Schedule ID
     */
    public static function createSchedule(array $data): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO email_digest_schedules
            (name, frequency, recipients, domain_filter, group_filter, enabled, next_scheduled)
            VALUES (:name, :frequency, :recipients, :domain_filter, :group_filter, :enabled, :next_scheduled)
        ');

        $db->bind(':name', $data['name']);
        $db->bind(':frequency', $data['frequency']);
        $db->bind(':recipients', json_encode($data['recipients']));
        $db->bind(':domain_filter', $data['domain_filter'] ?? '');
        $db->bind(':group_filter', $data['group_filter'] ?? null);
        $db->bind(':enabled', $data['enabled'] ?? 1);
        $db->bind(':next_scheduled', $data['next_scheduled'] ?? null);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Generate digest data for a schedule
     *
     * @param int $scheduleId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function generateDigestData(int $scheduleId, string $startDate, string $endDate): array
    {
        $db = DatabaseManager::getInstance();

        // Get schedule details
        $db->query('SELECT * FROM email_digest_schedules WHERE id = :id');
        $db->bind(':id', $scheduleId);
        $schedule = $db->single();

        if (!$schedule) {
            return [];
        }

        // Build filters
        $whereClause = '';
        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($schedule['domain_filter'])) {
            $whereClause .= ' AND d.domain = :domain_filter';
            $bindParams[':domain_filter'] = $schedule['domain_filter'];
        }

        $groupFilterClause = '';

        if (!empty($schedule['group_filter'])) {
            $groupFilterClause = '
                AND EXISTS (
                    SELECT 1
                    FROM domain_group_assignments dga
                    WHERE dga.domain_id = d.id
                    AND dga.group_id = :group_filter
                )';
            $bindParams[':group_filter'] = $schedule['group_filter'];
        }

        $bindAllParams = static function (DatabaseManager $manager, array $params): void {
            foreach ($params as $param => $value) {
                $manager->bind($param, $value);
            }
        };

        // Get summary data
        $summaryQuery = "
            SELECT
                COUNT(DISTINCT d.id) as domain_count,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                COUNT(DISTINCT dmar.source_ip) as unique_ips
            FROM domains d
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupFilterClause
        ";

        $db->query($summaryQuery);
        $bindAllParams($db, $bindParams);
        $summary = $db->single();

        // Get domain breakdown
        $domainQuery = "
            SELECT
                d.domain,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                ROUND(
                    (SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) * 100.0) /
                    NULLIF(SUM(dmar.count), 0), 2
                ) as pass_rate
            FROM domains d
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupFilterClause
            GROUP BY d.id, d.domain
            ORDER BY total_volume DESC
        ";

        $db->query($domainQuery);
        $bindAllParams($db, $bindParams);
        $domains = $db->resultSet();

        // Get top threats
        $threatsQuery = "
            SELECT
                dmar.source_ip,
                SUM(CASE WHEN dmar.disposition IN ('quarantine', 'reject') THEN dmar.count ELSE 0 END) as threat_volume,
                COUNT(DISTINCT d.domain) as affected_domains
            FROM dmarc_aggregate_records dmar
            JOIN dmarc_aggregate_reports dar ON dmar.report_id = dar.id
            JOIN domains d ON dar.domain_id = d.id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            AND dmar.disposition IN ('quarantine', 'reject')
            $whereClause
            $groupFilterClause
            GROUP BY dmar.source_ip
            ORDER BY threat_volume DESC
            LIMIT 10
        ";

        $db->query($threatsQuery);
        $bindAllParams($db, $bindParams);
        $threats = $db->resultSet();

        return [
            'schedule' => $schedule,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $summary,
            'domains' => $domains,
            'threats' => $threats
        ];
    }

    /**
     * Retrieve all enabled schedules for background processing.
     */
    public static function getEnabledSchedules(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT *
            FROM email_digest_schedules
            WHERE enabled = 1
            ORDER BY
                CASE WHEN next_scheduled IS NULL THEN 1 ELSE 0 END,
                next_scheduled
        ');

        return $db->resultSet();
    }

    /**
     * Retrieve a specific schedule.
     */
    public static function getSchedule(int $scheduleId): ?array
    {
        $db = DatabaseManager::getInstance();

        $db->query('SELECT * FROM email_digest_schedules WHERE id = :id');
        $db->bind(':id', $scheduleId);

        $result = $db->single();
        return $result ?: null;
    }

    /**
     * Enable or disable a schedule.
     */
    public static function setEnabled(int $scheduleId, bool $enabled): void
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            UPDATE email_digest_schedules
            SET enabled = :enabled
            WHERE id = :id
        ');

        $db->bind(':enabled', $enabled ? 1 : 0);
        $db->bind(':id', $scheduleId);
        $db->execute();
    }

    /**
     * Log digest send attempt
     *
     * @param int $scheduleId
     * @param array $recipients
     * @param string $subject
     * @param string $startDate
     * @param string $endDate
     * @param bool $success
     * @param string $errorMessage
     * @return int Log ID
     */
    public static function logDigestSend(
        int $scheduleId,
        array $recipients,
        string $subject,
        string $startDate,
        string $endDate,
        bool $success = true,
        string $errorMessage = ''
    ): int {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO email_digest_logs 
            (schedule_id, recipients, subject, report_period_start, report_period_end, success, error_message) 
            VALUES (:schedule_id, :recipients, :subject, :start_date, :end_date, :success, :error_message)
        ');

        $db->bind(':schedule_id', $scheduleId);
        $db->bind(':recipients', json_encode($recipients));
        $db->bind(':subject', $subject);
        $db->bind(':start_date', $startDate);
        $db->bind(':end_date', $endDate);
        $db->bind(':success', $success ? 1 : 0);
        $db->bind(':error_message', $errorMessage);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Update schedule last sent time
     *
     * @param int $scheduleId
     * @return void
     */
    public static function updateLastSent(int $scheduleId, ?string $nextScheduled = null): void
    {
        $db = DatabaseManager::getInstance();
        $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        if ($nextScheduled === null) {
            $db->query('
                UPDATE email_digest_schedules
                SET last_sent = :last_sent, next_scheduled = NULL
                WHERE id = :id
            ');
            $db->bind(':last_sent', $timestamp);
            $db->bind(':id', $scheduleId);
            $db->execute();
            return;
        }

        $db->query('
            UPDATE email_digest_schedules
            SET last_sent = :last_sent, next_scheduled = :next_scheduled
            WHERE id = :id
        ');

        $db->bind(':last_sent', $timestamp);
        $db->bind(':next_scheduled', $nextScheduled);
        $db->bind(':id', $scheduleId);
        $db->execute();
    }
}
