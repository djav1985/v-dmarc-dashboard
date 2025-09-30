<?php

namespace App\Services;

use App\Core\DatabaseManager;
use Exception;

/**
 * GeoIP and Threat Intelligence Service
 * Provides IP geolocation, ASN, and threat intelligence data
 */
class GeoIPService
{
    private static ?GeoIPService $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): GeoIPService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get comprehensive IP intelligence
     */
    public function getIPIntelligence(string $ipAddress): array
    {
        // Check cache first
        $cached = $this->getCachedIntelligence($ipAddress);
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
            $url = "http://ip-api.com/json/{$ipAddress}?fields=status,country,countryCode,region,regionName,city,timezone,isp,org,as,query";
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
            // Use AbuseIPDB free tier (requires API key)
            $apiKey = defined('ABUSEIPDB_API_KEY') ? ABUSEIPDB_API_KEY : null;
            if ($apiKey) {
                $result = $this->checkAbuseIPDB($ipAddress, $apiKey);
                if ($result) {
                    return $result;
                }
            }

            // Fallback to basic reputation checks
            return $this->basicThreatCheck($ipAddress);

        } catch (Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Check AbuseIPDB for threat intelligence
     */
    private function checkAbuseIPDB(string $ipAddress, string $apiKey): ?array
    {
        try {
            $url = "https://api.abuseipdb.com/api/v2/check";
            $headers = [
                "Key: $apiKey",
                "Accept: application/json"
            ];
            $params = [
                'ipAddress' => $ipAddress,
                'maxAgeInDays' => 90,
                'verbose' => ''
            ];

            $response = $this->makeHttpRequest($url . '?' . http_build_query($params), $headers);
            
            if ($response && isset($response['data'])) {
                $data = $response['data'];
                return [
                    'threat_score' => $data['abuseConfidencePercentage'] ?? 0,
                    'threat_categories' => $data['usageType'] ? [$data['usageType']] : [],
                    'is_malicious' => ($data['abuseConfidencePercentage'] ?? 0) > 50,
                    'is_tor' => $data['usageType'] === 'tor',
                    'is_proxy' => in_array($data['usageType'], ['proxy', 'vpn'])
                ];
            }
        } catch (Exception $e) {
            // Silently fail
        }

        return null;
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
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'DMARC Dashboard/1.0',
                    'header' => implode("\r\n", $headers)
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            return $data ?: null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Cache intelligence data
     */
    private function cacheIntelligence(array $intelligence): void
    {
        try {
            $db = DatabaseManager::getInstance();
            
            // Check if record exists
            $db->query('SELECT 1 FROM ip_intelligence WHERE ip_address = :ip');
            $db->bind(':ip', $intelligence['ip_address']);
            $exists = $db->single();

            if ($exists) {
                // Update existing record
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
                        last_updated = CURRENT_TIMESTAMP
                    WHERE ip_address = :ip_address
                ');
            } else {
                // Insert new record
                $db->query('
                    INSERT INTO ip_intelligence (
                        ip_address, country_code, country_name, region, city, timezone,
                        isp, organization, asn, asn_org, threat_score, threat_categories,
                        is_malicious, is_tor, is_proxy
                    ) VALUES (
                        :ip_address, :country_code, :country_name, :region, :city, :timezone,
                        :isp, :organization, :asn, :asn_org, :threat_score, :threat_categories,
                        :is_malicious, :is_tor, :is_proxy
                    )
                ');
            }

            // Bind values
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
            $db->bind(':threat_categories', json_encode($intelligence['threat_categories']));
            $db->bind(':is_malicious', $intelligence['is_malicious'] ? 1 : 0);
            $db->bind(':is_tor', $intelligence['is_tor'] ? 1 : 0);
            $db->bind(':is_proxy', $intelligence['is_proxy'] ? 1 : 0);

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
        try {
            $db = DatabaseManager::getInstance();
            $db->query('SELECT * FROM ip_intelligence WHERE ip_address = :ip');
            $db->bind(':ip', $ipAddress);
            $result = $db->single();

            if ($result) {
                $result['threat_categories'] = json_decode($result['threat_categories'] ?? '[]', true);
                $result['is_malicious'] = (bool) $result['is_malicious'];
                $result['is_tor'] = (bool) $result['is_tor'];
                $result['is_proxy'] = (bool) $result['is_proxy'];
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