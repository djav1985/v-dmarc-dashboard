<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\PdfReport;
use App\Models\PdfReportSchedule;
use App\Models\PolicySimulation;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Services\PdfReportService;
use App\Services\PdfReportScheduler;
use App\Utilities\AccessScopeValidator;
use Throwable;
use RuntimeException;

/**
 * Reports Controller for PDF generation and policy simulation
 */
class ReportsManagementController extends Controller
{
    /**
     * Data passed to views
     * @var array
     */
    protected array $data = [];
    /**
     * Display reports management interface
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);
        $action = $_GET['action'] ?? 'dashboard';

        switch ($action) {
            case 'pdf-templates':
                $this->showPdfTemplates();
                break;
            case 'generate-pdf':
                $this->showGeneratePdf();
                break;
            case 'policy-simulations':
                $this->showPolicySimulations();
                break;
            case 'create-simulation':
                $this->showCreateSimulation();
                break;
            case 'view-simulation':
                $this->viewSimulation();
                break;
            default:
                $this->showDashboard();
                break;
        }
    }

    /**
     * Handle form submissions
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_VIEW_REPORTS);
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                return;
            }

            header('Location: /reports-management');
            exit();
        }
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'generate_pdf':
                    $this->generatePdf();
                    break;
                case 'create_simulation':
                    $this->createSimulation();
                    break;
                case 'run_simulation':
                    $this->runSimulation();
                    break;
                case 'create_schedule':
                    $this->createSchedule();
                    break;
                case 'update_schedule':
                    $this->updateSchedule();
                    break;
                case 'delete_schedule':
                    $this->deleteSchedule();
                    break;
                case 'toggle_schedule':
                    $this->toggleSchedule();
                    break;
                case 'run_schedule':
                    $this->runSchedule();
                    break;
            }
        }

        header('Location: /reports-management');
        exit();
    }

    /**
     * Show reports management dashboard
     */
    private function showDashboard(): void
    {
        $templates = PdfReport::getAllTemplates();
        $recentGenerations = PdfReport::getRecentGenerations(5);
        $simulations = PolicySimulation::getAllSimulations();
        $schedules = PdfReportSchedule::getAllSchedules();
        $groups = DomainGroup::getAllGroups();

        $this->data = [
            'templates' => $templates,
            'recent_generations' => $recentGenerations,
            'simulations' => array_slice($simulations, 0, 5),
            'schedules' => $schedules,
            'groups' => $groups,
            'stats' => [
                'total_templates' => count($templates),
                'recent_generations' => count($recentGenerations),
                'total_simulations' => count($simulations),
                'active_schedules' => count(array_filter($schedules, static function ($schedule) {
                    return (int) ($schedule['enabled'] ?? 0) === 1;
                })),
            ]
        ];

        require __DIR__ . '/../Views/reports_management_dashboard.php';
    }

    /**
     * Show PDF templates
     */
    private function showPdfTemplates(): void
    {
        $templates = PdfReport::getAllTemplates();
        $this->data = ['templates' => $templates];
        require __DIR__ . '/../Views/pdf_templates.php';
    }

    /**
     * Show PDF generation form
     */
    private function showGeneratePdf(): void
    {
        $templateId = (int) ($_GET['template_id'] ?? 0);
        $templates = PdfReport::getAllTemplates();
        $domains = Domain::getAllDomains();
        $groups = DomainGroup::getAllGroups();

        $this->data = [
            'templates' => $templates,
            'selected_template' => $templateId,
            'domains' => $domains,
            'groups' => $groups
        ];

        require __DIR__ . '/../Views/generate_pdf.php';
    }

    /**
     * Show policy simulations
     */
    private function showPolicySimulations(): void
    {
        $simulations = PolicySimulation::getAllSimulations();
        $this->data = ['simulations' => $simulations];
        require __DIR__ . '/../Views/policy_simulations.php';
    }

    /**
     * Show create simulation form
     */
    private function showCreateSimulation(): void
    {
        $domains = Domain::getAllDomains();
        $this->data = ['domains' => $domains];
        require __DIR__ . '/../Views/create_policy_simulation.php';
    }

    /**
     * View simulation results
     */
    private function viewSimulation(): void
    {
        $simulationId = (int) ($_GET['id'] ?? 0);
        $simulation = PolicySimulation::getSimulation($simulationId);

        if (!$simulation) {
            header('Location: /reports-management?action=policy-simulations');
            exit();
        }

        // Decode results and recommendations if they exist
        if (!empty($simulation['results'])) {
            $simulation['results'] = json_decode($simulation['results'], true);
        }
        if (!empty($simulation['recommendations'])) {
            $simulation['recommendations'] = json_decode($simulation['recommendations'], true);
        }

        $this->data = ['simulation' => $simulation];
        require __DIR__ . '/../Views/view_simulation.php';
    }

    /**
     * Generate PDF report
     */
    private function generatePdf(): void
    {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $title = trim($_POST['title'] ?? 'DMARC Report');
        $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_POST['end_date'] ?? date('Y-m-d');
        $domainFilter = $_POST['domain_filter'] ?? '';
        $groupFilter = !empty($_POST['group_filter']) ? (int) $_POST['group_filter'] : null;

        if ($templateId <= 0) {
            $_SESSION['flash_message'] = 'A valid template must be selected before generating a PDF report.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $domainResolution = AccessScopeValidator::resolveDomain($domainFilter);
        if (!$domainResolution['authorized']) {
            $_SESSION['flash_message'] = 'The selected domain is unavailable or you do not have access to it.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $groupResolution = AccessScopeValidator::resolveGroup($groupFilter);
        if (!$groupResolution['authorized']) {
            $_SESSION['flash_message'] = 'The selected group is unavailable or you do not have access to it.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $domainFilter = $domainResolution['name'] ?? '';
        $groupFilter = $groupResolution['id'];

        $reportData = PdfReport::generateReportData($templateId, $startDate, $endDate, $domainFilter, $groupFilter);

        if (empty($reportData)) {
            $_SESSION['flash_message'] = 'No data was available for the selected template and filters.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        try {
            $generation = PdfReportService::generatePdf(
                $reportData,
                $title,
                [
                    'output_directory' => defined('PDF_REPORT_STORAGE_PATH') ? PDF_REPORT_STORAGE_PATH : null,
                    'prefix' => 'manual',
                ]
            );

            PdfReport::logGeneration([
                'template_id' => $templateId,
                'filename' => $generation['filename'],
                'file_path' => $generation['relative_path'],
                'title' => $title,
                'date_range_start' => $startDate,
                'date_range_end' => $endDate,
                'domain_filter' => $domainFilter,
                'group_filter' => $groupFilter,
                'parameters' => $_POST,
                'file_size' => $generation['size'],
                'generated_by' => $_SESSION['username'] ?? 'Unknown',
                'schedule_id' => null,
            ]);

            $_SESSION['flash_message'] = "PDF report '{$generation['filename']}' generated successfully.";
            $_SESSION['flash_type'] = 'success';
        } catch (Throwable $exception) {
            $_SESSION['flash_message'] = 'Failed to generate PDF: ' . $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }

    /**
     * Create a scheduled PDF report.
     */
    private function createSchedule(): void
    {
        $name = trim($_POST['schedule_name'] ?? '');
        $templateId = (int) ($_POST['schedule_template_id'] ?? 0);
        $title = trim($_POST['schedule_title'] ?? '');
        $frequency = trim($_POST['schedule_frequency'] ?? '');
        $recipients = $this->extractRecipients($_POST['schedule_recipients'] ?? '');

        if ($name === '' || $templateId <= 0 || $frequency === '' || empty($recipients)) {
            $_SESSION['flash_message'] = 'Schedule name, template, cadence, and at least one recipient are required.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $domainFilter = trim($_POST['schedule_domain_filter'] ?? '');
        $groupFilter = !empty($_POST['schedule_group_filter']) ? (int) $_POST['schedule_group_filter'] : null;
        $nextRun = trim($_POST['schedule_start_at'] ?? '');

        try {
            PdfReportSchedule::create([
                'name' => $name,
                'template_id' => $templateId,
                'title' => $title !== '' ? $title : $name,
                'frequency' => $frequency,
                'recipients' => $recipients,
                'domain_filter' => $domainFilter,
                'group_filter' => $groupFilter ?: null,
                'parameters' => [
                    'created_via' => 'dashboard',
                ],
                'enabled' => 1,
                'next_run_at' => $nextRun !== '' ? $nextRun : null,
                'created_by' => $_SESSION['username'] ?? null,
            ]);

            $_SESSION['flash_message'] = "Schedule '{$name}' created successfully.";
            $_SESSION['flash_type'] = 'success';
        } catch (RuntimeException $exception) {
            $_SESSION['flash_message'] = $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        } catch (Throwable $exception) {
            $_SESSION['flash_message'] = 'Failed to create schedule: ' . $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }

    /**
     * Update schedule configuration.
     */
    private function updateSchedule(): void
    {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        $schedule = $scheduleId > 0 ? PdfReportSchedule::find($scheduleId) : null;
        if (!$schedule) {
            $_SESSION['flash_message'] = 'The selected schedule could not be found.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $name = trim($_POST['schedule_name'] ?? ($schedule['name'] ?? ''));
        $templateId = (int) ($_POST['schedule_template_id'] ?? $schedule['template_id']);
        $title = trim($_POST['schedule_title'] ?? ($schedule['title'] ?? ''));
        $frequency = trim($_POST['schedule_frequency'] ?? ($schedule['frequency'] ?? ''));
        $recipients = $this->extractRecipients($_POST['schedule_recipients'] ?? '');
        $domainFilter = trim($_POST['schedule_domain_filter'] ?? ($schedule['domain_filter'] ?? ''));
        $groupFilter = isset($_POST['schedule_group_filter']) && $_POST['schedule_group_filter'] !== ''
            ? (int) $_POST['schedule_group_filter']
            : ($schedule['group_filter'] ?? null);
        $nextRun = trim($_POST['schedule_start_at'] ?? ($schedule['next_run_at'] ?? ''));
        $enabled = isset($_POST['schedule_enabled']) ? (int) $_POST['schedule_enabled'] : ($schedule['enabled'] ?? 1);

        if ($name === '' || $templateId <= 0 || $frequency === '' || empty($recipients)) {
            $_SESSION['flash_message'] = 'Updated schedule details are incomplete. Please verify cadence, template, and recipients.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        try {
            PdfReportSchedule::update($scheduleId, [
                'name' => $name,
                'template_id' => $templateId,
                'title' => $title !== '' ? $title : $name,
                'frequency' => $frequency,
                'recipients' => $recipients,
                'domain_filter' => $domainFilter,
                'group_filter' => $groupFilter ?: null,
                'parameters' => [
                    'updated_via' => 'dashboard',
                ],
                'enabled' => $enabled ? 1 : 0,
                'next_run_at' => $nextRun !== '' ? $nextRun : null,
            ]);

            $_SESSION['flash_message'] = "Schedule '{$name}' updated successfully.";
            $_SESSION['flash_type'] = 'success';
        } catch (RuntimeException $exception) {
            $_SESSION['flash_message'] = $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        } catch (Throwable $exception) {
            $_SESSION['flash_message'] = 'Failed to update schedule: ' . $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }

    /**
     * Delete a schedule.
     */
    private function deleteSchedule(): void
    {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            $_SESSION['flash_message'] = 'Unable to delete schedule: missing identifier.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        if (!PdfReportSchedule::delete($scheduleId)) {
            $_SESSION['flash_message'] = 'You are not authorized to remove this schedule or it no longer exists.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $_SESSION['flash_message'] = 'Schedule removed successfully.';
        $_SESSION['flash_type'] = 'success';
    }

    /**
     * Toggle schedule enabled state.
     */
    private function toggleSchedule(): void
    {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        $enabled = (int) ($_POST['schedule_enabled'] ?? 0) === 1;

        if ($scheduleId <= 0) {
            $_SESSION['flash_message'] = 'Unable to change schedule state: missing identifier.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        if (!PdfReportSchedule::setEnabled($scheduleId, $enabled)) {
            $_SESSION['flash_message'] = 'You are not authorized to modify this schedule or it no longer exists.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        $_SESSION['flash_message'] = $enabled ? 'Schedule enabled.' : 'Schedule paused.';
        $_SESSION['flash_type'] = 'success';
    }

    /**
     * Trigger an immediate run for a schedule.
     */
    private function runSchedule(): void
    {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            $_SESSION['flash_message'] = 'Unable to execute schedule: missing identifier.';
            $_SESSION['flash_type'] = 'error';
            return;
        }

        try {
            $result = PdfReportScheduler::runScheduleNow($scheduleId);
            if ($result === null) {
                $_SESSION['flash_message'] = 'Schedule could not be located.';
                $_SESSION['flash_type'] = 'error';
                return;
            }

            if ($result['success']) {
                $_SESSION['flash_message'] = 'Schedule executed successfully and recipients were notified.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Schedule execution completed with warnings: ' . ($result['message'] ?? 'Unknown issue');
                $_SESSION['flash_type'] = 'warning';
            }
        } catch (Throwable $exception) {
            $_SESSION['flash_message'] = 'Failed to run schedule: ' . $exception->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }

    /**
     * Normalize recipient input into an array list.
     */
    private function extractRecipients(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $recipients = array_filter(array_map('trim', $parts));

        return array_values($recipients);
    }

    /**
     * Create policy simulation
     */
    private function createSimulation(): void
    {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'domain_id' => (int) ($_POST['domain_id'] ?? 0),
            'current_policy' => [
                'p' => $_POST['current_p'] ?? 'none',
                'sp' => $_POST['current_sp'] ?? '',
                'aspf' => $_POST['current_aspf'] ?? 'r',
                'adkim' => $_POST['current_adkim'] ?? 'r',
                'pct' => $_POST['current_pct'] ?? 100,
                'rua' => $_POST['current_rua'] ?? '',
                'ruf' => $_POST['current_ruf'] ?? ''
            ],
            'simulated_policy' => [
                'p' => $_POST['simulated_p'] ?? 'none',
                'sp' => $_POST['simulated_sp'] ?? '',
                'aspf' => $_POST['simulated_aspf'] ?? 'r',
                'adkim' => $_POST['simulated_adkim'] ?? 'r',
                'pct' => $_POST['simulated_pct'] ?? 100,
                'rua' => $_POST['simulated_rua'] ?? '',
                'ruf' => $_POST['simulated_ruf'] ?? ''
            ],
            'period_start' => $_POST['period_start'] ?? date('Y-m-d', strtotime('-30 days')),
            'period_end' => $_POST['period_end'] ?? date('Y-m-d'),
            'created_by' => $_SESSION['username'] ?? 'Unknown'
        ];

        if (!empty($data['name']) && $data['domain_id'] > 0) {
            try {
                $simulationId = PolicySimulation::createSimulation($data);
                $_SESSION['flash_message'] = "Policy simulation '{$data['name']}' created successfully.";
                $_SESSION['flash_type'] = 'success';
            } catch (RuntimeException $exception) {
                $_SESSION['flash_message'] = $exception->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
    }

    /**
     * Run policy simulation
     */
    private function runSimulation(): void
    {
        $simulationId = (int) ($_POST['simulation_id'] ?? 0);

        if ($simulationId > 0) {
            try {
                $results = PolicySimulation::runSimulation($simulationId);
                $_SESSION['flash_message'] = "Policy simulation completed successfully.";
                $_SESSION['flash_type'] = 'success';
            } catch (RuntimeException $exception) {
                $_SESSION['flash_message'] = $exception->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
    }
}
