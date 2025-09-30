<?php

// install.php - Installs database tables using credentials from config.php and install.sql

require_once __DIR__ . '/../config.php';

/**
 * Transform the consolidated schema so it is compatible with the requested driver.
 */
function normalizeSchemaSql(string $sql, string $driver): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $sql);

    if ($driver === 'mysql') {
        $normalized = preg_replace('/^\s*PRAGMA[^;]*;?/mi', '', $normalized);
        $normalized = preg_replace('/INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i', 'INT AUTO_INCREMENT PRIMARY KEY', $normalized);
        $normalized = preg_replace('/AUTOINCREMENT/i', 'AUTO_INCREMENT', $normalized);
        $normalized = preg_replace(
            '/updated_at\s+DATETIME\s+DEFAULT\s+CURRENT_TIMESTAMP(?!\s+ON\s+UPDATE)/i',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            $normalized
        );
        $normalized = preg_replace(
            '/last_activity\s+DATETIME\s+DEFAULT\s+CURRENT_TIMESTAMP(?!\s+ON\s+UPDATE)/i',
            'last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            $normalized
        );
    }

    return (string) $normalized;
}

/**
 * Split SQL into executable statements while respecting quoted strings.
 *
 * @return array<int, string>
 */
function splitSqlStatements(string $sql): array
{
    $cleanSql = preg_replace('/--.*$/m', '', $sql);
    $cleanSql = preg_replace('/\/\*.*?\*\//s', '', (string) $cleanSql);

    $statements = [];
    $buffer = '';
    $inString = false;
    $stringDelimiter = '';
    $length = strlen($cleanSql);

    for ($i = 0; $i < $length; $i++) {
        $char = $cleanSql[$i];

        if ($inString) {
            $buffer .= $char;
            if ($char === $stringDelimiter) {
                $escaped = false;
                $backIndex = $i - 1;
                while ($backIndex >= 0 && $cleanSql[$backIndex] === '\\') {
                    $escaped = !$escaped;
                    $backIndex--;
                }
                if (!$escaped) {
                    $inString = false;
                    $stringDelimiter = '';
                }
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $inString = true;
            $stringDelimiter = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $remaining = trim($buffer);
    if ($remaining !== '') {
        $statements[] = $remaining;
    }

    return $statements;
}

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
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $rawSql = file_get_contents($sqlFile) ?: '';
    $normalizedSql = normalizeSchemaSql($rawSql, $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $statements = splitSqlStatements($normalizedSql);

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    echo 'Database tables installed successfully.';
} catch (PDOException $e) {
    echo 'Database installation failed: ' . $e->getMessage();
    exit(1);
}
