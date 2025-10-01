<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\ErrorManager;
use App\Utilities\AccessScopeValidator;
use DateTimeImmutable;

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
     * @param int|null $groupId
     * @param string|null $domainFilter
     * @return array
     */
    public static function getTrendData(
        string $startDate,
        string $endDate,
        string $domain = '',
        ?int $groupId = null
    ): array {
        $range = self::resolveDateRange($startDate, $endDate);
        if ($range === null) {
            return [];
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domain);
        if (!$domainResolution['authorized']) {
            return [];
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupId);
        if (!$groupResolution['authorized']) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $bindParams = [
            ':start_date' => $range['start'],
            ':end_date' => $range['end'],
        ];

        $whereConditions = [];

        if ($domainResolution['id'] !== null) {
            $whereConditions[] = 'd.id = :domain_id';
            $bindParams[':domain_id'] = $domainResolution['id'];
        }

        $groupJoin = '';
        $groupClause = '';
        if ($groupResolution['id'] !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupResolution['id'];
        }

        if ($domainResolution['id'] === null) {
            $authorization = AccessScopeValidator::buildDomainAuthorizationClause($bindParams, 'dar.domain_id');
            if ($authorization !== null) {
                if ($authorization['allowed'] === false) {
                    return [];
                }

                $whereConditions[] = $authorization['clause'];
            }
        }

        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'AND ' . implode(' AND ', $whereConditions);
        }

        $dateExpression = self::getDateBucketExpression('dar.date_range_begin');

        $query = "
            SELECT
                {$dateExpression} as date,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) as dkim_pass_count,
                SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) as spf_pass_count
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            $groupJoin
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupClause
            GROUP BY {$dateExpression}
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
     * @param int|null $groupId
     * @return array
     */
    public static function getDomainHealthScores(
        string $startDate,
        string $endDate,
        ?int $groupId = null,
        ?string $domainFilter = null
    ): array {
        $range = self::resolveDateRange($startDate, $endDate);
        if ($range === null) {
            return [];
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domainFilter);
        if (!$domainResolution['authorized']) {
            return [];
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupId);
        if (!$groupResolution['authorized']) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $groupJoin = '';
        $groupClause = '';
        $bindParams = [
            ':start_date' => $range['start'],
            ':end_date' => $range['end'],
        ];

        if ($groupResolution['id'] !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupResolution['id'];
        }

        $domainClause = '';
        if ($domainResolution['id'] !== null) {
            $domainClause = 'AND d.id = :domain_id';
            $bindParams[':domain_id'] = $domainResolution['id'];
        }

        $authorizationClause = '';
        if ($domainResolution['id'] === null) {
            $authorization = AccessScopeValidator::buildDomainAuthorizationClause($bindParams, 'd.id');
            if ($authorization !== null) {
                if ($authorization['allowed'] === false) {
                    return [];
                }

                $authorizationClause = 'AND ' . $authorization['clause'];
            }
        }

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
                    (
                        SUM(
                            CASE
                                WHEN dmar.disposition = 'none'
                                    AND dmar.dkim_result = 'pass'
                                    AND dmar.spf_result = 'pass'
                                THEN dmar.count
                                ELSE 0
                            END
                        ) * 100.0
                    ) /
                    NULLIF(SUM(dmar.count), 0),
                    2
                ) as health_score
            FROM domains d
            JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            $groupJoin
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $domainClause
            $groupClause
            $authorizationClause
            GROUP BY d.id, d.domain
            HAVING total_volume > 0
            ORDER BY health_score DESC
        ";

        $db->query($query);
        foreach ($bindParams as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

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
     * @param int|null $groupId
     * @return array
     */
    public static function getSummaryStatistics(
        string $startDate,
        string $endDate,
        string $domain = '',
        ?int $groupId = null
    ): array {
        $range = self::resolveDateRange($startDate, $endDate);
        if ($range === null) {
            return [];
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domain);
        if (!$domainResolution['authorized']) {
            return [];
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupId);
        if (!$groupResolution['authorized']) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $bindParams = [
            ':start_date' => $range['start'],
            ':end_date' => $range['end']
        ];

        $additionalConditions = [];

        if ($domainResolution['id'] !== null) {
            $additionalConditions[] = 'd.id = :domain_id';
            $bindParams[':domain_id'] = $domainResolution['id'];
        } else {
            $authorization = AccessScopeValidator::buildDomainAuthorizationClause($bindParams, 'dar.domain_id');
            if ($authorization !== null) {
                if (($authorization['allowed'] ?? true) === false) {
                    return [];
                }
                $additionalConditions[] = $authorization['clause'];
            }
        }

        $groupJoin = '';
        $groupClause = '';
        if ($groupResolution['id'] !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupResolution['id'];
        }

        $whereClause = '';
        if (!empty($additionalConditions)) {
            $whereClause = 'AND ' . implode(' AND ', $additionalConditions);
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
            $groupJoin
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupClause
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
     * @param int|null $groupId
     * @param string|null $domainFilter
     * @return array
     */
    public static function getTopThreats(
        string $startDate,
        string $endDate,
        int $limit = 10,
        ?int $groupId = null,
        ?string $domainFilter = null
    ): array {
        $range = self::resolveDateRange($startDate, $endDate);
        if ($range === null) {
            return [];
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domainFilter);
        if (!$domainResolution['authorized']) {
            return [];
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupId);
        if (!$groupResolution['authorized']) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $groupJoin = '';
        $groupClause = '';
        $bindParams = [
            ':start_date' => $range['start'],
            ':end_date' => $range['end'],
            ':limit' => $limit,
        ];

        if ($groupResolution['id'] !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupResolution['id'];
        }

        $domainClause = '';
        if ($domainResolution['id'] !== null) {
            $domainClause = 'AND d.id = :domain_id';
            $bindParams[':domain_id'] = $domainResolution['id'];
        }

        $authorizationClause = '';
        if ($domainResolution['id'] === null) {
            $authorization = AccessScopeValidator::buildDomainAuthorizationClause($bindParams, 'd.id');
            if ($authorization !== null) {
                if ($authorization['allowed'] === false) {
                    return [];
                }

                $authorizationClause = 'AND ' . $authorization['clause'];
            }
        }

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
            $groupJoin
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            AND dmar.disposition IN ('quarantine', 'reject')
            $domainClause
            $groupClause
            $authorizationClause
            GROUP BY dmar.source_ip
            HAVING threat_volume > 0
            ORDER BY threat_volume DESC, threat_rate DESC
            LIMIT :limit
        ";

        $db->query($query);
        foreach ($bindParams as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Get compliance data over time
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $domain
     * @param int|null $groupId
     * @return array
     */
    public static function getComplianceData(
        string $startDate,
        string $endDate,
        string $domain = '',
        ?int $groupId = null
    ): array {
        $range = self::resolveDateRange($startDate, $endDate);
        if ($range === null) {
            return [];
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domain);
        if (!$domainResolution['authorized']) {
            return [];
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupId);
        if (!$groupResolution['authorized']) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $bindParams = [
            ':start_date' => $range['start'],
            ':end_date' => $range['end']
        ];

        $whereConditions = [];

        if ($domainResolution['id'] !== null) {
            $whereConditions[] = 'd.id = :domain_id';
            $bindParams[':domain_id'] = $domainResolution['id'];
        } else {
            $authorization = AccessScopeValidator::buildDomainAuthorizationClause($bindParams, 'dar.domain_id');
            if ($authorization !== null) {
                if ($authorization['allowed'] === false) {
                    return [];
                }

                $whereConditions[] = $authorization['clause'];
            }
        }

        $groupJoin = '';
        $groupClause = '';
        if ($groupResolution['id'] !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupResolution['id'];
        }

        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'AND ' . implode(' AND ', $whereConditions);
        }

        $dateExpression = self::getDateBucketExpression('dar.date_range_begin');

        $query = "
            SELECT
                {$dateExpression} as date,
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
            $groupJoin
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupClause
            GROUP BY {$dateExpression}
            HAVING SUM(dmar.count) > 0
            ORDER BY date ASC
        ";

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        return $db->resultSet();
    }

    /**
     * Determine the SQL expression for converting a UNIX timestamp to a date.
     */
    private static function getDateBucketExpression(string $column): string
    {
        $db = DatabaseManager::getInstance();
        $driver = strtolower($db->getDriverName());
        $isSqlite = (defined('USE_SQLITE') && USE_SQLITE) || strpos($driver, 'sqlite') !== false;

        if ($isSqlite) {
            return "DATE(datetime($column, 'unixepoch'))";
        }

        return "DATE(FROM_UNIXTIME($column))";
    }

    /**
     * Resolve the provided dates into UNIX timestamps.
     *
     * @return array{start:int,end:int}|null
     */
    private static function resolveDateRange(string $startDate, string $endDate): ?array
    {
        $start = self::parseDate($startDate);
        $end = self::parseDate($endDate);

        if ($start === null || $end === null) {
            return null;
        }

        if ($end < $start) {
            ErrorManager::getInstance()->log(
                sprintf('Invalid analytics range: %s - %s.', $startDate, $endDate),
                'warning'
            );

            return null;
        }

        return [
            'start' => $start->setTime(0, 0)->getTimestamp(),
            'end' => $end->setTime(23, 59, 59)->getTimestamp(),
        ];
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            ErrorManager::getInstance()->log(
                sprintf('Invalid analytics date provided: %s.', $value),
                'warning'
            );

            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            ErrorManager::getInstance()->log(
                sprintf('Invalid analytics date provided: %s.', $value),
                'warning'
            );

            return null;
        }

        return $date;
    }

}
