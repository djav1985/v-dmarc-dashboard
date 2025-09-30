<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use App\Models\PasswordReset;
use App\Models\Users;

class PasswordResetController extends Controller
{
    /**
     * Display the password reset request form.
     */
    public function handleRequest(): void
    {
        $this->ensureCsrfToken();
        $this->render('password_reset/request');
    }

    /**
     * Handle a password reset email request.
     */
    public function handleSubmission(): void
    {
        $this->ensureCsrfToken();
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /password-reset');
            exit();
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            MessageHelper::addMessage('Please provide a valid email address.', 'error');
            header('Location: /password-reset');
            exit();
        }

        PasswordReset::purgeExpiredTokens();
        $user = Users::getUserByEmail($email);
        if ($user !== null) {
            $token = PasswordReset::createToken($user->username, $email);
            $resetUrl = $this->buildResetUrl($token);
            $mailerData = [
                'firstName' => $user->first_name ?? $user->username,
                'resetUrl' => $resetUrl,
                'appName' => defined('APP_NAME') ? APP_NAME : 'DMARC Dashboard',
            ];
            Mailer::sendTemplate($email, 'Password Reset Instructions', 'password_reset_email', $mailerData);
        }

        MessageHelper::addMessage('If the email matches an account, password reset instructions have been sent.', 'success');
        header('Location: /password-reset');
        exit();
    }

    /**
     * Show the password reset form when token is valid.
     */
    public function showResetForm(string $token): void
    {
        $this->ensureCsrfToken();
        PasswordReset::purgeExpiredTokens();
        $record = PasswordReset::validateToken($token);
        if ($record === null) {
            MessageHelper::addMessage('The password reset link is invalid or has expired.', 'error');
            header('Location: /password-reset');
            exit();
        }

        $this->render('password_reset/reset', [
            'token' => $token,
            'email' => $record['email'] ?? '',
        ]);
    }

    /**
     * Process the password reset submission.
     */
    public function processReset(string $token): void
    {
        $this->ensureCsrfToken();
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /password-reset');
            exit();
        }

        PasswordReset::purgeExpiredTokens();
        $record = PasswordReset::validateToken($token);
        if ($record === null) {
            MessageHelper::addMessage('The password reset link is invalid or has expired.', 'error');
            header('Location: /password-reset');
            exit();
        }

        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($password === '' || strlen($password) < 12) {
            MessageHelper::addMessage('Password must be at least 12 characters long.', 'error');
            header('Location: /password-reset/' . urlencode($token));
            exit();
        }

        if (!hash_equals($password, $confirm)) {
            MessageHelper::addMessage('Password confirmation does not match.', 'error');
            header('Location: /password-reset/' . urlencode($token));
            exit();
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $updated = Users::updateUser($record['username'], ['password' => $hashed]);
        if ($updated) {
            PasswordReset::consumeToken($record['selector']);
            MessageHelper::addMessage('Password has been reset. You can now log in.', 'success');
            header('Location: /login');
            exit();
        }

        MessageHelper::addMessage('Unable to update password at this time. Please try again.', 'error');
        header('Location: /password-reset/' . urlencode($token));
        exit();
    }

    private function buildResetUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return sprintf('%s://%s/password-reset/%s', $scheme, $host, urlencode($token));
    }

    private function ensureCsrfToken(): void
    {
        $session = SessionManager::getInstance();
        if (!$session->get('csrf_token')) {
            $session->set('csrf_token', bin2hex(random_bytes(32)));
        }
    }
}
