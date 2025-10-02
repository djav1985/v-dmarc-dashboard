<?php

namespace App\Utilities;

use ZipArchive;
use Exception;
use SimpleXMLElement;

class DmarcParser
{
    /**
     * Parse DMARC aggregate report from XML.
     *
     * @param string $xmlContent
     * @return array Parsed report data
     */
    public static function parseAggregateReport(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);

            $reportData = [
                'org_name' => (string) $xml->report_metadata->org_name,
                'email' => (string) $xml->report_metadata->email,
                'extra_contact_info' => (string) $xml->report_metadata->extra_contact_info ?: null,
                'report_id' => (string) $xml->report_metadata->report_id,
                'date_range_begin' => (int) $xml->report_metadata->date_range->begin,
                'date_range_end' => (int) $xml->report_metadata->date_range->end,
                'policy_published_domain' => (string) $xml->policy_published->domain,
                'policy_adkim' => self::extractOptionalString($xml->policy_published->adkim ?? null),
                'policy_aspf' => self::extractOptionalString($xml->policy_published->aspf ?? null),
                'policy_p' => self::extractOptionalString($xml->policy_published->p ?? null),
                'policy_sp' => self::extractOptionalString($xml->policy_published->sp ?? null),
                'policy_pct' => self::extractOptionalInt($xml->policy_published->pct ?? null),
                'policy_fo' => self::extractOptionalString($xml->policy_published->fo ?? null),
                'raw_xml' => $xmlContent,
                'records' => []
            ];

            // Parse individual records
            foreach ($xml->record as $record) {
                $policyEvaluated = $record->row->policy_evaluated ?? null;

                $recordData = [
                    'source_ip' => (string) $record->row->source_ip,
                    'count' => (int) $record->row->count,
                    'disposition' => (string) ($policyEvaluated->disposition ?? ''),
                    'dkim_result' => self::extractOptionalString($policyEvaluated->dkim ?? null),
                    'spf_result' => self::extractOptionalString($policyEvaluated->spf ?? null),
                ];

                $policyReasons = self::extractPolicyReasons($policyEvaluated, 'reason');
                if (!empty($policyReasons)) {
                    $recordData['policy_evaluated_reasons'] = $policyReasons;
                }

                $policyOverrides = self::extractPolicyReasons($policyEvaluated, 'policy_override');
                if (!empty($policyOverrides)) {
                    $recordData['policy_override_reasons'] = $policyOverrides;
                }

                $authResultsNode = $record->auth_results ?? ($record->row->auth_results ?? null);
                $authResults = self::extractAuthResults($authResultsNode);
                if (!empty($authResults)) {
                    $recordData['auth_results'] = $authResults;
                }

                // Add identifiers if present
                if (isset($record->identifiers)) {
                    $recordData['header_from'] = (string) $record->identifiers->header_from ?: null;
                    $recordData['envelope_from'] = (string) $record->identifiers->envelope_from ?: null;
                    $recordData['envelope_to'] = (string) $record->identifiers->envelope_to ?: null;
                }

                $reportData['records'][] = $recordData;
            }

            return $reportData;
        } catch (Exception $e) {
            throw new Exception("Failed to parse DMARC aggregate report: " . $e->getMessage());
        }
    }

    private static function extractOptionalString(?SimpleXMLElement $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private static function extractOptionalInt(?SimpleXMLElement $value): ?int
    {
        $stringValue = self::extractOptionalString($value);
        if ($stringValue === null || !preg_match('/^-?\d+$/', $stringValue)) {
            return null;
        }

        return (int) $stringValue;
    }

    private static function extractPolicyReasons(?SimpleXMLElement $context, string $nodeName): array
    {
        if ($context === null || !isset($context->{$nodeName})) {
            return [];
        }

        $reasons = [];

        foreach ($context->{$nodeName} as $node) {
            if (!$node instanceof SimpleXMLElement) {
                continue;
            }

            $type = self::extractOptionalString($node->type ?? null);
            $comment = self::extractOptionalString($node->comment ?? null);

            if ($type === null && $comment === null) {
                $fallback = self::extractOptionalString($node);
                if ($fallback === null) {
                    continue;
                }

                $reasons[] = [
                    'type' => $fallback,
                    'comment' => null,
                ];
                continue;
            }

            $reasons[] = [
                'type' => $type,
                'comment' => $comment,
            ];
        }

        return $reasons;
    }

    private static function extractAuthResults(?SimpleXMLElement $context): array
    {
        if ($context === null) {
            return [];
        }

        $results = [];

        foreach ($context->children() as $authNode) {
            if (!$authNode instanceof SimpleXMLElement) {
                continue;
            }

            $method = $authNode->getName();
            $entries = [];

            foreach ($authNode->children() as $child) {
                if (!$child instanceof SimpleXMLElement) {
                    continue;
                }

                $entries[$child->getName()] = self::extractOptionalString($child);
            }

            if (empty(array_filter($entries, static fn($value) => $value !== null && $value !== ''))) {
                $fallback = self::extractOptionalString($authNode);
                if ($fallback === null) {
                    continue;
                }

                $entries = ['value' => $fallback];
            }

            $filtered = array_filter(
                $entries,
                static fn($value) => $value !== null && $value !== ''
            );

            if (empty($filtered)) {
                continue;
            }

            $results[$method][] = $filtered;
        }

        return $results;
    }

    /**
     * Parse DMARC forensic report from XML.
     *
     * @param string $xmlContent
     * @return array
     */
    public static function parseForensicReport(string $xmlContent): array
    {
        if (trim($xmlContent) === '') {
            throw new Exception('Empty forensic report payload.');
        }

        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (Exception $e) {
            throw new Exception('Failed to parse DMARC forensic report: ' . $e->getMessage());
        }

        $domain = self::getFirstNodeValue($xml, [
            '//policy_published/domain',
            '//identifiers/header_from',
            '//record/identifiers/header_from',
            '//identity/domain',
        ]);

        if ($domain === null) {
            throw new Exception('Missing domain in forensic report.');
        }

        $sourceIp = self::getFirstNodeValue($xml, [
            '//record/row/source_ip',
            '//source_ip',
        ]);

        if ($sourceIp === null) {
            throw new Exception('Missing source IP in forensic report.');
        }

        $arrivalDateValue = self::getFirstNodeValue($xml, [
            '//arrival_date',
            '//event_time',
            '//record/row/date_range/begin',
        ]);

        if ($arrivalDateValue === null) {
            throw new Exception('Missing arrival date in forensic report.');
        }

        $arrivalDate = self::normalizeTimestamp($arrivalDateValue);
        if ($arrivalDate === null || $arrivalDate <= 0) {
            throw new Exception('Invalid arrival date in forensic report.');
        }

        return [
            'domain' => $domain,
            'arrival_date' => $arrivalDate,
            'source_ip' => $sourceIp,
            'authentication_results' => self::getFirstNodeValue($xml, ['//authentication_results']) ?? null,
            'original_envelope_id' => self::getFirstNodeValue($xml, ['//original_envelope_id']) ?? null,
            'dkim_domain' => self::getFirstNodeValue($xml, [
                '//dkim_auth_results/domain',
                '//auth_results/dkim/domain',
            ]) ?? null,
            'dkim_selector' => self::getFirstNodeValue($xml, [
                '//dkim_auth_results/selector',
                '//auth_results/dkim/selector',
            ]) ?? null,
            'dkim_result' => self::getFirstNodeValue($xml, [
                '//dkim_auth_results/result',
                '//auth_results/dkim/result',
            ]) ?? null,
            'spf_domain' => self::getFirstNodeValue($xml, [
                '//spf_auth_results/domain',
                '//auth_results/spf/domain',
            ]) ?? null,
            'spf_result' => self::getFirstNodeValue($xml, [
                '//spf_auth_results/result',
                '//auth_results/spf/result',
            ]) ?? null,
            'raw_message' => self::getFirstNodeValue($xml, ['//original_message']) ?? null,
        ];
    }

    /**
     * Convert a textual timestamp value into an integer epoch.
     */
    private static function normalizeTimestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        $parsed = strtotime($value);
        if ($parsed === false) {
            return null;
        }

        return $parsed;
    }

    /**
     * Extract and parse compressed DMARC report files.
     *
     * @param string $filePath Path to the compressed file
     * @return array Parsed report data
     */
    public static function parseCompressedReport(string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new Exception("Unreadable report file: $filePath");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new Exception("Failed to read report file: $filePath");
        }

        $prefix = substr($contents, 0, 4);

        if (strncmp($prefix, "\x1f\x8b", 2) === 0) {
            return self::parseGzipReport($filePath);
        }

        if (strncmp($prefix, "PK\x03\x04", 4) === 0) {
            return self::parseZipReport($filePath);
        }

        if (self::looksLikeXml($contents)) {
            return self::parseAggregateReport($contents);
        }

        $errors = [];

        foreach (['parseGzipReport', 'parseZipReport'] as $method) {
            try {
                return self::$method($filePath);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $errorMessage = 'Unsupported or corrupted DMARC report format.';
        if (!empty($errors)) {
            $errorMessage .= ' Details: ' . implode(' | ', array_unique($errors));
        }

        throw new Exception($errorMessage);
    }

    /**
     * Parse GZIP compressed DMARC report.
     *
     * @param string $filePath
     * @return array
     */
    private static function parseGzipReport(string $filePath): array
    {
        $rawContent = file_get_contents($filePath);
        if ($rawContent === false) {
            throw new Exception("Failed to read GZIP file: $filePath");
        }

        $xmlContent = @gzdecode($rawContent);
        if ($xmlContent === false) {
            throw new Exception("Failed to decompress GZIP file: $filePath");
        }

        return self::parseAggregateReport($xmlContent);
    }

    /**
     * Parse ZIP compressed DMARC report.
     *
     * @param string $filePath
     * @return array
     */
    private static function parseZipReport(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Failed to open ZIP file: $filePath");
        }

        // Look for XML files in the ZIP
        $xmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'xml') {
                $xmlContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if ($xmlContent === null) {
            throw new Exception("No XML file found in ZIP: $filePath");
        }

        return self::parseAggregateReport($xmlContent);
    }

    /**
     * Locate the first non-empty node value for the provided XPath expressions.
     *
     * @param SimpleXMLElement $xml
     * @param array $paths
     * @return string|null
     */
    private static function getFirstNodeValue(SimpleXMLElement $xml, array $paths): ?string
    {
        foreach ($paths as $path) {
            $results = $xml->xpath($path);
            if (!empty($results)) {
                $value = trim((string) $results[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Determine if the supplied content resembles XML.
     *
     * @param string $contents
     * @return bool
     */
    private static function looksLikeXml(string $contents): bool
    {
        $trimmed = ltrim($contents);
        return $trimmed !== '' && str_starts_with($trimmed, '<');
    }

    /**
     * Validate XML structure for DMARC aggregate reports.
     *
     * @param string $xmlContent
     * @return bool
     */
    public static function isValidDmarcXml(string $xmlContent): bool
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);

            // Check required elements
            $requiredElements = [
                'report_metadata',
                'report_metadata/org_name',
                'report_metadata/report_id',
                'report_metadata/date_range/begin',
                'report_metadata/date_range/end',
                'policy_published',
                'record'
            ];

            foreach ($requiredElements as $element) {
                if (!$xml->xpath("//$element")) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
