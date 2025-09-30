<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\RBACManager;

/**
 * Domain Groups model for organizing domains by business units/brands
 */
class DomainGroup
{
    /**
     * Get all domains (for user management)
     *
     * @return array
     */
    public static function getAllDomains(): array
    {
        $rbac = RBACManager::getInstance();
        return $rbac->getAccessibleDomains();
    }

    /**
     * Get all domain groups with their assigned domains
     *
     * @return array
     */
    public static function getAllGroups(): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
            $db->query('
                SELECT
                    dg.*, 
                    COUNT(dga.domain_id) as domain_count
                FROM domain_groups dg
                LEFT JOIN domain_group_assignments dga ON dg.id = dga.group_id
                GROUP BY dg.id, dg.name, dg.description, dg.created_at
                ORDER BY dg.name ASC
            ');

            return $db->resultSet();
        }

        $accessibleGroups = $rbac->getAccessibleGroups();

        if (empty($accessibleGroups)) {
            return [];
        }

        $groupIds = array_map(static fn($group) => (int) $group['id'], $accessibleGroups);
        [$placeholders, $bindings] = self::buildInClause($groupIds, 'group_id');

        $db->query('
            SELECT
                dg.*, 
                COUNT(dga.domain_id) as domain_count
            FROM domain_groups dg
            LEFT JOIN domain_group_assignments dga ON dg.id = dga.group_id
            WHERE dg.id IN (' . implode(', ', $placeholders) . ')
            GROUP BY dg.id, dg.name, dg.description, dg.created_at
            ORDER BY dg.name ASC
        ');

        foreach ($bindings as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Get domains assigned to a specific group
     *
     * @param int $groupId
     * @return array
     */
    public static function getGroupDomains(int $groupId): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        if (!$rbac->canAccessGroup($groupId)) {
            return [];
        }

        $domainFilterClause = '';
        $bindings = [];

        if ($rbac->getCurrentUserRole() !== RBACManager::ROLE_APP_ADMIN) {
            $accessibleDomains = $rbac->getAccessibleDomains();

            if (empty($accessibleDomains)) {
                return [];
            }

            $domainIds = array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
            [$placeholders, $bindings] = self::buildInClause($domainIds, 'domain_id');
            $domainFilterClause = ' AND d.id IN (' . implode(', ', $placeholders) . ')';
        }

        $db->query('
            SELECT d.*, dga.assigned_at
            FROM domains d
            JOIN domain_group_assignments dga ON d.id = dga.domain_id
            WHERE dga.group_id = :group_id' . $domainFilterClause . '
            ORDER BY d.domain ASC
        ');

        $db->bind(':group_id', $groupId);

        foreach ($bindings as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Get unassigned domains
     *
     * @return array
     */
    public static function getUnassignedDomains(): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
            $db->query('
                SELECT d.*
                FROM domains d
                LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                WHERE dga.domain_id IS NULL
                ORDER BY d.domain ASC
            ');

            return $db->resultSet();
        }

        $accessibleDomains = $rbac->getAccessibleDomains();

        if (empty($accessibleDomains)) {
            return [];
        }

        $domainIds = array_map(static fn($domain) => (int) $domain['id'], $accessibleDomains);
        [$placeholders, $bindings] = self::buildInClause($domainIds, 'domain_id');

        $db->query('
            SELECT d.*
            FROM domains d
            LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
            WHERE dga.domain_id IS NULL
              AND d.id IN (' . implode(', ', $placeholders) . ')
            ORDER BY d.domain ASC
        ');

        foreach ($bindings as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Create a new domain group
     *
     * @param string $name
     * @param string $description
     * @return int Group ID
     */
    public static function createGroup(string $name, string $description = ''): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO domain_groups (name, description) 
            VALUES (:name, :description)
        ');

        $db->bind(':name', $name);
        $db->bind(':description', $description);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Assign domain to group
     *
     * @param int $domainId
     * @param int $groupId
     * @return bool
     */
    public static function assignDomainToGroup(int $domainId, int $groupId): bool
    {
        $db = DatabaseManager::getInstance();

        try {
            $db->query('
                INSERT INTO domain_group_assignments (domain_id, group_id) 
                VALUES (:domain_id, :group_id)
            ');

            $db->bind(':domain_id', $domainId);
            $db->bind(':group_id', $groupId);
            $db->execute();
            return true;
        } catch (\Exception $e) {
            return false; // Already assigned or constraint violation
        }
    }

    /**
     * Remove domain from group
     *
     * @param int $domainId
     * @param int $groupId
     * @return bool
     */
    public static function removeDomainFromGroup(int $domainId, int $groupId): bool
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            DELETE FROM domain_group_assignments 
            WHERE domain_id = :domain_id AND group_id = :group_id
        ');

        $db->bind(':domain_id', $domainId);
        $db->bind(':group_id', $groupId);
        $db->execute();

        return $db->rowCount() > 0;
    }

    /**
     * Get group analytics summary
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getGroupAnalytics(string $startDate, string $endDate): array
    {
        $db = DatabaseManager::getInstance();
        $rbac = RBACManager::getInstance();

        $groupFilterClause = '';
        $bindings = [];

        if ($rbac->getCurrentUserRole() !== RBACManager::ROLE_APP_ADMIN) {
            $accessibleGroups = $rbac->getAccessibleGroups();

            if (empty($accessibleGroups)) {
                return [];
            }

            $groupIds = array_map(static fn($group) => (int) $group['id'], $accessibleGroups);
            [$placeholders, $bindings] = self::buildInClause($groupIds, 'group_id');
            $groupFilterClause = ' AND dg.id IN (' . implode(', ', $placeholders) . ')';
        }

        $db->query('
            SELECT
                dg.id,
                dg.name,
                dg.description,
                COUNT(DISTINCT d.id) as domain_count,
                COUNT(DISTINCT dar.id) as report_count,
                SUM(dmar.count) as total_volume,
                SUM(CASE WHEN dmar.disposition = "none" THEN dmar.count ELSE 0 END) as passed_count,
                SUM(CASE WHEN dmar.disposition = "quarantine" THEN dmar.count ELSE 0 END) as quarantined_count,
                SUM(CASE WHEN dmar.disposition = "reject" THEN dmar.count ELSE 0 END) as rejected_count,
                ROUND(
                    (SUM(CASE WHEN dmar.disposition = "none" THEN dmar.count ELSE 0 END) * 100.0) /
                    NULLIF(SUM(dmar.count), 0), 2
                ) as pass_rate
            FROM domain_groups dg
            LEFT JOIN domain_group_assignments dga ON dg.id = dga.group_id
            LEFT JOIN domains d ON dga.domain_id = d.id
            LEFT JOIN dmarc_aggregate_reports dar ON d.id = dar.domain_id
            LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
            WHERE dar.date_range_begin >= :start_date
            AND dar.date_range_end <= :end_date' . $groupFilterClause . '
            GROUP BY dg.id, dg.name, dg.description
            ORDER BY dg.name ASC
        ');

        $db->bind(':start_date', strtotime($startDate));
        $db->bind(':end_date', strtotime($endDate . ' 23:59:59'));

        foreach ($bindings as $placeholder => $value) {
            $db->bind($placeholder, $value);
        }

        return $db->resultSet();
    }

    /**
     * Build placeholders and bindings for parameterized IN clauses.
     *
     * @param array $ids
     * @param string $prefix
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private static function buildInClause(array $ids, string $prefix): array
    {
        $placeholders = [];
        $bindings = [];

        foreach (array_values($ids) as $index => $id) {
            $placeholder = ':' . $prefix . '_' . $index;
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = (int) $id;
        }

        return [$placeholders, $bindings];
    }
}
