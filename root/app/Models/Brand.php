<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * Brand model for managing organizations/brands
 */
class Brand
{
    /**
     * Get all brands
     */
    public static function getAll(): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM brands ORDER BY name');
        return $db->resultSet();
    }

    /**
     * Get brand by ID
     */
    public static function getById(int $id): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM brands WHERE id = :id');
        $db->bind(':id', $id);
        return $db->single();
    }

    /**
     * Get brand by name
     */
    public static function getByName(string $name): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM brands WHERE name = :name');
        $db->bind(':name', $name);
        return $db->single();
    }

    /**
     * Create new brand
     */
    public static function create(array $data): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            INSERT INTO brands (name, description, logo_url, color_scheme) 
            VALUES (:name, :description, :logo_url, :color_scheme)
        ');
        $db->bind(':name', $data['name']);
        $db->bind(':description', $data['description'] ?? null);
        $db->bind(':logo_url', $data['logo_url'] ?? null);
        $db->bind(':color_scheme', $data['color_scheme'] ?? '#007bff');

        return $db->execute();
    }

    /**
     * Update brand
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $validFields = ['name', 'description', 'logo_url', 'color_scheme'];

        foreach ($validFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
            }
        }

        if (empty($fields)) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $db->query('UPDATE brands SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $db->bind(':id', $id);

        foreach ($validFields as $field) {
            if (isset($data[$field])) {
                $db->bind(":$field", $data[$field]);
            }
        }

        return $db->execute();
    }

    /**
     * Delete brand
     */
    public static function delete(int $id): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('DELETE FROM brands WHERE id = :id');
        $db->bind(':id', $id);
        return $db->execute();
    }

    /**
     * Get domains for brand
     */
    public static function getDomains(int $brandId): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM domains WHERE brand_id = :brand_id ORDER BY domain');
        $db->bind(':brand_id', $brandId);
        return $db->resultSet();
    }
}
