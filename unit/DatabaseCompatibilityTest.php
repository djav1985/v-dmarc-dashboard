<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Models\Analytics;
use App\Models\EmailDigest;
use App\Models\Users;
use App\Services\ImapIngestionService;
use function TestHelpers\assertContains;
use function TestHelpers\assertEquals;
use function TestHelpers\assertFalse;
use function TestHelpers\assertTrue;

function compat_insert_report(DatabaseManager $db, int $domainId, string $reportIdentifier, int $begin, int $end): int
{
    $db->query('INSERT INTO dmarc_aggregate_reports (
        domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at
    ) VALUES (
        :domain_id, :org_name, :email, :report_id, :begin, :end, :received
    )');
    $db->bind(':domain_id', $domainId);
    $db->bind(':org_name', 'Compat Org');
    $db->bind(':email', 'reports@example.com');
    $db->bind(':report_id', $reportIdentifier);
    $db->bind(':begin', $begin);
    $db->bind(':end', $end);
    $db->bind(':received', date('Y-m-d H:i:s', $end));
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $row = $db->single();

    return (int) ($row['id'] ?? 0);
}

function compat_insert_record(DatabaseManager $db, int $reportId, string $sourceIp, int $count, string $disposition, string $dkim, string $spf): void
{
    $db->query('INSERT INTO dmarc_aggregate_records (
        report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to
    ) VALUES (
        :report_id, :source_ip, :count, :disposition, :dkim, :spf, :header_from, :envelope_from, :envelope_to
    )');
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

$failures = 0;
$db = DatabaseManager::getInstance();
$unique = bin2hex(random_bytes(6));

$cleanup = [
    'schedules' => [],
    'reports' => [],
    'domains' => [],
    'groups' => [],
    'users' => [],
];

$triggerName = 'prevent_delete_' . $unique;
$createdTrigger = false;
$logFile = __DIR__ . '/../root/php_app.log';

try {
    // Verify digest timestamp updates with PHP generated values.
    $scheduleId = EmailDigest::createSchedule([
        'name' => 'Digest Compat ' . $unique,
        'frequency' => 'daily',
        'recipients' => ['compat@example.com'],
        'domain_filter' => '',
        'group_filter' => null,
        'enabled' => 1,
        'next_scheduled' => null,
    ]);
    $cleanup['schedules'][] = $scheduleId;

    $nextRun = date('Y-m-d H:i:s', time() + 3600);
    $expectedFirstUpdate = time();
    EmailDigest::updateLastSent($scheduleId, $nextRun);

    $db->query('SELECT last_sent, next_scheduled FROM email_digest_schedules WHERE id = :id');
    $db->bind(':id', $scheduleId);
    $scheduleRow = $db->single();

    assertTrue(is_array($scheduleRow), 'Schedule row should be retrievable after update.', $failures);
    if (is_array($scheduleRow)) {
        $lastSent = $scheduleRow['last_sent'] ?? null;
        assertTrue(
            is_string($lastSent) && abs(strtotime($lastSent) - $expectedFirstUpdate) <= 3,
            'Last sent timestamp should be set using PHP time.',
            $failures
        );
        assertEquals($nextRun, $scheduleRow['next_scheduled'] ?? null, 'Next scheduled time should be updated.', $failures);
    }

    $expectedSecondUpdate = time();
    EmailDigest::updateLastSent($scheduleId);

    $db->query('SELECT last_sent, next_scheduled FROM email_digest_schedules WHERE id = :id');
    $db->bind(':id', $scheduleId);
    $secondRow = $db->single();

    assertTrue(is_array($secondRow), 'Schedule row should be retrievable after clearing next run.', $failures);
    if (is_array($secondRow)) {
        $secondLastSent = $secondRow['last_sent'] ?? null;
        assertTrue(
            is_string($secondLastSent) && abs(strtotime($secondLastSent) - $expectedSecondUpdate) <= 3,
            'Second update should refresh the last sent timestamp.',
            $failures
        );
        assertEquals(null, $secondRow['next_scheduled'] ?? null, 'Next scheduled should be cleared when not provided.', $failures);
    }

    // Seed analytics data and verify driver aware bucketing.
    $db->query('INSERT INTO domains (domain) VALUES (:domain)');
    $db->bind(':domain', 'analytics-' . $unique . '.example');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $domainRow = $db->single();
    $domainId = (int) ($domainRow['id'] ?? 0);
    $cleanup['domains'][] = $domainId;

    $baseTime = strtotime('2023-01-01 10:00:00');
    $reportOne = compat_insert_report($db, $domainId, 'trend-' . $unique . '-1', $baseTime, $baseTime + 1800);
    $reportTwo = compat_insert_report($db, $domainId, 'trend-' . $unique . '-2', $baseTime + 3600, $baseTime + 5400);
    $cleanup['reports'][] = $reportOne;
    $cleanup['reports'][] = $reportTwo;

    compat_insert_record($db, $reportOne, '198.51.100.10', 5, 'none', 'pass', 'pass');
    compat_insert_record($db, $reportTwo, '198.51.100.20', 3, 'quarantine', 'fail', 'fail');

    $trendData = Analytics::getTrendData('2022-12-31', '2023-01-02', 'analytics-' . $unique . '.example');
    assertEquals(1, count($trendData), 'Trend data should bucket reports into a single day.', $failures);
    if (!empty($trendData)) {
        $trendRow = $trendData[0];
        assertEquals(date('Y-m-d', $baseTime), $trendRow['date'] ?? null, 'Trend date should reflect the bucketed day.', $failures);
        assertTrue((int) ($trendRow['report_count'] ?? 0) === 2, 'Trend data should include both reports.', $failures);
        assertTrue((int) ($trendRow['total_volume'] ?? 0) === 8, 'Total volume should sum record counts.', $failures);
    }

    $complianceData = Analytics::getComplianceData('2022-12-31', '2023-01-02', 'analytics-' . $unique . '.example');
    assertEquals(1, count($complianceData), 'Compliance data should bucket reports into a single day.', $failures);
    if (!empty($complianceData)) {
        $complianceRow = $complianceData[0];
        $expectedRate = 62.5;
        assertEquals(date('Y-m-d', $baseTime), $complianceRow['date'] ?? null, 'Compliance date should reflect the bucketed day.', $failures);
        assertTrue(abs((float) ($complianceRow['dkim_compliance'] ?? 0) - $expectedRate) < 0.01, 'DKIM compliance should compute from aggregated data.', $failures);
        assertTrue(abs((float) ($complianceRow['spf_compliance'] ?? 0) - $expectedRate) < 0.01, 'SPF compliance should compute from aggregated data.', $failures);
        assertTrue(abs((float) ($complianceRow['dmarc_compliance'] ?? 0) - $expectedRate) < 0.01, 'DMARC compliance should compute from aggregated data.', $failures);
    }

    // Prepare supporting data for user deletion tests.
    $db->query('INSERT INTO domain_groups (name, description) VALUES (:name, :description)');
    $db->bind(':name', 'Compat Group ' . $unique);
    $db->bind(':description', 'Transactional delete coverage');
    $db->execute();

    $db->query('SELECT last_insert_rowid() as id');
    $groupRow = $db->single();
    $groupId = (int) ($groupRow['id'] ?? 0);
    $cleanup['groups'][] = $groupId;

    $successUser = 'delete-success-' . $unique;
    $cleanup['users'][] = $successUser;
    $db->query('INSERT INTO users (username, password, role, first_name, last_name, email, admin, is_active) VALUES (
        :username, :password, :role, :first_name, :last_name, :email, :admin, 1
    )');
    $db->bind(':username', $successUser);
    $db->bind(':password', password_hash('CompatPass123!', PASSWORD_BCRYPT));
    $db->bind(':role', 'viewer');
    $db->bind(':first_name', 'Compat');
    $db->bind(':last_name', 'User');
    $db->bind(':email', $successUser . '@example.com');
    $db->bind(':admin', 0);
    $db->execute();

    $db->query('INSERT INTO user_domain_assignments (user_id, domain_id, assigned_by) VALUES (:user_id, :domain_id, :assigned_by)');
    $db->bind(':user_id', $successUser);
    $db->bind(':domain_id', $domainId);
    $db->bind(':assigned_by', 'system');
    $db->execute();

    $db->query('INSERT INTO user_group_assignments (user_id, group_id, assigned_by) VALUES (:user_id, :group_id, :assigned_by)');
    $db->bind(':user_id', $successUser);
    $db->bind(':group_id', $groupId);
    $db->bind(':assigned_by', 'system');
    $db->execute();

    assertTrue(Users::deleteUser($successUser), 'User deletion should succeed when all statements execute.', $failures);

    $db->query('SELECT COUNT(*) as total FROM users WHERE username = :username');
    $db->bind(':username', $successUser);
    $remainingUsers = $db->single();
    assertTrue((int) ($remainingUsers['total'] ?? 0) === 0, 'User row should be removed after successful deletion.', $failures);

    $db->query('SELECT COUNT(*) as total FROM user_domain_assignments WHERE user_id = :username');
    $db->bind(':username', $successUser);
    $domainAssignments = $db->single();
    assertTrue((int) ($domainAssignments['total'] ?? 0) === 0, 'Domain assignments should be removed after deletion.', $failures);

    $db->query('SELECT COUNT(*) as total FROM user_group_assignments WHERE user_id = :username');
    $db->bind(':username', $successUser);
    $groupAssignments = $db->single();
    assertTrue((int) ($groupAssignments['total'] ?? 0) === 0, 'Group assignments should be removed after deletion.', $failures);

    // Failure path using trigger to raise an exception.
    $failureUser = 'delete-fail-' . $unique;
    $cleanup['users'][] = $failureUser;
    $db->query('INSERT INTO users (username, password, role, first_name, last_name, email, admin, is_active) VALUES (
        :username, :password, :role, :first_name, :last_name, :email, :admin, 1
    )');
    $db->bind(':username', $failureUser);
    $db->bind(':password', password_hash('CompatFail123!', PASSWORD_BCRYPT));
    $db->bind(':role', 'viewer');
    $db->bind(':first_name', 'Compat');
    $db->bind(':last_name', 'Failure');
    $db->bind(':email', $failureUser . '@example.com');
    $db->bind(':admin', 0);
    $db->execute();

    $db->query('INSERT INTO user_domain_assignments (user_id, domain_id, assigned_by) VALUES (:user_id, :domain_id, :assigned_by)');
    $db->bind(':user_id', $failureUser);
    $db->bind(':domain_id', $domainId);
    $db->bind(':assigned_by', 'system');
    $db->execute();

    $db->query('INSERT INTO user_group_assignments (user_id, group_id, assigned_by) VALUES (:user_id, :group_id, :assigned_by)');
    $db->bind(':user_id', $failureUser);
    $db->bind(':group_id', $groupId);
    $db->bind(':assigned_by', 'system');
    $db->execute();

    $db->query("CREATE TRIGGER $triggerName BEFORE DELETE ON users WHEN OLD.username = '$failureUser' BEGIN SELECT RAISE(FAIL, 'Deletion blocked for coverage'); END;");
    $db->execute();
    $createdTrigger = true;

    assertFalse(Users::deleteUser($failureUser), 'Deletion should fail when database raises an error.', $failures);

    $db->query('SELECT COUNT(*) as total FROM users WHERE username = :username');
    $db->bind(':username', $failureUser);
    $failureUserRow = $db->single();
    assertTrue((int) ($failureUserRow['total'] ?? 0) === 1, 'User should remain when deletion fails.', $failures);

    $db->query('SELECT COUNT(*) as total FROM user_domain_assignments WHERE user_id = :username');
    $db->bind(':username', $failureUser);
    $failureDomainAssignments = $db->single();
    assertTrue((int) ($failureDomainAssignments['total'] ?? 0) === 1, 'Domain assignments should remain after rollback.', $failures);

    $db->query('SELECT COUNT(*) as total FROM user_group_assignments WHERE user_id = :username');
    $db->bind(':username', $failureUser);
    $failureGroupAssignments = $db->single();
    assertTrue((int) ($failureGroupAssignments['total'] ?? 0) === 1, 'Group assignments should remain after rollback.', $failures);

    $db->query("DROP TRIGGER IF EXISTS $triggerName");
    $db->execute();
    $createdTrigger = false;

    // Validate attachment cleanup logging.
    $imapService = new ImapIngestionService();
    $method = new \ReflectionMethod(ImapIngestionService::class, 'processAttachment');
    $method->setAccessible(true);

    $initialSize = file_exists($logFile) ? filesize($logFile) : 0;
    $result = $method->invoke($imapService, 'not a compressed report');
    assertFalse($result, 'Invalid attachment should fail processing.', $failures);

    $logContents = file_exists($logFile) ? file_get_contents($logFile) : '';
    $newLogPortion = $initialSize > 0 ? substr($logContents, $initialSize) : $logContents;
    assertContains('Failed to process attachment at', $newLogPortion, 'Failure log should include attachment path.', $failures);

    if (preg_match('/Failed to process attachment at (.+?):/', $newLogPortion, $matches)) {
        $loggedPath = $matches[1];
        assertFalse(file_exists($loggedPath), 'Temporary attachment file should be removed even on failure.', $failures);
    } else {
        $failures++;
    }
} finally {
    if ($createdTrigger) {
        $db->query("DROP TRIGGER IF EXISTS $triggerName");
        $db->execute();
    }

    foreach (array_unique($cleanup['users']) as $username) {
        $db->query('DELETE FROM user_domain_assignments WHERE user_id = :username');
        $db->bind(':username', $username);
        $db->execute();

        $db->query('DELETE FROM user_group_assignments WHERE user_id = :username');
        $db->bind(':username', $username);
        $db->execute();

        $db->query('DELETE FROM users WHERE username = :username');
        $db->bind(':username', $username);
        $db->execute();
    }

    foreach (array_unique($cleanup['groups']) as $groupId) {
        if ($groupId > 0) {
            $db->query('DELETE FROM domain_groups WHERE id = :group_id');
            $db->bind(':group_id', $groupId);
            $db->execute();
        }
    }

    foreach (array_unique($cleanup['reports']) as $reportId) {
        if ($reportId > 0) {
            $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();

            $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();
        }
    }

    foreach (array_unique($cleanup['domains']) as $domainId) {
        if ($domainId > 0) {
            $db->query('DELETE FROM domains WHERE id = :domain_id');
            $db->bind(':domain_id', $domainId);
            $db->execute();
        }
    }

    foreach (array_unique($cleanup['schedules']) as $scheduleId) {
        if ($scheduleId > 0) {
            $db->query('DELETE FROM email_digest_schedules WHERE id = :id');
            $db->bind(':id', $scheduleId);
            $db->execute();
        }
    }
}

echo 'Database compatibility tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
