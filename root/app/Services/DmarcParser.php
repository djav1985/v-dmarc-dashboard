<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DmarcReport;
use App\Core\ErrorManager;
use SimpleXMLElement;
use Exception;

/**
 * DMARC XML Report Parser Service
 */
class DmarcParser
{
    /**
     * Parse DMARC aggregate report from XML string
     */
    public static function parseAggregateReport(string $xmlContent, string $filename = ''): array
    {
        try {
            // Handle compressed content
            $xmlContent = self::decompressIfNeeded($xmlContent, $filename);

            // Parse XML
            $xml = new SimpleXMLElement($xmlContent);

            // Extract report metadata
            $reportMetadata = [
                'org_name' => (string)$xml->report_metadata->org_name,
                'email' => (string)$xml->report_metadata->email,
                'extra_contact_info' => (string)$xml->report_metadata->extra_contact_info ?: null,
                'report_id' => (string)$xml->report_metadata->report_id,
                'report_begin' => date('Y-m-d H:i:s', (int)$xml->report_metadata->date_range->begin),
                'report_end' => date('Y-m-d H:i:s', (int)$xml->report_metadata->date_range->end),
            ];

            // Extract policy published
            $policy = [
                'domain' => (string)$xml->policy_published->domain,
                'adkim' => (string)$xml->policy_published->adkim ?: 'r',
                'aspf' => (string)$xml->policy_published->aspf ?: 'r',
                'p' => (string)$xml->policy_published->p,
                'sp' => (string)$xml->policy_published->sp ?: null,
                'pct' => (int)$xml->policy_published->pct ?: 100,
            ];

            // Extract records
            $records = [];
            foreach ($xml->record as $record) {
                $row = $record->row;
                $policyEvaluated = $row->policy_evaluated;
                $identifiers = $record->identifiers;
                $authResults = $record->auth_results;

                $recordData = [
                    'source_ip' => (string)$row->source_ip,
                    'count' => (int)$row->count,
                    'disposition' => (string)$policyEvaluated->disposition,
                    'dmarc_result' => (string)$policyEvaluated->dmarc,
                    'header_from' => (string)$identifiers->header_from,
                    'envelope_from' => (string)$identifiers->envelope_from ?: null,
                    'envelope_to' => (string)$identifiers->envelope_to ?: null,
                ];

                // Parse DKIM results
                if (isset($authResults->dkim)) {
                    $dkim = $authResults->dkim;
                    $recordData['dkim_result'] = (string)$dkim->result;
                    $recordData['dkim_domain'] = (string)$dkim->domain;
                } else {
                    $recordData['dkim_result'] = 'neutral';
                    $recordData['dkim_domain'] = null;
                }

                // Parse SPF results
                if (isset($authResults->spf)) {
                    $spf = $authResults->spf;
                    $recordData['spf_result'] = (string)$spf->result;
                    $recordData['spf_domain'] = (string)$spf->domain;
                } else {
                    $recordData['spf_result'] = 'neutral';
                    $recordData['spf_domain'] = null;
                }

                // Parse reasons (if any)
                if (isset($policyEvaluated->reason)) {
                    $reason = $policyEvaluated->reason;
                    $recordData['reason_type'] = (string)$reason->type;
                    $recordData['reason_comment'] = (string)$reason->comment ?: null;
                }

                $records[] = $recordData;
            }

            return [
                'success' => true,
                'metadata' => $reportMetadata,
                'policy' => $policy,
                'records' => $records,
                'raw_xml' => $xmlContent
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('DMARC parse error: ' . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'raw_xml' => $xmlContent
            ];
        }
    }

    /**
     * Process parsed report data and store in database
     */
    public static function processReport(array $parsedData): bool
    {
        if (!$parsedData['success']) {
            return false;
        }

        try {
            $metadata = $parsedData['metadata'];
            $policy = $parsedData['policy'];
            $records = $parsedData['records'];

            // Find or create domain
            $domain = Domain::getByName($policy['domain']);
            if (!$domain) {
                // Create domain automatically
                Domain::create([
                    'domain' => $policy['domain'],
                    'dmarc_policy' => $policy['p']
                ]);
                $domain = Domain::getByName($policy['domain']);
            }

            if (!$domain) {
                throw new Exception('Could not create domain: ' . $policy['domain']);
            }

            // Create report record
            $reportData = array_merge($metadata, [
                'domain_id' => $domain->id,
                'policy_domain' => $policy['domain'],
                'policy_adkim' => $policy['adkim'],
                'policy_aspf' => $policy['aspf'],
                'policy_p' => $policy['p'],
                'policy_sp' => $policy['sp'],
                'policy_pct' => $policy['pct'],
                'raw_xml' => $parsedData['raw_xml']
            ]);

            $reportId = DmarcReport::create($reportData);
            if (!$reportId) {
                throw new Exception('Failed to create DMARC report');
            }

            $recordsInserted = 0;
            foreach ($records as $record) {
                if (self::createDmarcRecord($reportId, $record)) {
                    $recordsInserted++;
                }
            }

            // Mark as processed
            DmarcReport::markProcessed($reportId);

            ErrorManager::getInstance()->log(
                "Processed DMARC report: {$metadata['report_id']} from {$metadata['org_name']} with {$recordsInserted} records",
                'info'
            );

            return true;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('DMARC processing error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create DMARC record in database
     */
    private static function createDmarcRecord(int $reportId, array $recordData): bool
    {
        $db = \App\Core\DatabaseManager::getInstance();
        $db->query('
            INSERT INTO dmarc_records (
                report_id, source_ip, count, disposition, dkim_result, dkim_domain,
                spf_result, spf_domain, dmarc_result, header_from, envelope_from,
                envelope_to, reason_type, reason_comment
            ) VALUES (
                :report_id, :source_ip, :count, :disposition, :dkim_result, :dkim_domain,
                :spf_result, :spf_domain, :dmarc_result, :header_from, :envelope_from,
                :envelope_to, :reason_type, :reason_comment
            )
        ');

        $db->bind(':report_id', $reportId);
        $db->bind(':source_ip', $recordData['source_ip']);
        $db->bind(':count', $recordData['count']);
        $db->bind(':disposition', $recordData['disposition']);
        $db->bind(':dkim_result', $recordData['dkim_result']);
        $db->bind(':dkim_domain', $recordData['dkim_domain']);
        $db->bind(':spf_result', $recordData['spf_result']);
        $db->bind(':spf_domain', $recordData['spf_domain']);
        $db->bind(':dmarc_result', $recordData['dmarc_result']);
        $db->bind(':header_from', $recordData['header_from']);
        $db->bind(':envelope_from', $recordData['envelope_from']);
        $db->bind(':envelope_to', $recordData['envelope_to']);
        $db->bind(':reason_type', $recordData['reason_type'] ?? null);
        $db->bind(':reason_comment', $recordData['reason_comment'] ?? null);

        return $db->execute();
    }

    /**
     * Decompress gzip or zip content if needed
     */
    private static function decompressIfNeeded(string $content, string $filename): string
    {
        // Check if gzipped
        if (substr($content, 0, 2) === "\x1f\x8b") {
            $content = gzdecode($content);
            if ($content === false) {
                throw new Exception('Failed to decompress gzip content');
            }
        }
        // Check if it's a zip file based on filename or magic bytes
        elseif (substr($content, 0, 4) === "PK\x03\x04" || str_ends_with(strtolower($filename), '.zip')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'dmarc_');
            file_put_contents($tempFile, $content);

            $zip = new \ZipArchive();
            if ($zip->open($tempFile) === true) {
                $content = $zip->getFromIndex(0);
                $zip->close();
                unlink($tempFile);

                if ($content === false) {
                    throw new Exception('Failed to extract zip content');
                }
            } else {
                unlink($tempFile);
                throw new Exception('Failed to open zip file');
            }
        }

        return $content;
    }

    /**
     * Validate XML structure
     */
    public static function validateXmlStructure(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);

            $errors = [];

            // Check required elements
            if (!isset($xml->report_metadata)) {
                $errors[] = 'Missing report_metadata';
            }
            if (!isset($xml->policy_published)) {
                $errors[] = 'Missing policy_published';
            }
            if (!isset($xml->record)) {
                $errors[] = 'Missing record elements';
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Invalid XML: ' . $e->getMessage()]
            ];
        }
    }
}
