<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RBACManager;
use App\Core\SessionManager;
use App\Helpers\MessageHelper;
use App\Models\Blacklist;

class BlacklistController extends Controller
{
    public function handleRequest(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_MANAGE_SECURITY);
        $this->ensureCsrfToken();

        $entries = Blacklist::getAll();
        $this->render('blacklist/index', ['entries' => $entries]);
    }

    public function handleSubmission(): void
    {
        $rbac = RBACManager::getInstance();
        $rbac->requirePermission(RBACManager::PERM_MANAGE_SECURITY);
        $this->ensureCsrfToken();

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.', 'error');
            header('Location: /blacklist');
            exit();
        }

        $action = $_POST['action'] ?? '';
        $ip = filter_var($_POST['ip'] ?? '', FILTER_VALIDATE_IP);
        if ($ip === false) {
            MessageHelper::addMessage('Please provide a valid IPv4 or IPv6 address.', 'error');
            header('Location: /blacklist');
            exit();
        }

        if ($action === 'ban') {
            if (Blacklist::banIp($ip)) {
                AuditLogger::getInstance()->log(
                    AuditLogger::ACTION_UPDATE,
                    AuditLogger::RESOURCE_SETTINGS,
                    'blacklist',
                    ['ip' => $ip, 'state' => 'banned']
                );
                MessageHelper::addMessage("IP {$ip} has been blacklisted.", 'success');
            } else {
                MessageHelper::addMessage('Unable to blacklist the IP address.', 'error');
            }
        } elseif ($action === 'unban') {
            if (Blacklist::unbanIp($ip)) {
                AuditLogger::getInstance()->log(
                    AuditLogger::ACTION_UPDATE,
                    AuditLogger::RESOURCE_SETTINGS,
                    'blacklist',
                    ['ip' => $ip, 'state' => 'unbanned']
                );
                MessageHelper::addMessage("IP {$ip} has been removed from the blacklist.", 'success');
            } else {
                MessageHelper::addMessage('Unable to update the blacklist entry.', 'error');
            }
        } else {
            MessageHelper::addMessage('Unknown blacklist action requested.', 'error');
        }

        header('Location: /blacklist');
        exit();
    }

    private function ensureCsrfToken(): void
    {
        $session = SessionManager::getInstance();
        if (!$session->get('csrf_token')) {
            $session->set('csrf_token', bin2hex(random_bytes(32)));
        }
    }
}
