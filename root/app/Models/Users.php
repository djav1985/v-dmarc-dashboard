<?php

namespace App\Models;

use App\Core\DatabaseManager;

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
        $db->query('SELECT * FROM users WHERE username = :username');
        $db->bind(':username', $username);
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
        $db->query('UPDATE users SET last_login = NOW() WHERE username = :username');
        $db->bind(':username', $username);
        return $db->execute();
    }

    /**
     * Check if user is admin.
     *
     * @param string $username
     * @return bool
     */
    public static function isAdmin(string $username): bool
    {
        $userInfo = self::getUserInfo($username);
        return $userInfo && (bool) $userInfo->admin;
    }
}
