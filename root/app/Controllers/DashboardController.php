<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Domain;
use App\Models\DmarcReport;
use App\Models\Brand;
use App\Core\SessionManager;

/**
 * Main DMARC Dashboard Controller
 */
class DashboardController extends Controller
{
    /**
     * Display main dashboard
     */
    public function handleRequest(): void
    {
        $session = SessionManager::getInstance();

        // Get dashboard statistics
        $stats = [
            'total_domains' => count(Domain::getAll()),
            'total_reports' => DmarcReport::getStats()['total_reports'] ?? 0,
            'recent_reports' => DmarcReport::getRecentActivity(7, 10),
            'brands' => Brand::getAll()
        ];

        // Get recent domain activity
        $domains = Domain::getAll();
        foreach ($domains as &$domain) {
            $domain->recent_stats = Domain::getStats($domain->id, 7);
        }

        $data = [
            'title' => 'DMARC Dashboard',
            'stats' => $stats,
            'domains' => $domains
        ];

        $this->render('dashboard', $data);
    }

    /**
     * Handle dashboard filters and actions
     */
    public function handleSubmission(): void
    {
        // Handle dashboard form submissions like filters
        $this->handleRequest();
    }
}
