<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\ErrorManager;
use Throwable;

class SavedReportFilter
{
    /**
     * Retrieve saved filters for a user ordered by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getForUser(string $userId): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM saved_report_filters WHERE user_id = :user ORDER BY name');
        $db->bind(':user', $userId);

        return $db->resultSet();
    }

    /**
     * Retrieve a specific saved filter for the provided user.
     */
    public static function getById(int $id, string $userId): ?array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM saved_report_filters WHERE id = :id AND user_id = :user');
        $db->bind(':id', $id);
        $db->bind(':user', $userId);
        $result = $db->single();

        return $result ?: null;
    }

    /**
     * Persist a new saved filter for a user.
     */
    public static function create(string $userId, string $name, array $filters): ?int
    {
        $db = DatabaseManager::getInstance();

        $db->query('INSERT INTO saved_report_filters (user_id, name, filters) VALUES (:user, :name, :filters)');
        $db->bind(':user', $userId);
        $db->bind(':name', $name);
        $db->bind(':filters', json_encode($filters));

        if (!$db->execute()) {
            return null;
        }

        return (int) $db->getLastInsertId();
    }

    /**
     * Update an existing saved filter.
     *
     * @param array<string, mixed> $attributes
     */
    public static function update(int $id, string $userId, array $attributes): bool
    {
        if (empty($attributes)) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $columns = [];

        foreach ($attributes as $column => $value) {
            if ($column === 'name') {
                $columns[$column] = $value;
            } elseif ($column === 'filters' && is_array($value)) {
                $columns[$column] = json_encode($value);
            }
        }

        if (empty($columns)) {
            return false;
        }

        $setClauses = [];
        foreach ($columns as $column => $value) {
            $placeholder = ':' . $column;
            $setClauses[] = $column . ' = ' . $placeholder;
        }
        $setClauses[] = 'updated_at = ' . self::getTimestampExpression();

        $db->query('UPDATE saved_report_filters SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND user_id = :user');
        $db->bind(':id', $id);
        $db->bind(':user', $userId);

        foreach ($columns as $column => $value) {
            $db->bind(':' . $column, $value);
        }

        return $db->execute();
    }

    /**
     * Delete a saved filter.
     */
    public static function delete(int $id, string $userId): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM saved_report_filters WHERE id = :id AND user_id = :user');
        $db->bind(':id', $id);
        $db->bind(':user', $userId);

        return $db->execute();
    }

    /**
     * Decode the stored filter payload.
     *
     * @return array<string, mixed>
     */
    public static function decodeFilters(array $record): array
    {
        if (empty($record['filters'])) {
            return [];
        }

        try {
            $decoded = json_decode((string) $record['filters'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            ErrorManager::getInstance()->log('Failed to decode saved filter payload: ' . $e->getMessage(), 'error');
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function getTimestampExpression(): string
    {
        $driver = strtolower(DatabaseManager::getInstance()->getDriverName());

        if (str_contains($driver, 'mysql')) {
            return 'NOW()';
        }

        return 'CURRENT_TIMESTAMP';
    }
}
