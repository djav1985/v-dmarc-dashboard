<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

$autoloadPath = __DIR__ . '/../root/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    echo 'DMARC report persistence tests skipped: composer autoloader not available.' . PHP_EOL;
    exit(0);
}

require $autoloadPath;
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

use App\Core\DatabaseManager;
use App\Models\DmarcReport;
use App\Utilities\DmarcParser;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertEquals;
use function TestHelpers\assertTrue;

$failures = 0;
$db = DatabaseManager::getInstance();

\App\Core\SessionManager::getInstance()->start();
\App\Core\SessionManager::getInstance()->set('logged_in', true);
\App\Core\SessionManager::getInstance()->set('user_role', \App\Core\RBACManager::ROLE_APP_ADMIN);
\App\Core\SessionManager::getInstance()->set('username', 'dmarc-report-persistence');

$fixturePath = __DIR__ . '/fixtures/dmarc/aggregate-sample.xml';
$xml = file_get_contents($fixturePath);
assertTrue($xml !== false, 'Aggregate fixture should be readable for persistence tests.', $failures);

if ($xml !== false) {
    $reportData = DmarcParser::parseAggregateReport($xml);

    $uniqueDomain = 'aggregate-' . bin2hex(random_bytes(4)) . '.example';
    $reportData['policy_published_domain'] = $uniqueDomain;
    foreach ($reportData['records'] as &$record) {
        if (!isset($record['header_from']) || $record['header_from'] === null) {
            $record['header_from'] = $uniqueDomain;
        }
    }
    unset($record);

    $reportId = DmarcReport::storeAggregateReport($reportData);
    assertTrue($reportId > 0, 'Aggregate report should persist and yield an ID.', $failures);

    if ($reportId > 0) {
        $stored = DmarcReport::storeAggregateRecords($reportId, $reportData['records']);
        assertTrue($stored, 'Aggregate records should persist successfully.', $failures);

        $details = DmarcReport::getReportDetails($reportId);
        assertTrue(is_array($details), 'Stored report details should be retrievable.', $failures);
        if (is_array($details)) {
            assertEquals('r', $details['policy_adkim'] ?? null, 'Stored report should capture adkim.', $failures);
            assertEquals('none', $details['policy_p'] ?? null, 'Stored report should capture policy p.', $failures);
            assertEquals(100, $details['policy_pct'] ?? null, 'Stored report should capture policy pct.', $failures);
            assertEquals('1', $details['policy_fo'] ?? null, 'Stored report should capture policy fo.', $failures);
        }

        $records = DmarcReport::getAggregateRecords($reportId);
        assertCountEquals(1, $records, 'Persisted records should be retrievable.', $failures);

        if (!empty($records)) {
            $record = $records[0];
            assertEquals('forwarded', $record['policy_evaluated_reasons'][0]['type'] ?? null, 'Persisted record should retain evaluation reason.', $failures);
            assertEquals('local_policy', $record['policy_override_reasons'][0]['type'] ?? null, 'Persisted record should retain override reason.', $failures);
            assertEquals('selector1', $record['auth_results']['dkim'][0]['selector'] ?? null, 'Persisted record should retain DKIM auth result.', $failures);
        }

        $db->query('DELETE FROM dmarc_aggregate_records WHERE report_id = :report_id');
        $db->bind(':report_id', $reportId);
        $db->execute();

        $db->query('DELETE FROM dmarc_aggregate_reports WHERE id = :report_id');
        $db->bind(':report_id', $reportId);
        $db->execute();

        $db->query('DELETE FROM domains WHERE domain = :domain');
        $db->bind(':domain', $uniqueDomain);
        $db->execute();
    }
}

echo 'DMARC report persistence tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
