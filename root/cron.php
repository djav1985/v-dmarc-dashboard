<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: cron.php
 * Description: V PHP Framework
 */

// This script is intended for CLI use only. If accessed via a web server,
// return HTTP 403 Forbidden.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\ErrorManager;
use App\Utilities\DataRetention;
use App\Services\ImapIngestionService;
use App\Services\AlertService;
use App\Services\EmailDigestService;
use App\Services\PdfReportScheduler;
use Throwable;

// Apply configured runtime limits after loading settings
ini_set('max_execution_time', (string) (defined('CRON_MAX_EXECUTION_TIME') ? CRON_MAX_EXECUTION_TIME : 0));
ini_set('memory_limit', defined('CRON_MEMORY_LIMIT') ? CRON_MEMORY_LIMIT : '512M');

// Run the job logic within the error Manager handler
ErrorManager::handle(function () {
    global $argv;

    // List of supported job types. Add additional types here as needed.
    $validJobTypes = [
        'daily',   // Runs once per day
        'hourly',  // Runs once per hour
        'imap',    // Process IMAP emails
    ];

    // The job type is provided as the first CLI argument. Defaults to
    // 'hourly' when no argument is supplied.
    $jobType = $argv[1] ?? 'hourly';

if (!in_array($jobType, $validJobTypes)) {
    die("Invalid job type specified.");
}

    // Run tasks for the selected job type. Place any custom work inside
    // the relevant case blocks below.
    switch ($jobType) {
        case 'daily':
            // Add tasks that should run once per day
            echo "Running daily DMARC Dashboard maintenance tasks...\n";
            
            // Clean up old reports based on retention settings
            if (DataRetention::isCleanupNeeded()) {
                echo "Starting data cleanup...\n";
                $cleanupResults = DataRetention::cleanupOldReports();
                
                echo "Cleanup completed:\n";
                echo "- Aggregate reports deleted: {$cleanupResults['aggregate_reports_deleted']}\n";
                echo "- Forensic reports deleted: {$cleanupResults['forensic_reports_deleted']}\n";
                echo "- TLS reports deleted: {$cleanupResults['tls_reports_deleted']}\n";
                
                if (!empty($cleanupResults['errors'])) {
                    echo "Cleanup errors:\n";
                    foreach ($cleanupResults['errors'] as $error) {
                        echo "- {$error}\n";
                    }
                }
            } else {
                echo "No cleanup needed at this time.\n";
            }
            
            // Display storage statistics
            $stats = DataRetention::getStorageStats();
            echo "\nStorage Statistics:\n";
            echo "- Total domains: {$stats['total_domains']}\n";
            echo "- Aggregate reports: {$stats['aggregate_reports_count']}\n";
            echo "- Forensic reports: {$stats['forensic_reports_count']}\n";
            echo "- TLS reports: {$stats['tls_reports_count']}\n";
            
            if ($stats['oldest_aggregate_report']) {
                echo "- Oldest report: " . date('Y-m-d', $stats['oldest_aggregate_report']) . "\n";
                echo "- Newest report: " . date('Y-m-d', $stats['newest_aggregate_report']) . "\n";
            }
            break;
            
        case 'hourly':
            // Add tasks that should run once per hour
            echo "Running hourly DMARC Dashboard tasks...\n";
            try {
                $alertResults = AlertService::runAlertChecks();
                if (!empty($alertResults)) {
                    echo 'Alert checks triggered ' . count($alertResults) . " incident(s).\n";
                } else {
                    echo "No new alert incidents detected.\n";
                }
            } catch (Throwable $exception) {
                echo "Error running alert checks: " . $exception->getMessage() . "\n";
            }

            try {
                $digestResults = EmailDigestService::processDueDigests();
                if (!empty($digestResults)) {
                    foreach ($digestResults as $result) {
                        $status = $result['success'] ? 'sent' : 'failed';
                        $next = $result['next_run'] ?? 'unscheduled';
                        echo "Digest schedule #{$result['schedule_id']} {$status}; next run {$next}.\n";
                        if (!$result['success'] && !empty($result['message'])) {
                            echo "  Reason: {$result['message']}\n";
                        }
                    }
                } else {
                    echo "No digests due this hour.\n";
                }
            } catch (Throwable $exception) {
                echo "Error processing digests: " . $exception->getMessage() . "\n";
            }

            try {
                $scheduleResults = PdfReportScheduler::processDueSchedules();
                if (!empty($scheduleResults)) {
                    foreach ($scheduleResults as $result) {
                        $status = $result['success'] ? 'generated' : 'failed';
                        $message = $result['message'] ?? '';
                        echo "Schedule #{$result['schedule_id']} {$status}.";
                        if ($message !== '') {
                            echo " {$message}";
                        }
                        if (!empty($result['next_run'])) {
                            echo " Next run {$result['next_run']}.";
                        }
                        echo "\n";
                    }
                } else {
                    echo "No scheduled PDF reports due this hour.\n";
                }
            } catch (Throwable $exception) {
                echo "Error processing PDF schedules: " . $exception->getMessage() . "\n";
            }
            break;
            
        case 'imap':
            // Process IMAP emails for DMARC reports
            echo "Processing IMAP emails for DMARC reports...\n";
            try {
                $imapService = new ImapIngestionService();
                $results = $imapService->processReports();
                
                echo "Email processing completed:\n";
                echo "- Reports processed: {$results['processed']}\n";
                echo "- Errors: {$results['errors']}\n";
                
                foreach ($results['messages'] as $message) {
                    echo "- $message\n";
                }
            } catch (Exception $e) {
                echo "Error during IMAP processing: " . $e->getMessage() . "\n";
            }
            break;
            
        default:
            die(1);
    }
});
