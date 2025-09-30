<?php

namespace App\Models;

use App\Core\DatabaseManager;

/**
 * Policy Simulation model for DMARC policy testing and recommendations
 */
class PolicySimulation
{
    /**
     * Get all policy simulations
     *
     * @return array
     */
    public static function getAllSimulations(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                ps.*,
                d.domain
            FROM policy_simulations ps
            JOIN domains d ON ps.domain_id = d.id
            ORDER BY ps.created_at DESC
        ');

        return $db->resultSet();
    }

    /**
     * Create a new policy simulation
     *
     * @param array $data
     * @return int Simulation ID
     */
    public static function createSimulation(array $data): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO policy_simulations 
            (name, description, domain_id, current_policy, simulated_policy, 
             simulation_period_start, simulation_period_end, created_by) 
            VALUES 
            (:name, :description, :domain_id, :current_policy, :simulated_policy,
             :period_start, :period_end, :created_by)
        ');

        $db->bind(':name', $data['name']);
        $db->bind(':description', $data['description'] ?? '');
        $db->bind(':domain_id', $data['domain_id']);
        $db->bind(':current_policy', json_encode($data['current_policy']));
        $db->bind(':simulated_policy', json_encode($data['simulated_policy']));
        $db->bind(':period_start', $data['period_start']);
        $db->bind(':period_end', $data['period_end']);
        $db->bind(':created_by', $data['created_by'] ?? 'Unknown');
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Run policy simulation analysis
     *
     * @param int $simulationId
     * @return array
     */
    public static function runSimulation(int $simulationId): array
    {
        $db = DatabaseManager::getInstance();

        // Get simulation details
        $db->query('SELECT * FROM policy_simulations WHERE id = :id');
        $db->bind(':id', $simulationId);
        $simulation = $db->single();

        if (!$simulation) {
            return [];
        }

        $currentPolicy = json_decode($simulation['current_policy'], true);
        $simulatedPolicy = json_decode($simulation['simulated_policy'], true);
        
        $startTime = strtotime($simulation['simulation_period_start']);
        $endTime = strtotime($simulation['simulation_period_end'] . ' 23:59:59');

        // Get historical data for the simulation period
        $db->query('
            SELECT 
                dmar.*,
                dar.org_name
            FROM dmarc_aggregate_records dmar
            JOIN dmarc_aggregate_reports dar ON dmar.report_id = dar.id
            WHERE dar.domain_id = :domain_id
            AND dar.date_range_begin >= :start_time
            AND dar.date_range_end <= :end_time
        ');

        $db->bind(':domain_id', $simulation['domain_id']);
        $db->bind(':start_time', $startTime);
        $db->bind(':end_time', $endTime);
        $records = $db->resultSet();

        // Simulate policy impact
        $results = self::simulatePolicyImpact($records, $currentPolicy, $simulatedPolicy);
        
        // Generate recommendations
        $recommendations = self::generatePolicyRecommendations($results, $currentPolicy, $simulatedPolicy);

        // Update simulation with results
        $db->query('
            UPDATE policy_simulations 
            SET results = :results, recommendations = :recommendations
            WHERE id = :id
        ');

        $db->bind(':id', $simulationId);
        $db->bind(':results', json_encode($results));
        $db->bind(':recommendations', json_encode($recommendations));
        $db->execute();

        return [
            'simulation' => $simulation,
            'results' => $results,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Simulate policy impact on historical data
     *
     * @param array $records
     * @param array $currentPolicy
     * @param array $simulatedPolicy
     * @return array
     */
    private static function simulatePolicyImpact(array $records, array $currentPolicy, array $simulatedPolicy): array
    {
        $results = [
            'total_messages' => 0,
            'current_quarantined' => 0,
            'current_rejected' => 0,
            'simulated_quarantined' => 0,
            'simulated_rejected' => 0,
            'legitimate_affected' => 0,
            'spam_blocked' => 0,
            'policy_effectiveness' => 0,
            'by_source' => []
        ];

        foreach ($records as $record) {
            $volume = (int) $record['count'];
            $results['total_messages'] += $volume;

            // Current policy impact
            if ($record['disposition'] === 'quarantine') {
                $results['current_quarantined'] += $volume;
            } elseif ($record['disposition'] === 'reject') {
                $results['current_rejected'] += $volume;
            }

            // Simulate new policy impact
            $simulatedDisposition = self::calculateSimulatedDisposition(
                $record, $simulatedPolicy, $currentPolicy
            );

            if ($simulatedDisposition === 'quarantine') {
                $results['simulated_quarantined'] += $volume;
            } elseif ($simulatedDisposition === 'reject') {
                $results['simulated_rejected'] += $volume;
            }

            // Analyze legitimacy (heuristic based on authentication results)
            $isLegitimate = self::assessMessageLegitimacy($record);
            
            if ($simulatedDisposition !== 'none' && $record['disposition'] === 'none') {
                if ($isLegitimate) {
                    $results['legitimate_affected'] += $volume;
                } else {
                    $results['spam_blocked'] += $volume;
                }
            }

            // Track by source IP
            $sourceIp = $record['source_ip'];
            if (!isset($results['by_source'][$sourceIp])) {
                $results['by_source'][$sourceIp] = [
                    'current_disposition' => $record['disposition'],
                    'simulated_disposition' => $simulatedDisposition,
                    'volume' => 0,
                    'legitimate' => 0,
                    'suspicious' => 0
                ];
            }
            $results['by_source'][$sourceIp]['volume'] += $volume;
            if ($isLegitimate) {
                $results['by_source'][$sourceIp]['legitimate'] += $volume;
            } else {
                $results['by_source'][$sourceIp]['suspicious'] += $volume;
            }
        }

        // Calculate effectiveness
        $totalBlocked = $results['spam_blocked'];
        $totalAffected = $results['legitimate_affected'] + $results['spam_blocked'];
        $results['policy_effectiveness'] = $totalAffected > 0 ? 
            round(($totalBlocked / $totalAffected) * 100, 2) : 0;

        return $results;
    }

    /**
     * Calculate what disposition a message would get under simulated policy
     *
     * @param array $record
     * @param array $simulatedPolicy
     * @param array $currentPolicy
     * @return string
     */
    private static function calculateSimulatedDisposition(array $record, array $simulatedPolicy, array $currentPolicy): string
    {
        $dkimPass = $record['dkim_result'] === 'pass';
        $spfPass = $record['spf_result'] === 'pass';
        
        // DMARC alignment check (simplified)
        $dkimAligned = $dkimPass; // Simplified - actual implementation would check domain alignment
        $spfAligned = $spfPass;   // Simplified - actual implementation would check domain alignment
        
        // Check if DMARC passes under simulated policy
        $dmarcPass = false;
        
        if (isset($simulatedPolicy['aspf']) && $simulatedPolicy['aspf'] === 's') {
            // Strict SPF alignment
            $dmarcPass = $dmarcPass || $spfAligned;
        } else {
            // Relaxed SPF alignment
            $dmarcPass = $dmarcPass || $spfPass;
        }
        
        if (isset($simulatedPolicy['adkim']) && $simulatedPolicy['adkim'] === 's') {
            // Strict DKIM alignment
            $dmarcPass = $dmarcPass || $dkimAligned;
        } else {
            // Relaxed DKIM alignment
            $dmarcPass = $dmarcPass || $dkimPass;
        }

        if ($dmarcPass) {
            return 'none';
        }

        // Apply percentage (pct) if specified
        $pct = isset($simulatedPolicy['pct']) ? (int) $simulatedPolicy['pct'] : 100;
        $randomValue = rand(1, 100);
        
        if ($randomValue > $pct) {
            return 'none'; // Not subject to policy due to percentage
        }

        // Return policy action
        return $simulatedPolicy['p'] ?? 'none';
    }

    /**
     * Assess if a message is likely legitimate based on authentication patterns
     *
     * @param array $record
     * @return bool
     */
    private static function assessMessageLegitimacy(array $record): bool
    {
        // Heuristic approach - in reality this would be more sophisticated
        $legitimacyScore = 0;

        // DKIM pass is a strong legitimacy indicator
        if ($record['dkim_result'] === 'pass') {
            $legitimacyScore += 3;
        }

        // SPF pass is a good legitimacy indicator
        if ($record['spf_result'] === 'pass') {
            $legitimacyScore += 2;
        }

        // Low volume from single IP might indicate legitimate email
        if ($record['count'] <= 10) {
            $legitimacyScore += 1;
        }

        // High volume from single IP might indicate spam
        if ($record['count'] > 100) {
            $legitimacyScore -= 2;
        }

        return $legitimacyScore > 0;
    }

    /**
     * Generate policy recommendations based on simulation results
     *
     * @param array $results
     * @param array $currentPolicy
     * @param array $simulatedPolicy
     * @return array
     */
    private static function generatePolicyRecommendations(array $results, array $currentPolicy, array $simulatedPolicy): array
    {
        $recommendations = [];

        // Check legitimate impact
        if ($results['legitimate_affected'] > 0) {
            $impactPercentage = round(($results['legitimate_affected'] / $results['total_messages']) * 100, 2);
            
            if ($impactPercentage > 5) {
                $recommendations[] = [
                    'type' => 'warning',
                    'priority' => 'high',
                    'title' => 'High legitimate mail impact',
                    'description' => "{$results['legitimate_affected']} legitimate messages ({$impactPercentage}%) would be affected. Consider gradual rollout with pct parameter.",
                    'action' => 'Use pct=10 initially and gradually increase'
                ];
            } elseif ($impactPercentage > 1) {
                $recommendations[] = [
                    'type' => 'caution',
                    'priority' => 'medium',
                    'title' => 'Moderate legitimate mail impact',
                    'description' => "{$results['legitimate_affected']} legitimate messages ({$impactPercentage}%) would be affected. Monitor carefully during rollout.",
                    'action' => 'Implement with close monitoring'
                ];
            }
        }

        // Check effectiveness
        if ($results['policy_effectiveness'] > 80) {
            $recommendations[] = [
                'type' => 'success',
                'priority' => 'low',
                'title' => 'High policy effectiveness',
                'description' => "Policy would block {$results['spam_blocked']} suspicious messages with {$results['policy_effectiveness']}% effectiveness.",
                'action' => 'Policy change recommended'
            ];
        } elseif ($results['policy_effectiveness'] < 50) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'medium',
                'title' => 'Low policy effectiveness',
                'description' => "Policy effectiveness is only {$results['policy_effectiveness']}%. Consider reviewing authentication setup.",
                'action' => 'Review SPF and DKIM configuration before policy change'
            ];
        }

        // Check if policy is too aggressive
        $totalBlocked = $results['simulated_quarantined'] + $results['simulated_rejected'];
        $blockPercentage = round(($totalBlocked / $results['total_messages']) * 100, 2);
        
        if ($blockPercentage > 20) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'high',
                'title' => 'Very aggressive policy',
                'description' => "Policy would affect {$blockPercentage}% of all messages. Ensure authentication infrastructure is robust.",
                'action' => 'Verify SPF, DKIM, and DMARC setup before implementation'
            ];
        }

        // Specific recommendations for policy parameters
        if ($simulatedPolicy['p'] === 'reject' && $currentPolicy['p'] !== 'quarantine') {
            $recommendations[] = [
                'type' => 'process',
                'priority' => 'medium',
                'title' => 'Consider intermediate quarantine step',
                'description' => 'Moving directly to p=reject. Consider using p=quarantine first to validate impact.',
                'action' => 'Implement p=quarantine for 30-60 days before p=reject'
            ];
        }

        return $recommendations;
    }

    /**
     * Get simulation by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function getSimulation(int $id): ?array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                ps.*,
                d.domain
            FROM policy_simulations ps
            JOIN domains d ON ps.domain_id = d.id
            WHERE ps.id = :id
        ');

        $db->bind(':id', $id);
        return $db->single();
    }
}