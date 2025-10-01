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
use App\Models\PolicySimulation;
use function TestHelpers\assertCountEquals;
use function TestHelpers\assertTrue;

$failures = 0;

$db = DatabaseManager::getInstance();
$timestamp = time();

$accessibleDomainName = 'simulation-access-' . $timestamp . '.example';
$restrictedDomainName = 'simulation-restricted-' . $timestamp . '.example';

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $accessibleDomainName);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$accessibleDomainId = (int) ($db->single()['id'] ?? 0);

$db->query('INSERT INTO domains (domain) VALUES (:domain)');
$db->bind(':domain', $restrictedDomainName);
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$restrictedDomainId = (int) ($db->single()['id'] ?? 0);

$_SESSION['username'] = 'policy-viewer-' . $timestamp;
$_SESSION['user_role'] = 'viewer';

$db->query('INSERT INTO user_domain_assignments (user_id, domain_id) VALUES (:user, :domain)');
$db->bind(':user', $_SESSION['username']);
$db->bind(':domain', $accessibleDomainId);
$db->execute();

$currentPolicy = [
    'p' => 'none',
    'sp' => '',
    'aspf' => 'r',
    'adkim' => 'r',
    'pct' => 100,
    'rua' => '',
    'ruf' => '',
];

$simulatedPolicy = [
    'p' => 'quarantine',
    'sp' => '',
    'aspf' => 'r',
    'adkim' => 'r',
    'pct' => 100,
    'rua' => '',
    'ruf' => '',
];

$exceptionCaught = false;
try {
    PolicySimulation::createSimulation([
        'name' => 'Unauthorized Simulation',
        'description' => 'Should not be created',
        'domain_id' => $restrictedDomainId,
        'current_policy' => $currentPolicy,
        'simulated_policy' => $simulatedPolicy,
        'period_start' => date('Y-m-d', $timestamp - 86400),
        'period_end' => date('Y-m-d', $timestamp),
        'created_by' => $_SESSION['username'],
    ]);
} catch (\RuntimeException $exception) {
    $exceptionCaught = true;
}

assertTrue($exceptionCaught, 'Simulation creation should be blocked for unauthorized domains.', $failures);

$simulationId = PolicySimulation::createSimulation([
    'name' => 'Authorized Simulation',
    'description' => 'Permitted',
    'domain_id' => $accessibleDomainId,
    'current_policy' => $currentPolicy,
    'simulated_policy' => $simulatedPolicy,
    'period_start' => date('Y-m-d', $timestamp - 86400),
    'period_end' => date('Y-m-d', $timestamp),
    'created_by' => $_SESSION['username'],
]);

assertTrue($simulationId > 0, 'Authorized domain simulations should be created successfully.', $failures);

$db->query('INSERT INTO policy_simulations (name, description, domain_id, current_policy, simulated_policy, simulation_period_start, simulation_period_end, created_by) VALUES (:name, :description, :domain_id, :current_policy, :simulated_policy, :start, :end, :created_by)');
$db->bind(':name', 'Foreign Simulation');
$db->bind(':description', 'Should be denied');
$db->bind(':domain_id', $restrictedDomainId);
$db->bind(':current_policy', json_encode($currentPolicy));
$db->bind(':simulated_policy', json_encode($simulatedPolicy));
$db->bind(':start', date('Y-m-d', $timestamp - 86400));
$db->bind(':end', date('Y-m-d', $timestamp));
$db->bind(':created_by', 'another-user');
$db->execute();
$db->query('SELECT last_insert_rowid() as id');
$foreignSimulationId = (int) ($db->single()['id'] ?? 0);

$runExceptionCaught = false;
try {
    PolicySimulation::runSimulation($foreignSimulationId);
} catch (\RuntimeException $exception) {
    $runExceptionCaught = true;
}

assertTrue($runExceptionCaught, 'Running a simulation for an unauthorized domain should be blocked.', $failures);

$allSimulations = PolicySimulation::getAllSimulations();
assertCountEquals(1, $allSimulations, 'User should only see simulations for permitted domains.', $failures);

$restrictedSimulation = PolicySimulation::getSimulation($foreignSimulationId);
assertTrue($restrictedSimulation === null, 'Unauthorized simulation lookups should return null.', $failures);

$accessibleSimulation = PolicySimulation::getSimulation($simulationId);
assertTrue($accessibleSimulation !== null, 'Authorized simulations should remain accessible.', $failures);

echo 'Policy simulation access tests completed with ' . ($failures === 0 ? 'no failures' : $failures . ' failure(s)') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
