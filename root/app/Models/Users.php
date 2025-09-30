<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

class Users
{
    /**
     * Get user info by username.
     *
     * @param string $username
     * @return object|null
     */
    public static function getUserInfo(string $username): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM users WHERE username = :username AND is_active = 1');
        $db->bind(':username', $username);
        $result = $db->single();

        return $result ? (object) $result : null;
    }

    /**
     * Find a user by email address.
     */
    public static function getUserByEmail(string $email): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $db->bind(':email', $email);
        $result = $db->single();

        return $result ? (object) $result : null;
    }

    /**
     * Update last login timestamp for user.
     *
     * @param string $username
     * @return bool
     */
    public static function updateLastLogin(string $username): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE username = :username');
        $db->bind(':username', $username);
        return $db->execute();
    }

    /**
     * Check if user is admin (legacy support).
     *
     * @param string $username
     * @return bool
     */
    public static function isAdmin(string $username): bool
    {
        $userInfo = self::getUserInfo($username);
        return $userInfo && (bool) $userInfo->admin;
    }

    /**
     * Get user role
     *
     * @param string $username
     * @return string
     */
    public static function getUserRole(string $username): string
    {
        $userInfo = self::getUserInfo($username);
        return $userInfo ? ($userInfo->role ?? RBACManager::ROLE_VIEWER) : RBACManager::ROLE_VIEWER;
    }

    /**
     * Create a new user
     *
     * @param array $userData
     * @return bool
     */
    public static function createUser(array $userData): bool
    {
        try {
            $db = DatabaseManager::getInstance();

            $db->query('
                INSERT INTO users (username, password, role, first_name, last_name, email, admin, is_active)
                VALUES (:username, :password, :role, :first_name, :last_name, :email, :admin, 1)
            ');

            $db->bind(':username', $userData['username']);
            $db->bind(':password', $userData['password']); // Should be pre-hashed
            $db->bind(':role', $userData['role'] ?? RBACManager::ROLE_VIEWER);
            $db->bind(':first_name', $userData['first_name'] ?? '');
            $db->bind(':last_name', $userData['last_name'] ?? '');
            $db->bind(':email', $userData['email'] ?? '');
            $db->bind(':admin', $userData['role'] === RBACManager::ROLE_APP_ADMIN ? 1 : 0);

            return $db->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update user information
     *
     * @param string $username
     * @param array $userData
     * @return bool
     */
    public static function updateUser(string $username, array $userData): bool
    {
        try {
            $db = DatabaseManager::getInstance();

            $setParts = [];
            $params = [':username' => $username];

            foreach (['role', 'first_name', 'last_name', 'email', 'is_active'] as $field) {
                if (isset($userData[$field])) {
                    $setParts[] = "$field = :$field";
                    $params[":$field"] = $userData[$field];
                }
            }

            // Update admin flag based on role
            if (isset($userData['role'])) {
                $setParts[] = "admin = :admin";
                $params[':admin'] = $userData['role'] === RBACManager::ROLE_APP_ADMIN ? 1 : 0;
            }

            if (isset($userData['password']) && !empty($userData['password'])) {
                $setParts[] = "password = :password";
                $params[':password'] = $userData['password']; // Should be pre-hashed
            }

            if (empty($setParts)) {
                return false;
            }

            $setParts[] = "updated_at = CURRENT_TIMESTAMP";
            $setClause = implode(', ', $setParts);

            $db->query("UPDATE users SET $setClause WHERE username = :username");

            foreach ($params as $param => $value) {
                $db->bind($param, $value);
            }

            return $db->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update profile fields for a user.
     */
    public static function updateProfile(string $username, array $profileData): bool
    {
        $fields = array_intersect_key(
            $profileData,
            array_flip(['first_name', 'last_name', 'email'])
        );

        if (empty($fields)) {
            return false;
        }

        return self::updateUser($username, $fields);
    }

    /**
     * Get all users (for admin interface)
     *
     * @return array
     */
    public static function getAllUsers(): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT username, role, first_name, last_name, email, is_active, created_at, last_login
            FROM users
            ORDER BY username
        ');
        return $db->resultSet();
    }

    /**
     * Deactivate a user
     *
     * @param string $username
     * @return bool
     */
    public static function deactivateUser(string $username): bool
    {
        return self::updateUser($username, ['is_active' => 0]);
    }

    /**
     * Activate a user
     *
     * @param string $username
     * @return bool
     */
    public static function activateUser(string $username): bool
    {
        return self::updateUser($username, ['is_active' => 1]);
    }

    /**
     * Delete a user and all associated assignments
     *
     * @param string $username
     * @return bool
     */
    public static function deleteUser(string $username): bool
    {
        $db = DatabaseManager::getInstance();
        $transactionStarted = false;

        try {
            $transactionStarted = $db->beginTransaction();

            // Delete domain assignments
            $db->query('DELETE FROM user_domain_assignments WHERE user_id = :username');
            $db->bind(':username', $username);
            $db->execute();

            // Delete group assignments
            $db->query('DELETE FROM user_group_assignments WHERE user_id = :username');
            $db->bind(':username', $username);
            $db->execute();

            // Delete user
            $db->query('DELETE FROM users WHERE username = :username');
            $db->bind(':username', $username);
            $db->execute();

            if ($transactionStarted) {
                $db->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                try {
                    $db->rollBack();
                } catch (\Throwable) {
                    // Ignore rollback errors to preserve original exception context
                }
            }

            return false;
        }
    }

    /**
     * Assign user to domain
     *
     * @param string $username
     * @param int $domainId
     * @param string $assignedBy
     * @return bool
     */
    public static function assignUserToDomain(string $username, int $domainId, string $assignedBy): bool
    {
        try {
            $db = DatabaseManager::getInstance();
            $db->query('
                INSERT INTO user_domain_assignments (user_id, domain_id, assigned_by)
                VALUES (:username, :domain_id, :assigned_by)
            ');
            $db->bind(':username', $username);
            $db->bind(':domain_id', $domainId);
            $db->bind(':assigned_by', $assignedBy);
            return $db->execute();
        } catch (\Exception $e) {
            return false; // Likely duplicate assignment
        }
    }

    /**
     * Remove user from domain
     *
     * @param string $username
     * @param int $domainId
     * @return bool
     */
    public static function removeUserFromDomain(string $username, int $domainId): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM user_domain_assignments WHERE user_id = :username AND domain_id = :domain_id');
        $db->bind(':username', $username);
        $db->bind(':domain_id', $domainId);
        return $db->execute();
    }

    /**
     * Assign user to group
     *
     * @param string $username
     * @param int $groupId
     * @param string $assignedBy
     * @return bool
     */
    public static function assignUserToGroup(string $username, int $groupId, string $assignedBy): bool
    {
        try {
            $db = DatabaseManager::getInstance();
            $db->query('
                INSERT INTO user_group_assignments (user_id, group_id, assigned_by)
                VALUES (:username, :group_id, :assigned_by)
            ');
            $db->bind(':username', $username);
            $db->bind(':group_id', $groupId);
            $db->bind(':assigned_by', $assignedBy);
            return $db->execute();
        } catch (\Exception $e) {
            return false; // Likely duplicate assignment
        }
    }

    /**
     * Remove user from group
     *
     * @param string $username
     * @param int $groupId
     * @return bool
     */
    public static function removeUserFromGroup(string $username, int $groupId): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM user_group_assignments WHERE user_id = :username AND group_id = :group_id');
        $db->bind(':username', $username);
        $db->bind(':group_id', $groupId);
        return $db->execute();
    }

    /**
     * Get user's domain assignments
     *
     * @param string $username
     * @return array
     */
    public static function getUserDomains(string $username): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT d.*, uda.assigned_at, uda.assigned_by
            FROM domains d
            JOIN user_domain_assignments uda ON d.id = uda.domain_id
            WHERE uda.user_id = :username
            ORDER BY d.domain
        ');
        $db->bind(':username', $username);
        return $db->resultSet();
    }

    /**
     * Get user's group assignments
     *
     * @param string $username
     * @return array
     */
    public static function getUserGroups(string $username): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT dg.*, uga.assigned_at, uga.assigned_by
            FROM domain_groups dg
            JOIN user_group_assignments uga ON dg.id = uga.group_id
            WHERE uga.user_id = :username
            ORDER BY dg.name
        ');
        $db->bind(':username', $username);
        return $db->resultSet();
    }
}
