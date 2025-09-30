<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Utilities\DmarcParser;
use App\Models\DmarcReport;
use App\Helpers\MessageHelper;
use App\Core\RBACManager;
use Exception;

class UploadController extends Controller
{
    /**
     * Display the upload form.
     */
    public function handleRequest(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_UPLOAD_REPORTS);
        $this->render('upload');
    }

    /**
     * Handle file upload and processing.
     */
    public function handleSubmission(): void
    {
        RBACManager::getInstance()->requirePermission(RBACManager::PERM_UPLOAD_REPORTS);
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.');
            header('Location: /upload');
            exit;
        }

        $files = $this->collectUploadedFiles($_FILES);
        if (empty($files)) {
            MessageHelper::addMessage('Please select at least one report to upload.', 'error');
            header('Location: /upload');
            exit;
        }

        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        foreach ($files as $file) {
            $results['processed']++;
            try {
                $this->validateFile($file);
                $message = $this->processReportFile($file);
                $results['success']++;
                $results['messages'][] = ['text' => $message, 'type' => 'success'];
            } catch (Exception $e) {
                $results['failed']++;
                $results['messages'][] = ['text' => $e->getMessage(), 'type' => 'error'];
            }
        }

        foreach ($results['messages'] as $message) {
            MessageHelper::addMessage($message['text'], $message['type']);
        }

        $summary = sprintf(
            'Processed %d file(s): %d succeeded, %d failed.',
            $results['processed'],
            $results['success'],
            $results['failed']
        );
        MessageHelper::addMessage($summary, $results['failed'] > 0 ? 'warning' : 'success');

        header('Location: /upload');
        exit;
    }

    /**
     * Normalize uploaded files into a consistent array structure.
     */
    private function collectUploadedFiles(array $files): array
    {
        $uploads = [];
        if (isset($files['report_files'])) {
            $group = $files['report_files'];
            if (is_array($group['name'])) {
                $count = count($group['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($group['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $uploads[] = [
                        'name' => $group['name'][$i],
                        'type' => $group['type'][$i],
                        'tmp_name' => $group['tmp_name'][$i],
                        'error' => $group['error'][$i],
                        'size' => $group['size'][$i],
                    ];
                }
            }
        }

        // The old single-file field 'report_file' is no longer supported.

        return $uploads;
    }

    /**
     * Validate size and format of uploaded file.
     */
    private function validateFile(array $file): void
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds 10MB limit.');
        }

        $allowedExtensions = ['xml', 'gz', 'zip'];
        $fileExtension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            throw new Exception('Only XML, GZ, and ZIP files are allowed.');
        }
    }

    /**
     * Process a single uploaded report file.
     */
    private function processReportFile(array $file): string
    {
        $reportData = DmarcParser::parseCompressedReport($file['tmp_name']);
        $reportId = DmarcReport::storeAggregateReport($reportData);

        if (!empty($reportData['records'])) {
            DmarcReport::storeAggregateRecords($reportId, $reportData['records']);
        }

        $this->triggerAdditionalConnectors($reportData, $reportId);

        $recordCount = count($reportData['records']);
        $domain = $reportData['policy_published_domain'];
        return sprintf(
            "Successfully processed '%s' with %d record(s) for domain '%s'.",
            $file['name'],
            $recordCount,
            $domain
        );
    }

    /**
     * Placeholder for future ingestion connectors.
     */
    private function triggerAdditionalConnectors(array $reportData, int $reportId): void
    {
        // Roadmap stubs: enqueue for SIEM ingestion, trigger webhook dispatch, etc.
        // These will be implemented as connectors become available.
        unset($reportData, $reportId);
    }
}
