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

use App\Core\DatabaseManager;
use App\Models\PdfReportSchedule;
use function TestHelpers\assertFalse;
use function TestHelpers\assertTrue;

$failures = 0;

$db = DatabaseManager::getInstance();
$timestamp = time();

// Ensure required table exists for SQLite demo databases that predate schedule support
$db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS pdf_report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    template_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    frequency TEXT NOT NULL,
    recipients TEXT NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    parameters TEXT DEFAULT '{}',
    enabled INTEGER DEFAULT 1,
    next_run_at DATETIME,
    last_run_at DATETIME,
    last_status TEXT,
    last_error TEXT,
    created_by TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_generation_id INTEGER,
    FOREIGN KEY (template_id) REFERENCES pdf_report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (last_generation_id) REFERENCES pdf_report_generations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(username) ON DELETE SET NULL
);
SQL);
$db->execute();

$db->query('PRAGMA table_info(pdf_report_generations)');
$generationColumns = $db->resultSet();
$generationColumnNames = array_filter(array_map(
    static fn(array $column) => strtolower((string) ($column['name'] ?? '')),
    $generationColumns
));

if (!in_array('file_path', $generationColumnNames, true)) {
    $db->query('ALTER TABLE pdf_report_generations ADD COLUMN file_path TEXT');
    $db->execute();
}

// Seed template required for schedules
$db->query('INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES (:name, :description, :type, :sections)');
$db->bind(':name', 'Access Template ' . $timestamp);
$db->bind(':description', 'Template for RBAC tests');
$db->bind(':type', 'unit-test');
$db->bind(':sections', json_encode(['summary']));
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$templateId = (int) ($db->single()['id'] ?? 0);

// Create domains and assignments
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', 'schedule-accessible-' . $timestamp . '.example');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleDomainId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', 'schedule-restricted-' . $timestamp . '.example');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedDomainId = (int) ($db->single()['id'] ?? 0);

// Accessible group setup
$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'Accessible Group ' . $timestamp);
$db->bind(':description', 'Group for schedule access test');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleGroupId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
$db->bind(':domain_id', $accessibleDomainId);
$db->bind(':group_id', $accessibleGroupId);
$db->execute();

// Restricted group setup
$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'Restricted Group ' . $timestamp);
$db->bind(':description', 'Group outside viewer scope');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedGroupId = (int) ($db->single()['id'] ?? 0);

$_SESSION['username'] = 'schedule-viewer-' . $timestamp;
$_SESSION['user_role'] = 'viewer';

$db->query('INSERT INTO user_domain_assignments (user_id, domain_id) VALUES (:user, :domain)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':domain', $accessibleDomainId);
$db->execute();

$db->query('INSERT INTO user_group_assignments (user_id, group_id) VALUES (:user, :group)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':group', $accessibleGroupId);
$db->execute();

// Schedule created by viewer
$viewerScheduleId = PdfReportSchedule::create([
    'name' => 'Viewer Owned ' . $timestamp,
    'template_id' => $templateId,
    'title' => 'Viewer Schedule',
    'frequency' => 'weekly',
    'recipients' => ['viewer@example.com'],
    'domain_filter' => '',
    'group_filter' => null,
    'enabled' => 1,
    'next_run_at' => null,
    'created_by' => $_SESSION['username'],
]);

assertTrue($viewerScheduleId > 0, 'Viewer should be able to create their own schedule.', $failures);

// Schedule targeting accessible domain but created by another user
$db->query('INSERT INTO pdf_report_schedules (name, template_id, title, frequency, recipients, domain_filter, group_filter, parameters, enabled, next_run_at, created_by) VALUES (:name, :template_id, :title, :frequency, :recipients, :domain_filter, :group_filter, :parameters, 1, NULL, :created_by)');
$db->bind(':name', 'Admin Accessible ' . $timestamp);
$db->bind(':template_id', $templateId);
$db->bind(':title', 'Admin Schedule');
$db->bind(':frequency', 'weekly');
$db->bind(':recipients', json_encode(['admin@example.com']));
$db->bind(':domain_filter', 'schedule-accessible-' . $timestamp . '.example');
$db->bind(':group_filter', null);
$db->bind(':parameters', json_encode([]));
$db->bind(':created_by', 'admin-user');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$adminScheduleId = (int) ($db->single()['id'] ?? 0);

// Schedule referencing restricted domain
$db->query('INSERT INTO pdf_report_schedules (name, template_id, title, frequency, recipients, domain_filter, group_filter, parameters, enabled, next_run_at, created_by) VALUES (:name, :template_id, :title, :frequency, :recipients, :domain_filter, :group_filter, :parameters, 1, NULL, :created_by)');
$db->bind(':name', 'Restricted Domain ' . $timestamp);
$db->bind(':template_id', $templateId);
$db->bind(':title', 'Restricted Domain');
$db->bind(':frequency', 'weekly');
$db->bind(':recipients', json_encode(['restricted@example.com']));
$db->bind(':domain_filter', 'schedule-restricted-' . $timestamp . '.example');
$db->bind(':group_filter', null);
$db->bind(':parameters', json_encode([]));
$db->bind(':created_by', 'admin-user');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedDomainScheduleId = (int) ($db->single()['id'] ?? 0);

// Schedule referencing restricted group
$db->query('INSERT INTO pdf_report_schedules (name, template_id, title, frequency, recipients, domain_filter, group_filter, parameters, enabled, next_run_at, created_by) VALUES (:name, :template_id, :title, :frequency, :recipients, :domain_filter, :group_filter, :parameters, 1, NULL, :created_by)');
$db->bind(':name', 'Restricted Group ' . $timestamp);
$db->bind(':template_id', $templateId);
$db->bind(':title', 'Restricted Group');
$db->bind(':frequency', 'weekly');
$db->bind(':recipients', json_encode(['restricted-group@example.com']));
$db->bind(':domain_filter', '');
$db->bind(':group_filter', $restrictedGroupId);
$db->bind(':parameters', json_encode([]));
$db->bind(':created_by', 'admin-user');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedGroupScheduleId = (int) ($db->single()['id'] ?? 0);

$schedules = PdfReportSchedule::getAllSchedules();
$retrievedIds = array_map(static fn(array $row) => (int) ($row['id'] ?? 0), $schedules);

assertTrue(in_array($viewerScheduleId, $retrievedIds, true), 'Viewer should see their own schedules.', $failures);
assertTrue(in_array($adminScheduleId, $retrievedIds, true), 'Viewer should see schedules for domains they can access.', $failures);
assertFalse(in_array($restrictedDomainScheduleId, $retrievedIds, true), 'Schedules for unauthorized domains must be hidden.', $failures);
assertFalse(in_array($restrictedGroupScheduleId, $retrievedIds, true), 'Schedules for unauthorized groups must be hidden.', $failures);

$restrictedLookup = PdfReportSchedule::find($restrictedDomainScheduleId);
assertTrue($restrictedLookup === null, 'Unauthorized schedules should not be retrievable.', $failures);

$accessibleLookup = PdfReportSchedule::find($viewerScheduleId);
assertTrue($accessibleLookup !== null, 'Authorized schedules should still be retrievable.', $failures);

assertTrue(PdfReportSchedule::setEnabled($viewerScheduleId, false), 'Authorized schedules should toggle successfully.', $failures);
assertFalse(PdfReportSchedule::setEnabled($restrictedDomainScheduleId, true), 'Unauthorized schedules must not toggle.', $failures);

assertFalse(PdfReportSchedule::delete($restrictedDomainScheduleId), 'Unauthorized schedules must not be deleted.', $failures);
assertTrue(PdfReportSchedule::delete($viewerScheduleId), 'Authorized schedules should be deletable.', $failures);

echo 'PdfReport schedule RBAC tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
