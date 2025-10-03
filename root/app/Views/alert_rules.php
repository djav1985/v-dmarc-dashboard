<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Alert rules management view.
 */

require 'partials/header.php';

function alertSeverityBadge(string $severity): string
{
    return match ($severity) {
        'critical' => 'label-error',
        'high' => 'label-warning',
        'medium' => 'label-primary',
        'low' => 'label-secondary',
        default => 'label-secondary',
    };
}

?>

<style>
    .rules-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1.5rem;
        background: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .rules-table td {
        vertical-align: top;
    }
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-bookmark icon-2x text-primary mr-2"></i>
                Alert Rules
            </h2>
            <div>
                <form method="POST" action="/alerts" class="d-inline mr-1">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="action" value="test_alerts">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="icon icon-refresh"></i> Test Alerts
                    </button>
                </form>
                <a href="/alerts?action=create-rule" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus"></i> Create Rule
                </a>
            </div>
        </div>
        <p class="text-gray">Define automated monitoring rules to detect anomalies and trigger incident notifications.</p>
    </div>
</div>

<div class="columns">
    <div class="column col-12">
        <div class="rules-card">
            <?php if (empty($this->data['rules'])): ?>
                <div class="empty">
                    <div class="empty-icon"><i class="icon icon-4x icon-flag text-gray"></i></div>
                    <p class="empty-title h5">No alert rules configured</p>
                    <p class="empty-subtitle">Create your first rule to begin monitoring DMARC health.</p>
                    <div class="empty-action">
                        <a class="btn btn-primary" href="/alerts?action=create-rule">Create Rule</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table rules-table">
                        <thead>
                            <tr>
                                <th>Name &amp; Description</th>
                                <th>Metric &amp; Threshold</th>
                                <th>Scope</th>
                                <th>Notifications</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->data['rules'] as $rule): ?>
                                <?php $channels = json_decode($rule['notification_channels'] ?? '[]', true) ?? []; ?>
                                <?php $recipients = json_decode($rule['notification_recipients'] ?? '[]', true) ?? []; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($rule['name']) ?></strong>
                                        <br>
                                        <small class="text-gray"><?= htmlspecialchars($rule['description'] ?? '') ?: 'No description' ?></small>
                                        <?php if (!empty($rule['incident_count'])): ?>
                                            <br>
                                            <small class="text-gray">Open incidents: <?= (int) $rule['incident_count'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            Metric: <strong><?= htmlspecialchars(str_replace('_', ' ', $rule['metric'])) ?></strong><br>
                                            Threshold: <?= htmlspecialchars($rule['threshold_operator']) ?> <?= htmlspecialchars((string) $rule['threshold_value']) ?><br>
                                            Window: last <?= (int) $rule['time_window'] ?> minutes
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            Domain: <?= $rule['domain_filter'] !== '' ? htmlspecialchars($rule['domain_filter']) : 'All' ?><br>
                                            Group: <?= !empty($rule['group_name']) ? htmlspecialchars($rule['group_name']) : 'All' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            Channels: <?= empty($channels) ? 'None' : htmlspecialchars(implode(', ', $channels)) ?><br>
                                            Recipients: <?= empty($recipients) ? 'None' : htmlspecialchars(implode(', ', $recipients)) ?><br>
                                            <?php if (!empty($rule['webhook_url'])): ?>
                                                Webhook: <?= htmlspecialchars($rule['webhook_url']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="label <?= alertSeverityBadge($rule['severity']) ?>"><?= ucfirst($rule['severity']) ?></span>
                                        <br>
                                        <span class="label <?= (int) $rule['enabled'] === 1 ? 'label-success' : 'label-secondary' ?> mt-1">
                                            <?= (int) $rule['enabled'] === 1 ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                        <br>
                                        <small class="text-gray">Last incident: <?= $rule['last_incident'] ? htmlspecialchars($rule['last_incident']) : 'None' ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>
