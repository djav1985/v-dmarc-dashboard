<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

class Domain
{
    /**
     * Get or create domain record.
     *
     * @param string $domain
     * @return int Domain ID
     */
    public static function getOrCreateDomain(string $domain): int
    {
        $db = DatabaseManager::getInstance();

        // Try to get existing domain
        $db->query('SELECT id FROM domains WHERE domain = :domain');
        $db->bind(':domain', $domain);
        $result = $db->single();

        if ($result) {
            return (int) $result['id'];
        }

        // Create new domain
        $db->query('INSERT INTO domains (domain) VALUES (:domain)');
        $db->bind(':domain', $domain);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Get all domains.
     *
     * @return array
     */
    public static function getAllDomains(): array
    {
        $rbac = RBACManager::getInstance();
        return $rbac->getAccessibleDomains();
    }

    /**
     * Get domain by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function getDomainById(int $id): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM domains WHERE id = :id');
        $db->bind(':id', $id);
        $result = $db->single();

        return $result ? (object) $result : null;
    }

    /**
     * Locate a domain record by its FQDN.
     */
    public static function findByDomain(string $domain): ?array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT * FROM domains WHERE domain = :domain LIMIT 1');
        $db->bind(':domain', trim($domain));
        $result = $db->single();

        return $result ?: null;
    }

    /**
     * Retrieve the ownership contact for a domain.
     */
    public static function getOwnershipContact(int $id): ?string
    {
        $domain = self::getDomainById($id);

        return $domain !== null ? ($domain->ownership_contact ?? null) : null;
    }

    /**
     * Retrieve the enforcement level for a domain.
     */
    public static function getEnforcementLevel(int $id): ?string
    {
        $domain = self::getDomainById($id);

        return $domain !== null ? ($domain->enforcement_level ?? null) : null;
    }

    /**
     * Update the ownership contact value for a domain.
     */
    public static function setOwnershipContact(int $id, ?string $contact): bool
    {
        return self::updateDomainMetadata($id, ['ownership_contact' => $contact]);
    }

    /**
     * Update the enforcement level for a domain.
     */
    public static function setEnforcementLevel(int $id, ?string $level): bool
    {
        return self::updateDomainMetadata($id, ['enforcement_level' => $level]);
    }

    /**
     * Update metadata values on the domains table while keeping timestamps current.
     *
     * @param array<string, scalar|null> $attributes
     */
    public static function updateDomainMetadata(int $id, array $attributes): bool
    {
        if ($id <= 0 || empty($attributes)) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $setClauses = [];

        foreach ($attributes as $column => $value) {
            if (!in_array($column, ['ownership_contact', 'enforcement_level'], true)) {
                continue;
            }

            $placeholder = ':' . $column;
            $setClauses[] = $column . ' = ' . $placeholder;
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = ' . self::getTimestampExpression();

        $db->query('UPDATE domains SET ' . implode(', ', $setClauses) . ' WHERE id = :id');
        $db->bind(':id', $id);

        foreach ($attributes as $column => $value) {
            if (!in_array($column, ['ownership_contact', 'enforcement_level'], true)) {
                continue;
            }

            $db->bind(':' . $column, $value);
        }

        return $db->execute();
    }

    /**
     * Resolve a driver-appropriate current timestamp expression.
     */
    private static function getTimestampExpression(): string
    {
        $driver = strtolower(DatabaseManager::getInstance()->getDriverName());

        if (str_contains($driver, 'mysql')) {
            return 'NOW()';
        }

        return 'CURRENT_TIMESTAMP';
    }
}
