<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Utilities\DmarcParser;
use App\Models\DmarcReport;
use App\Helpers\MessageHelper;
use Exception;

class UploadController extends Controller
{
    /**
     * Display the upload form.
     */
    public function handleRequest(): void
    {
        $this->render('upload');
    }

    /**
     * Handle file upload and processing.
     */
    public function handleSubmission(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            MessageHelper::addMessage('Invalid CSRF token. Please try again.');
            header('Location: /upload');
            exit;
        }

        if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
            MessageHelper::addMessage('Please select a valid file to upload.');
            header('Location: /upload');
            exit;
        }

        $uploadedFile = $_FILES['report_file'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($uploadedFile['size'] > $maxSize) {
            MessageHelper::addMessage('File size exceeds 10MB limit.');
            header('Location: /upload');
            exit;
        }

        // Validate file extension
        $allowedExtensions = ['xml', 'gz', 'zip'];
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
            MessageHelper::addMessage('Only XML, GZ, and ZIP files are allowed.');
            header('Location: /upload');
            exit;
        }

        try {
            // Parse the uploaded file
            $reportData = DmarcParser::parseCompressedReport($uploadedFile['tmp_name']);

            // Store aggregate report
            $reportId = DmarcReport::storeAggregateReport($reportData);

            // Store individual records
            if (!empty($reportData['records'])) {
                DmarcReport::storeAggregateRecords($reportId, $reportData['records']);
            }

            $recordCount = count($reportData['records']);
            $domain = $reportData['policy_published_domain'];
            MessageHelper::addMessage(
                "Successfully processed DMARC report with {$recordCount} records for domain '{$domain}'."
            );
        } catch (Exception $e) {
            MessageHelper::addMessage('Failed to process report: ' . $e->getMessage());
        }

        header('Location: /upload');
        exit;
    }
}
