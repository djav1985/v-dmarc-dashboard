<?php
// phpcs:ignoreFile

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';

use App\Utilities\DnsOverHttpsClient;

$failures = 0;

function assertPredicate(bool $condition, string $message, int &$failures): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

function testTxtParsing(): string
{
    DnsOverHttpsClient::clearCache();
    $calls = 0;

    $client = new DnsOverHttpsClient(function (string $url, array $headers) use (&$calls) {
        $calls++;
        return [
            'Answer' => [
                ['data' => '"v=DMARC1; p=none"', 'TTL' => 600],
            ],
        ];
    });

    $records = $client->query('_dmarc.example.com', 'TXT');

    if ($records === null || $records[0]['txt'] !== 'v=DMARC1; p=none') {
        return 'Failed to normalise TXT record data from DoH response';
    }

    if ($records[0]['ttl'] !== 600) {
        return 'TXT record TTL was not propagated from DoH response';
    }

    if ($calls !== 1) {
        return 'Unexpected number of DoH fetches for TXT parsing test';
    }

    return '';
}

function testCaching(): string
{
    DnsOverHttpsClient::clearCache();
    $calls = 0;

    $client = new DnsOverHttpsClient(function (string $url, array $headers) use (&$calls) {
        $calls++;
        return [
            'Answer' => [
                ['data' => '127.0.0.2', 'TTL' => 300],
            ],
        ];
    });

    $first = $client->query('4.3.2.1.zen.spamhaus.org', 'A');
    $second = $client->query('4.3.2.1.zen.spamhaus.org', 'A');

    if ($first === null || $second === null) {
        return 'Caching test received null DoH result';
    }

    if ($calls !== 1) {
        return 'DoH client did not cache responses by name/type TTL';
    }

    return '';
}

$error = testTxtParsing();
assertPredicate($error === '', $error ?: 'TXT parsing', $failures);

$error = testCaching();
assertPredicate($error === '', $error ?: 'DoH caching', $failures);

echo 'DnsOverHttpsClient tests completed with ' . ($failures === 0 ? 'no failures' : "{$failures} failure(s)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
