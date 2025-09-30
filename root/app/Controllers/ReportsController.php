<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\DmarcReport;
use App\Models\Domain;
use App\Core\RBACManager;
use App\Core\Mailer;
use App\Helpers\MessageHelper;

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
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);
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
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        if (($_POST['action'] ?? '') === 'send_report_email') {
            $this->sendReportByEmail();

            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                return;
            }

            header('Location: /reports');
            exit();
        }

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

    /**
     * Send a selected aggregate report via email using the configured template.
     */
    private function sendReportByEmail(): void
    {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $recipientsInput = $_POST['recipients'] ?? '';
        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientsInput))));

        if ($reportId <= 0) {
            MessageHelper::addMessage('Invalid report selected for emailing.', 'error');
            return;
        }

        if (empty($recipients)) {
            MessageHelper::addMessage('Please provide at least one recipient email address.', 'error');
            return;
        }

        $report = DmarcReport::getReportDetails($reportId);

        if (!$report) {
            MessageHelper::addMessage('Unable to load the requested report.', 'error');
            return;
        }

        $records = DmarcReport::getAggregateRecords($reportId);
        $summary = $this->buildReportSummary($records);

        $subject = sprintf(
            'DMARC Report for %s (%s - %s)',
            $report['domain'] ?? 'domain',
            date('Y-m-d', (int) $report['date_range_begin']),
            date('Y-m-d', (int) $report['date_range_end'])
        );

        $templateData = [
            'subject' => $subject,
            'report' => $report,
            'summary' => $summary,
            'records' => array_slice($records, 0, 25),
        ];

        $success = true;
        $failedRecipients = [];

        foreach ($recipients as $recipient) {
            $sent = Mailer::sendTemplate($recipient, $subject, 'report_send', $templateData);
            if (!$sent) {
                $success = false;
                $failedRecipients[] = $recipient;
            }
        }

        if ($success) {
            MessageHelper::addMessage('Report emailed successfully to ' . implode(', ', $recipients) . '.', 'success');
        } else {
            MessageHelper::addMessage(
                'Unable to send the report to: ' . implode(', ', $failedRecipients) . '.',
                'error'
            );
        }
    }

    /**
     * Build summary metrics for a DMARC aggregate report.
     */
    private function buildReportSummary(array $records): array
    {
        $summary = [
            'total_volume' => 0,
            'passed_count' => 0,
            'quarantined_count' => 0,
            'rejected_count' => 0,
            'dkim_pass_count' => 0,
            'spf_pass_count' => 0,
        ];

        foreach ($records as $record) {
            $count = (int) ($record['count'] ?? 0);
            $summary['total_volume'] += $count;

            $disposition = $record['disposition'] ?? '';
            if ($disposition === 'none') {
                $summary['passed_count'] += $count;
            } elseif ($disposition === 'quarantine') {
                $summary['quarantined_count'] += $count;
            } elseif ($disposition === 'reject') {
                $summary['rejected_count'] += $count;
            }

            if (($record['dkim_result'] ?? '') === 'pass') {
                $summary['dkim_pass_count'] += $count;
            }

            if (($record['spf_result'] ?? '') === 'pass') {
                $summary['spf_pass_count'] += $count;
            }
        }

        return $summary;
    }
}
