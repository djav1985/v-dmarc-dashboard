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
}

