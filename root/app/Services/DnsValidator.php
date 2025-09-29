<?php

namespace App\Services;

use App\Core\ErrorManager;
use Exception;

/**
 * DNS Validation Service for SPF, DKIM, DMARC and other DNS records
 */
class DnsValidator
{
    private static int $spfLookupCount = 0;
    private static array $spfLookupCache = [];

    /**
     * Validate DMARC record for a domain
     */
    public static function validateDmarc(string $domain): array
    {
        try {
            $record = self::getDnsRecord($domain, '_dmarc.' . $domain, DNS_TXT);

            if (empty($record)) {
                return [
                    'valid' => false,
                    'error' => 'No DMARC record found',
                    'record' => null
                ];
            }

            $dmarcRecord = self::parseDmarcRecord($record[0]);

            return [
                'valid' => $dmarcRecord['valid'],
                'record' => $record[0],
                'parsed' => $dmarcRecord,
                'error' => $dmarcRecord['error'] ?? null
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('DMARC validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'record' => null
            ];
        }
    }

    /**
     * Validate SPF record for a domain
     */
    public static function validateSpf(string $domain): array
    {
        self::$spfLookupCount = 0;
        self::$spfLookupCache = [];

        try {
            $record = self::getDnsRecord($domain, $domain, DNS_TXT);

            $spfRecord = null;
            foreach ($record as $txt) {
                if (str_starts_with($txt, 'v=spf1')) {
                    $spfRecord = $txt;
                    break;
                }
            }

            if (!$spfRecord) {
                return [
                    'valid' => false,
                    'error' => 'No SPF record found',
                    'record' => null,
                    'lookup_count' => 0
                ];
            }

            $spfValidation = self::validateSpfRecord($spfRecord, $domain);

            return [
                'valid' => $spfValidation['valid'],
                'record' => $spfRecord,
                'parsed' => $spfValidation,
                'lookup_count' => self::$spfLookupCount,
                'error' => $spfValidation['error'] ?? null
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('SPF validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'record' => null,
                'lookup_count' => self::$spfLookupCount
            ];
        }
    }

    /**
     * Validate DKIM record for a domain and selector
     */
    public static function validateDkim(string $domain, string $selector): array
    {
        try {
            $dkimDomain = $selector . '._domainkey.' . $domain;
            $record = self::getDnsRecord($domain, $dkimDomain, DNS_TXT);

            if (empty($record)) {
                return [
                    'valid' => false,
                    'error' => 'No DKIM record found for selector: ' . $selector,
                    'record' => null,
                    'selector' => $selector
                ];
            }

            $dkimRecord = self::parseDkimRecord($record[0]);

            return [
                'valid' => $dkimRecord['valid'],
                'record' => $record[0],
                'parsed' => $dkimRecord,
                'selector' => $selector,
                'error' => $dkimRecord['error'] ?? null
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('DKIM validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'record' => null,
                'selector' => $selector
            ];
        }
    }

    /**
     * Validate MTA-STS policy for a domain
     */
    public static function validateMtaSts(string $domain): array
    {
        try {
            $record = self::getDnsRecord($domain, '_mta-sts.' . $domain, DNS_TXT);

            if (empty($record)) {
                return [
                    'valid' => false,
                    'error' => 'No MTA-STS record found',
                    'record' => null,
                    'policy_url' => null
                ];
            }

            $mtaStsRecord = self::parseMtaStsRecord($record[0]);
            $policyUrl = "https://mta-sts.{$domain}/.well-known/mta-sts.txt";

            return [
                'valid' => $mtaStsRecord['valid'],
                'record' => $record[0],
                'parsed' => $mtaStsRecord,
                'policy_url' => $policyUrl,
                'error' => $mtaStsRecord['error'] ?? null
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('MTA-STS validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'record' => null,
                'policy_url' => null
            ];
        }
    }

    /**
     * Validate BIMI record for a domain
     */
    public static function validateBimi(string $domain): array
    {
        try {
            $record = self::getDnsRecord($domain, 'default._bimi.' . $domain, DNS_TXT);

            if (empty($record)) {
                return [
                    'valid' => false,
                    'error' => 'No BIMI record found',
                    'record' => null
                ];
            }

            $bimiRecord = self::parseBimiRecord($record[0]);

            return [
                'valid' => $bimiRecord['valid'],
                'record' => $record[0],
                'parsed' => $bimiRecord,
                'error' => $bimiRecord['error'] ?? null
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('BIMI validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'record' => null
            ];
        }
    }

    /**
     * Check DNSSEC validation for a domain
     */
    public static function validateDnssec(string $domain): array
    {
        try {
            // Check for DS records in parent zone
            $domainParts = explode('.', $domain);
            if (count($domainParts) > 1) {
                $parentDomain = implode('.', array_slice($domainParts, 1));
                $dsRecords = self::getDnsRecord($domain, $domain, DNS_DS);

                return [
                    'valid' => !empty($dsRecords),
                    'records' => $dsRecords,
                    'error' => empty($dsRecords) ? 'No DS records found' : null
                ];
            }

            return [
                'valid' => false,
                'error' => 'Cannot validate DNSSEC for TLD',
                'records' => []
            ];
        } catch (Exception $e) {
            ErrorManager::getInstance()->log('DNSSEC validation error: ' . $e->getMessage(), 'error');
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'records' => []
            ];
        }
    }

    /**
     * Get comprehensive domain validation report
     */
    public static function validateDomain(string $domain, array $dkimSelectors = []): array
    {
        $results = [
            'domain' => $domain,
            'timestamp' => date('Y-m-d H:i:s'),
            'dmarc' => self::validateDmarc($domain),
            'spf' => self::validateSpf($domain),
            'dkim' => [],
            'mta_sts' => self::validateMtaSts($domain),
            'bimi' => self::validateBimi($domain),
            'dnssec' => self::validateDnssec($domain)
        ];

        // Validate DKIM for each selector
        foreach ($dkimSelectors as $selector) {
            $results['dkim'][$selector] = self::validateDkim($domain, $selector);
        }

        // Add MX records
        $results['mx'] = self::getMxRecords($domain);

        return $results;
    }

    /**
     * Get MX records for a domain
     */
    private static function getMxRecords(string $domain): array
    {
        try {
            $mxRecords = dns_get_record($domain, DNS_MX);
            return [
                'valid' => !empty($mxRecords),
                'records' => $mxRecords,
                'error' => empty($mxRecords) ? 'No MX records found' : null
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'records' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get DNS record with error handling
     */
    private static function getDnsRecord(string $originalDomain, string $queryDomain, int $type): array
    {
        try {
            $records = dns_get_record($queryDomain, $type);
            if ($records === false) {
                return [];
            }

            $result = [];
            foreach ($records as $record) {
                if ($type === DNS_TXT) {
                    $result[] = $record['txt'];
                } elseif ($type === DNS_MX) {
                    $result[] = $record;
                } elseif ($type === DNS_DS) {
                    $result[] = $record;
                }
            }

            return $result;
        } catch (Exception $e) {
            ErrorManager::getInstance()->log("DNS lookup error for $queryDomain: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Parse and validate DMARC record
     */
    private static function parseDmarcRecord(string $record): array
    {
        $tags = [];
        $parts = explode(';', $record);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $tags[trim($key)] = trim($value);
            }
        }

        $errors = [];

        if (!isset($tags['v']) || $tags['v'] !== 'DMARC1') {
            $errors[] = 'Invalid version tag';
        }

        if (!isset($tags['p']) || !in_array($tags['p'], ['none', 'quarantine', 'reject'])) {
            $errors[] = 'Invalid or missing policy';
        }

        return [
            'valid' => empty($errors),
            'tags' => $tags,
            'errors' => $errors,
            'error' => empty($errors) ? null : implode(', ', $errors)
        ];
    }

    /**
     * Parse and validate SPF record
     */
    private static function validateSpfRecord(string $record, string $domain): array
    {
        $mechanisms = explode(' ', $record);
        $errors = [];

        if ($mechanisms[0] !== 'v=spf1') {
            $errors[] = 'Invalid SPF version';
        }

        foreach ($mechanisms as $mechanism) {
            if (str_starts_with($mechanism, 'include:')) {
                self::$spfLookupCount++;
                $includeDomain = substr($mechanism, 8);
                if (!in_array($includeDomain, self::$spfLookupCache)) {
                    self::$spflookupCache[] = $includeDomain;

                    // Recursive SPF lookup
                    $includeRecord = self::getDnsRecord($domain, $includeDomain, DNS_TXT);
                    foreach ($includeRecord as $txt) {
                        if (str_starts_with($txt, 'v=spf1')) {
                            self::validateSpfRecord($txt, $includeDomain);
                            break;
                        }
                    }
                }
            } elseif (str_starts_with($mechanism, 'redirect=')) {
                self::$spfLookupCount++;
            }
        }

        if (self::$spfLookupCount > 10) {
            $errors[] = 'Too many DNS lookups (>10)';
        }

        return [
            'valid' => empty($errors),
            'mechanisms' => $mechanisms,
            'lookup_count' => self::$spfLookupCount,
            'errors' => $errors,
            'error' => empty($errors) ? null : implode(', ', $errors)
        ];
    }

    /**
     * Parse and validate DKIM record
     */
    private static function parseDkimRecord(string $record): array
    {
        $tags = [];
        $parts = explode(';', $record);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $tags[trim($key)] = trim($value);
            }
        }

        $errors = [];

        if (!isset($tags['p']) || empty($tags['p'])) {
            $errors[] = 'Missing public key';
        }

        return [
            'valid' => empty($errors),
            'tags' => $tags,
            'errors' => $errors,
            'error' => empty($errors) ? null : implode(', ', $errors)
        ];
    }

    /**
     * Parse and validate MTA-STS record
     */
    private static function parseMtaStsRecord(string $record): array
    {
        $tags = [];
        $parts = explode(';', $record);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $tags[trim($key)] = trim($value);
            }
        }

        $errors = [];

        if (!isset($tags['v']) || $tags['v'] !== 'STSv1') {
            $errors[] = 'Invalid version tag';
        }

        if (!isset($tags['id'])) {
            $errors[] = 'Missing policy ID';
        }

        return [
            'valid' => empty($errors),
            'tags' => $tags,
            'errors' => $errors,
            'error' => empty($errors) ? null : implode(', ', $errors)
        ];
    }

    /**
     * Parse and validate BIMI record
     */
    private static function parseBimiRecord(string $record): array
    {
        $tags = [];
        $parts = explode(';', $record);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $tags[trim($key)] = trim($value);
            }
        }

        $errors = [];

        if (!isset($tags['v']) || $tags['v'] !== 'BIMI1') {
            $errors[] = 'Invalid version tag';
        }

        return [
            'valid' => empty($errors),
            'tags' => $tags,
            'errors' => $errors,
            'error' => empty($errors) ? null : implode(', ', $errors)
        ];
    }
}
