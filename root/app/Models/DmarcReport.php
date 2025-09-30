<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

class DmarcReport
{
    public const FILTERABLE_FIELDS = [
        'domain',
        'disposition',
        'policy_result',
        'date_from',
        'date_to',
        'org_name',
        'source_ip',
        'dkim_result',
        'spf_result',
        'header_from',
        'envelope_from',
        'envelope_to',
        'ownership_contact',
        'enforcement_level',
        'report_id',
        'reporter_email',
        'has_failures',
        'min_volume',
        'max_volume',
        'sort_by',
        'sort_dir',
        'limit',
        'offset'
    ];

    private const FAILURE_VOLUME_EXPR = "COALESCE(SUM(CASE WHEN (dmar.disposition IN ('quarantine','reject') OR COALESCE(dmar.dkim_result, '') NOT IN ('pass') OR COALESCE(dmar.spf_result, '') NOT IN ('pass')) THEN dmar.count ELSE 0 END), 0)";

    private const ALLOWED_DISPOSITIONS = ['none', 'quarantine', 'reject'];

    private const ALLOWED_AUTH_RESULTS = ['pass', 'fail', 'softfail', 'neutral', 'temperror', 'permerror', 'none'];

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

        $query = "\n            SELECT\n                d.domain,\n                COUNT(dar.id) as report_count,\n                MAX(dar.date_range_end) as last_report_date,\n                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,\n                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,\n                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count\n            FROM domains d\n            LEFT JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id\n            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id\n            WHERE dar.date_range_end >= :cutoff_timestamp";
        $query .= $domainFilterClause;
        $query .= "\n            GROUP BY d.id, d.domain\n            ORDER BY report_count DESC\n        ";

        $db->query($query);

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
        $filters = self::normalizeFilterInput($filters);

        $db = DatabaseManager::getInstance();

        $context = self::buildFilterContext($filters, false);
        if ($context === null || ($context['abort'] ?? false)) {
            return [];
        }

        $whereClause = $context['whereClause'] ?? '';
        $havingClause = $context['havingClause'] ?? '';
        $orderClause = $context['orderClause'] ?? '';
        $limit = $context['limit'] ?? null;
        $offset = $context['offset'] ?? null;
        $bindings = $context['bindings'] ?? [];
        $havingBindings = $context['havingBindings'] ?? [];

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
                d.ownership_contact,
                d.enforcement_level,
                COUNT(dmar.id) as total_records,
                COALESCE(SUM(dmar.count), 0) as total_volume,
                SUM(CASE WHEN dmar.disposition = 'reject' THEN dmar.count ELSE 0 END) as rejected_count,
                SUM(CASE WHEN dmar.disposition = 'quarantine' THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = 'none' THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.dkim_result = 'pass' THEN dmar.count ELSE 0 END) as dkim_pass_count,
                SUM(CASE WHEN dmar.spf_result = 'pass' THEN dmar.count ELSE 0 END) as spf_pass_count,
                " . self::FAILURE_VOLUME_EXPR . " as failure_volume
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            $whereClause
            GROUP BY dar.id, dar.org_name, dar.email, dar.report_id, dar.date_range_begin,
                     dar.date_range_end, dar.received_at, d.domain, d.ownership_contact, d.enforcement_level
            $havingClause
            $orderClause";

        if ($limit !== null) {
            $query .= "\n            LIMIT :limit";
        }

        if ($offset !== null) {
            $query .= "\n            OFFSET :offset";
        }

        $db->query($query);

        foreach ($bindings as $param => $value) {
            $db->bind($param, $value);
        }

        foreach ($havingBindings as $param => $value) {
            $db->bind($param, $value);
        }

        if ($limit !== null) {
            $db->bind(':limit', $limit);
        }

        if ($offset !== null) {
            $db->bind(':offset', $offset);
        }

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
        $filters = self::normalizeFilterInput($filters);

        $db = DatabaseManager::getInstance();

        $context = self::buildFilterContext($filters, true);
        if ($context === null || ($context['abort'] ?? false)) {
            return 0;
        }

        $whereClause = $context['whereClause'] ?? '';
        $havingClause = $context['havingClause'] ?? '';
        $bindings = $context['bindings'] ?? [];
        $havingBindings = $context['havingBindings'] ?? [];

        $subQuery = "
            SELECT dar.id
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            $whereClause
            GROUP BY dar.id, dar.org_name, dar.email, dar.report_id, dar.date_range_begin,
                     dar.date_range_end, dar.received_at, d.domain, d.ownership_contact, d.enforcement_level
            $havingClause
        ";

        $db->query('SELECT COUNT(*) as total FROM (' . $subQuery . ') as filtered_reports');

        foreach ($bindings as $param => $value) {
            $db->bind($param, $value);
        }

        foreach ($havingBindings as $param => $value) {
            $db->bind($param, $value);
        }

        $result = $db->single();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Normalize the incoming filter payload to ensure consistent handling.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function normalizeFilterInput(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if (!in_array($key, self::FILTERABLE_FIELDS, true)) {
                continue;
            }

            switch ($key) {
                case 'domain':
                    if (is_array($value)) {
                        $domains = array_values(array_filter(array_map(static fn($domain) => trim((string) $domain), $value)));
                        if (!empty($domains)) {
                            $normalized['domain'] = $domains;
                        }
                    } else {
                        $domain = trim((string) $value);
                        if ($domain !== '') {
                            $normalized['domain'] = $domain;
                        }
                    }
                    break;
                case 'disposition':
                case 'policy_result':
                    if (is_array($value)) {
                        $dispositions = array_values(array_filter(array_map(static function ($item) {
                            $val = strtolower(trim((string) $item));
                            return in_array($val, self::ALLOWED_DISPOSITIONS, true) ? $val : null;
                        }, $value)));
                        if (!empty($dispositions)) {
                            $normalized['disposition'] = $dispositions;
                        }
                    } else {
                        $val = strtolower(trim((string) $value));
                        if (in_array($val, self::ALLOWED_DISPOSITIONS, true)) {
                            $normalized['disposition'] = $val;
                        }
                    }
                    break;
                case 'date_from':
                case 'date_to':
                    $stringValue = trim((string) $value);
                    if ($stringValue !== '') {
                        $normalized[$key] = $stringValue;
                    }
                    break;
                case 'org_name':
                case 'source_ip':
                case 'header_from':
                case 'envelope_from':
                case 'envelope_to':
                case 'ownership_contact':
                case 'enforcement_level':
                case 'report_id':
                case 'reporter_email':
                    if (is_array($value)) {
                        $values = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $value)));
                        if (!empty($values)) {
                            $normalized[$key] = $values;
                        }
                    } else {
                        $stringValue = trim((string) $value);
                        if ($stringValue !== '') {
                            $normalized[$key] = $stringValue;
                        }
                    }
                    break;
                case 'dkim_result':
                case 'spf_result':
                    $stringValue = strtolower(trim((string) $value));
                    if (in_array($stringValue, self::ALLOWED_AUTH_RESULTS, true)) {
                        $normalized[$key] = $stringValue;
                    }
                    break;
                case 'has_failures':
                    $normalized[$key] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($normalized[$key] === null) {
                        unset($normalized[$key]);
                    }
                    break;
                case 'min_volume':
                case 'max_volume':
                    if ($value === null || $value === '') {
                        break;
                    }
                    $intValue = (int) $value;
                    if ($intValue >= 0) {
                        $normalized[$key] = $intValue;
                    }
                    break;
                case 'sort_by':
                case 'sort_dir':
                    $normalized[$key] = $value;
                    break;
                case 'limit':
                    if ($value === null || $value === '') {
                        $normalized[$key] = null;
                        break;
                    }
                    $limit = (int) $value;
                    $normalized[$key] = $limit > 0 ? $limit : null;
                    break;
                case 'offset':
                    $offset = max(0, (int) $value);
                    $normalized[$key] = $offset;
                    break;
            }
        }

        if (!isset($normalized['disposition']) && isset($normalized['policy_result'])) {
            $normalized['disposition'] = $normalized['policy_result'];
        }

        unset($normalized['policy_result']);

        return $normalized;
    }

    /**
     * Build SQL fragments for the provided filters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>|null
     */
    private static function buildFilterContext(array $filters, bool $forCount): ?array
    {
        $rbac = RBACManager::getInstance();

        $isAdmin = $rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN;
        $accessibleDomains = $rbac->getAccessibleDomains();

        if (!$isAdmin && empty($accessibleDomains)) {
            return ['abort' => true];
        }

        $accessibleDomainIds = array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
        $accessibleDomainMap = [];
        foreach ($accessibleDomains as $domain) {
            if (isset($domain['domain'])) {
                $accessibleDomainMap[$domain['domain']] = (int) $domain['id'];
            }
        }

        $whereConditions = [];
        $bindings = [];
        $havingConditions = [];
        $havingBindings = [];

        // Domain scoping
        if (!empty($filters['domain'])) {
            $domains = is_array($filters['domain']) ? $filters['domain'] : [$filters['domain']];
            $domains = array_values(array_unique(array_filter(array_map(static fn($domain) => trim((string) $domain), $domains))));

            if (empty($domains)) {
                return ['abort' => true];
            }

            if (!$isAdmin) {
                foreach ($domains as $domainName) {
                    if (!isset($accessibleDomainMap[$domainName])) {
                        return ['abort' => true];
                    }
                }
            }

            $placeholders = [];
            foreach ($domains as $index => $domainName) {
                $placeholder = ':domain_' . $index;
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = $domainName;
            }

            if (!empty($placeholders)) {
                $whereConditions[] = 'd.domain IN (' . implode(', ', $placeholders) . ')';
            }
        } elseif (!$isAdmin) {
            if (empty($accessibleDomainIds)) {
                return ['abort' => true];
            }

            [$placeholders, $inBindings] = self::buildInClause($accessibleDomainIds, 'domain_id');
            if (empty($placeholders)) {
                return ['abort' => true];
            }

            $whereConditions[] = 'dar.domain_id IN (' . implode(', ', $placeholders) . ')';
            $bindings = array_merge($bindings, $inBindings);
        }

        if (!empty($filters['disposition'])) {
            $dispositions = is_array($filters['disposition']) ? $filters['disposition'] : [$filters['disposition']];
            $dispositions = array_values(array_filter(array_map(static fn($value) => strtolower(trim((string) $value)), $dispositions)));

            if (!empty($dispositions)) {
                $placeholders = [];
                foreach ($dispositions as $index => $value) {
                    if (!in_array($value, self::ALLOWED_DISPOSITIONS, true)) {
                        continue;
                    }
                    $placeholder = ':disposition_' . $index;
                    $placeholders[] = $placeholder;
                    $bindings[$placeholder] = $value;
                }

                if (!empty($placeholders)) {
                    $whereConditions[] = 'dmar.disposition IN (' . implode(', ', $placeholders) . ')';
                }
            }
        }

        if (!empty($filters['date_from'])) {
            $dateFrom = strtotime($filters['date_from']);
            if ($dateFrom !== false) {
                $bindings[':date_from_ts'] = $dateFrom;
                $whereConditions[] = 'dar.date_range_begin >= :date_from_ts';
            }
        }

        if (!empty($filters['date_to'])) {
            $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
            if ($dateTo !== false) {
                $bindings[':date_to_ts'] = $dateTo;
                $whereConditions[] = 'dar.date_range_end <= :date_to_ts';
            }
        }

        if (!empty($filters['org_name'])) {
            $placeholder = ':org_name';
            $bindings[$placeholder] = '%' . str_replace('%', '\\%', $filters['org_name']) . '%';
            $whereConditions[] = 'dar.org_name LIKE ' . $placeholder;
        }

        if (!empty($filters['report_id'])) {
            $placeholder = ':report_id_exact';
            $bindings[$placeholder] = $filters['report_id'];
            $whereConditions[] = 'dar.report_id = ' . $placeholder;
        }

        if (!empty($filters['reporter_email'])) {
            $placeholder = ':reporter_email';
            $bindings[$placeholder] = '%' . str_replace('%', '\\%', $filters['reporter_email']) . '%';
            $whereConditions[] = 'dar.email LIKE ' . $placeholder;
        }

        if (!empty($filters['source_ip'])) {
            $value = is_array($filters['source_ip']) ? $filters['source_ip'][0] : $filters['source_ip'];
            $placeholder = ':source_ip';
            $likeValue = str_replace('*', '%', $value);
            if (!str_contains($likeValue, '%')) {
                $likeValue = '%' . $likeValue . '%';
            }
            $bindings[$placeholder] = $likeValue;
            $whereConditions[] = 'dmar.source_ip LIKE ' . $placeholder;
        }

        if (!empty($filters['dkim_result'])) {
            $placeholder = ':dkim_result';
            $bindings[$placeholder] = $filters['dkim_result'];
            $whereConditions[] = "LOWER(COALESCE(dmar.dkim_result, '')) = LOWER($placeholder)";
        }

        if (!empty($filters['spf_result'])) {
            $placeholder = ':spf_result';
            $bindings[$placeholder] = $filters['spf_result'];
            $whereConditions[] = "LOWER(COALESCE(dmar.spf_result, '')) = LOWER($placeholder)";
        }

        foreach (['header_from' => 'dmar.header_from', 'envelope_from' => 'dmar.envelope_from', 'envelope_to' => 'dmar.envelope_to'] as $filterKey => $column) {
            if (!empty($filters[$filterKey])) {
                $value = is_array($filters[$filterKey]) ? $filters[$filterKey][0] : $filters[$filterKey];
                $placeholder = ':' . $filterKey;
                $bindings[$placeholder] = '%' . str_replace('%', '\\%', $value) . '%';
                $whereConditions[] = $column . ' LIKE ' . $placeholder;
            }
        }

        if (!empty($filters['ownership_contact'])) {
            $value = is_array($filters['ownership_contact']) ? $filters['ownership_contact'][0] : $filters['ownership_contact'];
            $placeholder = ':ownership_contact';
            $bindings[$placeholder] = '%' . str_replace('%', '\\%', $value) . '%';
            $whereConditions[] = 'd.ownership_contact LIKE ' . $placeholder;
        }

        if (!empty($filters['enforcement_level'])) {
            $levels = is_array($filters['enforcement_level']) ? $filters['enforcement_level'] : [$filters['enforcement_level']];
            $levels = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $levels)));
            if (!empty($levels)) {
                $placeholders = [];
                foreach ($levels as $index => $level) {
                    $placeholder = ':enforcement_' . $index;
                    $placeholders[] = $placeholder;
                    $bindings[$placeholder] = $level;
                }
                if (!empty($placeholders)) {
                    $whereConditions[] = 'd.enforcement_level IN (' . implode(', ', $placeholders) . ')';
                }
            }
        }

        if (!empty($filters['min_volume'])) {
            $havingConditions[] = 'COALESCE(SUM(dmar.count), 0) >= :min_volume';
            $havingBindings[':min_volume'] = (int) $filters['min_volume'];
        }

        if (!empty($filters['max_volume'])) {
            $havingConditions[] = 'COALESCE(SUM(dmar.count), 0) <= :max_volume';
            $havingBindings[':max_volume'] = (int) $filters['max_volume'];
        }

        if (($filters['has_failures'] ?? false) === true) {
            $havingConditions[] = self::FAILURE_VOLUME_EXPR . ' > 0';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $havingClause = !empty($havingConditions) ? 'HAVING ' . implode(' AND ', $havingConditions) : '';

        $sortBy = (string) ($filters['sort_by'] ?? 'received_at');
        $sortDir = strtoupper((string) ($filters['sort_dir'] ?? 'DESC'));

        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }

        $allowedSortColumns = [
            'received_at' => 'dar.received_at',
            'domain' => 'd.domain',
            'org_name' => 'dar.org_name',
            'date_range_begin' => 'dar.date_range_begin',
            'date_range_end' => 'dar.date_range_end',
            'total_records' => 'total_records',
            'total_volume' => 'total_volume',
            'failure_volume' => 'failure_volume',
            'rejected_count' => 'rejected_count',
            'quarantined_count' => 'quarantined_count',
            'passed_count' => 'passed_count',
            'dkim_pass_count' => 'dkim_pass_count',
            'spf_pass_count' => 'spf_pass_count',
            'enforcement_level' => 'd.enforcement_level',
            'ownership_contact' => 'd.ownership_contact'
        ];

        $orderColumn = $allowedSortColumns[$sortBy] ?? 'dar.received_at';
        $orderClause = 'ORDER BY ' . $orderColumn . ' ' . $sortDir;

        $limit = $filters['limit'] ?? ($forCount ? null : 25);
        $offset = $filters['offset'] ?? ($forCount ? null : 0);

        if ($limit !== null) {
            $limit = max(1, (int) $limit);
        }

        if ($offset !== null) {
            $offset = max(0, (int) $offset);
        }

        if ($forCount) {
            $orderClause = '';
            $limit = null;
            $offset = null;
        }

        return [
            'abort' => false,
            'whereClause' => $whereClause,
            'havingClause' => $havingClause,
            'orderClause' => $orderClause,
            'bindings' => $bindings,
            'havingBindings' => $havingBindings,
            'limit' => $limit,
            'offset' => $offset,
        ];
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
