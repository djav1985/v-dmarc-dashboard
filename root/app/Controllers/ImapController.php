<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\ImapIngestionService;
use App\Helpers\MessageHelper;
use App\Core\RBACManager;

class ImapController extends Controller
{
    /**
     * Display IMAP configuration and management interface.
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_UPLOAD_REPORTS);
        $this->render('imap', [
            'imap_configured' => $this->isImapConfigured(),
            'connection_status' => null
        ]);
    }

    /**
     * Handle IMAP actions (test connection, process emails).
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_UPLOAD_REPORTS);
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.');
            header('Location: /imap');
            exit;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'test_connection':
                $this->testConnection();
                break;

            case 'process_emails':
                $this->processEmails();
                break;

            default:
                MessageHelper::addMessage('Invalid action specified.');
        }

        header('Location: /imap');
        exit;
    }

    /**
     * Test IMAP connection.
     */
    private function testConnection(): void
    {
        try {
            $imapService = new ImapIngestionService();
            $result = $imapService->testConnection();

            if ($result['success']) {
                $details = $result['details'];
                $message = "IMAP connection successful! Found {$details['messages']} messages ({$details['recent']} recent)";
                MessageHelper::addMessage($message);
            } else {
                MessageHelper::addMessage('IMAP connection failed: ' . $result['message']);
            }
        } catch (\Exception $e) {
            MessageHelper::addMessage('Error testing IMAP connection: ' . $e->getMessage());
        }
    }

    /**
     * Process emails from IMAP inbox.
     */
    private function processEmails(): void
    {
        try {
            $imapService = new ImapIngestionService();
            $results = $imapService->processReports();

            $message = "Email processing completed: {$results['processed']} reports processed";
            if ($results['errors'] > 0) {
                $message .= ", {$results['errors']} errors occurred";
            }

            MessageHelper::addMessage($message);

            foreach ($results['messages'] as $msg) {
                MessageHelper::addMessage($msg);
            }
        } catch (\Exception $e) {
            MessageHelper::addMessage('Error processing emails: ' . $e->getMessage());
        }
    }

    /**
     * Check if IMAP is properly configured.
     *
     * @return bool
     */
    private function isImapConfigured(): bool
    {
        return defined('IMAP_HOST') &&
               defined('IMAP_USERNAME') &&
               defined('IMAP_PASSWORD') &&
               !empty(IMAP_HOST) &&
               !empty(IMAP_USERNAME) &&
               !empty(IMAP_PASSWORD);
    }
}
