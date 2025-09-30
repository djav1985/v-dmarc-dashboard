<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Alert incident management view.
 */

require 'partials/header.php';

function incidentSeverity(string $severity): string
{
    return match ($severity) {
        'critical' => 'label-error',
        'high' => 'label-warning',
        'medium' => 'label-primary',
        'low' => 'label-secondary',
        default => 'label-secondary',
    };
}

function incidentStatus(string $status): string
{
    return match ($status) {
        'open' => 'label-error',
        'acknowledged' => 'label-warning',
        'resolved' => 'label-success',
        default => 'label-secondary',
    };
}

?>

<style>
.incident-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    background: #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.incident-actions {
    margin-top: 0.75rem;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-flag icon-2x text-error mr-2"></i>
                Active Incidents
            </h2>
            <a href="/alerts" class="btn btn-link btn-sm"><i class="icon icon-arrow-left"></i> Back to alerts dashboard</a>
        </div>
        <p class="text-gray">Review triggered incidents, investigate anomalies, and acknowledge items once handled.</p>
    </div>
</div>

<?php if (empty($this->data['incidents'])): ?>
    <div class="columns">
        <div class="column col-12">
            <div class="empty">
                <div class="empty-icon"><i class="icon icon-4x icon-check text-success"></i></div>
                <p class="empty-title h5">All clear</p>
                <p class="empty-subtitle">There are no open incidents at this time.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($this->data['incidents'] as $incident): ?>
        <?php $channels = json_decode($incident['notification_channels'] ?? '[]', true) ?? []; ?>
        <div class="columns">
            <div class="column col-12">
                <div class="incident-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="mb-1"><?= htmlspecialchars($incident['rule_name'] ?? 'Alert') ?></h4>
                            <p class="mb-1"><?= htmlspecialchars($incident['message']) ?></p>
                            <small class="text-gray">Triggered at <?= htmlspecialchars($incident['triggered_at']) ?> &middot; Metric value <?= htmlspecialchars((string) $incident['metric_value']) ?> (threshold <?= htmlspecialchars((string) $incident['threshold_value']) ?>)</small>
                            <?php if (!empty($incident['details'])): ?>
                                <?php $details = json_decode($incident['details'], true) ?? []; ?>
                                <?php if (!empty($details)): ?>
                                    <div class="mt-1">
                                        <small class="text-gray">Context:</small>
                                        <ul>
                                            <?php foreach ($details as $key => $value): ?>
                                                <li><small><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key))) ?>: <?= htmlspecialchars((string) $value) ?></small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="mt-1">
                                <small class="text-gray">Channels: <?= empty($channels) ? 'None configured' : htmlspecialchars(implode(', ', $channels)) ?></small>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="label <?= incidentSeverity($incident['severity'] ?? 'medium') ?> mb-1"><?= ucfirst($incident['severity'] ?? 'medium') ?></span>
                            <br>
                            <span class="label <?= incidentStatus($incident['status'] ?? 'open') ?>"><?= ucfirst($incident['status'] ?? 'open') ?></span>
                        </div>
                    </div>

                    <div class="incident-actions">
                        <form method="POST" action="/alerts" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="action" value="acknowledge_incident">
                            <input type="hidden" name="incident_id" value="<?= (int) $incident['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?= ($incident['status'] ?? '') !== 'open' ? 'disabled' : '' ?>>
                                <i class="icon icon-check"></i> Acknowledge
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
