<?php

namespace App\Core;

use App\Core\DatabaseManager;
use App\Core\SessionManager;

/**
 * Audit Logging System
 * Tracks user actions and security events
 */
class AuditLogger
{
    private static ?AuditLogger $instance = null;

    // Action types
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_UPLOAD = 'upload';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_SETTINGS_CHANGE = 'settings_change';

    // Resource types
    public const RESOURCE_USER = 'user';
    public const RESOURCE_DOMAIN = 'domain';
    public const RESOURCE_GROUP = 'group';
    public const RESOURCE_REPORT = 'report';
    public const RESOURCE_ALERT = 'alert';
    public const RESOURCE_SETTINGS = 'settings';

    private function __construct()
    {
    }

    public static function getInstance(): AuditLogger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log an action to the audit trail
     */
    public function log(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $details = null,
        ?string $userId = null
    ): void {
        try {
            $session = SessionManager::getInstance();
            
            // Get user ID from session if not provided
            if ($userId === null) {
                $userId = $session->get('username');
            }

            // Get IP and user agent
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $db = DatabaseManager::getInstance();
            $db->query('
                INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address, user_agent)
                VALUES (:user_id, :action, :resource_type, :resource_id, :details, :ip_address, :user_agent)
            ');

            $db->bind(':user_id', $userId);
            $db->bind(':action', $action);
            $db->bind(':resource_type', $resourceType);
            $db->bind(':resource_id', $resourceId);
            $db->bind(':details', $details ? json_encode($details) : null);
            $db->bind(':ip_address', $ipAddress);
            $db->bind(':user_agent', $userAgent);

            $db->execute();

        } catch (\Exception $e) {
            // Don't let audit logging break the application
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }

    /**
     * Log a login attempt
     */
    public function logLogin(string $username, bool $success): void
    {
        $action = $success ? self::ACTION_LOGIN : self::ACTION_LOGIN_FAILED;
        $details = [
            'username' => $username,
            'success' => $success,
            'timestamp' => date('c')
        ];

        $this->log($action, self::RESOURCE_USER, $username, $details, $success ? $username : null);
    }

    /**
     * Log a logout
     */
    public function logLogout(string $username): void
    {
        $this->log(self::ACTION_LOGOUT, self::RESOURCE_USER, $username);
    }

    /**
     * Log report access
     */
    public function logReportAccess(int $reportId, string $action = self::ACTION_VIEW): void
    {
        $this->log($action, self::RESOURCE_REPORT, (string) $reportId);
    }

    /**
     * Log settings changes
     */
    public function logSettingsChange(string $settingKey, $oldValue, $newValue): void
    {
        $details = [
            'setting_key' => $settingKey,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ];

        $this->log(self::ACTION_SETTINGS_CHANGE, self::RESOURCE_SETTINGS, $settingKey, $details);
    }

    /**
     * Get recent audit logs
     */
    public function getRecentLogs(int $limit = 100): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT al.*, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.username
            ORDER BY al.timestamp DESC
            LIMIT :limit
        ');
        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserLogs(string $username, int $limit = 50): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT * FROM audit_logs
            WHERE user_id = :username
            ORDER BY timestamp DESC
            LIMIT :limit
        ');
        $db->bind(':username', $username);
        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    /**
     * Get audit logs for a specific resource
     */
    public function getResourceLogs(string $resourceType, string $resourceId, int $limit = 50): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT al.*, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.username
            WHERE al.resource_type = :resource_type AND al.resource_id = :resource_id
            ORDER BY al.timestamp DESC
            LIMIT :limit
        ');
        $db->bind(':resource_type', $resourceType);
        $db->bind(':resource_id', $resourceId);
        $db->bind(':limit', $limit);
        return $db->resultSet();
    }

    /**
     * Get failed login attempts for security monitoring
     */
    public function getFailedLogins(int $hours = 24): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT * FROM audit_logs
            WHERE action = :action 
            AND timestamp >= datetime("now", "-' . $hours . ' hours")
            ORDER BY timestamp DESC
        ');
        $db->bind(':action', self::ACTION_LOGIN_FAILED);
        return $db->resultSet();
    }

    /**
     * Clean old audit logs
     */
    public function cleanOldLogs(int $daysToKeep = 90): int
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            DELETE FROM audit_logs
            WHERE timestamp < datetime("now", "-' . $daysToKeep . ' days")
        ');
        $db->execute();
        return $db->rowCount();
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}