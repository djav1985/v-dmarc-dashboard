<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

namespace App\Services;

use App\Models\Alert;
use Throwable;

/**
 * Service helper for executing alert rule checks across the application.
 */
class AlertService
{
    /**
     * Evaluate all enabled alert rules and dispatch notifications for new incidents.
     *
     * @return array<int, array<string, mixed>> Summary of processed incidents.
     */
    public static function runAlertChecks(): array
    {
        $results = [];

        try {
            $triggeredIncidents = Alert::checkAlertRules();
        } catch (Throwable $exception) {
            // Surface the exception upstream for logging while preserving compatibility.
            throw $exception;
        }

        foreach ($triggeredIncidents as $incident) {
            $incidentId = $incident['incident_id'] ?? null;
            if ($incidentId === null) {
                continue;
            }

            $notificationsSent = false;

            try {
                $notificationsSent = Alert::sendNotifications($incidentId);
            } catch (Throwable $exception) {
                // Allow notification failures to bubble up for diagnostics while
                // still returning the incident context to the caller.
                throw $exception;
            }

            $results[] = [
                'incident_id' => $incidentId,
                'rule' => $incident['rule'] ?? [],
                'metric_value' => $incident['metric_value'] ?? null,
                'notifications_sent' => $notificationsSent,
            ];
        }

        return $results;
    }
}
