<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Models\DmarcReport;
use App\Services\GeoIPService;

/**
 * Report Detail Controller for displaying individual DMARC report details
 */
class ReportDetailController extends Controller
{
    /**
     * Data passed to views
     * @var array
     */
    protected array $data = [];
    /**
     * Display detailed view of a specific DMARC report
     */
    public function handleRequest($id = null): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);
        // Get report ID from the route parameters
        $reportId = $id ?? null;

        if (!$reportId || !is_numeric($reportId)) {
            header('HTTP/1.0 404 Not Found');
            require __DIR__ . '/../Views/404.php';
            return;
        }

        $reportId = (int) $reportId;
        // Get the report details
        $report = DmarcReport::getReportDetails($reportId);

        if (!$report) {
            header('HTTP/1.0 404 Not Found');
            require __DIR__ . '/../Views/404.php';
            return;
        }

        // Get the aggregate records for this report
        $records = DmarcReport::getAggregateRecords($reportId);

        // Calculate summary statistics
        $summary = [
            'total_volume' => array_sum(array_column($records, 'count')),
            'pass_count' => 0,
            'quarantine_count' => 0,
            'reject_count' => 0,
            'dkim_pass_count' => 0,
            'spf_pass_count' => 0,
            'unique_ips' => count(array_unique(array_column($records, 'source_ip'))),
            'policy_evaluated_reason_volume' => 0,
            'policy_override_volume' => 0,
            'auth_results_volume' => 0,
            'policy_evaluated_reason_breakdown' => [],
            'policy_override_breakdown' => [],
            'auth_result_breakdown' => []
        ];

        foreach ($records as $record) {
            switch ($record['disposition']) {
                case 'none':
                    $summary['pass_count'] += $record['count'];
                    break;
                case 'quarantine':
                    $summary['quarantine_count'] += $record['count'];
                    break;
                case 'reject':
                    $summary['reject_count'] += $record['count'];
                    break;
            }

            if ($record['dkim_result'] === 'pass') {
                $summary['dkim_pass_count'] += $record['count'];
            }
            if ($record['spf_result'] === 'pass') {
                $summary['spf_pass_count'] += $record['count'];
            }

            if (!empty($record['policy_evaluated_reasons'])) {
                $summary['policy_evaluated_reason_volume'] += $record['count'];
                foreach ($record['policy_evaluated_reasons'] as $reason) {
                    $label = $reason['type'] ?? 'unspecified';
                    $labelKey = $label !== null && $label !== '' ? $label : 'unspecified';
                    $summary['policy_evaluated_reason_breakdown'][$labelKey] = ($summary['policy_evaluated_reason_breakdown'][$labelKey] ?? 0) + $record['count'];
                }
            }

            if (!empty($record['policy_override_reasons'])) {
                $summary['policy_override_volume'] += $record['count'];
                foreach ($record['policy_override_reasons'] as $reason) {
                    $label = $reason['type'] ?? 'unspecified';
                    $labelKey = $label !== null && $label !== '' ? $label : 'unspecified';
                    $summary['policy_override_breakdown'][$labelKey] = ($summary['policy_override_breakdown'][$labelKey] ?? 0) + $record['count'];
                }
            }

            if (!empty($record['auth_results'])) {
                $summary['auth_results_volume'] += $record['count'];
                foreach ($record['auth_results'] as $method => $entries) {
                    $methodLabel = strtoupper((string) $method);
                    $summary['auth_result_breakdown'][$methodLabel] = ($summary['auth_result_breakdown'][$methodLabel] ?? 0) + $record['count'];
                }
            }
        }

        arsort($summary['policy_evaluated_reason_breakdown']);
        arsort($summary['policy_override_breakdown']);
        arsort($summary['auth_result_breakdown']);

        // Group records by source IP for better visualization
        $ipGroups = [];
        foreach ($records as $record) {
            $ip = $record['source_ip'];
            if (!isset($ipGroups[$ip])) {
                $ipGroups[$ip] = [
                    'ip' => $ip,
                    'total_count' => 0,
                    'records' => []
                ];
            }
            $ipGroups[$ip]['total_count'] += $record['count'];
            $ipGroups[$ip]['records'][] = $record;
        }

        // Sort IP groups by volume (highest first)
        uasort($ipGroups, function ($a, $b) {
            return $b['total_count'] - $a['total_count'];
        });

        $ipIntelligence = [];
        $geoService = GeoIPService::getInstance();

        foreach (array_keys($ipGroups) as $ip) {
            $ipIntelligence[$ip] = $geoService->getIPIntelligence($ip);
        }

        // Pass data to view
        $this->data = [
            'report' => $report,
            'records' => $records,
            'summary' => $summary,
            'ip_groups' => $ipGroups,
            'ip_intelligence' => $ipIntelligence
        ];

        require __DIR__ . '/../Views/report_detail.php';
    }
}
