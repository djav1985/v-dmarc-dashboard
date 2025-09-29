<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Domain;
use App\Models\Brand;
use App\Services\DnsValidator;
use App\Core\SessionManager;

/**
 * Domain Management Controller
 */
class DomainController extends Controller
{
    /**
     * Display domains list or specific domain
     */
    public function handleRequest(): void
    {
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        $segments = array_filter(explode('/', $path));

        if (count($segments) >= 3 && $segments[2] === 'domains') {
            if (isset($segments[3])) {
                // View specific domain
                $domainId = (int)$segments[3];
                $this->viewDomain($domainId);
            } else {
                // List all domains
                $this->listDomains();
            }
        } else {
            $this->listDomains();
        }
    }

    /**
     * Handle domain form submissions
     */
    public function handleSubmission(): void
    {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $this->createDomain();
                break;
            case 'update':
                $this->updateDomain();
                break;
            case 'validate':
                $this->validateDomain();
                break;
            default:
                $this->listDomains();
        }
    }

    /**
     * List all domains
     */
    private function listDomains(): void
    {
        $domains = Domain::getAll();
        $brands = Brand::getAll();

        $data = [
            'title' => 'Domains',
            'domains' => $domains,
            'brands' => $brands
        ];

        $this->render('domains/list', $data);
    }

    /**
     * View specific domain details
     */
    private function viewDomain(int $domainId): void
    {
        $domain = Domain::getById($domainId);

        if (!$domain) {
            header('HTTP/1.0 404 Not Found');
            $this->render('404');
            return;
        }

        $reports = Domain::getRecentReports($domainId, 30);
        $stats = Domain::getStats($domainId, 30);

        $data = [
            'title' => 'Domain: ' . $domain->domain,
            'domain' => $domain,
            'reports' => $reports,
            'stats' => $stats
        ];

        $this->render('domains/view', $data);
    }

    /**
     * Create new domain
     */
    private function createDomain(): void
    {
        $domainName = $_POST['domain'] ?? '';
        $brandId = $_POST['brand_id'] ?? null;

        if (empty($domainName)) {
            $_SESSION['error'] = 'Domain name is required';
            header('Location: /domains');
            return;
        }

        // Check if domain exists
        if (Domain::getByName($domainName)) {
            $_SESSION['error'] = 'Domain already exists';
            header('Location: /domains');
            return;
        }

        $data = [
            'domain' => $domainName,
            'brand_id' => $brandId ?: null
        ];

        if (Domain::create($data)) {
            $_SESSION['success'] = 'Domain created successfully';
        } else {
            $_SESSION['error'] = 'Failed to create domain';
        }

        header('Location: /domains');
    }

    /**
     * Update existing domain
     */
    private function updateDomain(): void
    {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        $brandId = $_POST['brand_id'] ?? null;
        $retentionDays = (int)($_POST['retention_days'] ?? 365);
        $isActive = isset($_POST['is_active']);

        if (!$domainId) {
            $_SESSION['error'] = 'Invalid domain ID';
            header('Location: /domains');
            return;
        }

        $data = [
            'brand_id' => $brandId ?: null,
            'retention_days' => $retentionDays,
            'is_active' => $isActive
        ];

        if (Domain::update($domainId, $data)) {
            $_SESSION['success'] = 'Domain updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update domain';
        }

        header('Location: /domains/' . $domainId);
    }

    /**
     * Validate domain DNS records
     */
    private function validateDomain(): void
    {
        $domainId = (int)($_POST['domain_id'] ?? 0);

        if (!$domainId) {
            $_SESSION['error'] = 'Invalid domain ID';
            header('Location: /domains');
            return;
        }

        $domain = Domain::getById($domainId);
        if (!$domain) {
            $_SESSION['error'] = 'Domain not found';
            header('Location: /domains');
            return;
        }

        // Parse DKIM selectors from domain record
        $dkimSelectors = [];
        if ($domain->dkim_selectors) {
            $dkimSelectors = json_decode($domain->dkim_selectors, true) ?: [];
        }

        // Validate domain DNS records
        $validation = DnsValidator::validateDomain($domain->domain, $dkimSelectors);

        // Update domain with validation results
        $updateData = [
            'dmarc_record' => $validation['dmarc']['record'] ?? null,
            'spf_record' => $validation['spf']['record'] ?? null,
            'mta_sts_enabled' => $validation['mta_sts']['valid'] ?? false,
            'bimi_enabled' => $validation['bimi']['valid'] ?? false,
            'dnssec_enabled' => $validation['dnssec']['valid'] ?? false
        ];

        Domain::update($domainId, $updateData);
        Domain::updateLastChecked($domainId);

        $_SESSION['success'] = 'Domain validation completed';
        $_SESSION['validation_results'] = $validation;

        header('Location: /domains/' . $domainId);
    }
}
