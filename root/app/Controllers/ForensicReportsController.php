<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\DmarcReport;

class ForensicReportsController extends Controller
{
    public function handleRequest(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_VIEW_FORENSIC_REPORTS);

        $domainId = isset($_GET['domain']) ? (int) $_GET['domain'] : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $reports = DmarcReport::getForensicReports($domainId, $limit, $offset);
        $domains = $rbac->getAccessibleDomains();

        $this->render('forensic_reports/index', [
            'reports' => $reports,
            'domains' => $domains,
            'selectedDomain' => $domainId,
            'page' => $page,
        ]);
    }

    public function show(int $id): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_VIEW_FORENSIC_REPORTS);

        $report = DmarcReport::getForensicReportById($id);
        if ($report === null) {
            MessageHelper::addMessage('Forensic report not found or access denied.', 'error');
            header('Location: /forensic-reports');
            exit();
        }

        $this->render('forensic_reports/show', [
            'report' => $report,
        ]);
    }
}
