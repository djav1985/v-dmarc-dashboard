<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DmarcParser;
use App\Core\SessionManager;
use Exception;

/**
 * DMARC Report Upload Controller
 */
class UploadController extends Controller
{
    /**
     * Display upload form
     */
    public function handleRequest(): void
    {
        $data = [
            'title' => 'Upload DMARC Reports'
        ];

        $this->render('upload', $data);
    }

    /**
     * Handle file upload and processing
     */
    public function handleSubmission(): void
    {
        if (!isset($_FILES['dmarc_file']) || $_FILES['dmarc_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Please select a valid DMARC report file';
            header('Location: /upload');
            return;
        }

        $file = $_FILES['dmarc_file'];
        $allowedTypes = ['application/xml', 'text/xml', 'application/gzip', 'application/zip'];

        // Basic file validation
        if (!in_array($file['type'], $allowedTypes) && !$this->isValidFileExtension($file['name'])) {
            $_SESSION['error'] = 'Invalid file type. Please upload XML, GZ, or ZIP files only.';
            header('Location: /upload');
            return;
        }

        try {
            // Read file content
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                throw new Exception('Could not read uploaded file');
            }

            // Parse the DMARC report
            $parsed = DmarcParser::parseAggregateReport($content, $file['name']);

            if (!$parsed['success']) {
                $_SESSION['error'] = 'Failed to parse DMARC report: ' . ($parsed['error'] ?? 'Unknown error');
                header('Location: /upload');
                return;
            }

            // Process and store the report
            if (DmarcParser::processReport($parsed)) {
                $_SESSION['success'] = sprintf(
                    'Successfully processed DMARC report from %s for domain %s with %d records',
                    $parsed['metadata']['org_name'],
                    $parsed['policy']['domain'],
                    count($parsed['records'])
                );
            } else {
                $_SESSION['error'] = 'Failed to store DMARC report in database';
            }

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error processing file: ' . $e->getMessage();
        }

        header('Location: /upload');
    }

    /**
     * Check if file has valid extension
     */
    private function isValidFileExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, ['xml', 'gz', 'zip']);
    }
}