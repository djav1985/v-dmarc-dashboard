<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: DatabaseManager.php
 * Description: V PHP Framework
 */

namespace App\Core;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Exception;
use InvalidArgumentException;
use App\Core\ErrorManager;
class DatabaseManager
{
    private static ?DatabaseManager $instance = null;
    private static ?Connection $dbh = null;
    private static ?int $lastUsedTime = null;
    private static int $idleTimeout = 10;

    private string $sql = '';
    private array $params = [];
    private array $types = [];
    private ?Result $result = null;
    private ?int $affectedRows = null;

    /**
     * Create a new DatabaseManager instance and connect.
     *
     * @return void
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get the singleton DatabaseManager instance.
     *
     * @return DatabaseManager
     */
    public static function getInstance(): DatabaseManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish a database connection if needed.
     *
     * @return void
     */
    private function connect(): void
    {
        if (self::$dbh !== null && self::$lastUsedTime !== null && (time() - self::$lastUsedTime) > self::$idleTimeout) {
            $this->closeConnection();
        }

        if (self::$dbh === null) {
            // Use SQLite if configured for development
            if (defined('USE_SQLITE') && USE_SQLITE) {
                $params = [
                    'driver' => 'pdo_sqlite',
                    'path' => SQLITE_DB_PATH,
                ];
            } else {
                $params = [
                    'dbname'   => DB_NAME,
                    'user'     => DB_USER,
                    'password' => DB_PASSWORD,
                    'host'     => DB_HOST,
                    'driver'   => 'pdo_mysql',
                    'charset'  => 'utf8mb4',
                ];
            }

            try {
                self::$dbh = DriverManager::getConnection($params);
            } catch (DBALException $e) {
                ErrorManager::getInstance()->log('Database connection failed: ' . $e->getMessage(), 'error');
                throw new Exception('Database connection failed');
            }
        }

        self::$lastUsedTime = time();
    }

    /**
     * Close the current database connection.
     *
     * @return void
     */
    private function closeConnection(): void
    {
        self::$dbh = null;
        self::$lastUsedTime = null;
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     */
    private function reconnect(): void
    {
        $this->closeConnection();
        $this->connect();
    }

    /**
     * Build a portable SQL UPSERT statement with bindings for the active driver.
     *
     * @param string            $table          Table name to target.
     * @param array<string,mixed> $insertData   Column => value pairs for the insert section.
     * @param array<string,mixed> $updateData   Column => value pairs or helper descriptors for the update clause.
     * @param array<int,string>|string|null $conflictTarget Optional conflict target for SQLite drivers.
     *
     * @return array{sql:string,bindings:array<string,mixed>}
     */
    public function buildUpsertQuery(
        string $table,
        array $insertData,
        array $updateData,
        array|string|null $conflictTarget = null
    ): array {
        if ($table === '') {
            throw new InvalidArgumentException('Table name is required for UPSERT operations.');
        }

        if (empty($insertData)) {
            throw new InvalidArgumentException('Insert data cannot be empty when building an UPSERT statement.');
        }

        if (empty($updateData)) {
            throw new InvalidArgumentException('Update data cannot be empty when building an UPSERT statement.');
        }

        $driver = strtolower($this->getDriverName());
        $normalizedDriver = $this->normalizeDriverName($driver);
        $isSqlite = str_contains($driver, 'sqlite');

        if ($isSqlite && $conflictTarget === null) {
            throw new InvalidArgumentException('SQLite UPSERT statements require a conflict target.');
        }

        $columns = array_keys($insertData);
        $placeholders = [];
        $bindings = [];

        foreach ($insertData as $column => $value) {
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $bindings[$column] = $value;
        }

        $updateClauses = [];
        foreach ($updateData as $column => $value) {
            $updateExpression = null;

            if (is_array($value) && isset($value['type'])) {
                switch ($value['type']) {
                    case 'insert':
                        $sourceColumn = $value['column'] ?? $column;
                        $updateExpression = ':' . ltrim((string) $sourceColumn, ':');
                        break;
                    case 'raw':
                        $expression = $value['expression'] ?? '';
                        $updateExpression = $this->resolveRawExpression($expression, $normalizedDriver);
                        break;
                    default:
                        $placeholderName = $column . '_update';
                        $updateExpression = ':' . $placeholderName;
                        $bindings[$placeholderName] = $value['value'] ?? null;
                        break;
                }
            } elseif (is_string($value) && str_starts_with($value, ':')) {
                $updateExpression = $value;
            } else {
                $placeholderName = $column . '_update';
                $updateExpression = ':' . $placeholderName;
                $bindings[$placeholderName] = $value;
            }

            if ($updateExpression === null) {
                throw new InvalidArgumentException('Unable to resolve update expression for column: ' . $column);
            }

            $updateClauses[] = sprintf('%s = %s', $column, $updateExpression);
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        if ($isSqlite) {
            $targetClause = '';
            if (is_array($conflictTarget)) {
                $targetClause = '(' . implode(', ', $conflictTarget) . ')';
            } elseif (is_string($conflictTarget) && $conflictTarget !== '') {
                $targetClause = '(' . trim($conflictTarget, '() ') . ')';
            }

            $sql .= sprintf(' ON CONFLICT %s DO UPDATE SET %s', $targetClause, implode(', ', $updateClauses));
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses);
        }

        return [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }

    /**
     * Build a descriptor instructing the UPSERT helper to reuse the insert placeholder.
     */
    public static function useInsertValue(string $column): array
    {
        return [
            'type' => 'insert',
            'column' => $column,
        ];
    }

    /**
     * Build a descriptor instructing the UPSERT helper to inject a raw SQL expression.
     *
     * @param string|array<string,string> $expression Driver-agnostic string or driver keyed map of expressions.
     */
    public static function rawExpression($expression): array
    {
        return [
            'type' => 'raw',
            'expression' => $expression,
        ];
    }

    /**
     * Prepare an SQL query.
     *
     * @param string $sql SQL statement to execute
     * @return void
     */
    public function query(string $sql): void
    {
        $this->connect();
        $this->sql = $sql;
        $this->params = [];
        $this->types = [];
        $this->result = null;
        $this->affectedRows = null;
    }

    /**
     * Bind a value to a query parameter.
     *
     * @param string   $param Parameter name with or without colon
     * @param mixed    $value Value to bind
     * @param int|null $type  Parameter type constant
     * @return void
     */
    public function bind(string $param, $value, ?int $type = null): void
    {
        if ($type === null) {
            switch (true) {
                case is_int($value):
                    $type = ParameterType::INTEGER;
                    break;
                case is_bool($value):
                    $type = ParameterType::BOOLEAN;
                    break;
                case is_null($value):
                    $type = ParameterType::NULL;
                    break;
                default:
                    $type = ParameterType::STRING;
            }
        }

        $name = ltrim($param, ':');
        $this->params[$name] = $value;
        $this->types[$name] = $type;
    }

    /**
     * Execute the prepared statement.
     *
     * @return bool True on success
     */
    public function execute(): bool
    {
        try {
            self::$lastUsedTime = time();
            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|PRAGMA)/i', $this->sql)) {
                $this->result = self::$dbh->executeQuery($this->sql, $this->params, $this->types);
                $this->affectedRows = $this->result->rowCount();
            } else {
                $this->affectedRows = self::$dbh->executeStatement($this->sql, $this->params, $this->types);
            }
            return true;
        } catch (DBALException $e) {
            if ($this->isConnectionError($e)) {
                ErrorManager::getInstance()->log('MySQL connection lost during execution. Attempting to reconnect...', 'warning');
                $this->reconnect();
                return $this->execute();
            }
            throw $e;
        }
    }

    /**
     * Execute the query and return all rows.
     *
     * @return array
     */
    public function resultSet(): array
    {
        $this->execute();
        return $this->result ? $this->result->fetchAllAssociative() : [];
    }

    /**
     * Execute the query and return a single row.
     *
     * @return mixed
     */
    public function single(): mixed
    {
        $this->execute();
        return $this->result ? $this->result->fetchAssociative() : null;
    }

    /**
     * Get the number of affected rows.
     *
     * @return int
     */
    public function rowCount(): int
    {
        return $this->affectedRows ?? ($this->result ? $this->result->rowCount() : 0);
    }

    /**
     * Retrieve the last inserted ID from the current connection.
     *
     * @param string|null $sequenceName Optional sequence name for databases that require it
     * @return string
     */
    public function getLastInsertId(?string $sequenceName = null): string
    {
        $this->connect();

        try {
            self::$lastUsedTime = time();
            return self::$dbh->lastInsertId($sequenceName);
        } catch (DBALException $e) {
            if ($this->isConnectionError($e)) {
                ErrorManager::getInstance()->log(
                    'Database connection lost while retrieving last insert ID. Attempting to reconnect...',
                    'warning'
                );
                $this->reconnect();
                return self::$dbh->lastInsertId($sequenceName);
            }
            throw $e;
        }
    }

    /**
     * Start a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        try {
            $this->connect();
            self::$lastUsedTime = time();
            self::$dbh->beginTransaction();
            return true;
        } catch (DBALException $e) {
            if ($this->isConnectionError($e)) {
                ErrorManager::getInstance()->log('MySQL connection lost during transaction. Attempting to reconnect...', 'warning');
                $this->reconnect();
                self::$dbh->beginTransaction();
                return true;
            }
            throw $e;
        }
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        self::$lastUsedTime = time();
        self::$dbh->commit();
        return true;
    }

    /**
     * Roll back the current transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        self::$lastUsedTime = time();
        self::$dbh->rollBack();
        return true;
    }

    /**
     * Retrieve the configured database driver name.
     */
    public function getDriverName(): string
    {
        $this->connect();

        $params = self::$dbh->getParams();
        if (isset($params['driver'])) {
            return (string) $params['driver'];
        }

        try {
            return self::$dbh->getDatabasePlatform()->getName();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Check if the exception indicates a lost connection.
     *
     * @param DBALException $e
     * @return bool
     */
    private function isConnectionError(DBALException $e): bool
    {
        $code = $e->getPrevious() ? $e->getPrevious()->getCode() : $e->getCode();
        $errors = ['2006', '2013', '1047', '1049'];
        return in_array((string) $code, $errors, true);
    }

    /**
     * Allow tests to replace the singleton instance without touching the real connection.
     */
    public static function setInstanceForTesting(?DatabaseManager $instance): void
    {
        self::$instance = $instance;
        if ($instance === null) {
            self::$dbh = null;
            self::$lastUsedTime = null;
        }
    }

    /**
     * Normalize driver names for easier comparison.
     */
    private function normalizeDriverName(string $driver): string
    {
        if (str_contains($driver, 'mysql') || str_contains($driver, 'mariadb')) {
            return 'mysql';
        }

        if (str_contains($driver, 'sqlite')) {
            return 'sqlite';
        }

        return $driver;
    }

    /**
     * Resolve raw expressions for the active driver.
     *
     * @param array<string,string>|string $expression
     */
    private function resolveRawExpression($expression, string $driver): string
    {
        if (is_string($expression)) {
            return $expression;
        }

        if (isset($expression[$driver])) {
            return $expression[$driver];
        }

        if (isset($expression['default'])) {
            return $expression['default'];
        }

        $resolved = reset($expression);
        if (!is_string($resolved) || $resolved === '') {
            throw new InvalidArgumentException('Raw expression mapping must contain at least one SQL snippet.');
        }

        return $resolved;
    }
}
