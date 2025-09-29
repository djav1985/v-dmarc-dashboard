<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * Domain model for managing DMARC domains
 */
class Domain
{
    /**
     * Get all active domains
     */
    public static function getAll(): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT d.*, b.name as brand_name 
            FROM domains d 
            LEFT JOIN brands b ON d.brand_id = b.id 
            WHERE d.is_active = 1 
            ORDER BY d.domain
        ');
        return $db->resultSet();
    }

    /**
     * Get domain by ID
     */
    public static function getById(int $id): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT d.*, b.name as brand_name 
            FROM domains d 
            LEFT JOIN brands b ON d.brand_id = b.id 
            WHERE d.id = :id
        ');
        $db->bind(':id', $id);
        return $db->single();
    }

    /**
     * Get domain by name
     */
    public static function getByName(string $domain): ?object
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT d.*, b.name as brand_name 
            FROM domains d 
            LEFT JOIN brands b ON d.brand_id = b.id 
            WHERE d.domain = :domain
        ');
        $db->bind(':domain', $domain);
        return $db->single();
    }

    /**
     * Create new domain
     */
    public static function create(array $data): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            INSERT INTO domains (domain, brand_id, dmarc_policy, dmarc_record, spf_record, 
                               dkim_selectors, mta_sts_enabled, bimi_enabled, dnssec_enabled, 
                               retention_days) 
            VALUES (:domain, :brand_id, :dmarc_policy, :dmarc_record, :spf_record, 
                   :dkim_selectors, :mta_sts_enabled, :bimi_enabled, :dnssec_enabled, 
                   :retention_days)
        ');

        $db->bind(':domain', $data['domain']);
        $db->bind(':brand_id', $data['brand_id'] ?? null);
        $db->bind(':dmarc_policy', $data['dmarc_policy'] ?? 'none');
        $db->bind(':dmarc_record', $data['dmarc_record'] ?? null);
        $db->bind(':spf_record', $data['spf_record'] ?? null);
        $db->bind(':dkim_selectors', json_encode($data['dkim_selectors'] ?? []));
        $db->bind(':mta_sts_enabled', (bool)($data['mta_sts_enabled'] ?? false));
        $db->bind(':bimi_enabled', (bool)($data['bimi_enabled'] ?? false));
        $db->bind(':dnssec_enabled', (bool)($data['dnssec_enabled'] ?? false));
        $db->bind(':retention_days', $data['retention_days'] ?? 365);

        return $db->execute();
    }

    /**
     * Update domain
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $validFields = [
            'brand_id', 'dmarc_policy', 'dmarc_record', 'spf_record', 'dkim_selectors',
            'mta_sts_enabled', 'bimi_enabled', 'dnssec_enabled', 'retention_days', 'is_active'
        ];

        foreach ($validFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
            }
        }

        if (empty($fields)) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $db->query('UPDATE domains SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $db->bind(':id', $id);

        foreach ($validFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'dkim_selectors') {
                    $db->bind(":$field", json_encode($data[$field]));
                } elseif (in_array($field, ['mta_sts_enabled', 'bimi_enabled', 'dnssec_enabled', 'is_active'])) {
                    $db->bind(":$field", (bool)$data[$field]);
                } else {
                    $db->bind(":$field", $data[$field]);
                }
            }
        }

        return $db->execute();
    }

    /**
     * Update last checked timestamp
     */
    public static function updateLastChecked(int $id): bool
    {
        $db = DatabaseManager::getInstance();
        $db->query('UPDATE domains SET last_checked = NOW() WHERE id = :id');
        $db->bind(':id', $id);
        return $db->execute();
    }

    /**
     * Get domains by brand
     */
    public static function getByBrand(int $brandId): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT d.*, b.name as brand_name 
            FROM domains d 
            LEFT JOIN brands b ON d.brand_id = b.id 
            WHERE d.brand_id = :brand_id AND d.is_active = 1 
            ORDER BY d.domain
        ');
        $db->bind(':brand_id', $brandId);
        return $db->resultSet();
    }

    /**
     * Get recent DMARC reports for domain
     */
    public static function getRecentReports(int $domainId, int $days = 30): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT * FROM dmarc_reports 
            WHERE domain_id = :domain_id 
            AND report_begin >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY report_begin DESC
        ');
        $db->bind(':domain_id', $domainId);
        $db->bind(':days', $days);
        return $db->resultSet();
    }

    /**
     * Get domain statistics
     */
    public static function getStats(int $domainId, int $days = 30): object
    {
        $db = DatabaseManager::getInstance();
        $db->query('
            SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed_reports,
                MIN(report_begin) as first_report,
                MAX(report_end) as last_report
            FROM dmarc_reports 
            WHERE domain_id = :domain_id 
            AND report_begin >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ');
        $db->bind(':domain_id', $domainId);
        $db->bind(':days', $days);

        $result = $db->single();
        return $result ?: (object)[
            'total_reports' => 0,
            'processed_reports' => 0,
            'first_report' => null,
            'last_report' => null
        ];
    }
}
