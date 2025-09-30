<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Core\RBACManager;
use App\Core\SessionManager;
use App\Models\DmarcReport;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$session = SessionManager::getInstance();
$session->start();
$session->set('logged_in', true);
$session->set('user_role', RBACManager::ROLE_APP_ADMIN);
$session->set('username', 'advanced-filter-user');

$db = DatabaseManager::getInstance();

$unique = bin2hex(random_bytes(4));
$domainName = 'filters-' . $unique . '.example';
$secondDomain = 'filters-b-' . $unique . '.example';

$failures = 0;
$createdDomainIds = [];
$createdReportIds = [];

try {
    // Insert domains with metadata
    $db->query('INSERT INTO domains (domain, ownership_contact, enforcement_level) VALUES (:domain, :owner, :enforcement)');
    $db->bind(':domain', $domainName);
    $db->bind(':owner', 'Security Operations');
    $db->bind(':enforcement', 'reject');
    $db->execute();
    $db->query('SELECT last_insert_rowid() AS id');
    $result = $db->single();
    $primaryDomainId = (int) ($result['id'] ?? 0);
    $createdDomainIds[] = $primaryDomainId;

    $db->query('INSERT INTO domains (domain, ownership_contact, enforcement_level) VALUES (:domain, :owner, :enforcement)');
    $db->bind(':domain', $secondDomain);
    $db->bind(':owner', 'IT Operations');
    $db->bind(':enforcement', 'none');
    $db->execute();
    $db->query('SELECT last_insert_rowid() AS id');
    $result = $db->single();
    $secondaryDomainId = (int) ($result['id'] ?? 0);
    $createdDomainIds[] = $secondaryDomainId;

    assertTrue($primaryDomainId > 0 && $secondaryDomainId > 0, 'Domains should insert successfully.', $failures);

    $now = time();

    // Failure-heavy report for primary domain
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :begin, :end, :received)');
    $db->bind(':domain_id', $primaryDomainId);
    $db->bind(':org_name', 'Filter Org');
    $db->bind(':email', 'reports@filters.example');
    $db->bind(':report_id', 'filter-report-' . $unique);
    $db->bind(':begin', $now - 86400);
    $db->bind(':end', $now);
    $db->bind(':received', date('Y-m-d H:i:s', $now));
    $db->execute();
    $db->query('SELECT last_insert_rowid() AS id');
    $result = $db->single();
    $failureReportId = (int) ($result['id'] ?? 0);
    $createdReportIds[] = $failureReportId;

    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $failureReportId);
    $db->bind(':source_ip', '198.51.100.5');
    $db->bind(':count', 25);
    $db->bind(':disposition', 'reject');
    $db->bind(':dkim_result', 'fail');
    $db->bind(':spf_result', 'fail');
    $db->bind(':header_from', $domainName);
    $db->bind(':envelope_from', 'malicious.example');
    $db->bind(':envelope_to', 'victim@example.net');
    $db->execute();

    // Passing report for primary domain
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :begin, :end, :received)');
    $db->bind(':domain_id', $primaryDomainId);
    $db->bind(':org_name', 'Filter Org');
    $db->bind(':email', 'reports@filters.example');
    $db->bind(':report_id', 'pass-report-' . $unique);
    $db->bind(':begin', $now - (3 * 86400));
    $db->bind(':end', $now - (2 * 86400));
    $db->bind(':received', date('Y-m-d H:i:s', $now - (2 * 86400)));
    $db->execute();
    $db->query('SELECT last_insert_rowid() AS id');
    $result = $db->single();
    $passReportId = (int) ($result['id'] ?? 0);
    $createdReportIds[] = $passReportId;

    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $passReportId);
    $db->bind(':source_ip', '203.0.113.7');
    $db->bind(':count', 40);
    $db->bind(':disposition', 'none');
    $db->bind(':dkim_result', 'pass');
    $db->bind(':spf_result', 'pass');
    $db->bind(':header_from', $domainName);
    $db->bind(':envelope_from', $domainName);
    $db->bind(':envelope_to', 'user@' . $domainName);
    $db->execute();

    // Report for secondary domain to validate enforcement filters
    $db->query('INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES (:domain_id, :org_name, :email, :report_id, :begin, :end, :received)');
    $db->bind(':domain_id', $secondaryDomainId);
    $db->bind(':org_name', 'Second Org');
    $db->bind(':email', 'ops@second.example');
    $db->bind(':report_id', 'secondary-report-' . $unique);
    $db->bind(':begin', $now - 86400);
    $db->bind(':end', $now);
    $db->bind(':received', date('Y-m-d H:i:s', $now));
    $db->execute();
    $db->query('SELECT last_insert_rowid() AS id');
    $result = $db->single();
    $secondaryReportId = (int) ($result['id'] ?? 0);
    $createdReportIds[] = $secondaryReportId;

    $db->query('INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from, envelope_to) VALUES (:report_id, :source_ip, :count, :disposition, :dkim_result, :spf_result, :header_from, :envelope_from, :envelope_to)');
    $db->bind(':report_id', $secondaryReportId);
    $db->bind(':source_ip', '192.0.2.100');
    $db->bind(':count', 12);
    $db->bind(':disposition', 'none');
    $db->bind(':dkim_result', 'pass');
    $db->bind(':spf_result', 'pass');
    $db->bind(':header_from', $secondDomain);
    $db->bind(':envelope_from', $secondDomain);
    $db->bind(':envelope_to', 'team@' . $secondDomain);
    $db->execute();

    // Test filtering by ownership metadata and enforcement
    $ownershipFilter = DmarcReport::getFilteredReports([
        'ownership_contact' => 'Security',
        'enforcement_level' => 'reject',
    ]);
    assertCountEquals(2, $ownershipFilter, 'Ownership and enforcement filters should match only the primary domain reports.', $failures);

    $ipFilter = DmarcReport::getFilteredReports([
        'source_ip' => '198.51.100.5',
    ]);
    assertCountEquals(1, $ipFilter, 'Source IP filter should locate the failing report.', $failures);
    assertEquals('filter-report-' . $unique, $ipFilter[0]['report_id'] ?? '', 'Source IP filter should return the correct report.', $failures);

    $dkimFilter = DmarcReport::getFilteredReports([
        'dkim_result' => 'fail',
        'has_failures' => true,
    ]);
    assertCountEquals(1, $dkimFilter, 'DKIM failure filter should isolate the failing report.', $failures);

    $minVolumeFilter = DmarcReport::getFilteredReports([
        'domain' => $domainName,
        'min_volume' => 30,
    ]);
    assertCountEquals(1, $minVolumeFilter, 'Min volume should exclude the low volume failure report.', $failures);
    assertEquals('pass-report-' . $unique, $minVolumeFilter[0]['report_id'] ?? '', 'Min volume filter should return the high volume report.', $failures);

    $count = DmarcReport::getFilteredReportsCount([
        'domain' => $domainName,
        'has_failures' => true,
    ]);
    assertEquals(1, $count, 'Count method should respect advanced filters.', $failures);
} finally {
    foreach ($createdReportIds as $reportId) {
        if ($reportId > 0) {
            $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();

            $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
            $db->bind(':report_id', $reportId);
            $db->execute();
        }
    }

    foreach ($createdDomainIds as $domainId) {
        if ($domainId > 0) {
            $db->query('DELETE FROM domains WHERE id = :domain_id');
            $db->bind(':domain_id', $domainId);
            $db->execute();
        }
    }
}

echo "Dmarc report advanced filtering tests completed.\n";
exit($failures === 0 ? 0 : 1);
