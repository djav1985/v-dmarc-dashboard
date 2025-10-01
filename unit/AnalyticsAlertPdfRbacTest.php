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
use App\Core\RBACManager;
use App\Models\Alert;
use App\Models\Analytics;
use App\Models\PdfReport;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$db = DatabaseManager::getInstance();
$now = time();
$unique = (int) (microtime(true) * 1000000);
$today = date('Y-m-d', $now);

$domainName = 'rbac-validation-' . $unique . '.example';
$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $domainName);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$domainId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
$db->bind(':name', 'RBAC Validation Group ' . $unique);
$db->bind(':description', 'Ensures RBAC filters block unauthorized users');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$groupId = (int) ($db->single()['id'] ?? 0);

$templateSections = json_encode(['summary']);
$db->query('INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES (:name, :description, :type, :sections)');
$db->bind(':name', 'RBAC Template ' . $unique);
$db->bind(':description', 'Validates RBAC filtering for manual reports');
$db->bind(':type', 'integration-test');
$db->bind(':sections', $templateSections);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$templateId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO alert_rules (name, description, rule_type, metric, threshold_value, threshold_operator, time_window, domain_filter, group_filter, severity, notification_channels, notification_recipients, webhook_url, enabled) VALUES (:name, :description, :rule_type, :metric, :threshold_value, :threshold_operator, :time_window, :domain_filter, :group_filter, :severity, :channels, :recipients, :webhook, :enabled)');
$db->bind(':name', 'Unauthorized Domain Rule ' . $unique);
$db->bind(':description', 'Should be hidden from unauthorized users');
$db->bind(':rule_type', 'threshold');
$db->bind(':metric', 'spf_failures');
$db->bind(':threshold_value', 1);
$db->bind(':threshold_operator', '>=');
$db->bind(':time_window', 60);
$db->bind(':domain_filter', $domainName);
$db->bind(':group_filter', null);
$db->bind(':severity', 'high');
$db->bind(':channels', json_encode(['email']));
$db->bind(':recipients', json_encode(['alerts@example.com']));
$db->bind(':webhook', '');
$db->bind(':enabled', 1);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$ruleId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO alert_incidents (rule_id, metric_value, threshold_value, message, details, status, triggered_at) VALUES (:rule_id, :metric_value, :threshold_value, :message, :details, :status, :triggered_at)');
$db->bind(':rule_id', $ruleId);
$db->bind(':metric_value', 5);
$db->bind(':threshold_value', 1);
$db->bind(':message', 'Unauthorized incident test');
$db->bind(':details', json_encode(['test' => true]));
$db->bind(':status', 'open');
$db->bind(':triggered_at', date('Y-m-d H:i:s', $now));
$db->execute();

$_SESSION['username'] = 'rbac-viewer-' . $unique;
$_SESSION['user_role'] = RBACManager::ROLE_VIEWER;

$invalidTrend = Analytics::getTrendData('not-a-date', $today);
assertCountEquals(0, $invalidTrend, 'Invalid analytics dates should yield no trend data.', $failures);

$unauthorizedSummary = Analytics::getSummaryStatistics($today, $today, $domainName);
assertTrue($unauthorizedSummary === [], 'Unauthorized domain filters should block summary analytics.', $failures);

$unauthorizedCompliance = Analytics::getComplianceData($today, $today, '', $groupId);
assertCountEquals(0, $unauthorizedCompliance, 'Unauthorized group filters should return no compliance data.', $failures);

$pdfData = PdfReport::generateReportData($templateId, $today, $today, $domainName);
assertTrue(empty($pdfData), 'PDF generation should be blocked for unauthorized domains.', $failures);

$rules = Alert::getAllRules();
$blockedRules = array_filter($rules, static fn($rule) => ($rule['domain_filter'] ?? '') === $domainName);
assertCountEquals(0, $blockedRules, 'Alert rules should exclude unauthorized domain filters.', $failures);

$incidents = Alert::getOpenIncidents();
$blockedIncidents = array_filter($incidents, static fn($incident) => (int) ($incident['rule_id'] ?? 0) === $ruleId);
assertCountEquals(0, $blockedIncidents, 'Alert incidents should be hidden for unauthorized rules.', $failures);

$exceptionCaught = false;
try {
    Alert::createRule([
        'name' => 'Blocked Rule ' . $unique,
        'description' => 'Should not persist',
        'rule_type' => 'threshold',
        'metric' => 'spf_failures',
        'threshold_value' => 1,
        'threshold_operator' => '>=',
        'time_window' => 60,
        'domain_filter' => $domainName,
        'group_filter' => null,
        'severity' => 'medium',
        'notification_channels' => ['email'],
        'notification_recipients' => ['blocked@example.com'],
        'webhook_url' => '',
        'enabled' => 1,
    ]);
} catch (\RuntimeException $exception) {
    $exceptionCaught = true;
}
assertTrue($exceptionCaught, 'Model should reject unauthorized alert rule creation.', $failures);

echo 'Analytics/Alert/PDF RBAC validation completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
