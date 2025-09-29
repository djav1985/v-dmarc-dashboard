<?php

namespace App\Utilities;

use Exception;

class DnsValidator
{
    /**
     * Check if a domain has a DMARC record.
     *
     * @param string $domain
     * @return array|null DMARC record data or null if not found
     */
    public static function getDmarcRecord(string $domain): ?array
    {
        $dmarcDomain = "_dmarc.{$domain}";
        $records = dns_get_record($dmarcDomain, DNS_TXT);

        if (!$records) {
            return null;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=DMARC1') === 0) {
                return [
                    'domain' => $domain,
                    'record' => $record['txt'],
                    'parsed' => self::parseDmarcRecord($record['txt'])
                ];
            }
        }

        return null;
    }

    /**
     * Check if a domain has SPF records.
     *
     * @param string $domain
     * @return array|null SPF record data or null if not found
     */
    public static function getSpfRecord(string $domain): ?array
    {
        $records = dns_get_record($domain, DNS_TXT);

        if (!$records) {
            return null;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') === 0) {
                return [
                    'domain' => $domain,
                    'record' => $record['txt'],
                    'parsed' => self::parseSpfRecord($record['txt'])
                ];
            }
        }

        return null;
    }

    /**
     * Check DKIM record for a domain and selector.
     *
     * @param string $selector
     * @param string $domain
     * @return array|null DKIM record data or null if not found
     */
    public static function getDkimRecord(string $selector, string $domain): ?array
    {
        $dkimDomain = "{$selector}._domainkey.{$domain}";
        $records = dns_get_record($dkimDomain, DNS_TXT);

        if (!$records) {
            return null;
        }

        foreach ($records as $record) {
            if (isset($record['txt'])) {
                return [
                    'domain' => $domain,
                    'selector' => $selector,
                    'record' => $record['txt'],
                    'parsed' => self::parseDkimRecord($record['txt'])
                ];
            }
        }

        return null;
    }

    /**
     * Get MX records for a domain.
     *
     * @param string $domain
     * @return array MX records
     */
    public static function getMxRecords(string $domain): array
    {
        $records = dns_get_record($domain, DNS_MX);

        if (!$records) {
            return [];
        }

        $mxRecords = [];
        foreach ($records as $record) {
            $mxRecords[] = [
                'priority' => $record['pri'],
                'target' => $record['target'],
                'domain' => $domain
            ];
        }

        // Sort by priority
        usort($mxRecords, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $mxRecords;
    }

    /**
     * Get SOA record for a domain.
     *
     * @param string $domain
     * @return array|null SOA record or null if not found
     */
    public static function getSoaRecord(string $domain): ?array
    {
        $records = dns_get_record($domain, DNS_SOA);

        if (!$records || empty($records[0])) {
            return null;
        }

        return [
            'domain' => $domain,
            'mname' => $records[0]['mname'],
            'rname' => $records[0]['rname'],
            'serial' => $records[0]['serial'],
            'refresh' => $records[0]['refresh'],
            'retry' => $records[0]['retry'],
            'expire' => $records[0]['expire'],
            'minimum' => $records[0]['minimum']
        ];
    }

    /**
     * Parse DMARC record string into components.
     *
     * @param string $record
     * @return array
     */
    private static function parseDmarcRecord(string $record): array
    {
        $parsed = [];
        $parts = explode(';', $record);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Parse SPF record string into components.
     *
     * @param string $record
     * @return array
     */
    private static function parseSpfRecord(string $record): array
    {
        $parsed = [
            'version' => null,
            'mechanisms' => [],
            'modifiers' => []
        ];

        $parts = explode(' ', $record);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if ($part === 'v=spf1') {
                $parsed['version'] = 'spf1';
            } elseif (in_array($part, ['~all', '-all', '+all', '?all'])) {
                $parsed['all'] = $part;
            } elseif (strpos($part, '=') !== false) {
                // Modifier
                $keyValue = explode('=', $part, 2);
                $parsed['modifiers'][$keyValue[0]] = $keyValue[1];
            } else {
                // Mechanism
                $parsed['mechanisms'][] = $part;
            }
        }

        return $parsed;
    }

    /**
     * Parse DKIM record string into components.
     *
     * @param string $record
     * @return array
     */
    private static function parseDkimRecord(string $record): array
    {
        $parsed = [];

        // DKIM records can span multiple strings, join them
        $record = str_replace(['"', ' '], '', $record);

        $parts = explode(';', $record);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Validate domain DNS setup for email authentication.
     *
     * @param string $domain
     * @return array Validation results
     */
    public static function validateDomainSetup(string $domain): array
    {
        $results = [
            'domain' => $domain,
            'dmarc' => null,
            'spf' => null,
            'mx' => [],
            'soa' => null,
            'issues' => []
        ];

        try {
            // Check DMARC
            $dmarcRecord = self::getDmarcRecord($domain);
            if ($dmarcRecord) {
                $results['dmarc'] = $dmarcRecord;
                if (!isset($dmarcRecord['parsed']['p'])) {
                    $results['issues'][] = 'DMARC policy (p) not specified';
                }
            } else {
                $results['issues'][] = 'No DMARC record found';
            }

            // Check SPF
            $spfRecord = self::getSpfRecord($domain);
            if ($spfRecord) {
                $results['spf'] = $spfRecord;
                if (!isset($spfRecord['parsed']['all'])) {
                    $results['issues'][] = 'SPF record missing "all" mechanism';
                }
            } else {
                $results['issues'][] = 'No SPF record found';
            }

            // Check MX records
            $mxRecords = self::getMxRecords($domain);
            if (empty($mxRecords)) {
                $results['issues'][] = 'No MX records found';
            } else {
                $results['mx'] = $mxRecords;
            }

            // Check SOA
            $soaRecord = self::getSoaRecord($domain);
            if ($soaRecord) {
                $results['soa'] = $soaRecord;
            } else {
                $results['issues'][] = 'No SOA record found';
            }
        } catch (Exception $e) {
            $results['issues'][] = 'DNS lookup failed: ' . $e->getMessage();
        }

        return $results;
    }
}
