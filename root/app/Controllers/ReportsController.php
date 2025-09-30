<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\DmarcReport;
use App\Models\Domain;
use App\Models\SavedReportFilter;
use App\Core\RBACManager;
use App\Core\Mailer;
use App\Core\Csrf;
use App\Helpers\MessageHelper;
use App\Utilities\ReportExport;

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
        $savedFilterId = isset($_GET['saved_filter_id']) ? (int) $_GET['saved_filter_id'] : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $resolution = $this->resolveFilters($_GET, $savedFilterId);
        $filters = $resolution['filters'];
        $appliedSavedFilter = $resolution['savedFilter'];

        $defaultLimit = isset($filters['limit']) ? (int) $filters['limit'] : 25;
        $perPage = $this->resolvePerPage($defaultLimit, $_GET);

        $filters['limit'] = $perPage;
        $filters['offset'] = ($page - 1) * $perPage;
        $filters['sort_by'] = $filters['sort_by'] ?? 'received_at';
        $filters['sort_dir'] = $filters['sort_dir'] ?? 'DESC';

        $reports = DmarcReport::getFilteredReports($filters);

        $countFilters = $filters;
        unset($countFilters['limit'], $countFilters['offset']);
        $totalReports = DmarcReport::getFilteredReportsCount($countFilters);

        $domains = Domain::getAllDomains();
        $totalPages = $perPage > 0 ? (int) ceil(max(1, $totalReports) / $perPage) : 1;

        $username = $_SESSION['username'] ?? '';
        $savedFilters = [];
        if ($username !== '') {
            foreach (SavedReportFilter::getForUser($username) as $record) {
                $savedFilters[] = [
                    'id' => (int) $record['id'],
                    'name' => $record['name'],
                    'filters' => SavedReportFilter::decodeFilters($record),
                ];
            }
        }

        $filtersForView = $filters;
        $filtersForView['per_page'] = $perPage;
        $filtersForView['page'] = $page;
        unset($filtersForView['limit'], $filtersForView['offset']);

        $filtersForQuery = $filters;
        unset($filtersForQuery['offset']);
        $activeSavedFilterId = $appliedSavedFilter['id'] ?? ($savedFilterId ?: null);
        $queryParams = $this->buildQueryParams($filtersForQuery, $perPage, $page, $activeSavedFilterId);
        $queryParamsWithoutPage = $queryParams;
        unset($queryParamsWithoutPage['page']);

        $filtersForPersistence = $this->prepareFiltersForPersistence($filters);
        $filterJson = json_encode($filtersForPersistence, JSON_UNESCAPED_SLASHES);
        if ($filterJson === false) {
            $filterJson = '[]';
        }

        $this->data = [
            'reports' => $reports,
            'domains' => $domains,
            'filters' => $filtersForView,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => max(1, $totalPages),
                'total_reports' => $totalReports,
                'per_page' => $perPage,
                'query_params' => $queryParams,
            ],
            'saved_filters' => $savedFilters,
            'active_saved_filter_id' => $activeSavedFilterId,
            'saved_filter_name' => $appliedSavedFilter['name'] ?? null,
            'current_filter_json' => $filterJson,
            'query_params' => $queryParams,
            'query_params_no_page' => $queryParamsWithoutPage,
            'enforcement_levels' => ['none', 'monitor', 'quarantine', 'reject'],
        ];

        require __DIR__ . '/../Views/reports.php';
    }

    /**
     * Handle form submissions (filters, etc.)
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');

            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                return;
            }

            header('Location: /reports');
            exit();
        }

        if (($_POST['action'] ?? '') === 'send_report_email') {
            $this->sendReportByEmail();

            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                return;
            }

            header('Location: /reports');
            exit();
        }

        $savedFilterId = isset($_POST['saved_filter_id']) ? (int) $_POST['saved_filter_id'] : null;
        $filters = $this->extractFilterRequest($_POST);

        $normalized = DmarcReport::normalizeFilterInput($filters);
        $defaultLimit = isset($normalized['limit']) ? (int) $normalized['limit'] : 25;
        $perPage = $this->resolvePerPage($defaultLimit, $_POST);
        $normalized['limit'] = $perPage;
        unset($normalized['offset']);

        $queryParams = $this->buildQueryParams($normalized, $perPage, 1, $savedFilterId ?: null);
        $queryString = http_build_query($queryParams);

        header('Location: /reports' . ($queryString ? '?' . $queryString : ''));
        exit();
    }

    /**
     * Export filtered reports to CSV.
     */
    public function exportCsv(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        $savedFilterId = isset($_GET['saved_filter_id']) ? (int) $_GET['saved_filter_id'] : null;
        $resolution = $this->resolveFilters($_GET, $savedFilterId);
        $filters = $resolution['filters'];
        unset($filters['offset']);
        $filters['limit'] = null;

        $reports = DmarcReport::getFilteredReports($filters);

        $csv = ReportExport::buildCsv($reports);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dmarc_reports_' . date('Ymd_His') . '.csv"');
        echo $csv;
        exit();
    }

    /**
     * Export filtered reports to XLSX.
     */
    public function exportXlsx(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);

        $savedFilterId = isset($_GET['saved_filter_id']) ? (int) $_GET['saved_filter_id'] : null;
        $resolution = $this->resolveFilters($_GET, $savedFilterId);
        $filters = $resolution['filters'];
        unset($filters['offset']);
        $filters['limit'] = null;

        $reports = DmarcReport::getFilteredReports($filters);

        $xlsx = ReportExport::buildXlsx($reports);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="dmarc_reports_' . date('Ymd_His') . '.xlsx"');
        echo $xlsx;
        exit();
    }

    /**
     * Resolve filter input from the request and optional saved filter.
     *
     * @param array<string, mixed> $source
     * @return array{filters: array<string, mixed>, savedFilter: ?array}
     */
    private function resolveFilters(array $source, ?int $savedFilterId = null): array
    {
        $filters = $this->extractFilterRequest($source);
        $appliedSavedFilter = null;

        $username = $_SESSION['username'] ?? '';
        if ($savedFilterId && $savedFilterId > 0 && $username !== '') {
            $record = SavedReportFilter::getById($savedFilterId, $username);
            if ($record) {
                $savedFilters = SavedReportFilter::decodeFilters($record);
                if (!empty($savedFilters)) {
                    $filters = array_merge($savedFilters, $filters);
                }
                $appliedSavedFilter = [
                    'id' => (int) $record['id'],
                    'name' => $record['name'],
                    'filters' => $savedFilters,
                ];
            } elseif (!empty($source)) {
                MessageHelper::addMessage('The requested saved filter is not available.', 'error');
            }
        }

        $normalized = DmarcReport::normalizeFilterInput($filters);

        return [
            'filters' => $normalized,
            'savedFilter' => $appliedSavedFilter,
        ];
    }

    /**
     * Extract filter values from request data.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function extractFilterRequest(array $source): array
    {
        $filters = [];

        $map = [
            'domain' => 'domain',
            'disposition' => 'disposition',
            'policy_result' => 'policy_result',
            'date_from' => 'date_from',
            'date_to' => 'date_to',
            'org_name' => 'org_name',
            'source_ip' => 'source_ip',
            'dkim_result' => 'dkim_result',
            'spf_result' => 'spf_result',
            'header_from' => 'header_from',
            'envelope_from' => 'envelope_from',
            'envelope_to' => 'envelope_to',
            'ownership_contact' => 'ownership_contact',
            'enforcement_level' => 'enforcement_level',
            'report_id' => 'report_id',
            'reporter_email' => 'reporter_email',
            'min_volume' => 'min_volume',
            'max_volume' => 'max_volume',
            'sort' => 'sort_by',
            'dir' => 'sort_dir',
        ];

        foreach ($map as $param => $key) {
            if (!array_key_exists($param, $source)) {
                continue;
            }

            $value = $source[$param];

            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $filtered = array_values(array_filter($value, static fn($item) => $item !== ''));
                if (!empty($filtered)) {
                    $filters[$key] = $filtered;
                }
                continue;
            }

            $filters[$key] = $value;
        }

        if (isset($source['has_failures'])) {
            $filters['has_failures'] = $source['has_failures'];
        }

        if (isset($source['per_page'])) {
            $filters['limit'] = $source['per_page'];
        }

        return $filters;
    }

    private function resolvePerPage(int $defaultLimit, array $source): int
    {
        $perPage = (int) ($source['per_page'] ?? 0);
        $allowed = [25, 50, 100];

        if (!in_array($perPage, $allowed, true)) {
            $perPage = $defaultLimit;
        }

        if (!in_array($perPage, $allowed, true)) {
            $perPage = 25;
        }

        return $perPage;
    }

    /**
     * Build query parameters for redirects and links.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildQueryParams(array $filters, int $perPage, int $page, ?int $savedFilterId = null): array
    {
        $params = [];

        $map = [
            'domain' => 'domain',
            'disposition' => 'disposition',
            'date_from' => 'date_from',
            'date_to' => 'date_to',
            'org_name' => 'org_name',
            'source_ip' => 'source_ip',
            'dkim_result' => 'dkim_result',
            'spf_result' => 'spf_result',
            'header_from' => 'header_from',
            'envelope_from' => 'envelope_from',
            'envelope_to' => 'envelope_to',
            'ownership_contact' => 'ownership_contact',
            'enforcement_level' => 'enforcement_level',
            'report_id' => 'report_id',
            'reporter_email' => 'reporter_email',
            'min_volume' => 'min_volume',
            'max_volume' => 'max_volume',
        ];

        foreach ($map as $filterKey => $param) {
            if (!array_key_exists($filterKey, $filters)) {
                continue;
            }

            $value = $filters[$filterKey];

            if (is_array($value)) {
                $params[$param] = $value;
            } elseif ($value !== '' && $value !== null) {
                $params[$param] = $value;
            }
        }

        if (!empty($filters['disposition']) && is_array($filters['disposition'])) {
            $params['disposition'] = $filters['disposition'];
        }

        if (!empty($filters['sort_by'])) {
            $params['sort'] = $filters['sort_by'];
        }

        if (!empty($filters['sort_dir'])) {
            $params['dir'] = strtoupper((string) $filters['sort_dir']);
        }

        if (!empty($filters['has_failures'])) {
            $params['has_failures'] = '1';
        }

        $params['per_page'] = $perPage;
        $params['page'] = max(1, $page);

        if ($savedFilterId) {
            $params['saved_filter_id'] = $savedFilterId;
        }

        return $params;
    }

    /**
     * Prepare filter payload for persistence to saved filters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function prepareFiltersForPersistence(array $filters): array
    {
        $persistable = $filters;
        unset($persistable['offset']);

        if (isset($persistable['limit']) && $persistable['limit'] === null) {
            unset($persistable['limit']);
        }

        return $persistable;
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
