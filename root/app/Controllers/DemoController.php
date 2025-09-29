<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Demo Controller for showcasing DMARC Dashboard without database
 */
class DemoController extends Controller
{
    /**
     * Display demo dashboard with sample data
     */
    public function handleRequest(): void
    {
        // Sample data for demonstration
        $stats = [
            'total_domains' => 3,
            'total_reports' => 15,
            'recent_reports' => [
                (object)[
                    'domain' => 'example.com',
                    'org_name' => 'Google',
                    'report_begin' => '2025-09-22 00:00:00',
                    'report_end' => '2025-09-22 23:59:59',
                    'processed' => true
                ],
                (object)[
                    'domain' => 'demo.org',
                    'org_name' => 'Microsoft',
                    'report_begin' => '2025-09-21 00:00:00',
                    'report_end' => '2025-09-21 23:59:59',
                    'processed' => false
                ],
                (object)[
                    'domain' => 'test.net',
                    'org_name' => 'Yahoo',
                    'report_begin' => '2025-09-20 00:00:00',
                    'report_end' => '2025-09-20 23:59:59',
                    'processed' => true
                ]
            ],
            'brands' => [
                (object)['id' => 1, 'name' => 'Main Brand'],
                (object)['id' => 2, 'name' => 'Demo Company']
            ]
        ];

        // Sample domain data
        $domains = [
            (object)[
                'id' => 1,
                'domain' => 'example.com',
                'brand_name' => 'Main Brand',
                'dmarc_policy' => 'quarantine',
                'dmarc_record' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com',
                'spf_record' => 'v=spf1 include:_spf.google.com ~all',
                'dkim_selectors' => '["default", "google"]',
                'mta_sts_enabled' => true,
                'bimi_enabled' => false,
                'dnssec_enabled' => true,
                'is_active' => true,
                'last_checked' => '2025-09-29 06:00:00',
                'recent_stats' => (object)[
                    'total_reports' => 5,
                    'processed_reports' => 5
                ]
            ],
            (object)[
                'id' => 2,
                'domain' => 'demo.org',
                'brand_name' => 'Demo Company',
                'dmarc_policy' => 'reject',
                'dmarc_record' => 'v=DMARC1; p=reject; rua=mailto:dmarc@demo.org',
                'spf_record' => 'v=spf1 a mx ~all',
                'dkim_selectors' => '["selector1"]',
                'mta_sts_enabled' => false,
                'bimi_enabled' => true,
                'dnssec_enabled' => false,
                'is_active' => true,
                'last_checked' => '2025-09-28 12:30:00',
                'recent_stats' => (object)[
                    'total_reports' => 8,
                    'processed_reports' => 7
                ]
            ],
            (object)[
                'id' => 3,
                'domain' => 'test.net',
                'brand_name' => null,
                'dmarc_policy' => 'none',
                'dmarc_record' => 'v=DMARC1; p=none; rua=mailto:dmarc@test.net',
                'spf_record' => null,
                'dkim_selectors' => '[]',
                'mta_sts_enabled' => false,
                'bimi_enabled' => false,
                'dnssec_enabled' => false,
                'is_active' => false,
                'last_checked' => null,
                'recent_stats' => (object)[
                    'total_reports' => 2,
                    'processed_reports' => 1
                ]
            ]
        ];

        $data = [
            'title' => 'DMARC Dashboard (Demo)',
            'stats' => $stats,
            'domains' => $domains
        ];

        $this->render('dashboard', $data);
    }

    /**
     * Display demo domains list
     */
    public function domainsList(): void
    {
        $domains = [
            (object)[
                'id' => 1,
                'domain' => 'example.com',
                'brand_name' => 'Main Brand',
                'dmarc_policy' => 'quarantine',
                'dmarc_record' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com',
                'spf_record' => 'v=spf1 include:_spf.google.com ~all',
                'dkim_selectors' => '["default", "google"]',
                'mta_sts_enabled' => true,
                'bimi_enabled' => false,
                'dnssec_enabled' => true,
                'is_active' => true,
                'last_checked' => '2025-09-29 06:00:00'
            ],
            (object)[
                'id' => 2,
                'domain' => 'demo.org',
                'brand_name' => 'Demo Company',
                'dmarc_policy' => 'reject',
                'dmarc_record' => 'v=DMARC1; p=reject; rua=mailto:dmarc@demo.org',
                'spf_record' => 'v=spf1 a mx ~all',
                'dkim_selectors' => '["selector1"]',
                'mta_sts_enabled' => false,
                'bimi_enabled' => true,
                'dnssec_enabled' => false,
                'is_active' => true,
                'last_checked' => '2025-09-28 12:30:00'
            ],
            (object)[
                'id' => 3,
                'domain' => 'test.net',
                'brand_name' => null,
                'dmarc_policy' => 'none',
                'dmarc_record' => 'v=DMARC1; p=none; rua=mailto:dmarc@test.net',
                'spf_record' => null,
                'dkim_selectors' => '[]',
                'mta_sts_enabled' => false,
                'bimi_enabled' => false,
                'dnssec_enabled' => false,
                'is_active' => false,
                'last_checked' => null
            ]
        ];

        $brands = [
            (object)['id' => 1, 'name' => 'Main Brand'],
            (object)['id' => 2, 'name' => 'Demo Company']
        ];

        $data = [
            'title' => 'Domains (Demo)',
            'domains' => $domains,
            'brands' => $brands
        ];

        $this->render('domains/list', $data);
    }

    /**
     * Handle demo form submissions
     */
    public function handleSubmission(): void
    {
        $_SESSION['success'] = 'Demo mode: Form submission simulated successfully!';
        
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'create':
                header('Location: /demo/domains');
                break;
            case 'validate':
                $_SESSION['validation_results'] = [
                    'dmarc' => ['valid' => true, 'record' => 'v=DMARC1; p=quarantine; rua=mailto:demo@example.com'],
                    'spf' => ['valid' => true, 'record' => 'v=spf1 include:_spf.google.com ~all', 'lookup_count' => 3],
                    'mta_sts' => ['valid' => false, 'error' => 'No MTA-STS record found'],
                    'bimi' => ['valid' => false, 'error' => 'No BIMI record found'],
                    'dnssec' => ['valid' => true]
                ];
                header('Location: /demo/domains');
                break;
            default:
                header('Location: /demo');
        }
    }
}