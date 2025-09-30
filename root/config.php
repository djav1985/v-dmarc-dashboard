<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 1.0.0
 *
 * File: config.php
 * Description: A basic PHP framework
 */

// Session timeout limit in seconds (default: 30 minutes)
define('SESSION_TIMEOUT_LIMIT', 1800);

// Cron runtime limits
define('CRON_MAX_EXECUTION_TIME', getenv('CRON_MAX_EXECUTION_TIME') !== false ? getenv('CRON_MAX_EXECUTION_TIME') : 0);
define('CRON_MEMORY_LIMIT', getenv('CRON_MEMORY_LIMIT') !== false ? getenv('CRON_MEMORY_LIMIT') : '512M');
define('CRON_QUEUE_LIMIT', getenv('CRON_QUEUE_LIMIT') !== false ? (int) getenv('CRON_QUEUE_LIMIT') : 10);

// MySQL Database Connection Constants
define('DB_HOST', 'localhost'); // Database host or server
define('DB_USER', 'test'); // Database username
define('DB_PASSWORD', ''); // Database password
define('DB_NAME', 'dmarc_dashboard'); // Database schema name

// Development: Use SQLite for demo purposes
define('USE_SQLITE', true);
define('SQLITE_DB_PATH', __DIR__ . '/demo.db');

// SMTP settings for sending emails
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'user@example.com');
define('SMTP_PASSWORD', 'password');
define('SMTP_FROM_EMAIL', 'no-reply@example.com');
define('SMTP_FROM_NAME', 'DMARC Dashboard');

// File storage paths
define('PDF_REPORT_STORAGE_PATH', __DIR__ . '/storage/pdf_reports');

// Application settings
define('APP_NAME', 'DMARC Dashboard');
define('APP_VERSION', '1.0.0');

// IMAP settings for email ingestion
define('IMAP_HOST', 'imap.example.com');
define('IMAP_PORT', 993);
define('IMAP_USERNAME', 'dmarc@example.com');
define('IMAP_PASSWORD', 'password');
define('IMAP_MAILBOX', 'INBOX');
define('IMAP_SSL', true);


// Validate required configuration constants
$required_constants = ['DB_HOST', 'DB_USER', 'DB_NAME'];
$missing_config = [];

foreach ($required_constants as $constant) {
    if (!defined($constant) || empty(constant($constant))) {
        $missing_config[] = $constant;
    }
}

if (!empty($missing_config)) {
    error_log("Missing required configuration: " . implode(', ', $missing_config));
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        die("Configuration error: Missing required settings. Please check server logs.");
    }
}
