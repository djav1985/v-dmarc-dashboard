<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\TlsReport;

class TlsReportsController extends Controller
{
    public function handleRequest(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_VIEW_TLS_REPORTS);

        $reports = TlsReport::getRecentReports();
        $this->render('tls_reports/index', [
            'reports' => $reports,
        ]);
    }

    public function show(int $id): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_VIEW_TLS_REPORTS);

        $report = TlsReport::getReportDetail($id);
        if ($report === null) {
            MessageHelper::addMessage('TLS report not found or access denied.', 'error');
            header('Location: /tls-reports');
            exit();
        }

        $this->render('tls_reports/show', [
            'report' => $report,
        ]);
    }
}
