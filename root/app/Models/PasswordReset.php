<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\ErrorManager;
use Exception;

class PasswordReset
{
    private const TOKEN_TTL = 3600; // 1 hour

    /**
     * Create a password reset token for a given user.
     */
    public static function createToken(string $username, string $email): string
    {
        $selector = bin2hex(random_bytes(8));
        $verifier = bin2hex(random_bytes(32));
        $hash = password_hash($verifier, PASSWORD_DEFAULT);
        $expiresAt = time() + self::TOKEN_TTL;

        $db = DatabaseManager::getInstance();

        // Remove any existing tokens for this user/email combination
        $db->query('DELETE FROM password_reset_tokens WHERE username = :username');
        $db->bind(':username', $username);
        $db->execute();

        $db->query('
            INSERT INTO password_reset_tokens
            (username, email, selector, token_hash, expires_at, created_at)
            VALUES
            (:username, :email, :selector, :token_hash, :expires_at, :created_at)
        ');

        $db->bind(':username', $username);
        $db->bind(':email', $email);
        $db->bind(':selector', $selector);
        $db->bind(':token_hash', $hash);
        $db->bind(':expires_at', $expiresAt);
        $db->bind(':created_at', time());
        $db->execute();

        return $selector . ':' . $verifier;
    }

    /**
     * Validate a supplied password reset token.
     */
    public static function validateToken(string $token): ?array
    {
        [$selector, $verifier] = array_pad(explode(':', $token, 2), 2, null);
        if (empty($selector) || empty($verifier)) {
            return null;
        }

        try {
            $db = DatabaseManager::getInstance();
            $db->query('
                SELECT * FROM password_reset_tokens
                WHERE selector = :selector AND expires_at >= :now
                LIMIT 1
            ');
            $db->bind(':selector', $selector);
            $db->bind(':now', time());
            $record = $db->single();

            if (!$record) {
                return null;
            }

            if (!password_verify($verifier, $record['token_hash'])) {
                return null;
            }

            return $record;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('Password reset validation failed: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Delete a password reset token after use.
     */
    public static function consumeToken(string $selector): void
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM password_reset_tokens WHERE selector = :selector');
        $db->bind(':selector', $selector);
        $db->execute();
    }

    /**
     * Purge expired password reset tokens.
     */
    public static function purgeExpiredTokens(): void
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM password_reset_tokens WHERE expires_at < :now');
        $db->bind(':now', time());
        $db->execute();
    }
}
