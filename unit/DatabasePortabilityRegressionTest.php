<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../root/vendor/autoload.php';
require __DIR__ . '/../root/config.php';
require __DIR__ . '/TestHelpers.php';

if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

use App\Controllers\AlertController;
use App\Core\DatabaseManager;
use App\Models\Alert;
use App\Services\GeoIPService;
use function TestHelpers\assertContains;
use function TestHelpers\assertTrue;

class PortableDatabaseStub extends DatabaseManager
{
    public array $queries = [];
    public array $executions = [];
    public array $singleQueue = [];
    public array $resultQueue = [];
    public int $rowCountValue = 0;
    private array $currentBindings = [];

    public function __construct()
    {
        // Avoid parent constructor to prevent real connections.
    }

    public function reset(): void
    {
        $this->queries = [];
        $this->executions = [];
        $this->singleQueue = [];
        $this->resultQueue = [];
        $this->currentBindings = [];
    }

    public function queueSingle(array $rows): void
    {
        $this->singleQueue = $rows;
    }

    public function query(string $sql): void
    {
        $this->queries[] = trim(preg_replace('/\s+/', ' ', $sql));
        $this->currentBindings = [];
    }

    public function bind(string $param, $value, ?int $type = null): void
    {
        $this->currentBindings[ltrim($param, ':')] = $value;
    }

    public function execute(): bool
    {
        $this->executions[] = [
            'sql' => $this->queries[count($this->queries) - 1] ?? '',
            'params' => $this->currentBindings,
        ];
        return true;
    }

    public function resultSet(): array
    {
        $this->execute();
        return array_shift($this->resultQueue) ?? [];
    }

    public function single(): mixed
    {
        $this->execute();
        return array_shift($this->singleQueue) ?? [];
    }

    public function rowCount(): int
    {
        return $this->rowCountValue;
    }

    public function getLastInsertId(?string $sequenceName = null): string
    {
        return '1';
    }
}

function assertIsoTimestamp(?string $value, string $context, int &$failures): void
{
    $valid = is_string($value);
    if ($valid) {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        $valid = $dt !== false && $dt->format('Y-m-d H:i:s') === $value;
    }

    if (!$valid) {
        fwrite(STDERR, $context . ' expected ISO timestamp but received ' . var_export($value, true) . PHP_EOL);
        $failures++;
    }
}

$failures = 0;
$reflection = new ReflectionClass(DatabaseManager::class);
$instanceProperty = $reflection->getProperty('instance');
$instanceProperty->setAccessible(true);
$originalInstance = $instanceProperty->getValue();
$postBackup = $_POST;
$sessionBackup = $_SESSION;

$stub = new PortableDatabaseStub();
$instanceProperty->setValue(null, $stub);

try {
    $metricMethod = (new ReflectionClass(Alert::class))->getMethod('calculateMetric');
    $metricMethod->setAccessible(true);

    $ruleTemplate = [
        'id' => 1,
        'name' => 'Portability Rule',
        'rule_type' => 'threshold',
        'metric' => '',
        'threshold_value' => 1,
        'threshold_operator' => '>=',
        'time_window' => 30,
        'domain_filter' => 'portability.example',
        'group_filter' => 7,
    ];

    $metricsToValidate = [
        'dmarc_failure_rate' => 42.5,
        'volume_increase' => 125.0,
        'new_failure_ips' => 3,
        'spf_failures' => 9,
    ];

    foreach ($metricsToValidate as $metric => $expectedValue) {
        $stub->reset();
        $stub->queueSingle([[ 'value' => $expectedValue ]]);

        $rule = $ruleTemplate;
        $rule['metric'] = $metric;
        $result = $metricMethod->invoke(null, $rule);

        assertTrue(abs($result - $expectedValue) < 0.0001, 'Metric ' . $metric . ' should return stubbed value.', $failures);

        $execution = $stub->executions[count($stub->executions) - 1] ?? null;
        assertTrue(is_array($execution), 'Execution metadata should be captured for ' . $metric, $failures);
        if (is_array($execution)) {
            assertTrue(stripos($execution['sql'], 'datetime(') === false, 'Metric ' . $metric . ' should avoid SQLite-specific datetime().', $failures);
            $start = $execution['params']['start_time'] ?? null;
            assertIsoTimestamp($start, 'Metric ' . $metric, $failures);
            assertContains('d.domain = :domain_filter', $execution['sql'], 'Metric ' . $metric . ' should apply domain filter binding.', $failures);
            assertContains('dga.group_id = :group_filter', $execution['sql'], 'Metric ' . $metric . ' should apply group filter binding.', $failures);
            assertTrue(($execution['params']['domain_filter'] ?? null) === 'portability.example', 'Metric ' . $metric . ' should bind provided domain filter.', $failures);
            assertTrue(($execution['params']['group_filter'] ?? null) === 7, 'Metric ' . $metric . ' should bind provided group filter.', $failures);
            if ($metric === 'volume_increase') {
                $prev = $execution['params']['prev_start_time'] ?? null;
                assertIsoTimestamp($prev, 'Metric volume_increase previous window', $failures);
            }
        }
    }

    $stub->reset();
    $_POST['incident_id'] = 55;
    $_SESSION['username'] = 'portability-user';

    $controller = new AlertController();
    $ackMethod = (new ReflectionClass(AlertController::class))->getMethod('acknowledgeIncident');
    $ackMethod->setAccessible(true);
    $ackMethod->invoke($controller);

    $ackExecution = $stub->executions[count($stub->executions) - 1] ?? null;
    assertTrue(is_array($ackExecution), 'Acknowledgement should trigger a database update.', $failures);
    if (is_array($ackExecution)) {
        assertContains('acknowledged_at = :acknowledged_at', $ackExecution['sql'], 'Acknowledgement should bind timestamp parameter.', $failures);
        assertIsoTimestamp($ackExecution['params']['acknowledged_at'] ?? null, 'Incident acknowledgement', $failures);
    }

    $stub->reset();
    $stub->rowCountValue = 4;
    $geoService = GeoIPService::createWithDependencies();
    $expectedCutoff = date('Y-m-d H:i:s', strtotime('-5 days'));
    $removed = $geoService->cleanOldCache(5);
    assertTrue($removed === 4, 'Cache cleanup should return stubbed affected rows.', $failures);

    $cleanupExecution = $stub->executions[count($stub->executions) - 1] ?? null;
    assertTrue(is_array($cleanupExecution), 'Cache cleanup should execute a deletion statement.', $failures);
    if (is_array($cleanupExecution)) {
        assertContains('last_updated < :cutoff', $cleanupExecution['sql'], 'Cache cleanup should compare timestamps using bound cutoff.', $failures);
        assertIsoTimestamp($cleanupExecution['params']['cutoff'] ?? null, 'Cache cleanup cutoff', $failures);
        assertTrue(($cleanupExecution['params']['cutoff'] ?? '') === $expectedCutoff, 'Cache cleanup cutoff should match expected PHP timestamp.', $failures);
    }
} finally {
    $instanceProperty->setValue(null, $originalInstance);
    $_POST = $postBackup;
    $_SESSION = $sessionBackup;
}

assertTrue($failures === 0, 'All database portability checks should pass.', $failures);

echo 'Database portability regression tests completed with ' . ($failures === 0 ? 'no failures' : "{$failures} failure(s)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
