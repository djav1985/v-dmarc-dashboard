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
            break;
        case 'hourly':
            // Add tasks that should run once per hour
            break;
        default:
            die(1);
    }
});
