<?php

declare(strict_types=1);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use function TestHelpers\assertContains;

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? 'test-token';

$failures = 0;

// Test that a populated username is HTML escaped and rendered.
$_SESSION['username'] = 'Alice <script>alert(1)</script>';
ob_start();
// Mock the DmarcReport::getDashboardSummary to avoid database issues
class_alias('MockDmarcReport', 'App\\Models\\DmarcReport');
class MockDmarcReport {
    public static function getDashboardSummary(int $days = 7): array { return []; }
}
require __DIR__ . '/../root/app/Views/home.php';
$firstRender = ob_get_clean();
assertContains(
    'Welcome, Alice &lt;script&gt;alert(1)&lt;/script&gt;.',
    $firstRender,
    'The dashboard should escape and render the session username.',
    $failures
);

// Test that an empty username falls back to a neutral label.
$_SESSION['username'] = '';
ob_start();
require __DIR__ . '/../root/app/Views/home.php';
$secondRender = ob_get_clean();
assertContains(
    'Welcome, User.',
    $secondRender,
    'The dashboard should fall back to a neutral label when the username is empty.',
    $failures
);

// Test DMARC Dashboard specific content
assertContains(
    'DMARC Dashboard',
    $firstRender,
    'The dashboard should show DMARC Dashboard title.',
    $failures
);

assertContains(
    'Upload',
    $firstRender,
    'The dashboard should show upload button.',
    $failures
);

exit($failures === 0 ? 0 : 1);
