<?php

namespace App\Services;

use App\Core\DatabaseManager;
use App\Utilities\DnsOverHttpsClient;
use Exception;

/**
 * GeoIP and Threat Intelligence Service
 * Provides IP geolocation, ASN, and threat intelligence data
 */
class GeoIPService
{
    private const IP_API_FIELDS = 'status,country,countryCode,region,regionName,city,'
        . 'timezone,isp,org,as,query';

    private static ?GeoIPService $instance = null;

    /** @var callable|null */
    private $httpHandler;
    private DnsOverHttpsClient $dnsClient;
    private bool $cacheEnabled = true;

    private function __construct(?callable $httpHandler = null, ?DnsOverHttpsClient $dnsClient = null)
    {
        $this->httpHandler = $httpHandler;
        $this->dnsClient = $dnsClient ?? new DnsOverHttpsClient();
    }

    public static function getInstance(): GeoIPService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function createWithDependencies(
        ?callable $httpHandler = null,
        ?DnsOverHttpsClient $dnsClient = null
    ): GeoIPService {
        return new self($httpHandler, $dnsClient);
    }

    public function setHttpHandler(?callable $handler): void
    {
        $this->httpHandler = $handler;
    }

    public function setDnsClient(DnsOverHttpsClient $client): void
    {
        $this->dnsClient = $client;
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Get comprehensive IP intelligence
     */
    public function getIPIntelligence(string $ipAddress): array
    {
        // Check cache first
        $cached = $this->cacheEnabled ? $this->getCachedIntelligence($ipAddress) : null;
        if ($cached && $this->isCacheValid($cached)) {
            return $cached;
        }

        // Gather intelligence from multiple sources
        $intelligence = [
            'ip_address' => $ipAddress,
            'country_code' => null,
            'country_name' => null,
            'region' => null,
            'city' => null,
            'timezone' => null,
            'isp' => null,
            'organization' => null,
            'asn' => null,
            'asn_org' => null,
            'threat_score' => 0,
            'threat_categories' => [],
            'is_malicious' => false,
            'is_tor' => false,
            'is_proxy' => false,
            'rdap_registry' => null,
            'rdap_network_range' => null,
            'rdap_network_start' => null,
            'rdap_network_end' => null,
            'rdap_contacts' => [],
            'rdap_raw' => [],
            'rdap_checked_at' => null,
            'dnsbl_listed' => false,
            'dnsbl_sources' => [],
            'dnsbl_last_checked' => null,
            'reputation_score' => null,
            'reputation_context' => [],
            'reputation_last_checked' => null,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        // Try multiple data sources
        $geoData = $this->getGeoLocationData($ipAddress);
        if ($geoData) {
            $intelligence = array_merge($intelligence, $geoData);
        }

        $threatData = $this->getThreatIntelligence($ipAddress);
        if ($threatData) {
            $intelligence = array_merge($intelligence, $threatData);
        }

        $asnData = $this->getASNData($ipAddress);
        if ($asnData) {
            $intelligence = array_merge($intelligence, $asnData);
        }

        // Cache the results
        $this->cacheIntelligence($intelligence);

        return $intelligence;
    }

    /**
     * Get geolocation data using free services
     */
    private function getGeoLocationData(string $ipAddress): ?array
    {
        try {
            // Try ip-api.com first (free, no key required)
            $url = sprintf(
                'http://ip-api.com/json/%s?fields=%s',
                $ipAddress,
                self::IP_API_FIELDS
            );
            $response = $this->makeHttpRequest($url);

            if ($response && isset($response['status']) && $response['status'] === 'success') {
                return [
                    'country_code' => $response['countryCode'] ?? null,
                    'country_name' => $response['country'] ?? null,
                    'region' => $response['regionName'] ?? null,
                    'city' => $response['city'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['isp'] ?? null,
                    'organization' => $response['org'] ?? null,
                    'asn' => $this->extractASN($response['as'] ?? ''),
                    'asn_org' => $this->extractASNOrg($response['as'] ?? '')
                ];
            }

            // Fallback to ipapi.co (also free)
            $url = "https://ipapi.co/{$ipAddress}/json/";
            $response = $this->makeHttpRequest($url);

            if ($response && !isset($response['error'])) {
                return [
                    'country_code' => $response['country_code'] ?? null,
                    'country_name' => $response['country_name'] ?? null,
                    'region' => $response['region'] ?? null,
                    'city' => $response['city'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['org'] ?? null,
                    'organization' => $response['org'] ?? null,
                    'asn' => $response['asn'] ?? null,
                    'asn_org' => $response['org'] ?? null
                ];
            }
        } catch (Exception $e) {
            // Silently fail and return null
        }

        return null;
    }

    /**
     * Get threat intelligence data
     */
    private function getThreatIntelligence(string $ipAddress): ?array
    {
        try {
            $threat = $this->basicThreatCheck($ipAddress);

            $rdapData = $this->fetchRdapInfo($ipAddress);
            if ($rdapData) {
                $threat = array_merge($threat, $rdapData);
            }

            $dnsblData = $this->checkSpamhausDnsbl($ipAddress);
            $threat = array_merge($threat, $dnsblData);
            if (!empty($dnsblData['dnsbl_listed'])) {
                $threat['threat_categories'][] = 'dnsbl:spamhaus';
                $threat['is_malicious'] = true;
                $threat['threat_score'] = max($threat['threat_score'], 90);
            }

            $reputationData = $this->fetchSansReputation($ipAddress);
            if (!empty($reputationData)) {
                $threat = array_merge($threat, $reputationData);
                if (isset($reputationData['reputation_score'])) {
                    $score = (int) $reputationData['reputation_score'];
                    $threat['threat_score'] = max($threat['threat_score'], $score);
                    if ($score >= 60) {
                        $threat['is_malicious'] = true;
                    }
                    if ($score >= 30) {
                        $threat['threat_categories'][] = 'reputation:sans';
                    }
                }
            }

            $threat['threat_categories'] = array_values(array_unique(array_filter($threat['threat_categories'])));

            return $threat;
        } catch (Exception $e) {
            return $this->basicThreatCheck($ipAddress);
        }
    }

    /**
     * Basic threat checks using known patterns
     */
    private function basicThreatCheck(string $ipAddress): array
    {
        $threatScore = 0;
        $categories = [];
        $isMalicious = false;
        $isTor = false;
        $isProxy = false;

        // Check against known bad IP ranges (very basic)
        $badRanges = [
            '10.0.0.0/8',    // Private
            '172.16.0.0/12', // Private
            '192.168.0.0/16' // Private
        ];

        foreach ($badRanges as $range) {
            if ($this->ipInRange($ipAddress, $range)) {
                $categories[] = 'private';
                break;
            }
        }

        return [
            'threat_score' => $threatScore,
            'threat_categories' => $categories,
            'is_malicious' => $isMalicious,
            'is_tor' => $isTor,
            'is_proxy' => $isProxy
        ];
    }

    private function fetchRdapInfo(string $ipAddress): ?array
    {
        $rdap = $this->queryRdapDocument($ipAddress);
        if (!$rdap) {
            return null;
        }

        $document = $rdap['document'];
        $endpoint = $rdap['endpoint'];
        $range = $this->extractRdapRange($document);
        $contacts = $this->extractRdapContacts($document['entities'] ?? []);
        $registry = $this->identifyRegistry($document, $endpoint);

        return [
            'rdap_registry' => $registry,
            'rdap_network_range' => $range['label'],
            'rdap_network_start' => $range['start'],
            'rdap_network_end' => $range['end'],
            'rdap_contacts' => $contacts,
            'rdap_raw' => $document,
            'rdap_checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{document:array,endpoint:string}|null
     */
    private function queryRdapDocument(string $ipAddress): ?array
    {
        $ianaResponse = $this->makeHttpRequest('https://rdap.iana.org/ip/' . urlencode($ipAddress));
        $endpoint = $this->resolveRdapEndpoint($ianaResponse, $ipAddress);

        if ($endpoint === null) {
            $endpoint = 'https://rdap.arin.net/registry/ip/' . urlencode($ipAddress);
        }

        $document = $this->makeHttpRequest($endpoint);
        if (!$document) {
            return null;
        }

        return [
            'document' => $document,
            'endpoint' => $endpoint,
        ];
    }

    private function resolveRdapEndpoint(?array $ianaResponse, string $ipAddress): ?string
    {
        if (isset($ianaResponse['links']) && is_array($ianaResponse['links'])) {
            foreach ($ianaResponse['links'] as $link) {
                if (!is_array($link) || empty($link['href'])) {
                    continue;
                }

                $rel = strtolower($link['rel'] ?? '');
                if ($rel === 'self' || str_contains((string) $link['href'], 'rdap')) {
                    return $link['href'];
                }
            }
        }

        if (isset($ianaResponse['rdapConformance']) && isset($ianaResponse['links'][0]['href'])) {
            return $ianaResponse['links'][0]['href'];
        }

        return null;
    }

    private function identifyRegistry(array $rdapDocument, ?string $endpoint = null): ?string
    {
        $sources = [];
        if ($endpoint) {
            $sources[] = $endpoint;
        }

        if (isset($rdapDocument['links']) && is_array($rdapDocument['links'])) {
            foreach ($rdapDocument['links'] as $link) {
                if (isset($link['href'])) {
                    $sources[] = $link['href'];
                }
            }
        }

        foreach ($sources as $source) {
            $host = parse_url((string) $source, PHP_URL_HOST);
            if (!$host) {
                continue;
            }

            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $candidate = strtoupper($parts[count($parts) - 2]);
                if ($candidate !== 'RDAP') {
                    return $candidate;
                }
            }
        }

        if (!empty($rdapDocument['port43'])) {
            $segments = explode('.', (string) $rdapDocument['port43']);
            return strtoupper($segments[0] ?? $rdapDocument['port43']);
        }

        return null;
    }

    /**
     * @return array{start:?string,end:?string,label:?string}
     */
    private function extractRdapRange(array $rdapDocument): array
    {
        $start = $rdapDocument['startAddress'] ?? ($rdapDocument['network']['startAddress'] ?? null);
        $end = $rdapDocument['endAddress'] ?? ($rdapDocument['network']['endAddress'] ?? null);
        $label = null;

        if (isset($rdapDocument['cidr0_cidrs'][0]) && is_array($rdapDocument['cidr0_cidrs'][0])) {
            $cidr = $rdapDocument['cidr0_cidrs'][0];
            if (isset($cidr['v4prefix'], $cidr['length'])) {
                $label = $cidr['v4prefix'] . '/' . $cidr['length'];
                $start = $start ?? $cidr['v4prefix'];
            } elseif (isset($cidr['v6prefix'], $cidr['length'])) {
                $label = $cidr['v6prefix'] . '/' . $cidr['length'];
                $start = $start ?? $cidr['v6prefix'];
            }
        }

        if ($label === null && $start && $end) {
            $label = $start === $end ? $start : $start . ' - ' . $end;
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    private function extractRdapContacts(array $entities): array
    {
        $contacts = [];

        foreach ($entities as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $contact = [
                'handle' => $entity['handle'] ?? null,
                'roles' => $entity['roles'] ?? [],
                'name' => null,
                'email' => null,
                'phone' => null,
            ];

            if (isset($entity['vcardArray'][1]) && is_array($entity['vcardArray'][1])) {
                foreach ($entity['vcardArray'][1] as $vcard) {
                    if (!is_array($vcard) || count($vcard) < 4) {
                        continue;
                    }

                    $field = strtolower($vcard[0]);
                    $value = $this->normaliseVcardValue($vcard[3] ?? null);

                    if ($field === 'fn') {
                        $contact['name'] = $value;
                    } elseif ($field === 'email') {
                        $contact['email'] = $value;
                    } elseif ($field === 'tel') {
                        $contact['phone'] = $value;
                    }
                }
            }

            $contact = array_filter($contact, static function ($value) {
                return $value !== null && $value !== [];
            });

            if (!empty($contact)) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }

    private function normaliseVcardValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && isset($value['text']) && is_string($value['text'])) {
            return $value['text'];
        }

        return null;
    }

    private function checkSpamhausDnsbl(string $ipAddress): array
    {
        $result = [
            'dnsbl_listed' => false,
            'dnsbl_sources' => [],
            'dnsbl_last_checked' => date('Y-m-d H:i:s'),
        ];

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $result;
        }

        $query = $this->reverseIpv4($ipAddress) . '.zen.spamhaus.org';

        try {
            $answers = $this->dnsClient->query($query, 'A');
            if (!empty($answers)) {
                $result['dnsbl_listed'] = true;
                foreach ($answers as $answer) {
                    if (!isset($answer['ip'])) {
                        continue;
                    }

                    $result['dnsbl_sources'][] = [
                        'source' => 'zen.spamhaus.org',
                        'type' => 'A',
                        'response' => $answer['ip'],
                        'ttl' => $answer['ttl'] ?? null,
                    ];
                }

                $txtAnswers = $this->dnsClient->query($query, 'TXT');
                if (!empty($txtAnswers)) {
                    foreach ($txtAnswers as $txt) {
                        if (!isset($txt['txt'])) {
                            continue;
                        }

                        $result['dnsbl_sources'][] = [
                            'source' => 'zen.spamhaus.org',
                            'type' => 'TXT',
                            'response' => $txt['txt'],
                            'ttl' => $txt['ttl'] ?? null,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore DoH failures and fall back to defaults
        }

        return $result;
    }

    private function reverseIpv4(string $ipAddress): string
    {
        return implode('.', array_reverse(explode('.', $ipAddress)));
    }

    private function fetchSansReputation(string $ipAddress): array
    {
        $result = [
            'reputation_context' => [],
            'reputation_last_checked' => date('Y-m-d H:i:s'),
        ];

        $response = $this->makeHttpRequest('https://isc.sans.edu/api/ip/' . urlencode($ipAddress) . '?json');
        if (!$response || !isset($response['ip'])) {
            return $result;
        }

        $ipData = $response['ip'];
        $score = $this->mapThreatLevelToScore($ipData['threatlevel'] ?? null, $ipData['attacks'] ?? null);
        $result['reputation_score'] = $score;

        $context = [
            'source' => 'SANS ISC',
        ];

        foreach (['threatlevel', 'attacks', 'updated'] as $field) {
            if (isset($ipData[$field]) && $ipData[$field] !== '') {
                $context[$field] = $ipData[$field];
            }
        }

        $result['reputation_context'] = $context;

        return $result;
    }

    private function mapThreatLevelToScore(?string $level, mixed $attacks): ?int
    {
        $numericAttacks = is_numeric($attacks) ? (int) $attacks : null;

        if ($level === null || $level === '') {
            if ($numericAttacks && $numericAttacks > 0) {
                return min(100, 10 + $numericAttacks * 5);
            }

            return null;
        }

        $map = [
            'low' => 20,
            'medium' => 60,
            'high' => 90,
            'critical' => 100,
        ];

        $score = $map[strtolower($level)] ?? null;

        if ($score !== null && $numericAttacks) {
            $score = min(100, $score + min(40, $numericAttacks));
        }

        return $score;
    }

    /**
     * Get ASN data
     */
    private function getASNData(string $ipAddress): ?array
    {
        // This is usually covered by the geo services above
        return null;
    }

    /**
     * Make HTTP request with timeout and error handling
     */
    private function makeHttpRequest(string $url, array $headers = []): ?array
    {
        try {
            $raw = null;

            if ($this->httpHandler) {
                $raw = call_user_func($this->httpHandler, $url, $headers);
            } else {
                $raw = $this->performHttpRequest($url, $headers);
            }

            if ($raw === null) {
                return null;
            }

            if (is_array($raw)) {
                return $raw;
            }

            if (!is_string($raw)) {
                return null;
            }

            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function performHttpRequest(string $url, array $headers = []): ?string
    {
        $headers = array_merge(['Accept: application/json'], $headers);

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'DMARC Dashboard/1.0',
                'header' => implode("\r\n", array_filter($headers))
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        return $response === false ? null : $response;
    }

    /**
     * Cache intelligence data
     */
    private function cacheIntelligence(array $intelligence): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $db = DatabaseManager::getInstance();

            $db->query('SELECT 1 FROM ip_intelligence WHERE ip_address = :ip');
            $db->bind(':ip', $intelligence['ip_address']);
            $exists = $db->single();

            $rdapContacts = json_encode($intelligence['rdap_contacts'] ?? []);
            $rdapRaw = isset($intelligence['rdap_raw']) && $intelligence['rdap_raw'] !== []
                ? json_encode($intelligence['rdap_raw'])
                : null;
            $dnsblSources = json_encode($intelligence['dnsbl_sources'] ?? []);
            $reputationContext = json_encode($intelligence['reputation_context'] ?? []);
            $threatCategories = json_encode($intelligence['threat_categories'] ?? []);

            if ($exists) {
                $db->query('
                    UPDATE ip_intelligence SET
                        country_code = :country_code,
                        country_name = :country_name,
                        region = :region,
                        city = :city,
                        timezone = :timezone,
                        isp = :isp,
                        organization = :organization,
                        asn = :asn,
                        asn_org = :asn_org,
                        threat_score = :threat_score,
                        threat_categories = :threat_categories,
                        is_malicious = :is_malicious,
                        is_tor = :is_tor,
                        is_proxy = :is_proxy,
                        rdap_registry = :rdap_registry,
                        rdap_network_range = :rdap_network_range,
                        rdap_network_start = :rdap_network_start,
                        rdap_network_end = :rdap_network_end,
                        rdap_contacts = :rdap_contacts,
                        rdap_raw = :rdap_raw,
                        rdap_checked_at = :rdap_checked_at,
                        dnsbl_listed = :dnsbl_listed,
                        dnsbl_sources = :dnsbl_sources,
                        dnsbl_last_checked = :dnsbl_last_checked,
                        reputation_score = :reputation_score,
                        reputation_context = :reputation_context,
                        reputation_last_checked = :reputation_last_checked,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE ip_address = :ip_address
                ');
            } else {
                $db->query('
                    INSERT INTO ip_intelligence (
                        ip_address, country_code, country_name, region, city, timezone,
                        isp, organization, asn, asn_org, threat_score, threat_categories,
                        is_malicious, is_tor, is_proxy,
                        rdap_registry, rdap_network_range, rdap_network_start, rdap_network_end,
                        rdap_contacts, rdap_raw, rdap_checked_at,
                        dnsbl_listed, dnsbl_sources, dnsbl_last_checked,
                        reputation_score, reputation_context, reputation_last_checked
                    ) VALUES (
                        :ip_address, :country_code, :country_name, :region, :city, :timezone,
                        :isp, :organization, :asn, :asn_org, :threat_score, :threat_categories,
                        :is_malicious, :is_tor, :is_proxy,
                        :rdap_registry, :rdap_network_range, :rdap_network_start, :rdap_network_end,
                        :rdap_contacts, :rdap_raw, :rdap_checked_at,
                        :dnsbl_listed, :dnsbl_sources, :dnsbl_last_checked,
                        :reputation_score, :reputation_context, :reputation_last_checked
                    )
                ');
            }

            $db->bind(':ip_address', $intelligence['ip_address']);
            $db->bind(':country_code', $intelligence['country_code']);
            $db->bind(':country_name', $intelligence['country_name']);
            $db->bind(':region', $intelligence['region']);
            $db->bind(':city', $intelligence['city']);
            $db->bind(':timezone', $intelligence['timezone']);
            $db->bind(':isp', $intelligence['isp']);
            $db->bind(':organization', $intelligence['organization']);
            $db->bind(':asn', $intelligence['asn']);
            $db->bind(':asn_org', $intelligence['asn_org']);
            $db->bind(':threat_score', $intelligence['threat_score']);
            $db->bind(':threat_categories', $threatCategories);
            $db->bind(':is_malicious', $intelligence['is_malicious'] ? 1 : 0);
            $db->bind(':is_tor', $intelligence['is_tor'] ? 1 : 0);
            $db->bind(':is_proxy', $intelligence['is_proxy'] ? 1 : 0);
            $db->bind(':rdap_registry', $intelligence['rdap_registry']);
            $db->bind(':rdap_network_range', $intelligence['rdap_network_range']);
            $db->bind(':rdap_network_start', $intelligence['rdap_network_start']);
            $db->bind(':rdap_network_end', $intelligence['rdap_network_end']);
            $db->bind(':rdap_contacts', $rdapContacts);
            $db->bind(':rdap_raw', $rdapRaw);
            $db->bind(':rdap_checked_at', $intelligence['rdap_checked_at']);
            $db->bind(':dnsbl_listed', $intelligence['dnsbl_listed'] ? 1 : 0);
            $db->bind(':dnsbl_sources', $dnsblSources);
            $db->bind(':dnsbl_last_checked', $intelligence['dnsbl_last_checked']);
            $db->bind(':reputation_score', $intelligence['reputation_score']);
            $db->bind(':reputation_context', $reputationContext);
            $db->bind(':reputation_last_checked', $intelligence['reputation_last_checked']);
            $db->execute();
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get cached intelligence data
     */
    private function getCachedIntelligence(string $ipAddress): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            $db = DatabaseManager::getInstance();
            $db->query('SELECT * FROM ip_intelligence WHERE ip_address = :ip');
            $db->bind(':ip', $ipAddress);
            $result = $db->single();

            if ($result) {
                $result['threat_categories'] = json_decode($result['threat_categories'] ?? '[]', true) ?: [];
                $result['is_malicious'] = (bool) $result['is_malicious'];
                $result['is_tor'] = (bool) $result['is_tor'];
                $result['is_proxy'] = (bool) $result['is_proxy'];
                $result['rdap_contacts'] = json_decode($result['rdap_contacts'] ?? '[]', true) ?: [];
                $result['rdap_raw'] = json_decode($result['rdap_raw'] ?? 'null', true) ?? [];
                $result['dnsbl_listed'] = isset($result['dnsbl_listed']) ? (bool) $result['dnsbl_listed'] : false;
                $result['dnsbl_sources'] = json_decode($result['dnsbl_sources'] ?? '[]', true) ?: [];
                $result['reputation_context'] = json_decode($result['reputation_context'] ?? '[]', true) ?: [];
                return $result;
            }
        } catch (Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Check if cached data is still valid (24 hours)
     */
    private function isCacheValid(array $cached): bool
    {
        $lastUpdated = strtotime($cached['last_updated']);
        $cacheExpiry = 24 * 60 * 60; // 24 hours
        return (time() - $lastUpdated) < $cacheExpiry;
    }

    /**
     * Extract ASN number from string
     */
    private function extractASN(string $asString): ?string
    {
        if (preg_match('/AS(\d+)/', $asString, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract ASN organization from string
     */
    private function extractASNOrg(string $asString): ?string
    {
        if (preg_match('/AS\d+\s+(.+)/', $asString, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = ~((1 << (32 - $mask)) - 1);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Clean old cache entries
     */
    public function cleanOldCache(int $daysToKeep = 30): int
    {
        if (!$this->cacheEnabled) {
            return 0;
        }

        try {
            $db = DatabaseManager::getInstance();
            $db->query('
                DELETE FROM ip_intelligence
                WHERE last_updated < datetime("now", "-' . $daysToKeep . ' days")
            ');
            $db->execute();
            return $db->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}
