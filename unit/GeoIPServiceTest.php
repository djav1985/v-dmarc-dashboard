<?php
// phpcs:ignoreFile

declare(strict_types=1);

$autoload = __DIR__ . '/../root/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

require_once __DIR__ . '/../root/config.php';

use App\Services\GeoIPService;
use App\Utilities\DnsOverHttpsClient;
use App\Core\DatabaseManager;

$failures = 0;

function assertPredicate(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

class StubDnsClient extends DnsOverHttpsClient
{
    public array $queries = [];

    public function __construct(private array $responses)
    {
        parent::__construct(function (): ?array {
            return null;
        });
    }

    public function resetQueries(): void
    {
        $this->queries = [];
    }

    public function query(string $name, string $type): ?array
    {
        $key = strtolower($name) . '|' . strtoupper($type);
        $this->queries[] = [$name, strtoupper($type)];
        return $this->responses[$key] ?? [];
    }
}

function mockHttpHandler(?array &$httpLog = null): callable
{
    return function (string $url) use (&$httpLog) {
        if (is_array($httpLog)) {
            $httpLog[] = $url;
        }

        if (str_contains($url, 'ip-api.com')) {
            return [
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'New York',
                'city' => 'New York',
                'timezone' => 'America/New_York',
                'isp' => 'Example ISP',
                'org' => 'Example Org',
                'as' => 'AS64500 Example Backbone',
            ];
        }

        if (str_contains($url, 'rdap.iana.org')) {
            return [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://rdap.arin.net/registry/ip/203.0.113.0/24'],
                ],
            ];
        }

        if (str_contains($url, 'rdap.arin.net')) {
            return [
                'startAddress' => '203.0.113.0',
                'endAddress' => '203.0.113.255',
                'cidr0_cidrs' => [
                    ['v4prefix' => '203.0.113.0', 'length' => 24],
                ],
                'entities' => [
                    [
                        'handle' => 'NET-OPS',
                        'roles' => ['administrative'],
                        'vcardArray' => [
                            'vcard',
                            [
                                ['fn', [], 'text', 'Example Admin'],
                                ['email', [], 'text', 'admin@example.net'],
                            ],
                        ],
                    ],
                ],
                'links' => [
                    ['rel' => 'self', 'href' => 'https://rdap.arin.net/registry/ip/203.0.113.0/24'],
                ],
            ];
        }

        if (str_contains($url, 'isc.sans.edu')) {
            return [
                'ip' => [
                    'threatlevel' => 'medium',
                    'attacks' => '5',
                    'updated' => '2024-01-01',
                ],
            ];
        }

        return null;
    };
}

function buildGeoService(): GeoIPService
{
    $httpHandler = mockHttpHandler();

    $dnsClient = new StubDnsClient([
        '5.113.0.203.zen.spamhaus.org|A' => [
            ['type' => 'A', 'ip' => '127.0.0.2', 'ttl' => 300],
        ],
        '5.113.0.203.zen.spamhaus.org|TXT' => [
            ['type' => 'TXT', 'txt' => 'Listed by Spamhaus', 'ttl' => 300],
        ],
    ]);

    $service = GeoIPService::createWithDependencies($httpHandler, $dnsClient);
    $service->setCacheEnabled(false);

    return $service;
}

function ensureIpIntelligenceSchema(): void
{
    $db = DatabaseManager::getInstance();
    $db->query('
        CREATE TABLE IF NOT EXISTS ip_intelligence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT UNIQUE NOT NULL,
            country_code TEXT,
            country_name TEXT,
            region TEXT,
            city TEXT,
            timezone TEXT,
            isp TEXT,
            organization TEXT,
            asn TEXT,
            asn_org TEXT,
            threat_score INTEGER DEFAULT 0,
            threat_categories TEXT,
            is_malicious INTEGER DEFAULT 0,
            is_tor INTEGER DEFAULT 0,
            is_proxy INTEGER DEFAULT 0,
            rdap_registry TEXT,
            rdap_network_range TEXT,
            rdap_network_start TEXT,
            rdap_network_end TEXT,
            rdap_contacts TEXT,
            rdap_raw TEXT,
            rdap_checked_at DATETIME,
            dnsbl_listed INTEGER DEFAULT 0,
            dnsbl_sources TEXT,
            dnsbl_last_checked DATETIME,
            reputation_score INTEGER,
            reputation_context TEXT,
            reputation_last_checked DATETIME,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $db->execute();
}

function resetIpIntelligenceTable(): void
{
    $db = DatabaseManager::getInstance();
    $db->query('DELETE FROM ip_intelligence');
    $db->execute();
}

function testGeoIpAggregation(): string
{
    $service = buildGeoService();
    $intelligence = $service->getIPIntelligence('203.0.113.5');

    if (($intelligence['rdap_registry'] ?? null) !== 'ARIN') {
        return 'RDAP registry was not identified as ARIN';
    }

    if (($intelligence['rdap_network_range'] ?? null) !== '203.0.113.0/24') {
        return 'RDAP network range missing expected CIDR representation';
    }

    if (empty($intelligence['rdap_contacts'])) {
        return 'RDAP contacts were not extracted from the registry response';
    }

    if (empty($intelligence['dnsbl_listed']) || empty($intelligence['dnsbl_sources'])) {
        return 'Spamhaus DNSBL result did not reflect the mocked listing';
    }

    if (($intelligence['reputation_score'] ?? 0) < 60) {
        return 'SANS ISC reputation score did not propagate into the cache';
    }

    if (!in_array('dnsbl:spamhaus', $intelligence['threat_categories'], true)) {
        return 'Threat categories missing dnsbl:spamhaus tag';
    }

    if (!in_array('reputation:sans', $intelligence['threat_categories'], true)) {
        return 'Threat categories missing reputation:sans tag';
    }

    if (($intelligence['threat_score'] ?? 0) < 90) {
        return 'Threat score did not reflect DNSBL severity escalation';
    }

    return '';
}

$error = testGeoIpAggregation();
assertPredicate($error === '', $error ?: 'GeoIP aggregation', $failures);

function testPerFieldTtlRefresh(): string
{
    ensureIpIntelligenceSchema();
    resetIpIntelligenceTable();
    DnsOverHttpsClient::clearCache();

    $httpLog = [];
    $httpHandler = mockHttpHandler($httpLog);
    $dnsClient = new StubDnsClient([
        '5.113.0.203.zen.spamhaus.org|A' => [
            ['type' => 'A', 'ip' => '127.0.0.2', 'ttl' => 300],
        ],
        '5.113.0.203.zen.spamhaus.org|TXT' => [
            ['type' => 'TXT', 'txt' => 'Listed by Spamhaus', 'ttl' => 300],
        ],
    ]);

    $service = GeoIPService::createWithDependencies($httpHandler, $dnsClient);
    $service->setCacheEnabled(true);

    $first = $service->getIPIntelligence('203.0.113.5');

    if (empty($first['rdap_checked_at']) || empty($first['dnsbl_last_checked']) || empty($first['reputation_last_checked'])) {
        return 'Initial lookup did not populate all freshness timestamps';
    }

    $reflection = new \ReflectionClass(GeoIPService::class);
    $ttlConst = $reflection->getReflectionConstant('DYNAMIC_CACHE_TTL');
    $ttl = $ttlConst ? (int) $ttlConst->getValue() : 86400;
    $staleTime = date('Y-m-d H:i:s', time() - ($ttl + 120));

    $db = DatabaseManager::getInstance();
    $db->query('UPDATE ip_intelligence SET rdap_checked_at = :rdap, dnsbl_last_checked = :dnsbl, reputation_last_checked = :rep WHERE ip_address = :ip');
    $db->bind(':rdap', $staleTime);
    $db->bind(':dnsbl', $staleTime);
    $db->bind(':rep', $staleTime);
    $db->bind(':ip', '203.0.113.5');
    $db->execute();

    $httpLog = [];
    $dnsClient->resetQueries();

    $second = $service->getIPIntelligence('203.0.113.5');

    if (($second['rdap_checked_at'] ?? null) !== $staleTime) {
        return 'RDAP timestamp refreshed despite cached metadata being present';
    }

    if (strtotime($second['dnsbl_last_checked'] ?? '') <= strtotime($staleTime)) {
        return 'DNSBL timestamp did not refresh when cache expired';
    }

    if (strtotime($second['reputation_last_checked'] ?? '') <= strtotime($staleTime)) {
        return 'Reputation timestamp did not refresh when cache expired';
    }

    $rdapCalls = array_filter($httpLog, static fn(string $url) => str_contains($url, 'rdap.'));
    if (!empty($rdapCalls)) {
        return 'RDAP endpoints were queried even though cached data was reused';
    }

    if (count($dnsClient->queries) === 0) {
        return 'DNSBL refresh did not trigger DoH queries';
    }

    if (($second['country_code'] ?? null) !== 'US') {
        return 'Cached geo data was not preserved';
    }

    if (!in_array('dnsbl:spamhaus', $second['threat_categories'], true)) {
        return 'Threat categories lost dnsbl:spamhaus tag after refresh';
    }

    if (($second['threat_score'] ?? 0) < 60) {
        return 'Threat score was not recalculated from refreshed sources';
    }

    return '';
}

$error = testPerFieldTtlRefresh();
assertPredicate($error === '', $error ?: 'GeoIP per-field TTL', $failures);

echo 'GeoIPService tests completed with ' . ($failures === 0 ? 'no failures' : "{$failures} failure(s)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
