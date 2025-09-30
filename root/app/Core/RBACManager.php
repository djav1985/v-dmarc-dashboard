<?php

namespace App\Core;

use App\Core\DatabaseManager;
use App\Core\SessionManager;

/**
 * Role-Based Access Control (RBAC) Manager
 * Handles permissions and access control for the DMARC Dashboard
 */
class RBACManager
{
    // Define available roles
    public const ROLE_APP_ADMIN = 'app_admin';
    public const ROLE_DOMAIN_ADMIN = 'domain_admin';
    public const ROLE_GROUP_ADMIN = 'group_admin';
    public const ROLE_VIEWER = 'viewer';

    // Define permissions
    public const PERM_MANAGE_USERS = 'manage_users';
    public const PERM_MANAGE_DOMAINS = 'manage_domains';
    public const PERM_MANAGE_GROUPS = 'manage_groups';
    public const PERM_VIEW_REPORTS = 'view_reports';
    public const PERM_UPLOAD_REPORTS = 'upload_reports';
    public const PERM_MANAGE_SETTINGS = 'manage_settings';
    public const PERM_VIEW_ANALYTICS = 'view_analytics';
    public const PERM_MANAGE_ALERTS = 'manage_alerts';
    public const PERM_MANAGE_SECURITY = 'manage_security';
    public const PERM_VIEW_TLS_REPORTS = 'view_tls_reports';
    public const PERM_VIEW_FORENSIC_REPORTS = 'view_forensic_reports';

    private static ?RBACManager $instance = null;
    private array $rolePermissions;

    private function __construct()
    {
        $this->initializeRolePermissions();
    }

    public static function getInstance(): RBACManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeRolePermissions(): void
    {
        $this->rolePermissions = [
            self::ROLE_APP_ADMIN => [
                self::PERM_MANAGE_USERS,
                self::PERM_MANAGE_DOMAINS,
                self::PERM_MANAGE_GROUPS,
                self::PERM_VIEW_REPORTS,
                self::PERM_UPLOAD_REPORTS,
                self::PERM_MANAGE_SETTINGS,
                self::PERM_VIEW_ANALYTICS,
                self::PERM_MANAGE_ALERTS,
                self::PERM_MANAGE_SECURITY,
                self::PERM_VIEW_TLS_REPORTS,
                self::PERM_VIEW_FORENSIC_REPORTS,
            ],
            self::ROLE_DOMAIN_ADMIN => [
                self::PERM_VIEW_REPORTS,
                self::PERM_UPLOAD_REPORTS,
                self::PERM_VIEW_ANALYTICS,
                self::PERM_MANAGE_ALERTS,
                self::PERM_VIEW_TLS_REPORTS,
                self::PERM_VIEW_FORENSIC_REPORTS,
            ],
            self::ROLE_GROUP_ADMIN => [
                self::PERM_MANAGE_GROUPS,
                self::PERM_VIEW_REPORTS,
                self::PERM_UPLOAD_REPORTS,
                self::PERM_VIEW_ANALYTICS,
                self::PERM_MANAGE_ALERTS,
                self::PERM_VIEW_TLS_REPORTS,
                self::PERM_VIEW_FORENSIC_REPORTS,
            ],
            self::ROLE_VIEWER => [
                self::PERM_VIEW_REPORTS,
                self::PERM_VIEW_ANALYTICS,
                self::PERM_VIEW_TLS_REPORTS,
                self::PERM_VIEW_FORENSIC_REPORTS,
            ],
        ];
    }

    /**
     * Get current user's role from session
     */
    public function getCurrentUserRole(): string
    {
        $session = SessionManager::getInstance();
        return $session->get('user_role', self::ROLE_VIEWER);
    }

    /**
     * Check if current user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $userRole = $this->getCurrentUserRole();
        return $this->roleHasPermission($userRole, $permission);
    }

    /**
     * Check if a specific role has a permission
     */
    public function roleHasPermission(string $role, string $permission): bool
    {
        return in_array($permission, $this->rolePermissions[$role] ?? []);
    }

    /**
     * Check if current user can access a specific domain
     */
    public function canAccessDomain(int $domainId): bool
    {
        $userRole = $this->getCurrentUserRole();
        
        // App admins can access all domains
        if ($userRole === self::ROLE_APP_ADMIN) {
            return true;
        }

        $session = SessionManager::getInstance();
        $username = $session->get('username');
        
        if (!$username) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        
        // Check direct domain assignment
        $db->query('SELECT 1 FROM user_domain_assignments WHERE user_id = :username AND domain_id = :domain_id');
        $db->bind(':username', $username);
        $db->bind(':domain_id', $domainId);
        $directAccess = $db->single();
        
        if ($directAccess) {
            return true;
        }

        // Check group assignment
        $db->query('
            SELECT 1 FROM user_group_assignments uga
            JOIN domain_group_assignments dga ON uga.group_id = dga.group_id
            WHERE uga.user_id = :username AND dga.domain_id = :domain_id
        ');
        $db->bind(':username', $username);
        $db->bind(':domain_id', $domainId);
        $groupAccess = $db->single();
        
        return (bool) $groupAccess;
    }

    /**
     * Check if current user can access a specific domain group
     */
    public function canAccessGroup(int $groupId): bool
    {
        $userRole = $this->getCurrentUserRole();
        
        // App admins can access all groups
        if ($userRole === self::ROLE_APP_ADMIN) {
            return true;
        }

        $session = SessionManager::getInstance();
        $username = $session->get('username');
        
        if (!$username) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $db->query('SELECT 1 FROM user_group_assignments WHERE user_id = :username AND group_id = :group_id');
        $db->bind(':username', $username);
        $db->bind(':group_id', $groupId);
        $result = $db->single();
        
        return (bool) $result;
    }

    /**
     * Get domains accessible by current user
     */
    public function getAccessibleDomains(): array
    {
        $userRole = $this->getCurrentUserRole();
        
        // App admins can access all domains
        if ($userRole === self::ROLE_APP_ADMIN) {
            $db = DatabaseManager::getInstance();
            $db->query('SELECT * FROM domains ORDER BY domain');
            return $db->resultSet();
        }

        $session = SessionManager::getInstance();
        $username = $session->get('username');
        
        if (!$username) {
            return [];
        }

        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT DISTINCT d.* FROM domains d
            LEFT JOIN user_domain_assignments uda ON d.id = uda.domain_id AND uda.user_id = :username
            LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
            LEFT JOIN user_group_assignments uga ON dga.group_id = uga.group_id AND uga.user_id = :username
            WHERE uda.domain_id IS NOT NULL OR uga.group_id IS NOT NULL
            ORDER BY d.domain
        ');
        $db->bind(':username', $username);
        return $db->resultSet();
    }

    /**
     * Get groups accessible by current user
     */
    public function getAccessibleGroups(): array
    {
        $userRole = $this->getCurrentUserRole();
        
        // App admins can access all groups
        if ($userRole === self::ROLE_APP_ADMIN) {
            $db = DatabaseManager::getInstance();
            $db->query('SELECT * FROM domain_groups ORDER BY name');
            return $db->resultSet();
        }

        $session = SessionManager::getInstance();
        $username = $session->get('username');
        
        if (!$username) {
            return [];
        }

        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT dg.* FROM domain_groups dg
            JOIN user_group_assignments uga ON dg.id = uga.group_id
            WHERE uga.user_id = :username
            ORDER BY dg.name
        ');
        $db->bind(':username', $username);
        return $db->resultSet();
    }

    /**
     * Require a specific permission or redirect/exit
     */
    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            http_response_code(403);
            $message = "Access denied. Required permission: $permission";

            if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
                throw new \RuntimeException($message, 403);
            }

            echo $message;
            exit();
        }
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        return [
            self::ROLE_APP_ADMIN => 'Application Administrator',
            self::ROLE_DOMAIN_ADMIN => 'Domain Administrator', 
            self::ROLE_GROUP_ADMIN => 'Group Administrator',
            self::ROLE_VIEWER => 'Viewer'
        ];
    }

    /**
     * Get permissions for a role
     */
    public function getRolePermissions(string $role): array
    {
        return $this->rolePermissions[$role] ?? [];
    }
}
