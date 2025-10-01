<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Utilities\UrlValidator;
use function TestHelpers\assertTrue;
use function TestHelpers\assertFalse;
use function TestHelpers\assertEquals;

echo "Testing webhook URL validation to prevent SSRF attacks...\n";

$failures = 0;

// Test 1: Valid HTTPS URLs should pass
$result = UrlValidator::validateWebhookUrl('https://example.com/webhook');
assertTrue($result['valid'], 'Valid HTTPS URL should be accepted', $failures);
assertEquals(null, $result['error'], 'Valid HTTPS URL should have no error', $failures);

// Test 2: Valid HTTP URLs should pass
$result = UrlValidator::validateWebhookUrl('http://example.com/webhook');
assertTrue($result['valid'], 'Valid HTTP URL should be accepted', $failures);
assertEquals(null, $result['error'], 'Valid HTTP URL should have no error', $failures);

// Test 3: Empty URLs should pass (webhooks are optional)
$result = UrlValidator::validateWebhookUrl('');
assertTrue($result['valid'], 'Empty URL should be accepted', $failures);
assertEquals(null, $result['error'], 'Empty URL should have no error', $failures);

// Test 4: file:// scheme should be blocked
$result = UrlValidator::validateWebhookUrl('file:///etc/passwd');
assertFalse($result['valid'], 'file:// scheme should be rejected', $failures);
assertTrue(str_contains($result['error'] ?? '', 'HTTP') || str_contains($result['error'] ?? '', 'scheme'), 'file:// rejection should mention allowed schemes', $failures);

// Test 5: gopher:// scheme should be blocked
$result = UrlValidator::validateWebhookUrl('gopher://internal-service/');
assertFalse($result['valid'], 'gopher:// scheme should be rejected', $failures);
assertTrue(str_contains($result['error'] ?? '', 'HTTP') || str_contains($result['error'] ?? '', 'scheme'), 'gopher:// rejection should mention allowed schemes', $failures);

// Test 6: ftp:// scheme should be blocked
$result = UrlValidator::validateWebhookUrl('ftp://internal-server/');
assertFalse($result['valid'], 'ftp:// scheme should be rejected', $failures);

// Test 7: localhost should be blocked
$result = UrlValidator::validateWebhookUrl('http://localhost/webhook');
assertFalse($result['valid'], 'localhost should be rejected', $failures);
assertTrue(str_contains($result['error'] ?? '', 'Internal'), 'localhost rejection should mention internal addresses', $failures);

// Test 8: 127.0.0.1 should be blocked
$result = UrlValidator::validateWebhookUrl('http://127.0.0.1/webhook');
assertFalse($result['valid'], '127.0.0.1 should be rejected', $failures);

// Test 9: ::1 (IPv6 localhost) should be blocked
$result = UrlValidator::validateWebhookUrl('http://[::1]/webhook');
assertFalse($result['valid'], '::1 should be rejected', $failures);

// Test 10: 0.0.0.0 should be blocked
$result = UrlValidator::validateWebhookUrl('http://0.0.0.0/webhook');
assertFalse($result['valid'], '0.0.0.0 should be rejected', $failures);

// Test 11: Private IP (10.0.0.0/8) should be blocked
$result = UrlValidator::validateWebhookUrl('http://10.0.0.1/webhook');
assertFalse($result['valid'], 'Private IP 10.0.0.1 should be rejected', $failures);

// Test 12: Private IP (172.16.0.0/12) should be blocked
$result = UrlValidator::validateWebhookUrl('http://172.16.0.1/webhook');
assertFalse($result['valid'], 'Private IP 172.16.0.1 should be rejected', $failures);

// Test 13: Private IP (192.168.0.0/16) should be blocked
$result = UrlValidator::validateWebhookUrl('http://192.168.1.1/webhook');
assertFalse($result['valid'], 'Private IP 192.168.1.1 should be rejected', $failures);

// Test 14: Link-local address (169.254.0.0/16) should be blocked
$result = UrlValidator::validateWebhookUrl('http://169.254.169.254/webhook');
assertFalse($result['valid'], 'Link-local IP 169.254.169.254 should be rejected', $failures);

// Test 15: .local domain should be blocked
$result = UrlValidator::validateWebhookUrl('http://server.local/webhook');
assertFalse($result['valid'], '.local domain should be rejected', $failures);

// Test 16: localhost.localdomain should be blocked
$result = UrlValidator::validateWebhookUrl('http://localhost.localdomain/webhook');
assertFalse($result['valid'], 'localhost.localdomain should be rejected', $failures);

// Test 17: Invalid URL format should be rejected
$result = UrlValidator::validateWebhookUrl('not-a-url');
assertFalse($result['valid'], 'Invalid URL format should be rejected', $failures);

// Test 18: URL without scheme should be rejected
$result = UrlValidator::validateWebhookUrl('example.com/webhook');
assertFalse($result['valid'], 'URL without scheme should be rejected', $failures);

// Test 19: URL with port should work for valid URLs
$result = UrlValidator::validateWebhookUrl('https://example.com:8443/webhook');
assertTrue($result['valid'], 'Valid HTTPS URL with port should be accepted', $failures);

// Test 20: URL with path and query string should work
$result = UrlValidator::validateWebhookUrl('https://example.com/webhook?token=abc123');
assertTrue($result['valid'], 'Valid URL with query string should be accepted', $failures);

// Test 21: Uppercase HTTP should work
$result = UrlValidator::validateWebhookUrl('HTTP://example.com/webhook');
assertTrue($result['valid'], 'Uppercase HTTP scheme should be accepted', $failures);

// Test 22: Mixed case HTTPS should work
$result = UrlValidator::validateWebhookUrl('HtTpS://example.com/webhook');
assertTrue($result['valid'], 'Mixed case HTTPS scheme should be accepted', $failures);

if ($failures === 0) {
    echo "All URL validation tests passed!\n";
    exit(0);
} else {
    echo "\n$failures test(s) failed.\n";
    exit(1);
}
