<?php

namespace App\Utilities;

use Exception;

class DnsValidator
{
    private static ?DnsOverHttpsClient $dnsClient = null;

    public static function setDnsClient(?DnsOverHttpsClient $client): void
    {
        self::$dnsClient = $client;
    }

    private static function getDnsClient(): DnsOverHttpsClient
    {
        if (self::$dnsClient === null) {
            self::$dnsClient = new DnsOverHttpsClient();
        }

        return self::$dnsClient;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function queryDnsOverHttps(string $domain, string $type): array
    {
        try {
            $records = self::getDnsClient()->query($domain, $type);
            return $records ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function legacyDnsLookup(string $domain, int $recordType, string $type): array
    {
        if (!function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($domain, $recordType);
        if (!$records) {
            return [];
        }

        return self::mapLegacyRecords($records, $type);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private static function mapLegacyRecords(array $records, string $type): array
    {
        $normalised = [];

        foreach ($records as $record) {
            switch ($type) {
                case 'TXT':
                    if (isset($record['txt'])) {
                        $normalised[] = [
                            'type' => 'TXT',
                            'txt' => $record['txt'],
                            'ttl' => $record['ttl'] ?? null,
                        ];
                    }
                    break;
                case 'MX':
                    if (isset($record['pri'], $record['target'])) {
                        $normalised[] = [
                            'type' => 'MX',
                            'pri' => (int) $record['pri'],
                            'target' => rtrim($record['target'], '.'),
                            'ttl' => $record['ttl'] ?? null,
                        ];
                    }
                    break;
                case 'SOA':
                    if (isset($record['mname'])) {
                        $normalised[] = [
                            'type' => 'SOA',
                            'mname' => $record['mname'],
                            'rname' => $record['rname'] ?? '',
                            'serial' => $record['serial'] ?? 0,
                            'refresh' => $record['refresh'] ?? 0,
                            'retry' => $record['retry'] ?? 0,
                            'expire' => $record['expire'] ?? 0,
                            'minimum' => $record['minimum'] ?? 0,
                            'ttl' => $record['ttl'] ?? null,
                        ];
                    }
                    break;
                case 'A':
                    if (isset($record['ip'])) {
                        $normalised[] = [
                            'type' => 'A',
                            'ip' => $record['ip'],
                            'ttl' => $record['ttl'] ?? null,
                        ];
                    }
                    break;
            }
        }

        return $normalised;
    }

    /**
     * Check if a domain has a DMARC record.
     *
     * @param string $domain
     * @return array|null DMARC record data or null if not found
     */
    public static function getDmarcRecord(string $domain): ?array
    {
        $dmarcDomain = "_dmarc.{$domain}";
        $records = self::queryDnsOverHttps($dmarcDomain, 'TXT');
        if (empty($records)) {
            $records = self::legacyDnsLookup($dmarcDomain, DNS_TXT, 'TXT');
        }

        if (empty($records)) {
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
        $records = self::queryDnsOverHttps($domain, 'TXT');
        if (empty($records)) {
            $records = self::legacyDnsLookup($domain, DNS_TXT, 'TXT');
        }

        if (empty($records)) {
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
        $records = self::queryDnsOverHttps($dkimDomain, 'TXT');
        if (empty($records)) {
            $records = self::legacyDnsLookup($dkimDomain, DNS_TXT, 'TXT');
        }

        if (empty($records)) {
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
        $records = self::queryDnsOverHttps($domain, 'MX');
        if (empty($records)) {
            $records = self::legacyDnsLookup($domain, DNS_MX, 'MX');
        }

        if (empty($records)) {
            return [];
        }

        $mxRecords = [];
        foreach ($records as $record) {
            if (!isset($record['pri'], $record['target'])) {
                continue;
            }
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
        $records = self::queryDnsOverHttps($domain, 'SOA');
        if (empty($records)) {
            $records = self::legacyDnsLookup($domain, DNS_SOA, 'SOA');
        }

        if (empty($records) || empty($records[0])) {
            return null;
        }

        $record = $records[0];

        return [
            'domain' => $domain,
            'mname' => $record['mname'],
            'rname' => $record['rname'],
            'serial' => $record['serial'],
            'refresh' => $record['refresh'],
            'retry' => $record['retry'],
            'expire' => $record['expire'],
            'minimum' => $record['minimum']
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
