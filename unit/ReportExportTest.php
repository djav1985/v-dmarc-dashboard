<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Utilities\ReportExport;
use function TestHelpers\assertTrue;

$report = [
    'domain' => 'example.com',
    'ownership_contact' => 'Security Team',
    'enforcement_level' => 'reject',
    'org_name' => 'Example Org',
    'email' => 'reports@example.com',
    'report_id' => 'test-report-123',
    'date_range_begin' => strtotime('-2 days'),
    'date_range_end' => strtotime('-1 day'),
    'received_at' => date('Y-m-d H:i:s'),
    'total_records' => 5,
    'total_volume' => 75,
    'rejected_count' => 25,
    'quarantined_count' => 10,
    'passed_count' => 40,
    'dkim_pass_count' => 35,
    'spf_pass_count' => 60,
    'failure_volume' => 35,
];

$failures = 0;

$csv = ReportExport::buildCsv([$report]);
assertTrue(str_contains($csv, 'Domain,Ownership Contact'), 'CSV export should include headers.', $failures);
assertTrue(str_contains($csv, 'example.com'), 'CSV export should include data rows.', $failures);

$xlsx = ReportExport::buildXlsx([$report]);
assertTrue(strlen($xlsx) > 0, 'XLSX export should produce binary content.', $failures);

$tempFile = tempnam(sys_get_temp_dir(), 'xlsx-test');
file_put_contents($tempFile, $xlsx);

$zip = new ZipArchive();
assertTrue($zip->open($tempFile) === true, 'Generated XLSX should be a valid zip archive.', $failures);

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
assertTrue($sheetXml !== false, 'Worksheet XML should exist inside the XLSX archive.', $failures);
assertTrue(str_contains((string) $sheetXml, 'example.com'), 'Worksheet should include the exported domain.', $failures);

$zip->close();
unlink($tempFile);

echo "Report export tests completed.\n";
exit($failures === 0 ? 0 : 1);
