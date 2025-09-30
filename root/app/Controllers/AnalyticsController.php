<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\Analytics;
use App\Models\DmarcReport;

/**
 * Analytics Controller for DMARC dashboard analytics and visualizations
 */
class AnalyticsController extends Controller
{
    /**
     * Display analytics dashboard with trends and health scores
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_ANALYTICS);
        // Default to the last seven days when no explicit range is provided
        $defaultEndDate = date('Y-m-d');
        $defaultStartDate = date('Y-m-d', strtotime('-6 days'));

        $endDate = $_GET['end_date'] ?? $defaultEndDate;
        $startDate = $_GET['start_date'] ?? $defaultStartDate;
        $domain = $_GET['domain'] ?? '';

        // Get trend data for charts
        $trendData = Analytics::getTrendData($startDate, $endDate, $domain);

        // Get domain health scores
        $domainFilter = $domain !== '' ? $domain : null;

        $healthScores = Analytics::getDomainHealthScores($startDate, $endDate, null, $domainFilter);

        // Get summary statistics
        $summaryStats = Analytics::getSummaryStatistics($startDate, $endDate, $domain);

        // Get top threats (IPs with most failures)
        $topThreats = Analytics::getTopThreats($startDate, $endDate, 10, null, $domainFilter);

        // Get compliance trends
        $complianceData = Analytics::getComplianceData($startDate, $endDate, $domain);

        // Get all domains for filter
        $domains = \App\Models\Domain::getAllDomains();

        // Pass data to view
        $this->data = [
            'trend_data' => $trendData,
            'health_scores' => $healthScores,
            'summary_stats' => $summaryStats,
            'top_threats' => $topThreats,
            'compliance_data' => $complianceData,
            'domains' => $domains,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'domain' => $domain
            ]
        ];

        require __DIR__ . '/../Views/analytics.php';
    }

    /**
     * Handle filter form submissions
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_ANALYTICS);
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /analytics');
            exit();
        }
        // Redirect to GET request with parameters
        $params = [];

        if (!empty($_POST['start_date'])) {
            $params['start_date'] = $_POST['start_date'];
        }
        if (!empty($_POST['end_date'])) {
            $params['end_date'] = $_POST['end_date'];
        }
        if (!empty($_POST['domain'])) {
            $params['domain'] = $_POST['domain'];
        }

        $queryString = http_build_query($params);
        $url = '/analytics' . ($queryString ? '?' . $queryString : '');

        header('Location: ' . $url);
        exit();
    }
}
