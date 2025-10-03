<?php

namespace App\Utilities;

use App\Core\DatabaseManager;
use App\Core\ErrorManager;
use Exception;

class DataRetention
{
    /**
     * Maximum number of IDs to include in a single IN clause to avoid SQL parameter limits.
     * SQLite default limit is 999, so we use 500 to stay well within bounds.
     */
    private const MAX_SQL_PARAMS = 500;

    /**
     * Clean up old DMARC reports based on retention settings.
     *
     * @return array Cleanup results
     */
    public static function cleanupOldReports(): array
    {
        $results = [
            'aggregate_reports_deleted' => 0,
            'aggregate_records_deleted' => 0,
            'forensic_reports_deleted' => 0,
            'tls_reports_deleted' => 0,
            'errors' => []
        ];

        try {
            $db = DatabaseManager::getInstance();

            // Get retention settings
            $retentionSettings = self::getRetentionSettings();

            $transactionStarted = false;

            if (
                isset($retentionSettings['aggregate_reports_retention_days'])
                || isset($retentionSettings['forensic_reports_retention_days'])
                || isset($retentionSettings['tls_reports_retention_days'])
            ) {
                $transactionStarted = $db->beginTransaction();
            }

            // Clean up aggregate reports
            if (isset($retentionSettings['aggregate_reports_retention_days'])) {
                $days = (int) $retentionSettings['aggregate_reports_retention_days'];
                $cutoffDate = time() - ($days * 24 * 60 * 60);

                $db->query('SELECT id FROM dmarc_aggregate_reports WHERE date_range_end < :cutoff_date');
                $db->bind(':cutoff_date', $cutoffDate);
                $reportRows = $db->resultSet();

                $reportIds = array_values(array_filter(array_map(
                    static fn($row) => isset($row['id']) ? (int) $row['id'] : null,
                    $reportRows
                )));

                if (!empty($reportIds)) {
                    // Process deletions in batches to avoid SQL parameter limits
                    $batches = array_chunk($reportIds, self::MAX_SQL_PARAMS);
                    $totalRecordsDeleted = 0;
                    $totalReportsDeleted = 0;

                    foreach ($batches as $batch) {
                        [$placeholders, $bindings] = self::buildInClause($batch, 'aggregate_report');

                        $db->query(
                            'DELETE FROM dmarc_aggregate_records WHERE report_id IN ('
                                . implode(', ', $placeholders) . ')'
                        );
                        foreach ($bindings as $placeholder => $value) {
                            $db->bind($placeholder, $value);
                        }
                        $db->execute();
                        $totalRecordsDeleted += $db->rowCount();

                        $db->query(
                            'DELETE FROM dmarc_aggregate_reports WHERE id IN ('
                                . implode(', ', $placeholders) . ')'
                        );
                        foreach ($bindings as $placeholder => $value) {
                            $db->bind($placeholder, $value);
                        }
                        $db->execute();
                        $totalReportsDeleted += $db->rowCount();
                    }

                    $results['aggregate_records_deleted'] = $totalRecordsDeleted;
                    $results['aggregate_reports_deleted'] = $totalReportsDeleted;
                }
            }

            // Clean up forensic reports
            if (isset($retentionSettings['forensic_reports_retention_days'])) {
                $days = (int) $retentionSettings['forensic_reports_retention_days'];
                $cutoffDate = time() - ($days * 24 * 60 * 60);

                $db->query('DELETE FROM dmarc_forensic_reports WHERE arrival_date < :cutoff_date');
                $db->bind(':cutoff_date', $cutoffDate);
                $db->execute();
                $results['forensic_reports_deleted'] = $db->rowCount();
            }

            // Clean up TLS reports
            if (isset($retentionSettings['tls_reports_retention_days'])) {
                $days = (int) $retentionSettings['tls_reports_retention_days'];
                $cutoffDate = time() - ($days * 24 * 60 * 60);

                $db->query('DELETE FROM smtp_tls_reports WHERE date_range_end < :cutoff_date');
                $db->bind(':cutoff_date', $cutoffDate);
                $db->execute();
                $results['tls_reports_deleted'] = $db->rowCount();
            }

            if (!empty($transactionStarted)) {
                $db->commit();
            }
        } catch (Exception $e) {
            if (isset($db, $transactionStarted) && !empty($transactionStarted)) {
                try {
                    $db->rollBack();
                } catch (Exception $rollbackException) {
                    ErrorManager::getInstance()->log(
                        'Failed to roll back data retention cleanup: ' . $rollbackException->getMessage(),
                        'error'
                    );
                }
            }
            $error = 'Data retention cleanup failed: ' . $e->getMessage();
            $results['errors'][] = $error;
            ErrorManager::getInstance()->log($error, 'error');
        }

        return $results;
    }

    /**
     * Build a parameterized IN clause.
     *
     * @param array<int> $ids
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

    /**
     * Get retention settings from database.
     *
     * @return array
     */
    public static function getRetentionSettings(): array
    {
        $db = DatabaseManager::getInstance();
        $db->query('SELECT setting_name, setting_value FROM retention_settings');
        $rows = $db->resultSet();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Update retention setting.
     *
     * @param string $settingName
     * @param string $settingValue
     * @return bool
     */
    public static function updateRetentionSetting(string $settingName, string $settingValue): bool
    {
        try {
            $db = DatabaseManager::getInstance();
            $upsert = $db->buildUpsertQuery(
                'retention_settings',
                [
                    'setting_name' => $settingName,
                    'setting_value' => $settingValue,
                ],
                [
                    'setting_value' => DatabaseManager::useInsertValue('setting_value'),
                    'updated_at' => DatabaseManager::rawExpression([
                        'sqlite' => 'CURRENT_TIMESTAMP',
                        'mysql' => 'NOW()',
                        'default' => 'CURRENT_TIMESTAMP',
                    ]),
                ],
                'setting_name'
            );

            $db->query($upsert['sql']);
            foreach ($upsert['bindings'] as $param => $value) {
                $db->bind(':' . $param, $value);
            }
            return $db->execute();
        } catch (Exception $e) {
            ErrorManager::getInstance()->log(
                'Failed to update retention setting: ' . $e->getMessage(),
                'error'
            );
            return false;
        }
    }

    /**
     * Get statistics about stored reports.
     *
     * @return array
     */
    public static function getStorageStats(): array
    {
        $stats = [
            'aggregate_reports_count' => 0,
            'aggregate_records_count' => 0,
            'forensic_reports_count' => 0,
            'tls_reports_count' => 0,
            'oldest_aggregate_report' => null,
            'newest_aggregate_report' => null,
            'total_domains' => 0
        ];

        try {
            $db = DatabaseManager::getInstance();

            // Count aggregate reports
            $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_reports');
            $result = $db->single();
            $stats['aggregate_reports_count'] = (int) $result['count'];

            // Count aggregate records
            $db->query('SELECT COUNT(*) as count FROM dmarc_aggregate_records');
            $result = $db->single();
            $stats['aggregate_records_count'] = (int) $result['count'];

            // Count forensic reports
            $db->query('SELECT COUNT(*) as count FROM dmarc_forensic_reports');
            $result = $db->single();
            $stats['forensic_reports_count'] = (int) $result['count'];

            // Count TLS reports
            $db->query('SELECT COUNT(*) as count FROM smtp_tls_reports');
            $result = $db->single();
            $stats['tls_reports_count'] = (int) $result['count'];

            // Get date range for aggregate reports
            $db->query('
                SELECT MIN(date_range_begin) as oldest, MAX(date_range_end) as newest 
                FROM dmarc_aggregate_reports
            ');
            $result = $db->single();
            if ($result && $result['oldest']) {
                $stats['oldest_aggregate_report'] = (int) $result['oldest'];
                $stats['newest_aggregate_report'] = (int) $result['newest'];
            }

            // Count domains
            $db->query('SELECT COUNT(*) as count FROM domains');
            $result = $db->single();
            $stats['total_domains'] = (int) $result['count'];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log(
                'Failed to get storage stats: ' . $e->getMessage(),
                'error'
            );
        }

        return $stats;
    }

    /**
     * Check if cleanup is needed based on storage stats.
     *
     * @return bool
     */
    public static function isCleanupNeeded(): bool
    {
        $stats = self::getStorageStats();
        $settings = self::getRetentionSettings();

        // Check if we have reports older than retention period
        if (isset($stats['oldest_aggregate_report']) && $stats['oldest_aggregate_report']) {
            $retentionDays = (int) ($settings['aggregate_reports_retention_days'] ?? 90);
            $cutoffDate = time() - ($retentionDays * 24 * 60 * 60);

            if ($stats['oldest_aggregate_report'] < $cutoffDate) {
                return true;
            }
        }

        return false;
    }
}
