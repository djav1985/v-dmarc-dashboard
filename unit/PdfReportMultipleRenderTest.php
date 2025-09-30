<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Services\PdfReportService;
use function TestHelpers\assertTrue;

$failures = 0;

// Prepare minimal report data for two PDFs
$reportData1 = [
    'period' => ['start' => '2024-01-01', 'end' => '2024-01-31'],
    'filters' => [],
    'sections' => [
        'summary' => [
            'domain_count' => 5,
            'report_count' => 100,
            'total_volume' => 1000,
            'pass_rate' => 95.5,
        ],
    ],
    'template' => ['name' => 'Test Template 1'],
];

$reportData2 = [
    'period' => ['start' => '2024-02-01', 'end' => '2024-02-28'],
    'filters' => [],
    'sections' => [
        'summary' => [
            'domain_count' => 3,
            'report_count' => 50,
            'total_volume' => 500,
            'pass_rate' => 92.3,
        ],
    ],
    'template' => ['name' => 'Test Template 2'],
];

try {
    // First PDF generation - should work
    $html1 = PdfReportService::renderHtml($reportData1, 'Report 1');
    assertTrue(!empty($html1), 'First PDF HTML should be generated successfully.', $failures);
    assertTrue(str_contains($html1, 'Report 1'), 'First PDF should contain the report title.', $failures);
    
    // Second PDF generation - this should NOT throw "Cannot redeclare function" error
    $html2 = PdfReportService::renderHtml($reportData2, 'Report 2');
    assertTrue(!empty($html2), 'Second PDF HTML should be generated successfully.', $failures);
    assertTrue(str_contains($html2, 'Report 2'), 'Second PDF should contain the report title.', $failures);
    
    // Third PDF generation - verify it still works
    $html3 = PdfReportService::renderHtml($reportData1, 'Report 3');
    assertTrue(!empty($html3), 'Third PDF HTML should be generated successfully.', $failures);
    assertTrue(str_contains($html3, 'Report 3'), 'Third PDF should contain the report title.', $failures);
    
    echo 'Multiple PDF render test completed successfully with no function redeclaration errors.' . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    $failures++;
}

echo 'PdfReport multiple render test completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
