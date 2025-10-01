<?php

namespace App\Utilities;

use App\Core\DatabaseManager;
use App\Core\ErrorManager;
use App\Core\RBACManager;

/**
 * Helper for normalizing and validating domain/group filters against RBAC.
 */
class AccessScopeValidator
{
    /**
     * Resolve a domain name to an ID and ensure the current user can access it.
     *
     * @return array{id:?int,name:?string,authorized:bool}
     */
    public static function resolveDomain(?string $domain): array
    {
        $normalized = is_string($domain) ? trim($domain) : '';
        if ($normalized === '') {
            return ['id' => null, 'name' => null, 'authorized' => true];
        }

        $db = DatabaseManager::getInstance();
        $db->query('SELECT id, domain FROM domains WHERE domain = :domain LIMIT 1');
        $db->bind(':domain', $normalized);
        $record = $db->single();

        if (!$record) {
            ErrorManager::getInstance()->log(
                sprintf('Domain filter "%s" could not be resolved.', $normalized),
                'warning'
            );

            return ['id' => null, 'name' => null, 'authorized' => false];
        }

        $domainId = (int) $record['id'];
        $rbac = RBACManager::getInstance();
        if (!$rbac->canAccessDomain($domainId)) {
            ErrorManager::getInstance()->log(
                sprintf('Unauthorized domain filter "%s" requested.', $normalized),
                'warning'
            );

            return ['id' => null, 'name' => null, 'authorized' => false];
        }

        return ['id' => $domainId, 'name' => $record['domain'], 'authorized' => true];
    }

    /**
     * Resolve a group ID ensuring the current user can access it.
     *
     * @return array{id:?int,authorized:bool}
     */
    public static function resolveGroup(?int $groupId): array
    {
        if ($groupId === null) {
            return ['id' => null, 'authorized' => true];
        }

        $db = DatabaseManager::getInstance();
        $db->query('SELECT id FROM domain_groups WHERE id = :id');
        $db->bind(':id', $groupId);
        $record = $db->single();

        if (!$record) {
            ErrorManager::getInstance()->log(
                sprintf('Group filter %d could not be resolved.', $groupId),
                'warning'
            );

            return ['id' => null, 'authorized' => false];
        }

        $rbac = RBACManager::getInstance();
        if (!$rbac->canAccessGroup($groupId)) {
            ErrorManager::getInstance()->log(
                sprintf('Unauthorized group filter %d requested.', $groupId),
                'warning'
            );

            return ['id' => null, 'authorized' => false];
        }

        return ['id' => $groupId, 'authorized' => true];
    }

    /**
     * Build a domain authorization clause limited to the caller's accessible domains.
     *
     * @return array{allowed:bool,clause:string}|null
     */
    public static function buildDomainAuthorizationClause(array &$bindParams, string $column): ?array
    {
        $rbac = RBACManager::getInstance();
        if ($rbac->getCurrentUserRole() === RBACManager::ROLE_APP_ADMIN) {
            return null;
        }

        $accessibleDomains = $rbac->getAccessibleDomains();
        if (empty($accessibleDomains)) {
            return ['allowed' => false, 'clause' => ''];
        }

        $domainIds = array_values(array_filter(array_map(
            static fn($domain) => (int) ($domain['id'] ?? 0),
            $accessibleDomains
        )));

        if (empty($domainIds)) {
            return ['allowed' => false, 'clause' => ''];
        }

        $placeholders = [];
        foreach ($domainIds as $index => $domainId) {
            $placeholder = ':authorized_domain_' . $index;
            $placeholders[] = $placeholder;
            $bindParams[$placeholder] = $domainId;
        }

        return [
            'allowed' => true,
            'clause' => $column . ' IN (' . implode(', ', $placeholders) . ')',
        ];
    }
}
