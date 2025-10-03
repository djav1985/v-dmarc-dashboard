<?php

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Provision the application schema from the consolidated install.sql definition.
 */
class Installer
{
    private const SENTINEL_TABLE = 'users';

    private DatabaseManager $db;
    private string $schemaPath;

    public function __construct(?DatabaseManager $db = null, ?string $schemaPath = null)
    {
        $this->db = $db ?? DatabaseManager::getInstance();
        $this->schemaPath = $schemaPath ?? dirname(__DIR__, 2) . '/install/install.sql';
    }

    /**
     * Ensure the schema exists, running the installer if the sentinel table is missing.
     */
    public static function ensureInstalled(?string $schemaPath = null): bool
    {
        $installer = new self(null, $schemaPath);

        return $installer->runIfMissing();
    }

    /**
     * Execute the install workflow when the schema has not been provisioned yet.
     */
    public function runIfMissing(): bool
    {
        if ($this->isInstalled()) {
            return false;
        }

        $statements = $this->loadStatements();

        foreach ($statements as $statement) {
            $this->db->query($statement);
            $this->db->execute();
        }

        if (!$this->isInstalled()) {
            throw new RuntimeException('Database installation did not complete successfully.');
        }

        return true;
    }

    private function isInstalled(): bool
    {
        $driver = $this->getDriverVariant();

        try {
            if ($driver === 'mysql') {
                $this->db->query(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
                );
                $this->db->bind(':table', self::SENTINEL_TABLE);
            } else {
                $this->db->query(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1"
                );
                $this->db->bind(':table', self::SENTINEL_TABLE);
            }

            return (bool) $this->db->single();
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * Load and normalize SQL statements for the active driver.
     *
     * @return array<int,string>
     */
    private function loadStatements(): array
    {
        if (!is_file($this->schemaPath)) {
            throw new RuntimeException('Install schema not found at ' . $this->schemaPath);
        }

        $rawSql = file_get_contents($this->schemaPath);
        if ($rawSql === false) {
            throw new RuntimeException('Unable to read install schema.');
        }

        $normalized = $this->normalizeSql($rawSql, $this->getDriverVariant());

        return $this->splitStatements($normalized);
    }

    private function getDriverVariant(): string
    {
        $driver = strtolower($this->db->getDriverName());

        if (str_contains($driver, 'mysql') || str_contains($driver, 'mariadb')) {
            return 'mysql';
        }

        if (str_contains($driver, 'sqlite')) {
            return 'sqlite';
        }

        return $driver;
    }

    private function normalizeSql(string $sql, string $driver): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $sql);

        if ($driver === 'mysql') {
            $normalized = preg_replace('/^\s*PRAGMA[^;]*;?/mi', '', $normalized) ?? $normalized;
            $normalized = preg_replace(
                '/INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i',
                'INT AUTO_INCREMENT PRIMARY KEY',
                $normalized
            ) ?? $normalized;
            $normalized = preg_replace('/AUTOINCREMENT/i', 'AUTO_INCREMENT', $normalized) ?? $normalized;
            $normalized = preg_replace(
                '/updated_at\s+DATETIME\s+DEFAULT\s+CURRENT_TIMESTAMP(?!\s+ON\s+UPDATE)/i',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                $normalized
            ) ?? $normalized;
            $normalized = preg_replace(
                '/last_activity\s+DATETIME\s+DEFAULT\s+CURRENT_TIMESTAMP(?!\s+ON\s+UPDATE)/i',
                'last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                $normalized
            ) ?? $normalized;
        }

        return trim($normalized);
    }

    /**
     * Split normalized SQL into executable statements while respecting quoted strings.
     *
     * @return array<int,string>
     */
    private function splitStatements(string $sql): array
    {
        $cleanSql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql) ?? $cleanSql;

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
}

