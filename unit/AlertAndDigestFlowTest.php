<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Controllers\AlertController;
use App\Controllers\ReportsController;
use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\Mailer;
use App\Helpers\MessageHelper;
use App\Models\Alert;
use App\Models\EmailDigest;
use App\Services\AlertService;
use App\Services\EmailDigestService;
use function TestHelpers\assertContains;
use function TestHelpers\assertPredicate;
use function TestHelpers\assertTrue;

$failures = 0;

function resetSessionState(): void
{
    $_SESSION['logged_in'] = true;
    $_SESSION['user_role'] = RBACManager::ROLE_APP_ADMIN;
    $_SESSION['username'] = 'test-user';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(8));
    $_SESSION['messages'] = [];
}

function insertDomain(string $domainName): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', $domainName);
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function insertGroup(string $name): int
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', $name);
    $db->bind(':description', 'Automated test group');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function insertDmarcReport(int $domainId, string $identifier): int
{
    $now = time();
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :start, :end, :received)');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Test Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $identifier);
    $db->bind(':start', $now - 3600);
    $db->bind(':end', $now);
    $db->bind(':received', date('Y-m-d H:i:s', $now));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $result = $db->single();
    return (int) ($result['id'] ?? 0);
}

function insertDmarcRecord(int $reportId, string $sourceIp, int $count, string $disposition, string $spf = 'pass', string $dkim = 'pass'): void
{
    $db = DatabaseManager::getInstance();
    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $reportId);
    $db->bind(':source_ip', $sourceIp);
    $db->bind(':count', $count);
    $db->bind(':disposition', $disposition);
    $db->bind(':dkim', $dkim);
    $db->bind(':spf', $spf);
    $db->bind(':header_from', 'example.com');
    $db->bind(':envelope_from', 'noreply@example.com');
    $db->bind(':envelope_to', 'postmaster@example.com');
    $db->execute();
}

function captureOutput(callable $callback): string
{
    ob_start();
    $callback();
    return (string) ob_get_clean();
}

resetSessionState();

// Prepare supporting domain/group data.
$timestamp = time();
$domainName = 'alerts-' . $timestamp . '.example';
$domainId = insertDomain($domainName);
$groupId = insertGroup('Alert Group ' . $timestamp);

$db = DatabaseManager::getInstance();
$db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
$db->bind(':domain_id', $domainId);
$db->bind(':group_id', $groupId);
$db->execute();

// Seed alert rule and incident data.
$ruleId = Alert::createRule([
    'name' => 'High SPF Failures',
    'description' => 'Trigger when SPF failures exceed one.',
    'rule_type' => 'threshold',
    'metric' => 'spf_failures',
    'threshold_value' => 1,
    'threshold_operator' => '>=',
    'time_window' => 60,
    'domain_filter' => $domainName,
    'group_filter' => $groupId,
    'severity' => 'high',
    'notification_channels' => ['email'],
    'notification_recipients' => ['alerts@example.com'],
    'webhook_url' => '',
    'enabled' => 1,
]);

$db->query('INSERT INTO alert_incidents (rule_id, metric_value, threshold_value, message) VALUES (:rule_id, :metric_value, :threshold_value, :message)');
$db->bind(':rule_id', $ruleId);
$db->bind(':metric_value', 5);
$db->bind(':threshold_value', 1);
$db->bind(':message', 'Test incident message');
$db->execute();

$alertController = new AlertController();

// Validate rules page renders expected controls.
$_GET['action'] = 'rules';
$outputRules = captureOutput(static fn() => $alertController->handleRequest());
assertContains('Create Rule', $outputRules, 'Alert rules page should include creation button.', $failures);
assertContains('High SPF Failures', $outputRules, 'Alert rules page should render seeded rule.', $failures);

// Validate incidents page includes acknowledge control.
$_GET['action'] = 'incidents';
$outputIncidents = captureOutput(static fn() => $alertController->handleRequest());
assertContains('Acknowledge', $outputIncidents, 'Incident view should include acknowledge action.', $failures);

// Validate create form renders domain/group selectors.
$_GET['action'] = 'create-rule';
$outputCreate = captureOutput(static fn() => $alertController->handleRequest());
assertContains('name="notification_channels[]"', $outputCreate, 'Create rule form should include notification channel checkboxes.', $failures);
assertContains('Domain Group', $outputCreate, 'Create rule form should provide group selection.', $failures);

// Prepare DMARC data to trigger alert rule.
$reportId = insertDmarcReport($domainId, 'report-' . $timestamp);
insertDmarcRecord($reportId, '203.0.113.10', 5, 'reject', 'fail', 'fail');

$sentEmails = [];
Mailer::setTransportOverride(static function (string $to, string $subject) use (&$sentEmails): bool {
    $sentEmails[] = ['to' => $to, 'subject' => $subject];
    return true;
});

$alertResults = AlertService::runAlertChecks();

assertPredicate(!empty($alertResults), 'Alert service should detect a triggered incident.', $failures);
assertTrue(!empty($sentEmails), 'Alert notifications should be attempted for triggered incidents.', $failures);

$db->query('SELECT COUNT(*) as total FROM alert_incidents');
$incidentCount = $db->single();
assertTrue(($incidentCount['total'] ?? 0) > 0, 'Incident table should contain entries after running checks.', $failures);

// Simulate a non-UTC environment to ensure alert metrics honour the configured timezone.
$originalTimezone = date_default_timezone_get();
date_default_timezone_set('America/New_York');

$tzTimestamp = time();
$tzDomainName = 'alerts-tz-' . $tzTimestamp . '.example';
$tzDomainId = insertDomain($tzDomainName);
$tzGroupId = insertGroup('Alert TZ Group ' . $tzTimestamp);

$db->query('INSERT INTO domain_group_assignments (domain_id, group_id) VALUES (:domain_id, :group_id)');
$db->bind(':domain_id', $tzDomainId);
$db->bind(':group_id', $tzGroupId);
$db->execute();

$tzRuleId = Alert::createRule([
    'name' => 'Timezone SPF Failures',
    'description' => 'Trigger when SPF failures exceed one in a non-UTC timezone.',
    'rule_type' => 'threshold',
    'metric' => 'spf_failures',
    'threshold_value' => 1,
    'threshold_operator' => '>=',
    'time_window' => 60,
    'domain_filter' => $tzDomainName,
    'group_filter' => $tzGroupId,
    'severity' => 'high',
    'notification_channels' => ['email'],
    'notification_recipients' => ['alerts@example.com'],
    'webhook_url' => '',
    'enabled' => 1,
]);

$tzReportId = insertDmarcReport($tzDomainId, 'report-tz-' . $tzTimestamp);
insertDmarcRecord($tzReportId, '198.51.100.42', 3, 'reject', 'fail', 'fail');

$timezoneIncidents = Alert::checkAlertRules();
$matchingIncident = array_filter($timezoneIncidents, static function (array $incident) use ($tzRuleId): bool {
    return isset($incident['rule']['id']) && (int) $incident['rule']['id'] === $tzRuleId;
});

assertPredicate(!empty($matchingIncident), 'Alert::checkAlertRules() should trigger in non-UTC timezone environments.', $failures);

date_default_timezone_set($originalTimezone);

// Validate digest scheduling and execution.
$sentEmails = [];
Mailer::setTransportOverride(static function (string $to, string $subject) use (&$sentEmails): bool {
    $sentEmails[] = ['to' => $to, 'subject' => $subject];
    return true;
});

$scheduleId = EmailDigest::createSchedule([
    'name' => 'Weekly Digest Test',
    'frequency' => 'weekly',
    'recipients' => ['digest@example.com'],
    'domain_filter' => $domainName,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', time() - 60),
]);

$digestResults = EmailDigestService::processDueDigests();
assertPredicate(!empty($digestResults), 'Digest service should process due schedules.', $failures);
assertTrue(!empty($sentEmails), 'Digest processing should send email notifications.', $failures);

$db->query('SELECT COUNT(*) as total FROM email_digest_logs WHERE schedule_id = :schedule_id');
$db->bind(':schedule_id', $scheduleId);
$logCount = $db->single();
assertTrue(($logCount['total'] ?? 0) > 0, 'Digest logs should record send attempts.', $failures);

$db->query('SELECT last_sent, next_scheduled FROM email_digest_schedules WHERE id = :schedule_id');
$db->bind(':schedule_id', $scheduleId);
$successSchedule = $db->single();
assertTrue(is_array($successSchedule), 'Successful digest run should persist schedule timing fields.', $failures);
if (is_array($successSchedule)) {
    assertPredicate(!empty($successSchedule['last_sent']), 'Successful digest should stamp last_sent.', $failures);
    assertPredicate(!empty($successSchedule['next_scheduled']), 'Successful digest should compute next_scheduled.', $failures);
}

$failureScheduleId = EmailDigest::createSchedule([
    'name' => 'Weekly Digest Failure ' . $timestamp,
    'frequency' => 'weekly',
    'recipients' => ['digest-failure@example.com'],
    'domain_filter' => $domainName,
    'group_filter' => null,
    'enabled' => 1,
    'next_scheduled' => date('Y-m-d H:i:s', time() - 60),
]);

$previousLastSent = date('Y-m-d H:i:s', time() - 86400);
$db->query('UPDATE email_digest_schedules SET last_sent = :last_sent WHERE id = :id');
$db->bind(':last_sent', $previousLastSent);
$db->bind(':id', $failureScheduleId);
$db->execute();

$failedAttempts = [];
Mailer::setTransportOverride(static function (string $to, string $subject) use (&$failedAttempts): bool {
    $failedAttempts[] = ['to' => $to, 'subject' => $subject];
    return false;
});

$failureResults = EmailDigestService::processDueDigests();
assertPredicate(!empty($failureResults), 'Digest service should report failed attempts.', $failures);

$failureResult = null;
foreach ($failureResults as $result) {
    if ((int) ($result['schedule_id'] ?? 0) === (int) $failureScheduleId) {
        $failureResult = $result;
        break;
    }
}

assertTrue(is_array($failureResult) && $failureResult['success'] === false, 'Failed sends should be marked unsuccessful.', $failures);
assertTrue(!empty($failedAttempts), 'Failed digest sends should still attempt delivery.', $failures);

$db->query('SELECT last_sent, next_scheduled FROM email_digest_schedules WHERE id = :schedule_id');
$db->bind(':schedule_id', $failureScheduleId);
$failureSchedule = $db->single();
assertTrue(is_array($failureSchedule), 'Failed digest run should persist schedule row.', $failures);
if (is_array($failureSchedule)) {
    assertEquals($previousLastSent, $failureSchedule['last_sent'] ?? null, 'Failed digest should preserve last_sent.', $failures);
    $scheduledTime = $failureSchedule['next_scheduled'] ?? null;
    assertPredicate(!empty($scheduledTime), 'Failed digest should compute a retry time.', $failures);
    if (!empty($scheduledTime)) {
        assertTrue(strtotime($scheduledTime) >= time(), 'Retry time should not be set in the past.', $failures);
    }
}

Mailer::setTransportOverride(null);

// Exercise report email action.
$_POST = [
    'action' => 'send_report_email',
    'report_id' => $reportId,
    'recipients' => 'reports@example.com'
];

$sentEmails = [];
Mailer::setTransportOverride(static function (string $to, string $subject) use (&$sentEmails): bool {
    $sentEmails[] = ['to' => $to, 'subject' => $subject];
    return true;
});

$reportsController = new ReportsController();
$reportsController->handleSubmission();

assertTrue(!empty($sentEmails), 'Report controller should dispatch an email when requested.', $failures);
assertTrue(!empty($_SESSION['messages']), 'Report email action should add a feedback message.', $failures);

Mailer::setTransportOverride(null);

echo 'Alert & digest flow tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
