<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\SessionManager;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Email Digest model for managing automated email reports
 */
class EmailDigest
{
    private static ?bool $hasCreatedByColumn = null;

    /**
     * Get all digest schedules
     *
     * @return array
     */
    public static function getAllSchedules(): array
    {
        $db = DatabaseManager::getInstance();

        $rbac = RBACManager::getInstance();
        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
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

        $accessibleDomains = array_values(array_filter(array_map(
            static fn(array $domain) => strtolower((string) ($domain['domain'] ?? '')),
            $rbac->getAccessibleDomains()
        )));

        $accessibleGroups = array_values(array_filter(array_map(
            static fn(array $group) => (int) ($group['id'] ?? 0),
            $rbac->getAccessibleGroups()
        )));

        $conditions = [];
        $bindings = [];
        $supportsCreatedBy = self::supportsCreatedByColumn();

        if ($supportsCreatedBy) {
            $session = SessionManager::getInstance();
            $username = (string) $session->get('username', '');
            if ($username !== '') {
                $conditions[] = '(eds.created_by = :current_user)';
                $bindings[':current_user'] = $username;
            }
        }

        if (!empty($accessibleDomains)) {
            $placeholders = [];
            foreach ($accessibleDomains as $index => $domain) {
                $placeholder = ':authorized_domain_' . $index;
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = $domain;
            }
            $conditions[] = '(eds.domain_filter <> "" AND LOWER(eds.domain_filter) IN (' . implode(', ', $placeholders) . '))';
        }

        if (!empty($accessibleGroups)) {
            $groupPlaceholders = [];
            foreach ($accessibleGroups as $index => $groupId) {
                $placeholder = ':authorized_group_' . $index;
                $groupPlaceholders[] = $placeholder;
                $bindings[$placeholder] = $groupId;
            }
            $conditions[] = '(eds.group_filter IS NOT NULL AND eds.group_filter IN (' . implode(', ', $groupPlaceholders) . '))';
        }

        if (empty($conditions)) {
            return [];
        }

        $whereClause = 'WHERE ' . implode(' OR ', $conditions);

        $db->query('
            SELECT
                eds.*, 
                dg.name as group_name,
                COUNT(edl.id) as sent_count
            FROM email_digest_schedules eds
            LEFT JOIN domain_groups dg ON eds.group_filter = dg.id
            LEFT JOIN email_digest_logs edl ON eds.id = edl.schedule_id
            ' . $whereClause . '
            GROUP BY eds.id
            ORDER BY eds.created_at DESC
        ');

        foreach ($bindings as $placeholder => $value) {
            if (str_starts_with($placeholder, ':authorized_domain_')) {
                $db->bind($placeholder, strtolower((string) $value));
                continue;
            }

            $db->bind($placeholder, $value);
        }

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

        $domainFilter = trim((string) ($data['domain_filter'] ?? ''));
        $groupFilter = isset($data['group_filter']) && $data['group_filter'] !== ''
            ? (int) $data['group_filter']
            : null;

        self::assertFilterAccess($domainFilter, $groupFilter);

        $supportsCreatedBy = self::supportsCreatedByColumn();

        if ($supportsCreatedBy) {
            $db->query('
                INSERT INTO email_digest_schedules
                (name, frequency, recipients, domain_filter, group_filter, enabled, next_scheduled, created_by)
                VALUES (:name, :frequency, :recipients, :domain_filter, :group_filter, :enabled, :next_scheduled, :created_by)
            ');
        } else {
            $db->query('
                INSERT INTO email_digest_schedules
                (name, frequency, recipients, domain_filter, group_filter, enabled, next_scheduled)
                VALUES (:name, :frequency, :recipients, :domain_filter, :group_filter, :enabled, :next_scheduled)
            ');
        }

        $db->bind(':name', $data['name']);
        $db->bind(':frequency', $data['frequency']);
        $db->bind(':recipients', json_encode($data['recipients']));
        $db->bind(':domain_filter', $domainFilter);
        $db->bind(':group_filter', $groupFilter);
        $db->bind(':enabled', $data['enabled'] ?? 1);
        $db->bind(':next_scheduled', $data['next_scheduled'] ?? null);
        if ($supportsCreatedBy) {
            $db->bind(':created_by', $data['created_by'] ?? null);
        }
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Generate digest data for a schedule
     *
     * @param int $scheduleId
     * @param string $startDate
     * @param string $endDate
     * @param bool $bypassRbac Skip RBAC checks (for background jobs)
     * @return array
     */
    public static function generateDigestData(int $scheduleId, string $startDate, string $endDate, bool $bypassRbac = false): array
    {
        $db = DatabaseManager::getInstance();

        // Get schedule details
        $db->query('SELECT * FROM email_digest_schedules WHERE id = :id');
        $db->bind(':id', $scheduleId);
        $schedule = $db->single();

        if (!$schedule) {
            return [];
        }

        if (!$bypassRbac && !self::scheduleIsAccessible($schedule)) {
            return [];
        }

        // Build filters
        $conditions = [
            'dar.date_range_begin >= :start_date',
            'dar.date_range_end <= :end_date'
        ];

        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($schedule['domain_filter'])) {
            $conditions[] = 'd.domain = :domain_filter';
            $bindParams[':domain_filter'] = $schedule['domain_filter'];
        }

        $groupJoinClause = '';

        if (!empty($schedule['group_filter'])) {
            $groupJoinClause = "\n            JOIN domain_group_assignments dga ON dga.domain_id = d.id AND dga.group_id = :group_filter";
            $bindParams[':group_filter'] = $schedule['group_filter'];
        }

        $whereClause = '            ' . implode("\n            AND ", $conditions);

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
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id$groupJoinClause
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE
            $whereClause
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
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id$groupJoinClause
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE
            $whereClause
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
            JOIN domains d ON dar.domain_id = d.id$groupJoinClause
            WHERE
            $whereClause
            AND dmar.disposition IN ('quarantine', 'reject')
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
    public static function getSchedule(int $scheduleId, bool $enforceAccess = false): ?array
    {
        $db = DatabaseManager::getInstance();

        $db->query('SELECT * FROM email_digest_schedules WHERE id = :id');
        $db->bind(':id', $scheduleId);

        $result = $db->single();
        if (!$result) {
            return null;
        }

        if ($enforceAccess && !self::scheduleIsAccessible($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Enable or disable a schedule.
     */
    public static function setEnabled(int $scheduleId, bool $enabled): bool
    {
        $schedule = self::getSchedule($scheduleId, true);
        if ($schedule === null) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $db->query('
            UPDATE email_digest_schedules
            SET enabled = :enabled
            WHERE id = :id
        ');

        $db->bind(':enabled', $enabled ? 1 : 0);
        $db->bind(':id', $scheduleId);
        $db->execute();
        return true;
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
    public static function updateLastSent(int $scheduleId, ?string $nextScheduled = null, bool $updateLastSent = true): void
    {
        $db = DatabaseManager::getInstance();
        $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        if ($updateLastSent) {
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
            return;
        }

        if ($nextScheduled === null) {
            $db->query('
                UPDATE email_digest_schedules
                SET next_scheduled = NULL
                WHERE id = :id
            ');
            $db->bind(':id', $scheduleId);
            $db->execute();
            return;
        }

        $db->query('
            UPDATE email_digest_schedules
            SET next_scheduled = :next_scheduled
            WHERE id = :id
        ');
        $db->bind(':next_scheduled', $nextScheduled);
        $db->bind(':id', $scheduleId);
        $db->execute();
    }

    private static function scheduleIsAccessible(array $schedule): bool
    {
        $rbac = RBACManager::getInstance();
        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
            return true;
        }

        $session = SessionManager::getInstance();
        $username = (string) $session->get('username', '');
        if ($username !== '' && ($schedule['created_by'] ?? null) === $username) {
            return true;
        }

        $domainFilter = strtolower(trim((string) ($schedule['domain_filter'] ?? '')));
        if ($domainFilter !== '') {
            $accessibleDomains = array_map(
                static fn(array $domain) => strtolower((string) ($domain['domain'] ?? '')),
                $rbac->getAccessibleDomains()
            );

            if (in_array($domainFilter, $accessibleDomains, true)) {
                return true;
            }
        }

        $groupFilter = $schedule['group_filter'] ?? null;
        if ($groupFilter !== null && $rbac->canAccessGroup((int) $groupFilter)) {
            return true;
        }

        return false;
    }

    private static function assertFilterAccess(?string $domainFilter, ?int $groupFilter): void
    {
        $rbac = RBACManager::getInstance();
        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
            return;
        }

        $domainFilter = trim((string) $domainFilter);
        if ($domainFilter !== '') {
            $accessibleDomains = array_map(
                static fn(array $domain) => strtolower((string) ($domain['domain'] ?? '')),
                $rbac->getAccessibleDomains()
            );

            if (!in_array(strtolower($domainFilter), $accessibleDomains, true)) {
                throw new RuntimeException('You are not authorized to create digests for the selected domain.');
            }
        }

        if ($groupFilter !== null && !$rbac->canAccessGroup($groupFilter)) {
            throw new RuntimeException('You are not authorized to create digests for the selected group.');
        }
    }

    private static function supportsCreatedByColumn(): bool
    {
        if (self::$hasCreatedByColumn !== null) {
            return self::$hasCreatedByColumn;
        }

        try {
            $db = DatabaseManager::getInstance();
            $driver = strtolower($db->getDriverName());

            if (str_contains($driver, 'sqlite')) {
                $db->query('PRAGMA table_info(email_digest_schedules)');
                $columns = $db->resultSet();
                foreach ($columns as $column) {
                    $name = strtolower((string) ($column['name'] ?? ''));
                    if ($name === 'created_by') {
                        self::$hasCreatedByColumn = true;
                        return true;
                    }
                }
                self::$hasCreatedByColumn = false;
                return false;
            }

            $db->query("SHOW COLUMNS FROM email_digest_schedules LIKE 'created_by'");
            $column = $db->single();
            self::$hasCreatedByColumn = !empty($column);
            return self::$hasCreatedByColumn;
        } catch (Throwable $exception) {
            self::$hasCreatedByColumn = false;
            return false;
        }
    }
}
