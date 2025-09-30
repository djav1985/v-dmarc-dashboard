<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RBACManager;
use App\Core\AuditLogger;
use App\Core\SessionManager;
use App\Models\Users;
use App\Models\DomainGroup;
use App\Helpers\MessageHelper;

/**
 * User Management Controller
 * Handles user administration for RBAC system
 */
class UserManagementController extends Controller
{
    public function handleRequest(): void
    {
        // Require user management permission
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_USERS);

        $users = Users::getAllUsers();
        $roles = RBACManager::getInstance()->getAllRoles();
        $domains = DomainGroup::getAllDomains();
        $groups = DomainGroup::getAllGroups();

        $this->render('user_management', [
            'users' => $users,
            'roles' => $roles,
            'domains' => $domains,
            'groups' => $groups
        ]);
    }

    public function handleSubmission(): void
    {
        // Require user management permission
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_MANAGE_USERS);

        $action = $_POST['action'] ?? '';
        $session = SessionManager::getInstance();
        $currentUser = $session->get('username');

        switch ($action) {
            case 'create_user':
                $this->createUser();
                break;
            case 'update_user':
                $this->updateUser();
                break;
            case 'delete_user':
                $this->deleteUser();
                break;
            case 'assign_domain':
                $this->assignDomain();
                break;
            case 'assign_group':
                $this->assignGroup();
                break;
            case 'remove_domain':
                $this->removeDomain();
                break;
            case 'remove_group':
                $this->removeGroup();
                break;
            default:
                MessageHelper::addMessage('Invalid action specified.', 'error');
        }

        // Redirect back to management page
        header('Location: /user-management');
        exit();
    }

    private function createUser(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? RBACManager::ROLE_VIEWER;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Validation
        if (empty($username) || empty($password)) {
            MessageHelper::addMessage('Username and password are required.', 'error');
            return;
        }

        if (strlen($password) < 8) {
            MessageHelper::addMessage('Password must be at least 8 characters long.', 'error');
            return;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            MessageHelper::addMessage('Invalid email address.', 'error');
            return;
        }

        // Check if username already exists
        if (Users::getUserInfo($username)) {
            MessageHelper::addMessage('Username already exists.', 'error');
            return;
        }

        $userData = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email
        ];

        if (Users::createUser($userData)) {
            MessageHelper::addMessage("User '$username' created successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_CREATE,
                AuditLogger::RESOURCE_USER,
                $username,
                ['role' => $role, 'email' => $email]
            );
        } else {
            MessageHelper::addMessage('Failed to create user.', 'error');
        }
    }

    private function updateUser(): void
    {
        $username = $_POST['username'] ?? '';
        $role = $_POST['role'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = trim($_POST['new_password'] ?? '');

        if (empty($username)) {
            MessageHelper::addMessage('Username is required.', 'error');
            return;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            MessageHelper::addMessage('Invalid email address.', 'error');
            return;
        }

        $userData = [
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'is_active' => $isActive
        ];

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                MessageHelper::addMessage('Password must be at least 8 characters long.', 'error');
                return;
            }
            $userData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (Users::updateUser($username, $userData)) {
            MessageHelper::addMessage("User '$username' updated successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_UPDATE,
                AuditLogger::RESOURCE_USER,
                $username,
                ['role' => $role, 'is_active' => $isActive]
            );
        } else {
            MessageHelper::addMessage('Failed to update user.', 'error');
        }
    }

    private function deleteUser(): void
    {
        $username = $_POST['username'] ?? '';

        if (empty($username)) {
            MessageHelper::addMessage('Username is required.', 'error');
            return;
        }

        // Prevent deleting yourself
        $session = SessionManager::getInstance();
        if ($username === $session->get('username')) {
            MessageHelper::addMessage('You cannot delete your own account.', 'error');
            return;
        }

        if (Users::deleteUser($username)) {
            MessageHelper::addMessage("User '$username' deleted successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_DELETE,
                AuditLogger::RESOURCE_USER,
                $username
            );
        } else {
            MessageHelper::addMessage('Failed to delete user.', 'error');
        }
    }

    private function assignDomain(): void
    {
        $username = $_POST['username'] ?? '';
        $domainId = (int) ($_POST['domain_id'] ?? 0);
        
        $session = SessionManager::getInstance();
        $assignedBy = $session->get('username');

        if (empty($username) || empty($domainId)) {
            MessageHelper::addMessage('Username and domain are required.', 'error');
            return;
        }

        if (Users::assignUserToDomain($username, $domainId, $assignedBy)) {
            MessageHelper::addMessage("Domain assigned to user '$username' successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_CREATE,
                'user_domain_assignment',
                "$username:$domainId",
                ['assigned_by' => $assignedBy]
            );
        } else {
            MessageHelper::addMessage('Failed to assign domain. User may already have access.', 'error');
        }
    }

    private function assignGroup(): void
    {
        $username = $_POST['username'] ?? '';
        $groupId = (int) ($_POST['group_id'] ?? 0);
        
        $session = SessionManager::getInstance();
        $assignedBy = $session->get('username');

        if (empty($username) || empty($groupId)) {
            MessageHelper::addMessage('Username and group are required.', 'error');
            return;
        }

        if (Users::assignUserToGroup($username, $groupId, $assignedBy)) {
            MessageHelper::addMessage("Group assigned to user '$username' successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_CREATE,
                'user_group_assignment',
                "$username:$groupId",
                ['assigned_by' => $assignedBy]
            );
        } else {
            MessageHelper::addMessage('Failed to assign group. User may already have access.', 'error');
        }
    }

    private function removeDomain(): void
    {
        $username = $_POST['username'] ?? '';
        $domainId = (int) ($_POST['domain_id'] ?? 0);

        if (empty($username) || empty($domainId)) {
            MessageHelper::addMessage('Username and domain are required.', 'error');
            return;
        }

        if (Users::removeUserFromDomain($username, $domainId)) {
            MessageHelper::addMessage("Domain access removed from user '$username' successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_DELETE,
                'user_domain_assignment',
                "$username:$domainId"
            );
        } else {
            MessageHelper::addMessage('Failed to remove domain access.', 'error');
        }
    }

    private function removeGroup(): void
    {
        $username = $_POST['username'] ?? '';
        $groupId = (int) ($_POST['group_id'] ?? 0);

        if (empty($username) || empty($groupId)) {
            MessageHelper::addMessage('Username and group are required.', 'error');
            return;
        }

        if (Users::removeUserFromGroup($username, $groupId)) {
            MessageHelper::addMessage("Group access removed from user '$username' successfully.", 'success');
            
            // Log the action
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_DELETE,
                'user_group_assignment',
                "$username:$groupId"
            );
        } else {
            MessageHelper::addMessage('Failed to remove group access.', 'error');
        }
    }
}