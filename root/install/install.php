<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Installer;

$missingConfig = [];

if (defined('DB_HOST') && trim(DB_HOST) === '') {
    $missingConfig[] = 'DB_HOST';
}

foreach (['DB_NAME', 'DB_USER'] as $constant) {
    if (!defined($constant) || trim((string) constant($constant)) === '') {
        $missingConfig[] = $constant;
    }
}

if (!empty($missingConfig)) {
    echo 'Database configuration missing or empty for: ' . implode(', ', $missingConfig)
        . ". Update root/config.php before running the installer." . PHP_EOL;
    exit(1);
}

try {
    $executed = Installer::ensureInstalled(__DIR__ . '/install.sql');

    if ($executed) {
        echo 'Database tables installed successfully.' . PHP_EOL;
    } else {
        echo 'Database already provisioned. No action taken.' . PHP_EOL;
    }
} catch (\Throwable $exception) {
    echo 'Database installation failed: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
