<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

class TlsReport
{
    public static function getRecentReports(int $limit = 25): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $accessibleDomainIds = self::getAccessibleDomainIds();
        if (empty($accessibleDomainIds)) {
            return [];
        }

        [$placeholders, $bindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
        $db->query(
            '
            SELECT r.*, d.domain,
                   SUM(p.successful_session_count) AS success_sessions,
                   SUM(p.failure_session_count) AS failure_sessions
            FROM smtp_tls_reports r
            JOIN domains d ON r.domain_id = d.id
            LEFT JOIN smtp_tls_policies p ON p.tls_report_id = r.id
            WHERE r.domain_id IN (' . implode(', ', $placeholders) . ')
            GROUP BY r.id
            ORDER BY r.received_at DESC
            LIMIT :limit
        '
        );

        foreach ($bindings as $param => $value) {
            $db->bind($param, $value);
        }

        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    public static function getReportDetail(int $id): ?array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $db->query(
            '
            SELECT r.*, d.domain,
                   SUM(p.successful_session_count) AS success_sessions,
                   SUM(p.failure_session_count) AS failure_sessions
            FROM smtp_tls_reports r
            JOIN domains d ON r.domain_id = d.id
            LEFT JOIN smtp_tls_policies p ON p.tls_report_id = r.id
            WHERE r.id = :id
            GROUP BY r.id
        '
        );
        $db->bind(':id', $id);
        $report = $db->single();

        if (!$report) {
            return null;
        }

        if (!in_array((int) $report['domain_id'], self::getAccessibleDomainIds(), true)) {
            return null;
        }

        $db->query(
            'SELECT * FROM smtp_tls_policies WHERE tls_report_id = :id ORDER BY mx_host'
        );
        $db->bind(':id', $id);
        $policies = $db->resultSet();

        $report['policies'] = $policies;
        return $report;
    }

    private static function getAccessibleDomainIds(): array
    {
        $rbac = RBACManager::getInstance();
        $domains = $rbac->getAccessibleDomains();
        return array_map(function ($domain) { return (int) $domain['id']; }, $domains);
    }

    private static function buildInClause(array $ids, string $prefix): array
    {
        $placeholders = [];
        $bindings = [];

        foreach ($ids as $index => $id) {
            $placeholder = ':' . $prefix . '_' . $index;
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = (int) $id;
        }

        return [$placeholders, $bindings];
    }
}
