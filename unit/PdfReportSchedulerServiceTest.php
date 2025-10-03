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
use App\Core\Mailer;
use App\Models\PdfReportSchedule;
use App\Services\PdfReportScheduler;
use DateTimeImmutable;
use Throwable;
use function TestHelpers\assertTrue;
use function TestHelpers\assertEquals;
use function TestHelpers\assertCountEquals;

$failures = 0;
$db = DatabaseManager::getInstance();

// Ensure tables and columns exist for schedules.
$db->query("CREATE TABLE IF NOT EXISTS pdf_report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    template_id INTEGER NOT NULL,
    title TEXT,
    frequency TEXT NOT NULL,
    recipients TEXT NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    parameters TEXT,
    enabled INTEGER DEFAULT 1,
    last_run_at DATETIME,
    next_run_at DATETIME,
    last_status VARCHAR(50),
    last_error TEXT,
    last_generation_id INTEGER,
    created_by TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)" );
$db->execute();

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

// Seed template
$sections = json_encode(['summary']);
$db->query('INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES (:name, :description, :type, :sections)');
$db->bind(':name', 'Scheduler Template ' . $timestamp);
$db->bind(':description', 'Covers summary data for scheduler test');
$db->bind(':type', 'unit-test');
$db->bind(':sections', $sections);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$templateId = (int) (($db->single()['id'] ?? 0));
assertTrue($templateId > 0, 'Template should be created for schedule test.', $failures);

// Seed domain and DMARC report data to satisfy analytics queries.
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', 'schedule-' . $timestamp . '.example');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$domainId = (int) (($db->single()['id'] ?? 0));
assertTrue($domainId > 0, 'Domain should be created for schedule test.', $failures);

$rangeBegin = strtotime('-1 day');
$rangeEnd = strtotime('-12 hours');

$db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org, :email, :report_id, :begin, :end, :received)');
$db->bind(':domain_id', $domainId);
$db->bind(':org', 'Scheduler Org');
$db->bind(':email', 'reports@example.com');
$db->bind(':report_id', 'sched-' . $timestamp);
$db->bind(':begin', $rangeBegin);
$db->bind(':end', $rangeEnd);
$db->bind(':received', date('Y-m-d H:i:s', $rangeEnd));
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$aggregateId = (int) (($db->single()['id'] ?? 0));
assertTrue($aggregateId > 0, 'Aggregate report should be created for schedule test.', $failures);

$db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :ip, :count, :disp, :dkim, :spf, :header_from, :env_from, :env_to)');
$db->bind(':report_id', $aggregateId);
$db->bind(':ip', '203.0.113.5');
$db->bind(':count', 15);
$db->bind(':disp', 'none');
$db->bind(':dkim', 'pass');
$db->bind(':spf', 'pass');
$db->bind(':header_from', 'example.com');
$db->bind(':env_from', 'sender@example.com');
$db->bind(':env_to', 'recipient@example.com');
$db->execute();

$scheduleId = PdfReportSchedule::create([
    'name' => 'Unit Schedule ' . $timestamp,
    'template_id' => $templateId,
    'title' => 'Scheduled Report',
    'frequency' => 'daily',
    'recipients' => ['alerts@example.com'],
    'domain_filter' => '',
    'group_filter' => null,
    'parameters' => [],
    'enabled' => 1,
    'next_run_at' => null,
    'created_by' => 'unit-test',
]);
assertTrue($scheduleId > 0, 'Schedule should be created successfully.', $failures);

$capturedEmails = [];
Mailer::setTransportOverride(function (string $to, string $subject, string $body) use (&$capturedEmails): bool {
    $capturedEmails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    return true;
});

$result = PdfReportScheduler::runScheduleNow($scheduleId, new DateTimeImmutable('now'));
assertTrue($result !== null, 'Scheduler should return a result payload.', $failures);
assertTrue($result['success'] ?? false, 'Schedule execution should succeed.', $failures);
assertCountEquals(1, $capturedEmails, 'Exactly one email should be dispatched for the schedule.', $failures);

$db->query('SELECT * FROM pdf_report_generations WHERE schedule_id = :schedule_id ORDER BY id DESC LIMIT 1');
$db->bind(':schedule_id', $scheduleId);
$generationRow = $db->single();

assertTrue(!empty($generationRow), 'Generation row should exist for schedule run.', $failures);
assertEquals($scheduleId, (int) ($generationRow['schedule_id'] ?? 0), 'Generation should be linked to the schedule.', $failures);
assertTrue(!empty($generationRow['file_path']), 'Stored file path should be recorded for scheduled generation.', $failures);

$relativePath = $generationRow['file_path'];
$storagePath = defined('PDF_REPORT_STORAGE_PATH') ? PDF_REPORT_STORAGE_PATH : __DIR__;
$absolutePath = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
if (is_file($absolutePath)) {
    unlink($absolutePath);
}

Mailer::setTransportOverride(null);

// Cleanup inserted rows.
$db->query('DELETE FROM pdf_report_generations WHERE schedule_id = :schedule_id');
$db->bind(':schedule_id', $scheduleId);
$db->execute();

$db->query('DELETE FROM pdf_report_schedules WHERE id = :id');
$db->bind(':id', $scheduleId);
$db->execute();

$db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
$db->bind(':report_id', $aggregateId);
$db->execute();
$db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :id');
$db->bind(':id', $aggregateId);
$db->execute();
$db->query('DELETE FROM domains WHERE id = :id');
$db->bind(':id', $domainId);
$db->execute();
$db->query('DELETE FROM pdf_report_templates WHERE id = :id');
$db->bind(':id', $templateId);
$db->execute();

echo 'PdfReport scheduler service test completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
