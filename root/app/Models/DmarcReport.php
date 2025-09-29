<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * DMARC Report model for managing aggregate reports
 */
class DmarcReport
{
    /**
     * Get all reports with pagination
     */
    public static function getAll(int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;

        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT r.*, d.domain, b.name as brand_name 
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            LEFT JOIN brands b ON d.brand_id = b.id
            ORDER BY r.report_begin DESC
            LIMIT :limit OFFSET :offset
        ');
        $db->bind(':limit', $limit);
        $db->bind(':offset', $offset);
        return $db->resultSet();
    }

    /**
     * Get report by ID
     */
    public static function getById(int $id): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT r.*, d.domain, b.name as brand_name 
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            LEFT JOIN brands b ON d.brand_id = b.id
            WHERE r.id = :id
        ');
        $db->bind(':id', $id);
        return $db->single();
    }

    /**
     * Get reports by domain
     */
    public static function getByDomain(int $domainId, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;

        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT r.*, d.domain 
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            WHERE r.domain_id = :domain_id
            ORDER BY r.report_begin DESC
            LIMIT :limit OFFSET :offset
        ');
        $db->bind(':domain_id', $domainId);
        $db->bind(':limit', $limit);
        $db->bind(':offset', $offset);
        return $db->resultSet();
    }

    /**
     * Create new DMARC report
     */
    public static function create(array $data): int|false
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            INSERT INTO dmarc_reports (domain_id, report_id, org_name, email, extra_contact_info,
                                     report_begin, report_end, policy_domain, policy_adkim, 
                                     policy_aspf, policy_p, policy_sp, policy_pct, raw_xml)
            VALUES (:domain_id, :report_id, :org_name, :email, :extra_contact_info,
                   :report_begin, :report_end, :policy_domain, :policy_adkim,
                   :policy_aspf, :policy_p, :policy_sp, :policy_pct, :raw_xml)
        ');

        $db->bind(':domain_id', $data['domain_id']);
        $db->bind(':report_id', $data['report_id']);
        $db->bind(':org_name', $data['org_name']);
        $db->bind(':email', $data['email']);
        $db->bind(':extra_contact_info', $data['extra_contact_info'] ?? null);
        $db->bind(':report_begin', $data['report_begin']);
        $db->bind(':report_end', $data['report_end']);
        $db->bind(':policy_domain', $data['policy_domain']);
        $db->bind(':policy_adkim', $data['policy_adkim'] ?? 'r');
        $db->bind(':policy_aspf', $data['policy_aspf'] ?? 'r');
        $db->bind(':policy_p', $data['policy_p']);
        $db->bind(':policy_sp', $data['policy_sp'] ?? null);
        $db->bind(':policy_pct', $data['policy_pct'] ?? 100);
        $db->bind(':raw_xml', $data['raw_xml']);

        if ($db->execute()) {
            return $db->lastInsertId();
        }
        return false;
    }

    /**
     * Mark report as processed
     */
    public static function markProcessed(int $id): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('UPDATE dmarc_reports SET processed = 1 WHERE id = :id');
        $db->bind(':id', $id);
        return $db->execute();
    }

    /**
     * Get unprocessed reports
     */
    public static function getUnprocessed(int $limit = 100): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT r.*, d.domain 
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            WHERE r.processed = 0
            ORDER BY r.created_at ASC
            LIMIT :limit
        ');
        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    /**
     * Get report statistics
     */
    public static function getStats(array $filters = []): object
    {
        $whereClause = 'WHERE 1=1';
        $params = [];

        if (!empty($filters['domain_id'])) {
            $whereClause .= ' AND r.domain_id = :domain_id';
            $params[':domain_id'] = $filters['domain_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND r.report_begin >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClause .= ' AND r.report_end <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $db = DatabaseManager::getInstance();
        $db->query("
            SELECT 
                COUNT(*) as total_reports,
                COUNT(DISTINCT r.domain_id) as unique_domains,
                COUNT(DISTINCT r.org_name) as unique_reporters,
                MIN(r.report_begin) as first_report,
                MAX(r.report_end) as last_report,
                SUM(CASE WHEN r.processed = 1 THEN 1 ELSE 0 END) as processed_reports
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            $whereClause
        ");

        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }

        return $db->single() ?: (object)[
            'total_reports' => 0,
            'unique_domains' => 0,
            'unique_reporters' => 0,
            'first_report' => null,
            'last_report' => null,
            'processed_reports' => 0
        ];
    }

    /**
     * Get recent activity
     */
    public static function getRecentActivity(int $days = 7, int $limit = 20): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT r.*, d.domain, b.name as brand_name
            FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            LEFT JOIN brands b ON d.brand_id = b.id
            WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY r.created_at DESC
            LIMIT :limit
        ');
        $db->bind(':days', $days);
        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    /**
     * Delete old reports based on retention policy
     */
    public static function cleanupOldReports(): int
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            DELETE r FROM dmarc_reports r
            JOIN domains d ON r.domain_id = d.id
            WHERE r.report_end < DATE_SUB(NOW(), INTERVAL d.retention_days DAY)
        ');
        $db->execute();
        return $db->rowCount();
    }
}
