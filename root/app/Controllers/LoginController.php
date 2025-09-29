<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: LoginController.php
 * Description: V PHP Framework
 */

namespace App\Controllers;

use App\Models\Users;
use App\Models\Blacklist;
use App\Core\ErrorManager;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;

class LoginController extends Controller
{
    /**
     * Show the login form when the user is not already authenticated.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        $session = SessionManager::getInstance();
        if ($session->get('logged_in') === true) {
            header('Location: /');
            exit();
        }

        $this->render('login', []);
    }

    /**
     * Handle login form submission and logout actions.
     *
     * @return void
     */
    public function handleSubmission(): void
    {
        $session = SessionManager::getInstance();
        if ($session->get('logged_in') === true && isset($_POST['logout'])) {
            if (Csrf::validate($_POST['csrf_token'] ?? '')) {
                self::logoutUser();
            } else {
                MessageHelper::addMessage('Invalid CSRF token. Please try again.');
                header('Location: /login');
                exit();
            }
        }

        if ($session->get('logged_in') === true && !isset($_POST['logout'])) {
            header('Location: /');
            exit();
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid CSRF token. Please try again.';
            ErrorManager::getInstance()->log($error);
            MessageHelper::addMessage($error);
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $userInfo = self::validateCredentials($username, $password);

            if ($userInfo) {
                $session->set('logged_in', true);
                $session->set('username', $userInfo->username);
                $session->set('user_agent', $_SERVER['HTTP_USER_AGENT']);
                $session->set('csrf_token', bin2hex(random_bytes(32)));
                $session->set('is_admin', $userInfo->admin);
                $session->set('timeout', time());
                $session->regenerate();
                
                // Update last login timestamp
                Users::updateLastLogin($userInfo->username);
                
                header('Location: /');
                exit();
            }

            $ip = $_SERVER['REMOTE_ADDR'];
            if (Blacklist::isBlacklisted($ip)) {
                $error = 'Your IP has been blacklisted due to multiple failed login attempts.';
                ErrorManager::getInstance()->log($error);
                MessageHelper::addMessage($error);
            } else {
                Blacklist::updateFailedAttempts($ip);
                $error = 'Invalid username or password.';
                ErrorManager::getInstance()->log($error);
                MessageHelper::addMessage($error);
            }
        }

        $this->render('login', []);
    }

    /**
     * Destroy the user session and redirect to the login page.
     *
     * @return void
     */
    private static function logoutUser(): void
    {
        SessionManager::getInstance()->destroy();
        header('Location: /login');
        exit();
    }

    /**
     * Validate the supplied login credentials.
     *
     * @param string $username Submitted username
     * @param string $password Submitted password
     * @return object|null Returns user info on success or null on failure
     */
    private static function validateCredentials(string $username, string $password): ?object
    {
        $userInfo = Users::getUserInfo($username);

        return ($userInfo && password_verify($password, $userInfo->password)) ? $userInfo : null;
    }
}
