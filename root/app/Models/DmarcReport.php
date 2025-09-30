<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

class DmarcReport
{
    /**
     * Store an aggregate DMARC report.
     *
     * @param array $reportData
     * @return int Report ID
     */
    public static function storeAggregateReport(array $reportData): int
    {
        $db = DatabaseManager::getInstance();
        $domainId = Domain::getOrCreateDomain($reportData['policy_published_domain']);

        $db->query('
            INSERT INTO dmarc_aggregate_reports 
            (domain_id, org_name, email, extra_contact_info, report_id, 
             date_range_begin, date_range_end, raw_xml) 
            VALUES 
            (:domain_id, :org_name, :email, :extra_contact_info, :report_id, 
             :date_range_begin, :date_range_end, :raw_xml)
        ');

        $db->bind(':domain_id', $domainId);
        $db->bind(':org_name', $reportData['org_name']);
        $db->bind(':email', $reportData['email']);
        $db->bind(':extra_contact_info', $reportData['extra_contact_info'] ?? null);
        $db->bind(':report_id', $reportData['report_id']);
        $db->bind(':date_range_begin', $reportData['date_range_begin']);
        $db->bind(':date_range_end', $reportData['date_range_end']);
        $db->bind(':raw_xml', $reportData['raw_xml'] ?? null);

        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Store individual records for an aggregate report.
     *
     * @param int $reportId
     * @param array $records
     * @return bool
     */
    public static function storeAggregateRecords(int $reportId, array $records): bool
    {
        $db = DatabaseManager::getInstance();

        foreach ($records as $record) {
            $db->query('
                INSERT INTO dmarc_aggregate_records 
                (report_id, source_ip, count, disposition, dkim_result, spf_result, 
                 header_from, envelope_from, envelope_to) 
                VALUES 
                (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, 
                 :header_from, :envelope_from, :envelope_to)
            ');

            $db->bind(':report_id', $reportId);
            $db->bind(':source_ip', $record['source_ip']);
            $db->bind(':count', $record['count'] ?? 1);
            $db->bind(':disposition', $record['disposition']);
            $db->bind(':dkim_result', $record['dkim_result'] ?? null);
            $db->bind(':spf_result', $record['spf_result'] ?? null);
            $db->bind(':header_from', $record['header_from'] ?? null);
            $db->bind(':envelope_from', $record['envelope_from'] ?? null);
            $db->bind(':envelope_to', $record['envelope_to'] ?? null);

            $db->execute();
        }

        return true;
    }

    /**
     * Get aggregate reports for a domain.
     *
     * @param int $domainId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAggregateReports(?int $domainId = null, int $limit = 50, int $offset = 0): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $domainId = $domainId !== null ? (int) $domainId : null;
        if ($domainId !== null && $domainId > 0 && !$rbac->canAccessDomain($domainId)) {
            return [];
        }

        $clauses = [];
        $bindings = [];

        if ($domainId !== null && $domainId > 0) {
            $clauses[] = 'dar.domain_id = :domain_id';
            $bindings[':domain_id'] = $domainId;
        }

        $isAdmin = $rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN;

        if (!$isAdmin && ($domainId === null || $domainId <= 0)) {
            $accessibleDomainIds = self::getAccessibleDomainIds();

            if (empty($accessibleDomainIds)) {
                return [];
            }

            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            $clauses[] = 'dar.domain_id IN (' . implode(', ', $placeholders) . ')';
            $bindings = array_merge($bindings, $inBindings);
        }

        $whereClause = '';
        if (!empty($clauses)) {
            $whereClause = 'WHERE ' . implode(' AND ', $clauses);
        }

        $db->query(
            "
            SELECT dar.*, d.domain
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            $whereClause
            ORDER BY dar.received_at DESC
            LIMIT :limit OFFSET :offset
        "
        );

        foreach ($bindings as $param => $value) {
            $db->bind($param, $value);
        }

        $db->bind(':limit', $limit);
        $db->bind(':offset', $offset);

        return $db->resultSet();
    }

    /**
     * Store a forensic DMARC report.
     *
     * @param array $reportData
     * @return int Report ID
     */
    public static function storeForensicReport(array $reportData): int
    {
        $db = DatabaseManager::getInstance();
        $domainId = Domain::getOrCreateDomain($reportData['domain']);

        $db->query('
            INSERT INTO dmarc_forensic_reports 
            (domain_id, arrival_date, source_ip, authentication_results, 
             original_envelope_id, dkim_domain, dkim_selector, dkim_result, 
             spf_domain, spf_result, raw_message) 
            VALUES 
            (:domain_id, :arrival_date, :source_ip, :authentication_results, 
             :original_envelope_id, :dkim_domain, :dkim_selector, :dkim_result, 
             :spf_domain, :spf_result, :raw_message)
        ');

        $db->bind(':domain_id', $domainId);
        $db->bind(':arrival_date', $reportData['arrival_date']);
        $db->bind(':source_ip', $reportData['source_ip']);
        $db->bind(':authentication_results', $reportData['authentication_results'] ?? null);
        $db->bind(':original_envelope_id', $reportData['original_envelope_id'] ?? null);
        $db->bind(':dkim_domain', $reportData['dkim_domain'] ?? null);
        $db->bind(':dkim_selector', $reportData['dkim_selector'] ?? null);
        $db->bind(':dkim_result', $reportData['dkim_result'] ?? null);
        $db->bind(':spf_domain', $reportData['spf_domain'] ?? null);
        $db->bind(':spf_result', $reportData['spf_result'] ?? null);
        $db->bind(':raw_message', $reportData['raw_message'] ?? null);

        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Retrieve stored forensic reports with optional domain filtering.
     */
    public static function getForensicReports(?int $domainId = null, int $limit = 50, int $offset = 0): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $clauses = [];
        $bindings = [];

        if ($domainId !== null && $domainId > 0) {
            if (!$rbac->canAccessDomain($domainId)) {
                return [];
            }
            $clauses[] = 'dfr.domain_id = :domain_id';
            $bindings[':domain_id'] = $domainId;
        } else {
            $accessibleDomainIds = self::getAccessibleDomainIds();
            if (empty($accessibleDomainIds)) {
                return [];
            }
            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            $clauses[] = 'dfr.domain_id IN (' . implode(', ', $placeholders) . ')';
            $bindings = array_merge($bindings, $inBindings);
        }

        $whereClause = '';
        if (!empty($clauses)) {
            $whereClause = 'WHERE ' . implode(' AND ', $clauses);
        }

        $db->query(
            "
            SELECT dfr.*, d.domain
            FROM dmarc_forensic_reports dfr
            JOIN domains d ON dfr.domain_id = d.id
            $whereClause
            ORDER BY dfr.arrival_date DESC
            LIMIT :limit OFFSET :offset
        "
        );

        foreach ($bindings as $param => $value) {
            $db->bind($param, $value);
        }

        $db->bind(':limit', $limit);
        $db->bind(':offset', $offset);

        return $db->resultSet();
    }

    /**
     * Retrieve a single forensic report by ID.
     */
    public static function getForensicReportById(int $reportId): ?array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $db->query(
            '
            SELECT dfr.*, d.domain
            FROM dmarc_forensic_reports dfr
            JOIN domains d ON dfr.domain_id = d.id
            WHERE dfr.id = :id
        '
        );
        $db->bind(':id', $reportId);
        $report = $db->single();

        if (!$report) {
            return null;
        }

        $domainId = (int) $report['domain_id'];
        if (!$rbac->canAccessDomain($domainId)) {
            return null;
        }

        return $report;
    }

    /**
     * Get recent aggregate reports summary for dashboard.
     *
     * @param int $days
     * @return array
     */
    public static function getDashboardSummary(int $days = 7): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $isAdmin = $rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN;
        $domainFilterClause = '';
        $bindings = [];

        $normalizedDays = max(0, $days);
        $cutoffTimestamp = time() - ($normalizedDays * 24 * 60 * 60);

        if (!$isAdmin) {
            $accessibleDomainIds = self::getAccessibleDomainIds();

            if (empty($accessibleDomainIds)) {
                return [];
            }

            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            $domainFilterClause = ' AND d.id IN (' . implode(', ', $placeholders) . ')';
            $bindings = $inBindings;
        }

        $db->query('
            SELECT
                d.domain,
                COUNT(dar.id) as report_count,
                MAX(dar.date_range_end) as last_report_date,
                SUM(CASE WHEN dmar.disposition = "reject" THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.disposition = "quarantine" THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = "none" THEN dmar.count ELSE 0 END) as passed_count
            FROM domains d
            LEFT JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_end >= :cutoff_timestamp' . $domainFilterClause . '
            GROUP BY d.id, d.domain
            ORDER BY report_count DESC
        ');

        $db->bind(':cutoff_timestamp', $cutoffTimestamp);

        foreach ($bindings as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Get filtered aggregate reports with detailed information.
     *
     * @param array $filters
     * @return array
     */
    public static function getFilteredReports(array $filters): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $isAdmin = $rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN;
        $accessibleDomains = $rbac->getAccessibleDomains();

        if (!$isAdmin && empty($accessibleDomains)) {
            return [];
        }

        $accessibleDomainIds = array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
        $accessibleDomainMap = [];
        foreach ($accessibleDomains as $domain) {
            if (isset($domain['domain'])) {
                $accessibleDomainMap[$domain['domain']] = (int) $domain['id'];
            }
        }

        $whereConditions = [];
        $bindParams = [];

        // Build WHERE clause based on filters
        if (!empty($filters['domain'])) {
            if (!$isAdmin && !isset($accessibleDomainMap[$filters['domain']])) {
                return [];
            }
            $whereConditions[] = 'd.domain = :domain';
            $bindParams[':domain'] = $filters['domain'];
        }

        if (!empty($filters['disposition'])) {
            $whereConditions[] = 'dmar.disposition = :disposition';
            $bindParams[':disposition'] = $filters['disposition'];
        }

        if (!empty($filters['date_from'])) {
            $dateFrom = strtotime($filters['date_from']);
            if ($dateFrom !== false) {
                $whereConditions[] = 'dar.date_range_begin >= :date_from_ts';
                $bindParams[':date_from_ts'] = $dateFrom;
            }
        }

        if (!empty($filters['date_to'])) {
            $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
            if ($dateTo !== false) {
                $whereConditions[] = 'dar.date_range_end <= :date_to_ts';
                $bindParams[':date_to_ts'] = $dateTo;
            }
        }

        if (!$isAdmin && empty($filters['domain'])) {
            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            $whereConditions[] = 'dar.domain_id IN (' . implode(', ', $placeholders) . ')';
            $bindParams = array_merge($bindParams, $inBindings);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Build ORDER BY clause
        $sortBy = $filters['sort_by'] ?? 'dar.received_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');

        // Validate sort direction
        if (!in_array($sortDir, ['ASC', 'DESC'])) {
            $sortDir = 'DESC';
        }

        // Validate sort column
        $allowedSortColumns = [
            'received_at' => 'dar.received_at',
            'domain' => 'd.domain',
            'org_name' => 'dar.org_name',
            'date_range_begin' => 'dar.date_range_begin',
            'date_range_end' => 'dar.date_range_end',
            'report_count' => 'total_records'
        ];

        $orderColumn = $allowedSortColumns[$sortBy] ?? 'dar.received_at';

        $query = "
            SELECT 
                dar.id,
                dar.org_name,
                dar.email,
                dar.report_id,
                dar.date_range_begin,
                dar.date_range_end,
                dar.received_at,
                d.domain,
                COUNT(dmar.id) as total_records,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) as dkim_pass_count,
                SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) as spf_pass_count
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            $whereClause
            GROUP BY dar.id, dar.org_name, dar.email, dar.report_id, dar.date_range_begin, 
                     dar.date_range_end, dar.received_at, d.domain
            ORDER BY $orderColumn $sortDir
            LIMIT :limit OFFSET :offset
        ";

        $db->query($query);

        // Bind parameters
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        $db->bind(':limit', (int) ($filters['limit'] ?? 25));
        $db->bind(':offset', (int) ($filters['offset'] ?? 0));

        return $db->resultSet();
    }

    /**
     * Get count of filtered aggregate reports.
     *
     * @param array $filters
     * @return int
     */
    public static function getFilteredReportsCount(array $filters): int
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $isAdmin = $rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN;
        $accessibleDomains = $rbac->getAccessibleDomains();

        if (!$isAdmin && empty($accessibleDomains)) {
            return 0;
        }

        $accessibleDomainIds = array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
        $accessibleDomainMap = [];
        foreach ($accessibleDomains as $domain) {
            if (isset($domain['domain'])) {
                $accessibleDomainMap[$domain['domain']] = (int) $domain['id'];
            }
        }

        $whereConditions = [];
        $bindParams = [];

        // Build WHERE clause based on filters
        if (!empty($filters['domain'])) {
            if (!$isAdmin && !isset($accessibleDomainMap[$filters['domain']])) {
                return 0;
            }
            $whereConditions[] = 'd.domain = :domain';
            $bindParams[':domain'] = $filters['domain'];
        }

        if (!empty($filters['disposition'])) {
            $whereConditions[] = 'dmar.disposition = :disposition';
            $bindParams[':disposition'] = $filters['disposition'];
        }

        if (!empty($filters['date_from'])) {
            $dateFrom = strtotime($filters['date_from']);
            if ($dateFrom !== false) {
                $whereConditions[] = 'dar.date_range_begin >= :date_from_ts';
                $bindParams[':date_from_ts'] = $dateFrom;
            }
        }

        if (!empty($filters['date_to'])) {
            $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
            if ($dateTo !== false) {
                $whereConditions[] = 'dar.date_range_end <= :date_to_ts';
                $bindParams[':date_to_ts'] = $dateTo;
            }
        }

        if (!$isAdmin && empty($filters['domain'])) {
            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            $whereConditions[] = 'dar.domain_id IN (' . implode(', ', $placeholders) . ')';
            $bindParams = array_merge($bindParams, $inBindings);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "
            SELECT COUNT(DISTINCT dar.id) as total
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            $whereClause
        ";

        $db->query($query);

        // Bind parameters
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        $result = $db->single();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Get detailed information for a specific report.
     *
     * @param int $reportId
     * @return array|null
     */
    public static function getReportDetails(int $reportId): ?array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $db->query('
            SELECT
                dar.*, 
                d.domain
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            WHERE dar.id = :report_id
        ');

        $db->bind(':report_id', $reportId);
        $result = $db->single();

        if (!$result) {
            return null;
        }

        if (!$rbac->canAccessDomain((int) $result['domain_id'])) {
            return null;
        }

        return $result;
    }

    /**
     * Get aggregate records for a specific report.
     *
     * @param int $reportId
     * @return array
     */
    public static function getAggregateRecords(int $reportId): array
    {
        if (!self::canAccessReport($reportId)) {
            return [];
        }

        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT *
            FROM dmarc_aggregate_records
            WHERE report_id = :report_id
            ORDER BY count DESC, source_ip ASC
        ');

        $db->bind(':report_id', $reportId);
        return $db->resultSet();
    }

    /**
     * Retrieve the domain IDs accessible to the current user.
     *
     * @return array<int>
     */
    private static function getAccessibleDomainIds(): array
    {
        $accessibleDomains = RBACManager::getInstance()->getAccessibleDomains();

        return array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
    }

    /**
     * Build placeholders and bindings for a parameterized IN clause.
     *
     * @param array<int> $ids
     * @param string $prefix
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private static function buildInClause(array $ids, string $prefix): array
    {
        $placeholders = [];
        $bindings = [];

        foreach (array_values($ids) as $index => $id) {
            $placeholder = ':' . $prefix . '_' . $index;
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = (int) $id;
        }

        return [$placeholders, $bindings];
    }

    /**
     * Determine whether the current user can access a given report.
     */
    private static function canAccessReport(int $reportId): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT domain_id FROM dmarc_aggregate_reports WHERE id = :report_id');
        $db->bind(':report_id', $reportId);
        $result = $db->single();

        if (!$result) {
            return false;
        }

        return RBACManager::getInstance()->canAccessDomain((int) $result['domain_id']);
    }
}
