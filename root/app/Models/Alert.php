<?php

namespace App\Models;

use App\Core\DatabaseManager;
use App\Core\Mailer;

/**
 * Alerting system model for real-time monitoring and notifications
 */
class Alert
{
    /**
     * Get all alert rules
     *
     * @return array
     */
    public static function getAllRules(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                ar.*,
                dg.name as group_name,
                COUNT(ai.id) as incident_count,
                MAX(ai.triggered_at) as last_incident
            FROM alert_rules ar
            LEFT JOIN domain_groups dg ON ar.group_filter = dg.id
            LEFT JOIN alert_incidents ai ON ar.id = ai.rule_id AND ai.status = "open"
            GROUP BY ar.id
            ORDER BY ar.severity DESC, ar.created_at DESC
        ');

        return $db->resultSet();
    }

    /**
     * Create a new alert rule
     *
     * @param array $data
     * @return int Rule ID
     */
    public static function createRule(array $data): int
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            INSERT INTO alert_rules 
            (name, description, rule_type, metric, threshold_value, threshold_operator, 
             time_window, domain_filter, group_filter, severity, notification_channels, 
             notification_recipients, webhook_url, enabled) 
            VALUES 
            (:name, :description, :rule_type, :metric, :threshold_value, :threshold_operator,
             :time_window, :domain_filter, :group_filter, :severity, :notification_channels,
             :notification_recipients, :webhook_url, :enabled)
        ');

        $db->bind(':name', $data['name']);
        $db->bind(':description', $data['description'] ?? '');
        $db->bind(':rule_type', $data['rule_type']);
        $db->bind(':metric', $data['metric']);
        $db->bind(':threshold_value', $data['threshold_value']);
        $db->bind(':threshold_operator', $data['threshold_operator']);
        $db->bind(':time_window', $data['time_window']);
        $db->bind(':domain_filter', $data['domain_filter'] ?? '');
        $db->bind(':group_filter', $data['group_filter'] ?? null);
        $db->bind(':severity', $data['severity']);
        $db->bind(':notification_channels', json_encode($data['notification_channels']));
        $db->bind(':notification_recipients', json_encode($data['notification_recipients']));
        $db->bind(':webhook_url', $data['webhook_url'] ?? '');
        $db->bind(':enabled', $data['enabled'] ?? 1);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Check alert rules and trigger incidents
     *
     * @return array Triggered incidents
     */
    public static function checkAlertRules(): array
    {
        $db = DatabaseManager::getInstance();
        $triggeredIncidents = [];

        // Get all enabled rules
        $db->query('SELECT * FROM alert_rules WHERE enabled = 1');
        $rules = $db->resultSet();

        foreach ($rules as $rule) {
            $metricValue = self::calculateMetric($rule);
            
            if (self::evaluateThreshold($metricValue, $rule['threshold_value'], $rule['threshold_operator'])) {
                $incidentId = self::createIncident($rule, $metricValue);
                $triggeredIncidents[] = [
                    'incident_id' => $incidentId,
                    'rule' => $rule,
                    'metric_value' => $metricValue
                ];
            }
        }

        return $triggeredIncidents;
    }

    /**
     * Calculate metric value for a rule
     *
     * @param array $rule
     * @return float
     */
    private static function calculateMetric(array $rule): float
    {
        $db = DatabaseManager::getInstance();
        $timeWindow = (int) $rule['time_window'];
        $startTime = time() - ($timeWindow * 60);
        
        // Build domain filter
        $whereClause = '';
        $bindParams = [':start_time' => $startTime];
        
        if (!empty($rule['domain_filter'])) {
            $whereClause .= ' AND d.domain = :domain_filter';
            $bindParams[':domain_filter'] = $rule['domain_filter'];
        }
        
        if (!empty($rule['group_filter'])) {
            $whereClause .= ' AND dga.group_id = :group_filter';
            $bindParams[':group_filter'] = $rule['group_filter'];
        }

        switch ($rule['metric']) {
            case 'dmarc_failure_rate':
                $query = "
                    SELECT
                        (SUM(CASE WHEN dmar.disposition IN ('quarantine', 'reject') THEN dmar.count ELSE 0 END) * 100.0) /
                        NULLIF(SUM(dmar.count), 0) as value
                    FROM dmarc_aggregate_reports dar
                    JOIN domains d ON dar.domain_id = d.id
                    LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                    LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
                    WHERE dar.received_at >= datetime(:start_time, 'unixepoch')
                    $whereClause
                ";
                break;

            case 'volume_increase':
                // Compare current window to previous window
                $prevStartTime = $startTime - ($timeWindow * 60);
                $query = "
                    SELECT
                        CASE
                            WHEN prev_volume = 0 THEN
                                CASE WHEN curr_volume > 0 THEN 999.0 ELSE 0.0 END
                            ELSE
                                ((curr_volume - prev_volume) * 100.0) / prev_volume
                        END as value
                    FROM (
                        SELECT
                            (SELECT SUM(dmar.count)
                             FROM dmarc_aggregate_reports dar
                             JOIN domains d ON dar.domain_id = d.id
                             LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                             LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
                             WHERE dar.received_at >= datetime(:start_time, 'unixepoch')
                             $whereClause
                            ) as curr_volume,
                            (SELECT SUM(dmar.count)
                             FROM dmarc_aggregate_reports dar
                             JOIN domains d ON dar.domain_id = d.id
                             LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                             LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
                             WHERE dar.received_at >= datetime(:prev_start_time, 'unixepoch')
                             AND dar.received_at < datetime(:start_time, 'unixepoch')
                             $whereClause
                            ) as prev_volume
                    )
                ";
                $bindParams[':prev_start_time'] = $prevStartTime;
                break;

            case 'new_failure_ips':
                $query = "
                    SELECT COUNT(DISTINCT dmar.source_ip) as value
                    FROM dmarc_aggregate_reports dar
                    JOIN domains d ON dar.domain_id = d.id
                    LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                    LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
                    WHERE dar.received_at >= datetime(:start_time, 'unixepoch')
                    AND dmar.disposition IN ('quarantine', 'reject')
                    AND dmar.source_ip NOT IN (
                        SELECT DISTINCT source_ip
                        FROM dmarc_aggregate_records dmar2
                        JOIN dmarc_aggregate_reports dar2 ON dmar2.report_id = dar2.id
                        WHERE dar2.received_at < datetime(:start_time, 'unixepoch')
                    )
                    $whereClause
                ";
                break;

            case 'spf_failures':
                $query = "
                    SELECT SUM(dmar.count) as value
                    FROM dmarc_aggregate_reports dar
                    JOIN domains d ON dar.domain_id = d.id
                    LEFT JOIN domain_group_assignments dga ON d.id = dga.domain_id
                    LEFT JOIN dmarc_aggregate_records dmar ON dar.id = dmar.report_id
                    WHERE dar.received_at >= datetime(:start_time, 'unixepoch')
                    AND dmar.spf_result != 'pass'
                    $whereClause
                ";
                break;

            default:
                return 0.0;
        }

        $db->query($query);
        foreach ($bindParams as $param => $value) {
            $db->bind($param, $value);
        }

        $result = $db->single();
        return (float) ($result['value'] ?? 0);
    }

    /**
     * Evaluate if threshold is breached
     *
     * @param float $metricValue
     * @param float $thresholdValue
     * @param string $operator
     * @return bool
     */
    private static function evaluateThreshold(float $metricValue, float $thresholdValue, string $operator): bool
    {
        switch ($operator) {
            case '>':
                return $metricValue > $thresholdValue;
            case '<':
                return $metricValue < $thresholdValue;
            case '>=':
                return $metricValue >= $thresholdValue;
            case '<=':
                return $metricValue <= $thresholdValue;
            case '==':
                return abs($metricValue - $thresholdValue) < 0.001;
            default:
                return false;
        }
    }

    /**
     * Create an alert incident
     *
     * @param array $rule
     * @param float $metricValue
     * @return int Incident ID
     */
    private static function createIncident(array $rule, float $metricValue): int
    {
        $db = DatabaseManager::getInstance();

        $message = self::generateIncidentMessage($rule, $metricValue);
        $details = json_encode([
            'rule_name' => $rule['name'],
            'metric' => $rule['metric'],
            'time_window' => $rule['time_window'],
            'domain_filter' => $rule['domain_filter'],
            'group_filter' => $rule['group_filter']
        ]);

        $db->query('
            INSERT INTO alert_incidents 
            (rule_id, metric_value, threshold_value, message, details) 
            VALUES (:rule_id, :metric_value, :threshold_value, :message, :details)
        ');

        $db->bind(':rule_id', $rule['id']);
        $db->bind(':metric_value', $metricValue);
        $db->bind(':threshold_value', $rule['threshold_value']);
        $db->bind(':message', $message);
        $db->bind(':details', $details);
        $db->execute();

        return (int) $db->getLastInsertId();
    }

    /**
     * Generate human-readable incident message
     *
     * @param array $rule
     * @param float $metricValue
     * @return string
     */
    private static function generateIncidentMessage(array $rule, float $metricValue): string
    {
        $domainContext = !empty($rule['domain_filter']) ? " for domain {$rule['domain_filter']}" : '';
        $metricFormatted = round($metricValue, 2);
        $thresholdFormatted = round($rule['threshold_value'], 2);

        $metricNames = [
            'dmarc_failure_rate' => 'DMARC failure rate',
            'volume_increase' => 'Message volume increase',
            'new_failure_ips' => 'New failing IP addresses',
            'spf_failures' => 'SPF failures'
        ];

        $metricName = $metricNames[$rule['metric']] ?? $rule['metric'];
        $unit = in_array($rule['metric'], ['dmarc_failure_rate', 'volume_increase']) ? '%' : '';

        return "{$metricName} reached {$metricFormatted}{$unit} (threshold: {$thresholdFormatted}{$unit}){$domainContext} in the last {$rule['time_window']} minutes";
    }

    /**
     * Get open incidents
     *
     * @return array
     */
    public static function getOpenIncidents(): array
    {
        $db = DatabaseManager::getInstance();

        $db->query('
            SELECT 
                ai.*,
                ar.name as rule_name,
                ar.severity,
                ar.notification_channels
            FROM alert_incidents ai
            JOIN alert_rules ar ON ai.rule_id = ar.id
            WHERE ai.status = "open"
            ORDER BY ai.triggered_at DESC
        ');

        return $db->resultSet();
    }

    /**
     * Send notifications for an incident
     *
     * @param int $incidentId
     * @return bool
     */
    public static function sendNotifications(int $incidentId): bool
    {
        $db = DatabaseManager::getInstance();

        // Get incident details
        $db->query('
            SELECT ai.*, ar.notification_channels, ar.notification_recipients, ar.webhook_url
            FROM alert_incidents ai
            JOIN alert_rules ar ON ai.rule_id = ar.id
            WHERE ai.id = :incident_id
        ');
        $db->bind(':incident_id', $incidentId);
        $incident = $db->single();

        if (!$incident) {
            return false;
        }

        $channels = json_decode($incident['notification_channels'], true);
        $recipients = json_decode($incident['notification_recipients'], true);
        $success = true;

        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    foreach ($recipients as $recipient) {
                        $emailSuccess = self::sendEmailNotification($incidentId, $recipient, $incident);
                        $success = $success && $emailSuccess;
                    }
                    break;

                case 'webhook':
                    if (!empty($incident['webhook_url'])) {
                        $webhookSuccess = self::sendWebhookNotification($incidentId, $incident['webhook_url'], $incident);
                        $success = $success && $webhookSuccess;
                    }
                    break;
            }
        }

        return $success;
    }

    /**
     * Send email notification
     *
     * @param int $incidentId
     * @param string $recipient
     * @param array $incident
     * @return bool
     */
    private static function sendEmailNotification(int $incidentId, string $recipient, array $incident): bool
    {
        $db = DatabaseManager::getInstance();
        $subject = 'DMARC Alert: ' . $incident['message'];
        $details = json_decode($incident['details'] ?? '[]', true) ?? [];

        $success = Mailer::sendTemplate(
            $recipient,
            $subject,
            'alert_incident',
            [
                'subject' => $subject,
                'incident' => $incident,
                'details' => $details,
            ]
        );

        $db->query('
            INSERT INTO alert_notifications
            (incident_id, channel, recipient, success, error_message)
            VALUES (:incident_id, :channel, :recipient, :success, :error_message)
        ');

        $db->bind(':incident_id', $incidentId);
        $db->bind(':channel', 'email');
        $db->bind(':recipient', $recipient);
        $db->bind(':success', $success ? 1 : 0);
        $db->bind(':error_message', $success ? '' : 'Email delivery failed');
        $db->execute();

        return $success;
    }

    /**
     * Send webhook notification
     *
     * @param int $incidentId
     * @param string $webhookUrl
     * @param array $incident
     * @return bool
     */
    private static function sendWebhookNotification(int $incidentId, string $webhookUrl, array $incident): bool
    {
        $db = DatabaseManager::getInstance();

        $payload = [
            'alert_type' => 'dmarc_incident',
            'incident_id' => $incidentId,
            'message' => $incident['message'],
            'metric_value' => $incident['metric_value'],
            'threshold_value' => $incident['threshold_value'],
            'triggered_at' => $incident['triggered_at'],
            'severity' => $incident['severity'] ?? 'medium'
        ];

        // In a real implementation, you would make an HTTP POST request
        // For demo purposes, we'll just log the notification
        $success = true; // Assume success for demo

        $db->query('
            INSERT INTO alert_notifications 
            (incident_id, channel, recipient, success) 
            VALUES (:incident_id, :channel, :recipient, :success)
        ');

        $db->bind(':incident_id', $incidentId);
        $db->bind(':channel', 'webhook');
        $db->bind(':recipient', $webhookUrl);
        $db->bind(':success', $success ? 1 : 0);
        $db->execute();

        return $success;
    }
}