<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Helpers\MessageHelper;
use App\Models\DomainGroup;
use App\Models\Domain;

/**
 * Domain Groups Controller for managing domain organization
 */
class DomainGroupsController extends Controller
{
    /**
     * Data passed to views
     * @var array
     */
    protected array $data = [];

    // ...existing methods, all indented 4 spaces inside the class...
    // ...existing methods...
    /**
     * Display domain groups management interface
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_GROUPS);
        // Get all groups with analytics
        $groups = DomainGroup::getAllGroups();

        // Get group analytics for the last 30 days
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $groupAnalytics = DomainGroup::getGroupAnalytics($startDate, $endDate);

        // Get unassigned domains
        $unassignedDomains = DomainGroup::getUnassignedDomains();

        // Get all domains for assignment
        $allDomains = Domain::getAllDomains();

        $this->data = [
            'groups' => $groups,
            'group_analytics' => $groupAnalytics,
            'unassigned_domains' => $unassignedDomains,
            'all_domains' => $allDomains
        ];

        require __DIR__ . '/../Views/domain_groups.php';
    }

    /**
     * Handle form submissions for group management
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_GROUPS);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /domain-groups');
            exit();
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_group':
                    $this->createGroup();
                    break;
                case 'assign_domain':
                    $this->assignDomain();
                    break;
                case 'remove_domain':
                    $this->removeDomain();
                    break;
            }
        }

        header('Location: /domain-groups');
        exit();
    }

    /**
     * Create a new domain group
     */
    private function createGroup(): void
    {
        $name = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['group_description'] ?? '');

        if (!empty($name)) {
            DomainGroup::createGroup($name, $description);
        }
    }

    /**
     * Assign domain to group
     */
    private function assignDomain(): void
    {
        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $groupId = (int) ($_POST['group_id'] ?? 0);

        if ($domainId <= 0 || $groupId <= 0) {
            MessageHelper::addMessage('A valid domain and group must be selected for assignment.', 'error');
            return;
        }

        $rbac = RBACManager::getInstance();
        if (!$rbac->canAccessDomain($domainId) || !$rbac->canAccessGroup($groupId)) {
            MessageHelper::addMessage('You are not authorized to manage the selected domain assignment.', 'error');
            return;
        }

        if (!DomainGroup::assignDomainToGroup($domainId, $groupId)) {
            MessageHelper::addMessage('The domain could not be assigned to the group.', 'error');
            return;
        }

        MessageHelper::addMessage('Domain assigned to group successfully.', 'success');
    }

    /**
     * Remove domain from group
     */
    private function removeDomain(): void
    {
        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $groupId = (int) ($_POST['group_id'] ?? 0);

        if ($domainId <= 0 || $groupId <= 0) {
            MessageHelper::addMessage('A valid domain and group must be selected for removal.', 'error');
            return;
        }

        $rbac = RBACManager::getInstance();
        if (!$rbac->canAccessDomain($domainId) || !$rbac->canAccessGroup($groupId)) {
            MessageHelper::addMessage('You are not authorized to update the selected domain assignment.', 'error');
            return;
        }

        if (!DomainGroup::removeDomainFromGroup($domainId, $groupId)) {
            MessageHelper::addMessage('The domain could not be removed from the group.', 'error');
            return;
        }

        MessageHelper::addMessage('Domain removed from group successfully.', 'success');
    }
}
