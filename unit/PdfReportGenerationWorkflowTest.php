<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Models\PdfReport;
use App\Services\PdfReportService;
use function TestHelpers\assertTrue;
use function TestHelpers\assertEquals;

$failures = 0;
$db = DatabaseManager::getInstance();

// Ensure optional columns exist for older demo databases.
try {
    $db->query("ALTER TABLE pdf_report_generations ADD COLUMN file_path TEXT");
    $db->execute();
} catch (Throwable $exception) {
    // Column already exists.
}

try {
    $db->query("ALTER TABLE pdf_report_generations ADD COLUMN schedule_id INTEGER");
    $db->execute();
} catch (Throwable $exception) {
    // Column already exists.
}

$timestamp = time();
$templateSections = json_encode(['summary']);

$db->query('INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES (:name, :description, :type, :sections)');
$db->bind(':name', 'Unit Test Template ' . $timestamp);
$db->bind(':description', 'Validates Dompdf generation workflow');
$db->bind(':type', 'unit-test');
$db->bind(':sections', $templateSections);
$db->execute();

$db->query('SELECT last_insert_rowid() as id');
$templateRow = $db->single();
$templateId = (int) ($templateRow['id'] ?? 0);

assertTrue($templateId > 0, 'Template should be created for PDF workflow test.', $failures);

$startDate = date('Y-m-d', strtotime('-2 days'));
$endDate = date('Y-m-d');

$reportData = PdfReport::generateReportData($templateId, $startDate, $endDate);
assertTrue(!empty($reportData), 'Report data should be generated for the template.', $failures);

$tempDir = sys_get_temp_dir() . '/pdf-report-' . $timestamp;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

$generation = PdfReportService::generatePdf(
    $reportData,
    'Unit Test Report',
    [
        'output_directory' => $tempDir,
        'prefix' => 'unit',
    ]
);

$expectedPath = $generation['path'] ?? '';
assertTrue(is_file($expectedPath), 'Generated PDF file should exist on disk.', $failures);
assertTrue(($generation['size'] ?? 0) > 0, 'Generated PDF should have non-zero size.', $failures);

$logId = PdfReport::logGeneration([
    'template_id' => $templateId,
    'filename' => $generation['filename'],
    'file_path' => $generation['relative_path'],
    'title' => 'Unit Test Report',
    'date_range_start' => $startDate,
    'date_range_end' => $endDate,
    'domain_filter' => '',
    'group_filter' => null,
    'parameters' => ['test' => true],
    'file_size' => $generation['size'],
    'generated_by' => 'unit-test',
    'schedule_id' => null,
]);

assertTrue($logId > 0, 'Log entry should be created for generated PDF.', $failures);

$db->query('SELECT * FROM pdf_report_generations WHERE id = :id');
$db->bind(':id', $logId);
$logRow = $db->single();

assertEquals($generation['filename'], $logRow['filename'] ?? '', 'Stored filename should match generated file.', $failures);
assertEquals($generation['relative_path'], $logRow['file_path'] ?? '', 'Stored file path should match relative path.', $failures);
assertTrue(($logRow['schedule_id'] ?? null) === null, 'Manual generation should not be tied to a schedule.', $failures);

if (is_file($expectedPath)) {
    unlink($expectedPath);
}
if (is_dir($tempDir)) {
    rmdir($tempDir);
}

$db->query('DELETE FROM pdf_report_generations WHERE id = :id');
$db->bind(':id', $logId);
$db->execute();

$db->query('DELETE FROM pdf_report_templates WHERE id = :id');
$db->bind(':id', $templateId);
$db->execute();

echo 'PdfReport generation workflow test completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
