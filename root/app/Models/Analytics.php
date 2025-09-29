<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * Analytics model for generating DMARC dashboard analytics and trends
 */
class Analytics
{
    /**
     * Get trend data for charts (daily aggregates)
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $domain
     * @return array
     */
    public static function getTrendData(string $startDate, string $endDate, string $domain = ''): array
    {
        $db = DatabaseManager::getInstance();

        $whereClause = '';
        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($domain)) {
            $whereClause = 'AND d.domain = :domain';
            $bindParams[':domain'] = $domain;
        }

        $query = "
            SELECT 
                DATE(datetime(dar.date_range_begin, 'unixepoch')) as date,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) as dkim_pass_count,
                SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) as spf_pass_count
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date 
            AND dar.date_range_end <= :end_date
            $whereClause
            GROUP BY DATE(datetime(dar.date_range_begin, 'unixepoch'))
            ORDER BY date ASC
        ";

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        return $db->resultSet();
    }

    /**
     * Calculate domain health scores based on authentication success rates
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getDomainHealthScores(string $startDate, string $endDate): array
    {
        $db = DatabaseManager::getInstance();

        $query = "
            SELECT 
                d.domain,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) as dkim_pass_count,
                SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) as spf_pass_count,
                ROUND(
                    (SUM(CASE WHEN dmar.disposition = 'none' AND dmar.dkim_result = 'pass' AND dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) * 100.0) / 
                    NULLIF(SUM(dmar.count), 0), 2
                ) as health_score
            FROM domains d
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date 
            AND dar.date_range_end <= :end_date
            GROUP BY d.id, d.domain
            HAVING total_volume > 0
            ORDER BY health_score DESC
        ";

        $db->query($query);
        $db->bind(':start_date', strtotime($startDate));
        $db->bind(':end_date', strtotime($endDate . ' 23:59:59'));

        $results = $db->resultSet();

        // Add health score categories
        foreach ($results as &$result) {
            $score = (float) $result['health_score'];
            if ($score >= 95) {
                $result['health_category'] = 'excellent';
                $result['health_label'] = 'Excellent';
            } elseif ($score >= 85) {
                $result['health_category'] = 'good';
                $result['health_label'] = 'Good';
            } elseif ($score >= 70) {
                $result['health_category'] = 'warning';
                $result['health_label'] = 'Needs Attention';
            } else {
                $result['health_category'] = 'critical';
                $result['health_label'] = 'Critical';
            }
        }

        return $results;
    }

    /**
     * Get summary statistics for the dashboard
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $domain
     * @return array
     */
    public static function getSummaryStatistics(string $startDate, string $endDate, string $domain = ''): array
    {
        $db = DatabaseManager::getInstance();

        $whereClause = '';
        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($domain)) {
            $whereClause = 'AND d.domain = :domain';
            $bindParams[':domain'] = $domain;
        }

        $query = "
            SELECT 
                COUNT(DISTINCT d.id) as domain_count,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                COUNT(DISTINCT dmar.source_ip) as unique_ips,
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
        ";

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        $result = $db->single();
        return $result ?: [];
    }

    /**
     * Get top threatening IP addresses
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    public static function getTopThreats(string $startDate, string $endDate, int $limit = 10): array
    {
        $db = DatabaseManager::getInstance();

        $query = "
            SELECT 
                dmar.source_ip,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                (SUM(CASE WHEN dmar.disposition IN ('quarantine', 'reject') THEN dmar.count ELSE 0 END)) as threat_volume,
                ROUND(
                    (SUM(CASE WHEN dmar.disposition IN ('quarantine', 'reject') THEN dmar.count ELSE 0 END) * 100.0) / 
                    NULLIF(SUM(dmar.count), 0), 2
                ) as threat_rate,
                GROUP_CONCAT(DISTINCT d.domain) as affected_domains
            FROM dmarc_aggregate_records dmar
            JOIN dmarc_aggregate_reports dar ON dmar.report_id = dar.id
            JOIN domains d ON dar.domain_id = d.id
            WHERE dar.date_range_begin >= :start_date 
            AND dar.date_range_end <= :end_date
            AND dmar.disposition IN ('quarantine', 'reject')
            GROUP BY dmar.source_ip
            HAVING threat_volume > 0
            ORDER BY threat_volume DESC, threat_rate DESC
            LIMIT :limit
        ";

        $db->query($query);
        $db->bind(':start_date', strtotime($startDate));
        $db->bind(':end_date', strtotime($endDate . ' 23:59:59'));
        $db->bind(':limit', $limit);

        return $db->resultSet();
    }

    /**
     * Get compliance data over time
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $domain
     * @return array
     */
    public static function getComplianceData(string $startDate, string $endDate, string $domain = ''): array
    {
        $db = DatabaseManager::getInstance();

        $whereClause = '';
        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($domain)) {
            $whereClause = 'AND d.domain = :domain';
            $bindParams[':domain'] = $domain;
        }

        $query = "
            SELECT 
                DATE(datetime(dar.date_range_begin, 'unixepoch')) as date,
                ROUND(
                    (SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) * 100.0) / 
                    NULLIF(SUM(dmar.count), 0), 2
                ) as dkim_compliance,
                ROUND(
                    (SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) * 100.0) / 
                    NULLIF(SUM(dmar.count), 0), 2
                ) as spf_compliance,
                ROUND(
                    (SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) * 100.0) / 
                    NULLIF(SUM(dmar.count), 0), 2
                ) as dmarc_compliance
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date 
            AND dar.date_range_end <= :end_date
            $whereClause
            GROUP BY DATE(datetime(dar.date_range_begin, 'unixepoch'))
            HAVING SUM(dmar.count) > 0
            ORDER BY date ASC
        ";

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        return $db->resultSet();
    }
}
