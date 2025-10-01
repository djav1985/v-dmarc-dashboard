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

echo "Testing Alert webhook SSRF prevention at runtime...\n";

$failures = 0;

/**
 * Simulate the validation that would happen in sendWebhookNotification
 */
function testWebhookNotificationValidation(string $webhookUrl): bool
{
    $urlValidation = UrlValidator::validateWebhookUrl($webhookUrl);
    return $urlValidation['valid'];
}

// Test 1: Valid HTTPS URLs should pass at notification time
$valid = testWebhookNotificationValidation('https://example.com/webhook');
assertTrue($valid, 'Valid HTTPS webhook should pass notification validation', $failures);

// Test 2: Valid HTTP URLs should pass at notification time
$valid = testWebhookNotificationValidation('http://example.com/webhook');
assertTrue($valid, 'Valid HTTP webhook should pass notification validation', $failures);

// Test 3: Empty URLs should pass (no webhook to send)
$valid = testWebhookNotificationValidation('');
assertTrue($valid, 'Empty webhook URL should pass validation', $failures);

// Test 4: file:// URLs should be blocked at notification time
$valid = testWebhookNotificationValidation('file:///etc/passwd');
assertFalse($valid, 'file:// scheme should be blocked at notification time', $failures);

// Test 5: gopher:// URLs should be blocked at notification time
$valid = testWebhookNotificationValidation('gopher://internal-service/');
assertFalse($valid, 'gopher:// scheme should be blocked at notification time', $failures);

// Test 6: localhost URLs should be blocked at notification time
$valid = testWebhookNotificationValidation('http://localhost/webhook');
assertFalse($valid, 'localhost webhook should be blocked at notification time', $failures);

// Test 7: 127.0.0.1 URLs should be blocked at notification time
$valid = testWebhookNotificationValidation('http://127.0.0.1/webhook');
assertFalse($valid, '127.0.0.1 webhook should be blocked at notification time', $failures);

// Test 8: Private IP URLs should be blocked at notification time
$valid = testWebhookNotificationValidation('http://192.168.1.1/webhook');
assertFalse($valid, 'Private IP webhook should be blocked at notification time', $failures);

// Test 9: Cloud metadata service IPs should be blocked
$valid = testWebhookNotificationValidation('http://169.254.169.254/latest/meta-data/');
assertFalse($valid, 'Cloud metadata service IP should be blocked at notification time', $failures);

// Test 10: IPv6 localhost should be blocked
$valid = testWebhookNotificationValidation('http://[::1]/webhook');
assertFalse($valid, 'IPv6 localhost should be blocked at notification time', $failures);

if ($failures === 0) {
    echo "All runtime webhook SSRF prevention tests passed!\n";
    exit(0);
} else {
    echo "\n$failures test(s) failed.\n";
    exit(1);
}
