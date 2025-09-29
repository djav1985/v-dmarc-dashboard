<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\PdfReport;
use App\Models\PolicySimulation;
use App\Models\Domain;
use App\Models\DomainGroup;

/**
 * Reports Controller for PDF generation and policy simulation
 */
class ReportsManagementController extends Controller
{
    /**
     * Display reports management interface
     */
    public function handleRequest(): void
    {
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

        $this->data = [
            'templates' => $templates,
            'recent_generations' => $recentGenerations,
            'simulations' => array_slice($simulations, 0, 5),
            'stats' => [
                'total_templates' => count($templates),
                'recent_generations' => count($recentGenerations),
                'total_simulations' => count($simulations)
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

        if ($templateId > 0) {
            // Generate report data
            $reportData = PdfReport::generateReportData($templateId, $startDate, $endDate, $domainFilter, $groupFilter);

            // In a real implementation, you would use a PDF library like TCPDF or Dompdf
            // For demo purposes, we'll simulate the generation
            $filename = 'dmarc_report_' . date('Y-m-d_H-i-s') . '.pdf';
            $fileSize = rand(100000, 500000); // Simulate file size

            // Log the generation
            PdfReport::logGeneration([
                'template_id' => $templateId,
                'filename' => $filename,
                'title' => $title,
                'date_range_start' => $startDate,
                'date_range_end' => $endDate,
                'domain_filter' => $domainFilter,
                'group_filter' => $groupFilter,
                'parameters' => $_POST,
                'file_size' => $fileSize,
                'generated_by' => $_SESSION['username'] ?? 'Unknown'
            ]);

            // In reality, you would:
            // 1. Generate HTML from template and data
            // 2. Convert HTML to PDF using library
            // 3. Save PDF to file system
            // 4. Provide download link

            $_SESSION['flash_message'] = "PDF report '{$filename}' generated successfully.";
            $_SESSION['flash_type'] = 'success';
        }
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
            $simulationId = PolicySimulation::createSimulation($data);
            $_SESSION['flash_message'] = "Policy simulation '{$data['name']}' created successfully.";
            $_SESSION['flash_type'] = 'success';
        }
    }

    /**
     * Run policy simulation
     */
    private function runSimulation(): void
    {
        $simulationId = (int) ($_POST['simulation_id'] ?? 0);

        if ($simulationId > 0) {
            $results = PolicySimulation::runSimulation($simulationId);
            $_SESSION['flash_message'] = "Policy simulation completed successfully.";
            $_SESSION['flash_type'] = 'success';
        }
    }
}