<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\DmarcReport;
use App\Models\Domain;

/**
 * Reports Controller for DMARC report listing and filtering
 */
class ReportsController extends Controller
{
    /**
     * Display reports listing with filters and sorting
     */
    public function handleRequest(): void
    {
        // Get filter parameters from URL
        $domain = $_GET['domain'] ?? '';
        $disposition = $_GET['disposition'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $sortBy = $_GET['sort'] ?? 'received_at';
        $sortDir = $_GET['dir'] ?? 'DESC';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Get filtered reports
        $reports = DmarcReport::getFilteredReports([
            'domain' => $domain,
            'disposition' => $disposition,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'limit' => $perPage,
            'offset' => $offset
        ]);

        // Get total count for pagination
        $totalReports = DmarcReport::getFilteredReportsCount([
            'domain' => $domain,
            'disposition' => $disposition,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);

        // Get all domains for filter dropdown
        $domains = Domain::getAllDomains();

        // Calculate pagination
        $totalPages = ceil($totalReports / $perPage);

        // Pass data to view
        $this->data = [
            'reports' => $reports,
            'domains' => $domains,
            'filters' => [
                'domain' => $domain,
                'disposition' => $disposition,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_reports' => $totalReports,
                'per_page' => $perPage
            ]
        ];

        require __DIR__ . '/../Views/reports.php';
    }

    /**
     * Handle form submissions (filters, etc.)
     */
    public function handleSubmission(): void
    {
        // Redirect to GET request with parameters
        $params = [];

        if (!empty($_POST['domain'])) {
            $params['domain'] = $_POST['domain'];
        }
        if (!empty($_POST['disposition'])) {
            $params['disposition'] = $_POST['disposition'];
        }
        if (!empty($_POST['date_from'])) {
            $params['date_from'] = $_POST['date_from'];
        }
        if (!empty($_POST['date_to'])) {
            $params['date_to'] = $_POST['date_to'];
        }

        $queryString = http_build_query($params);
        $url = '/reports' . ($queryString ? '?' . $queryString : '');

        header('Location: ' . $url);
        exit();
    }
}
