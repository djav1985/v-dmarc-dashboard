<?php
// install.php - Installs database tables using credentials from config.php and install.sql

require_once __DIR__ . '/../config.php';

$dbHost = defined('DB_HOST') && trim(DB_HOST) !== '' ? DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? DB_NAME : '';
$dbUser = defined('DB_USER') ? DB_USER : '';
$dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';

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

$sqlFile = __DIR__ . '/install.sql';
if (!file_exists($sqlFile)) {
    die('install.sql not found.');
}

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "Database tables installed successfully.";
} catch (PDOException $e) {
    echo "Database installation failed: " . $e->getMessage();
    exit(1);
}
