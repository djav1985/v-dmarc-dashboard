<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use App\Models\Users;

class ProfileController extends Controller
{
    public function handleRequest(): void
    {
        $session = SessionManager::getInstance();
        $this->ensureCsrfToken($session);
        $username = $session->get('username');
        $user = $username ? Users::getUserInfo($username) : null;

        $this->render('profile/index', [
            'user' => $user,
        ]);
    }

    public function handleSubmission(): void
    {
        $session = SessionManager::getInstance();
        $this->ensureCsrfToken($session);

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /profile');
            exit();
        }

        $username = $session->get('username');
        $user = $username ? Users::getUserInfo($username) : null;
        if ($user === null) {
            MessageHelper::addMessage('Unable to load your profile. Please sign in again.', 'error');
            header('Location: /login');
            exit();
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'update_details') {
            $this->updateDetails($user->username, $session);
        } elseif ($action === 'update_password') {
            $this->updatePassword($user);
        } else {
            MessageHelper::addMessage('Unknown profile action requested.', 'error');
        }

        header('Location: /profile');
        exit();
    }

    private function updateDetails(string $username, SessionManager $session): void
    {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';

        if ($email === '') {
            MessageHelper::addMessage('Please provide a valid email address.', 'error');
            return;
        }

        $updated = Users::updateProfile($username, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        if ($updated) {
            $session->set('user_first_name', $firstName);
            $session->set('user_last_name', $lastName);
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_UPDATE,
                AuditLogger::RESOURCE_USER,
                $username,
                ['section' => 'profile_details']
            );
            MessageHelper::addMessage('Profile details updated successfully.', 'success');
            return;
        }

        MessageHelper::addMessage('No changes were saved. Please try again.', 'error');
    }

    private function updatePassword(object $user): void
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user->password)) {
            MessageHelper::addMessage('Your current password is incorrect.', 'error');
            return;
        }

        if (strlen($newPassword) < 12) {
            MessageHelper::addMessage('New password must be at least 12 characters long.', 'error');
            return;
        }

        if (!hash_equals($newPassword, $confirmPassword)) {
            MessageHelper::addMessage('New password confirmation does not match.', 'error');
            return;
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = Users::updateUser($user->username, ['password' => $hashed]);
        if ($updated) {
            AuditLogger::getInstance()->log(
                AuditLogger::ACTION_UPDATE,
                AuditLogger::RESOURCE_USER,
                $user->username,
                ['section' => 'profile_password']
            );
            MessageHelper::addMessage('Password updated successfully.', 'success');
            return;
        }

        MessageHelper::addMessage('Unable to update password at this time. Please try again.', 'error');
    }

    private function ensureCsrfToken(SessionManager $session): void
    {
        if (!$session->get('csrf_token')) {
            $session->set('csrf_token', bin2hex(random_bytes(32)));
        }
    }
}
