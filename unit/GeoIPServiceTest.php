<?php
// phpcs:ignoreFile

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';

use App\Services\GeoIPService;
use App\Utilities\DnsOverHttpsClient;

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
    public function __construct(private array $responses)
    {
        parent::__construct(function (): ?array {
            return null;
        });
    }

    public function query(string $name, string $type): ?array
    {
        $key = strtolower($name) . '|' . strtoupper($type);
        return $this->responses[$key] ?? [];
    }
}

function buildGeoService(): GeoIPService
{
    $httpHandler = function (string $url) {
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

echo 'GeoIPService tests completed with ' . ($failures === 0 ? 'no failures' : "{$failures} failure(s)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
