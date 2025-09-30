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

    private const DYNAMIC_CACHE_TTL = 86400; // 24 hours per dynamic section

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

    private function getDefaultIntelligence(string $ipAddress): array
    {
        return [
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
            'last_updated' => null,
        ];
    }

    private function shouldRefreshGeoData(array $intelligence): bool
    {
        $geoFields = [
            'country_code',
            'country_name',
            'region',
            'city',
            'timezone',
            'isp',
            'organization',
            'asn',
            'asn_org',
        ];

        foreach ($geoFields as $field) {
            if (!isset($intelligence[$field]) || $intelligence[$field] === null || $intelligence[$field] === '') {
                return true;
            }
        }

        return false;
    }

    private function needsRefresh(?string $timestamp, int $ttlSeconds): bool
    {
        if (empty($timestamp)) {
            return true;
        }

        $parsed = strtotime($timestamp);
        if ($parsed === false) {
            return true;
        }

        return (time() - $parsed) >= $ttlSeconds;
    }

    /**
     * Get comprehensive IP intelligence
     */
    public function getIPIntelligence(string $ipAddress): array
    {
        $cached = $this->cacheEnabled ? $this->getCachedIntelligence($ipAddress) : null;

        $intelligence = $this->getDefaultIntelligence($ipAddress);
        if ($cached) {
            $intelligence = array_merge($intelligence, $cached);
        }

        if (!$cached || $this->shouldRefreshGeoData($intelligence)) {
            $geoData = $this->getGeoLocationData($ipAddress);
            if ($geoData) {
                $intelligence = array_merge($intelligence, $geoData);
            }
        }

        if (empty($intelligence['rdap_checked_at'])) {
            $rdapData = $this->fetchRdapInfo($ipAddress);
            if ($rdapData) {
                $intelligence = array_merge($intelligence, $rdapData);
            }
        }

        $asnData = $this->getASNData($ipAddress);
        if ($asnData) {
            $intelligence = array_merge($intelligence, $asnData);
        }

        if ($this->needsRefresh($intelligence['dnsbl_last_checked'] ?? null, self::DYNAMIC_CACHE_TTL)) {
            $dnsblData = $this->checkSpamhausDnsbl($ipAddress);
            $intelligence = array_merge($intelligence, $dnsblData);
        }

        if ($this->needsRefresh($intelligence['reputation_last_checked'] ?? null, self::DYNAMIC_CACHE_TTL)) {
            $reputationData = $this->fetchSansReputation($ipAddress);
            if (!empty($reputationData)) {
                $intelligence = array_merge($intelligence, $reputationData);
            }
        }

        $intelligence = $this->recalculateThreatAssessment($ipAddress, $intelligence);
        $intelligence['last_updated'] = date('Y-m-d H:i:s');

        $this->cacheIntelligence($intelligence, $cached);

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

    private function recalculateThreatAssessment(string $ipAddress, array $intelligence): array
    {
        $baseline = $this->basicThreatCheck($ipAddress);
        $categories = $baseline['threat_categories'];
        $score = $baseline['threat_score'];
        $isMalicious = $baseline['is_malicious'];
        $isTor = $intelligence['is_tor'] ?? $baseline['is_tor'];
        $isProxy = $intelligence['is_proxy'] ?? $baseline['is_proxy'];

        $existingCategories = $intelligence['threat_categories'] ?? [];
        $preservedCategories = array_filter(
            $existingCategories,
            fn(string $category) => !$this->isDynamicThreatCategory($category)
        );

        if (!empty($preservedCategories)) {
            $categories = array_merge($categories, $preservedCategories);
        }

        if (!empty($intelligence['dnsbl_listed'])) {
            $categories[] = 'dnsbl:spamhaus';
            $score = max($score, 90);
            $isMalicious = true;
        }

        if (isset($intelligence['reputation_score'])) {
            $reputationScore = (int) $intelligence['reputation_score'];
            if ($reputationScore > 0) {
                $score = max($score, $reputationScore);
                if ($reputationScore >= 30) {
                    $categories[] = 'reputation:sans';
                }
                if ($reputationScore >= 60) {
                    $isMalicious = true;
                }
            }
        }

        if (!empty($preservedCategories) && !empty($intelligence['is_malicious'])) {
            $isMalicious = true;
        }

        if (!empty($preservedCategories) && isset($intelligence['threat_score'])) {
            $score = max($score, (int) $intelligence['threat_score']);
        }

        $intelligence['threat_categories'] = array_values(array_unique(array_filter($categories)));
        $intelligence['threat_score'] = $score;
        $intelligence['is_malicious'] = $isMalicious;
        $intelligence['is_tor'] = $isTor;
        $intelligence['is_proxy'] = $isProxy;

        return $intelligence;
    }

    private function isDynamicThreatCategory(string $category): bool
    {
        return str_starts_with($category, 'dnsbl:') || str_starts_with($category, 'reputation:');
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
    private function cacheIntelligence(array $intelligence, ?array $existing = null): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $db = DatabaseManager::getInstance();

            $existing ??= $this->getCachedIntelligence($intelligence['ip_address']);
            $hasExisting = $existing !== null;

            $payload = $hasExisting
                ? $this->mergeIntelligenceRecords($existing, $intelligence)
                : array_merge($this->getDefaultIntelligence($intelligence['ip_address']), $intelligence);

            $payload['threat_categories'] = is_array($payload['threat_categories'] ?? null)
                ? $payload['threat_categories']
                : [];
            $payload['rdap_contacts'] = is_array($payload['rdap_contacts'] ?? null)
                ? $payload['rdap_contacts']
                : [];
            $payload['rdap_raw'] = is_array($payload['rdap_raw'] ?? null)
                ? $payload['rdap_raw']
                : [];
            $payload['dnsbl_sources'] = is_array($payload['dnsbl_sources'] ?? null)
                ? $payload['dnsbl_sources']
                : [];
            $payload['reputation_context'] = is_array($payload['reputation_context'] ?? null)
                ? $payload['reputation_context']
                : [];

            $rdapContacts = json_encode($payload['rdap_contacts']);
            $rdapRaw = $payload['rdap_raw'] !== [] ? json_encode($payload['rdap_raw']) : null;
            $dnsblSources = json_encode($payload['dnsbl_sources']);
            $reputationContext = json_encode($payload['reputation_context']);
            $threatCategories = json_encode($payload['threat_categories']);

            if ($hasExisting) {
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

            $db->bind(':ip_address', $payload['ip_address']);
            $db->bind(':country_code', $payload['country_code']);
            $db->bind(':country_name', $payload['country_name']);
            $db->bind(':region', $payload['region']);
            $db->bind(':city', $payload['city']);
            $db->bind(':timezone', $payload['timezone']);
            $db->bind(':isp', $payload['isp']);
            $db->bind(':organization', $payload['organization']);
            $db->bind(':asn', $payload['asn']);
            $db->bind(':asn_org', $payload['asn_org']);
            $db->bind(':threat_score', $payload['threat_score']);
            $db->bind(':threat_categories', $threatCategories);
            $db->bind(':is_malicious', !empty($payload['is_malicious']) ? 1 : 0);
            $db->bind(':is_tor', !empty($payload['is_tor']) ? 1 : 0);
            $db->bind(':is_proxy', !empty($payload['is_proxy']) ? 1 : 0);
            $db->bind(':rdap_registry', $payload['rdap_registry']);
            $db->bind(':rdap_network_range', $payload['rdap_network_range']);
            $db->bind(':rdap_network_start', $payload['rdap_network_start']);
            $db->bind(':rdap_network_end', $payload['rdap_network_end']);
            $db->bind(':rdap_contacts', $rdapContacts);
            $db->bind(':rdap_raw', $rdapRaw);
            $db->bind(':rdap_checked_at', $payload['rdap_checked_at']);
            $db->bind(':dnsbl_listed', !empty($payload['dnsbl_listed']) ? 1 : 0);
            $db->bind(':dnsbl_sources', $dnsblSources);
            $db->bind(':dnsbl_last_checked', $payload['dnsbl_last_checked']);
            $db->bind(':reputation_score', $payload['reputation_score']);
            $db->bind(':reputation_context', $reputationContext);
            $db->bind(':reputation_last_checked', $payload['reputation_last_checked']);
            $db->execute();
        } catch (Exception $e) {
            // Silently fail
        }
    }

    private function mergeIntelligenceRecords(array $existing, array $updates): array
    {
        $merged = $existing;

        $staticFields = [
            'country_code',
            'country_name',
            'region',
            'city',
            'timezone',
            'isp',
            'organization',
            'asn',
            'asn_org',
            'rdap_registry',
            'rdap_network_range',
            'rdap_network_start',
            'rdap_network_end',
            'rdap_contacts',
            'rdap_raw',
            'rdap_checked_at',
        ];

        foreach ($updates as $field => $value) {
            if (in_array($field, $staticFields, true) && ($value === null || $value === [])) {
                continue;
            }

            $merged[$field] = $value;
        }

        return $merged;
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
            $cutoff = date('Y-m-d H:i:s', strtotime('-' . $daysToKeep . ' days'));
            $db->query('
                DELETE FROM ip_intelligence
                WHERE last_updated < :cutoff
            ');
            $db->bind(':cutoff', $cutoff);
            $db->execute();
            return $db->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}
