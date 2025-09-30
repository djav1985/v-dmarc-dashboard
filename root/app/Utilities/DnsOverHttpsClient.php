<?php

namespace App\Utilities;

use Exception;

/**
 * Lightweight DNS-over-HTTPS client with response caching.
 */
class DnsOverHttpsClient
{
    private const PROVIDERS = [
        'google' => 'https://dns.google/resolve',
        'cloudflare' => 'https://cloudflare-dns.com/dns-query',
    ];

    /** @var array<string, array{expires_at:int,data:array}> */
    private static array $cache = [];

    /** @var callable|null */
    private $httpFetcher;

    public function __construct(?callable $httpFetcher = null)
    {
        $this->httpFetcher = $httpFetcher;
    }

    /**
     * Forget any cached DNS responses.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Query DNS information over HTTPS.
     *
     * @param string $name The domain name to query.
     * @param string $type Record type (TXT, MX, SOA, A, etc.).
     *
     * @return array|null Normalised record set on success, null otherwise.
     */
    public function query(string $name, string $type): ?array
    {
        $type = strtoupper($type);
        $cacheKey = strtolower($name) . '|' . $type;

        if (isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            if ($cached['expires_at'] > time()) {
                return $cached['data'];
            }
            unset(self::$cache[$cacheKey]);
        }

        $formatted = $this->performQuery($name, $type);
        if ($formatted === null) {
            return null;
        }

        $ttl = $formatted['ttl'] ?? 300;
        self::$cache[$cacheKey] = [
            'expires_at' => time() + max(30, (int) $ttl),
            'data' => $formatted['records'],
        ];

        return $formatted['records'];
    }

    /**
     * Issue the DoH request against the supported providers.
     *
     * @param string $name
     * @param string $type
     *
     * @return array{records:array,ttl:int}|null
     */
    private function performQuery(string $name, string $type): ?array
    {
        foreach (self::PROVIDERS as $provider => $endpoint) {
            try {
                $queryUrl = $endpoint . '?name=' . urlencode($name) . '&type=' . urlencode($type);
                $headers = [];
                if ($provider === 'cloudflare') {
                    $headers[] = 'Accept: application/dns-json';
                }

                $response = $this->fetch($queryUrl, $headers);
                if (!$response || empty($response['Answer'])) {
                    continue;
                }

                $formatted = $this->formatAnswers($type, $response['Answer']);
                if (!empty($formatted['records'])) {
                    return $formatted;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Fetch a DoH payload.
     *
     * @param string $url
     * @param string[] $headers
     */
    private function fetch(string $url, array $headers): ?array
    {
        $raw = null;
        if ($this->httpFetcher) {
            $raw = call_user_func($this->httpFetcher, $url, $headers);
        } else {
            $raw = $this->defaultHttpFetcher($url, $headers);
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

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function defaultHttpFetcher(string $url, array $headers): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'DMARC Dashboard/1.0',
                'header' => implode("\r\n", array_filter($headers)),
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result === false ? null : $result;
    }

    /**
     * Normalise DoH answers to the dns_get_record-esque structure we expect.
     *
     * @param string $type
     * @param array<int, array<string, mixed>> $answers
     *
     * @return array{records:array,ttl:int}
     */
    private function formatAnswers(string $type, array $answers): array
    {
        $records = [];
        $ttls = [];

        foreach ($answers as $answer) {
            if (!isset($answer['data'])) {
                continue;
            }

            $ttl = isset($answer['TTL']) ? (int) $answer['TTL'] : null;
            if ($ttl !== null) {
                $ttls[] = $ttl;
            }

            $data = $answer['data'];
            switch ($type) {
                case 'TXT':
                    $records[] = [
                        'type' => 'TXT',
                        'txt' => $this->normaliseTxt($data),
                        'ttl' => $ttl,
                    ];
                    break;
                case 'MX':
                    $records[] = $this->parseMx($data, $ttl);
                    break;
                case 'SOA':
                    $soa = $this->parseSoa($data, $ttl);
                    if ($soa !== null) {
                        $records[] = $soa;
                    }
                    break;
                case 'A':
                    $records[] = [
                        'type' => 'A',
                        'ip' => trim($data, '.'),
                        'ttl' => $ttl,
                    ];
                    break;
                default:
                    $records[] = [
                        'type' => $type,
                        'data' => $data,
                        'ttl' => $ttl,
                    ];
                    break;
            }
        }

        $ttl = !empty($ttls) ? min($ttls) : 300;

        return [
            'records' => $records,
            'ttl' => $ttl,
        ];
    }

    private function normaliseTxt(string $value): string
    {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return stripcslashes($trimmed);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMx(string $value, ?int $ttl): array
    {
        $parts = preg_split('/\s+/', trim($value), 2);
        $priority = isset($parts[0]) ? (int) $parts[0] : 0;
        $target = isset($parts[1]) ? rtrim($parts[1], '.') : '';

        return [
            'type' => 'MX',
            'pri' => $priority,
            'target' => $target,
            'ttl' => $ttl,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSoa(string $value, ?int $ttl): ?array
    {
        $parts = preg_split('/\s+/', trim($value));
        if (count($parts) < 7) {
            return null;
        }

        return [
            'type' => 'SOA',
            'mname' => rtrim($parts[0], '.'),
            'rname' => rtrim($parts[1], '.'),
            'serial' => (int) $parts[2],
            'refresh' => (int) $parts[3],
            'retry' => (int) $parts[4],
            'expire' => (int) $parts[5],
            'minimum' => (int) $parts[6],
            'ttl' => $ttl,
        ];
    }
}
