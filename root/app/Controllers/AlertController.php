<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\Alert;
use App\Models\DomainGroup;
use App\Models\Domain;
use App\Services\AlertService;
use App\Utilities\AccessScopeValidator;
use RuntimeException;

/**
 * Alert Controller for managing real-time alerting system
 */
class AlertController extends Controller
{
    /**
     * Display alerts dashboard
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_ALERTS);
        $action = $_GET['action'] ?? 'dashboard';

        switch ($action) {
            case 'rules':
                $this->showRules();
                break;
            case 'incidents':
                $this->showIncidents();
                break;
            case 'create-rule':
                $this->showCreateRule();
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
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_ALERTS);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /alerts');
            exit();
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_rule':
                    $this->createRule();
                    break;
                case 'test_alerts':
                    $this->testAlerts();
                    break;
                case 'acknowledge_incident':
                    $this->acknowledgeIncident();
                    break;
            }
        }

        header('Location: /alerts');
        exit();
    }

    /**
     * Show alerts dashboard
     */
    private function showDashboard(): void
    {
        // Get alert rules summary
        $rules = Alert::getAllRules();

        // Get recent incidents
        $incidents = Alert::getOpenIncidents();

        // Calculate summary statistics
        $totalRules = count($rules);
        $enabledRules = count(array_filter($rules, fn($r) => $r['enabled']));
        $openIncidents = count($incidents);
        $criticalIncidents = count(array_filter($incidents, fn($i) => $i['severity'] === 'critical'));

        $this->data = [
            'rules' => $rules,
            'incidents' => $incidents,
            'stats' => [
                'total_rules' => $totalRules,
                'enabled_rules' => $enabledRules,
                'open_incidents' => $openIncidents,
                'critical_incidents' => $criticalIncidents
            ]
        ];

        require __DIR__ . '/../Views/alerts_dashboard.php';
    }

    /**
     * Show alert rules management
     */
    private function showRules(): void
    {
        $rules = Alert::getAllRules();
        $this->data = ['rules' => $rules];
        require __DIR__ . '/../Views/alert_rules.php';
    }

    /**
     * Show incidents management
     */
    private function showIncidents(): void
    {
        $incidents = Alert::getOpenIncidents();
        $this->data = ['incidents' => $incidents];
        require __DIR__ . '/../Views/alert_incidents.php';
    }

    /**
     * Show create rule form
     */
    private function showCreateRule(): void
    {
        $domains = Domain::getAllDomains();
        $groups = DomainGroup::getAllGroups();

        $this->data = [
            'domains' => $domains,
            'groups' => $groups
        ];

        require __DIR__ . '/../Views/create_alert_rule.php';
    }

    /**
     * Create a new alert rule
     */
    private function createRule(): void
    {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'rule_type' => $_POST['rule_type'] ?? 'threshold',
            'metric' => $_POST['metric'] ?? '',
            'threshold_value' => (float) ($_POST['threshold_value'] ?? 0),
            'threshold_operator' => $_POST['threshold_operator'] ?? '>',
            'time_window' => (int) ($_POST['time_window'] ?? 60),
            'domain_filter' => $_POST['domain_filter'] ?? '',
            'group_filter' => !empty($_POST['group_filter']) ? (int) $_POST['group_filter'] : null,
            'severity' => $_POST['severity'] ?? 'medium',
            'notification_channels' => $_POST['notification_channels'] ?? [],
            'notification_recipients' => array_filter(explode(',', $_POST['notification_recipients'] ?? '')),
            'webhook_url' => trim($_POST['webhook_url'] ?? ''),
            'enabled' => isset($_POST['enabled']) ? 1 : 0
        ];

        if (!empty($data['name']) && !empty($data['metric'])) {
            $domainResolution = AccessScopeValidator::resolveDomain($data['domain_filter']);
            if (!$domainResolution['authorized']) {
                MessageHelper::addMessage('The selected domain is unavailable or unauthorized.', 'error');
                return;
            }

            $groupResolution = AccessScopeValidator::resolveGroup($data['group_filter']);
            if (!$groupResolution['authorized']) {
                MessageHelper::addMessage('The selected group is unavailable or unauthorized.', 'error');
                return;
            }

            $data['domain_filter'] = $domainResolution['name'] ?? '';
            $data['group_filter'] = $groupResolution['id'];

            try {
                Alert::createRule($data);
                MessageHelper::addMessage('Alert rule created successfully.', 'success');
            } catch (RuntimeException $exception) {
                MessageHelper::addMessage($exception->getMessage(), 'error');
            }
        }
    }

    /**
     * Test alert system by checking rules
     */
    private function testAlerts(): void
    {
        AlertService::runAlertChecks();
    }

    /**
     * Acknowledge an incident
     */
    private function acknowledgeIncident(): void
    {
        $incidentId = (int) ($_POST['incident_id'] ?? 0);
        $acknowledgedBy = $_SESSION['username'] ?? 'Unknown';

        if ($incidentId > 0) {
            $db = \App\Core\DatabaseManager::getInstance();
            $db->query('
                SELECT ai.id, ar.domain_filter, ar.group_filter
                FROM alert_incidents ai
                JOIN alert_rules ar ON ai.rule_id = ar.id
                WHERE ai.id = :incident_id
            ');
            $db->bind(':incident_id', $incidentId);
            $incident = $db->single();

            if (!$incident || !\App\Models\Alert::canCurrentUserAccessRule($incident)) {
                MessageHelper::addMessage('You do not have permission to acknowledge this incident.', 'error');
                return;
            }

            $acknowledgedAt = date('Y-m-d H:i:s');
            $db->query('
                UPDATE alert_incidents
                SET status = "acknowledged", acknowledged_by = :acknowledged_by, acknowledged_at = :acknowledged_at
                WHERE id = :incident_id
            ');
            $db->bind(':incident_id', $incidentId);
            $db->bind(':acknowledged_by', $acknowledgedBy);
            $db->bind(':acknowledged_at', $acknowledgedAt);
            $db->execute();
            MessageHelper::addMessage('Incident acknowledged.', 'success');
        }
    }
}
