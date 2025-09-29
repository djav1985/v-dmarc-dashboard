<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? 'test-token';

/**
 * Simple assertion helper that records failures and reports a helpful message.
 */
function assertContains(string $needle, string $haystack, string $message, int &$failures): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        $failures++;
    }
}

$failures = 0;

// Test that a populated username is HTML escaped and rendered.
$_SESSION['username'] = 'Alice <script>alert(1)</script>';
ob_start();
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

// Test that a null username also falls back to the neutral label.
$_SESSION['username'] = null;
ob_start();
require __DIR__ . '/../root/app/Views/home.php';
$thirdRender = ob_get_clean();
assertContains(
    'Welcome, User.',
    $thirdRender,
    'The dashboard should fall back to a neutral label when the username is null.',
    $failures
);

exit($failures === 0 ? 0 : 1);
