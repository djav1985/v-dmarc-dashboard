<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * PDF Report model for generating and managing PDF reports
 */
class PdfReport
{
    /**
     * Get all report templates
     *
     * @return array
     */
    public static function getAllTemplates(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                prt.*,
                COUNT(prg.id) as usage_count
            FROM pdf_report_templates prt
            LEFT JOIN pdf_report_generations prg ON prt.id = prg.template_id
            GROUP BY prt.id
            ORDER BY prt.template_type, prt.name
        ');

        return $db->resultSet();
    }

    /**
     * Generate PDF report data
     *
     * @param int $templateId
     * @param string $startDate
     * @param string $endDate
     * @param string $domainFilter
     * @param int|null $groupFilter
     * @return array
     */
    public static function generateReportData(
        int $templateId,
        string $startDate,
        string $endDate,
        string $domainFilter = '',
        ?int $groupFilter = null
    ): array {
        $db = DatabaseManager::getInstance();

        // Get template details
        $db->query('SELECT * FROM pdf_report_templates WHERE id = :template_id');
        $db->bind(':template_id', $templateId);
        $template = $db->single();

        if (!$template) {
            return [];
        }

        $sections = json_decode($template['sections'], true);
        $reportData = [
            'template' => $template,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'filters' => ['domain' => $domainFilter, 'group' => $groupFilter],
            'sections' => []
        ];

        // Generate data for each section
        foreach ($sections as $section) {
            $reportData['sections'][$section] = self::generateSectionData(
                $section,
                $startDate,
                $endDate,
                $domainFilter,
                $groupFilter
            );
        }

        return $reportData;
    }

    /**
     * Generate data for a specific report section
     *
     * @param string $section
     * @param string $startDate
     * @param string $endDate
     * @param string $domainFilter
     * @param int|null $groupFilter
     * @return array
     */
    private static function generateSectionData(
        string $section,
        string $startDate,
        string $endDate,
        string $domainFilter,
        ?int $groupFilter
    ): array {
        switch ($section) {
            case 'summary':
                return self::generateSummaryData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'domain_health':
                return self::generateDomainHealthData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'top_threats':
                return self::generateTopThreatsData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'compliance_status':
                return self::generateComplianceData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'detailed_analytics':
                return self::generateDetailedAnalyticsData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'volume_trends':
                return self::generateVolumeTrendsData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'authentication_breakdown':
                return self::generateAuthenticationBreakdownData($startDate, $endDate, $domainFilter, $groupFilter);
            case 'recommendations':
                return self::generateRecommendationsData($startDate, $endDate, $domainFilter, $groupFilter);
            default:
                return [];
        }
    }

    /**
     * Generate summary data section
    */
    private static function generateSummaryData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        return \App\Models\Analytics::getSummaryStatistics($startDate, $endDate, $domainFilter, $groupFilter);
    }

    /**
     * Generate domain health data section
     */
    private static function generateDomainHealthData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        $domain = $domainFilter !== '' ? $domainFilter : null;

        return \App\Models\Analytics::getDomainHealthScores($startDate, $endDate, $groupFilter, $domain);
    }

    /**
     * Generate top threats data section
     */
    private static function generateTopThreatsData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        $domain = $domainFilter !== '' ? $domainFilter : null;

        return \App\Models\Analytics::getTopThreats($startDate, $endDate, 20, $groupFilter, $domain);
    }

    /**
     * Generate compliance data section
     */
    private static function generateComplianceData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        return \App\Models\Analytics::getComplianceData($startDate, $endDate, $domainFilter, $groupFilter);
    }

    /**
     * Generate detailed analytics data section
     */
    private static function generateDetailedAnalyticsData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        $trendData = \App\Models\Analytics::getTrendData($startDate, $endDate, $domainFilter, $groupFilter);
        $complianceData = \App\Models\Analytics::getComplianceData($startDate, $endDate, $domainFilter, $groupFilter);

        return [
            'trends' => $trendData,
            'compliance' => $complianceData
        ];
    }

    /**
     * Generate volume trends data section
     */
    private static function generateVolumeTrendsData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        return \App\Models\Analytics::getTrendData($startDate, $endDate, $domainFilter, $groupFilter);
    }

    /**
     * Generate authentication breakdown data section
     */
    private static function generateAuthenticationBreakdownData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        $db = DatabaseManager::getInstance();

        $whereClause = '';
        $bindParams = [
            ':start_date' => strtotime($startDate),
            ':end_date' => strtotime($endDate . ' 23:59:59')
        ];

        if (!empty($domainFilter)) {
            $whereClause = 'AND d.domain = :domain';
            $bindParams[':domain'] = $domainFilter;
        }

        $groupJoin = '';
        $groupClause = '';
        if ($groupFilter !== null) {
            $groupJoin = 'JOIN domain_group_assignments dga ON d.id = dga.domain_id';
            $groupClause = 'AND dga.group_id = :group_id';
            $bindParams[':group_id'] = $groupFilter;
        }

        $query = "
            SELECT
                dmar.dkim_result,
                dmar.spf_result,
                dmar.disposition,
                SUM(dmar.count) as volume,
                COUNT(DISTINCT dmar.source_ip) as unique_ips
            FROM dmarc_aggregate_reports dar
            JOIN domains d ON dar.domain_id = d.id
            $groupJoin
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date
            $whereClause
            $groupClause
            GROUP BY dmar.dkim_result, dmar.spf_result, dmar.disposition
            ORDER BY volume DESC
        ";

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        return $db->resultSet();
    }

    /**
     * Generate recommendations data section
     */
    private static function generateRecommendationsData(string $startDate, string $endDate, string $domainFilter, ?int $groupFilter): array
    {
        $healthScores = self::generateDomainHealthData($startDate, $endDate, $domainFilter, $groupFilter);
        $threats = self::generateTopThreatsData($startDate, $endDate, $domainFilter, $groupFilter);
        $compliance = self::generateComplianceData($startDate, $endDate, $domainFilter, $groupFilter);

        $recommendations = [];

        // Analyze health scores for recommendations
        foreach ($healthScores as $domain) {
            if ($domain['health_score'] < 95) {
                $recommendations[] = [
                    'type' => 'domain_health',
                    'priority' => $domain['health_score'] < 80 ? 'high' : 'medium',
                    'title' => "Improve authentication for {$domain['domain']}",
                    'description' => "Domain health score is {$domain['health_score']}%. Consider reviewing SPF and DKIM configurations.",
                    'domain' => $domain['domain']
                ];
            }
        }

        // Analyze threats for recommendations
        if (count($threats) > 0) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'title' => 'Investigate suspicious IP addresses',
                'description' => count($threats) . ' IP addresses show suspicious authentication failure patterns.',
                'details' => array_slice($threats, 0, 5)
            ];
        }

        // Analyze compliance for recommendations
        $avgDmarcCompliance = 0;
        if (!empty($compliance)) {
            $avgDmarcCompliance = array_sum(array_column($compliance, 'dmarc_compliance')) / count($compliance);
        }

        if ($avgDmarcCompliance < 90) {
            $recommendations[] = [
                'type' => 'compliance',
                'priority' => 'medium',
                'title' => 'Improve DMARC compliance',
                'description' => "Average DMARC compliance is {$avgDmarcCompliance}%. Consider policy adjustments.",
            ];
        }

        return $recommendations;
    }

    /**
     * Log PDF report generation
     *
     * @param array $data
     * @return int Generation ID
     */
    public static function logGeneration(array $data): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO pdf_report_generations
            (template_id, filename, file_path, title, date_range_start, date_range_end,
             domain_filter, group_filter, parameters, file_size, generated_by, schedule_id)
            VALUES
            (:template_id, :filename, :file_path, :title, :date_range_start, :date_range_end,
             :domain_filter, :group_filter, :parameters, :file_size, :generated_by, :schedule_id)
        ');

        $db->bind(':template_id', $data['template_id']);
        $db->bind(':filename', $data['filename']);
        $db->bind(':file_path', $data['file_path'] ?? null);
        $db->bind(':title', $data['title']);
        $db->bind(':date_range_start', $data['date_range_start']);
        $db->bind(':date_range_end', $data['date_range_end']);
        $db->bind(':domain_filter', $data['domain_filter'] ?? '');
        $db->bind(':group_filter', $data['group_filter'] ?? null);
        $db->bind(':parameters', json_encode($data['parameters'] ?? []));
        $db->bind(':file_size', $data['file_size'] ?? 0);
        $db->bind(':generated_by', $data['generated_by'] ?? 'Unknown');
        $db->bind(':schedule_id', $data['schedule_id'] ?? null);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Get recent PDF generations
     *
     * @param int $limit
     * @return array
     */
    public static function getRecentGenerations(int $limit = 10): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT
                prg.*,
                prt.name as template_name,
                prs.name as schedule_name
            FROM pdf_report_generations prg
            JOIN pdf_report_templates prt ON prg.template_id = prt.id
            LEFT JOIN pdf_report_schedules prs ON prg.schedule_id = prs.id
            ORDER BY prg.generated_at DESC
            LIMIT :limit
        ');

        $db->bind(':limit', $limit);
        return $db->resultSet();
    }
}
