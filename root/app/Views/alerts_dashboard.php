<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: alerts_dashboard.php
 * Description: Real-time alerting dashboard
 */

require 'partials/header.php';

function getSeverityClass($severity) {
    switch ($severity) {
        case 'critical': return 'label-error';
        case 'high': return 'label-warning';
        case 'medium': return 'label-primary';
        case 'low': return 'label-secondary';
        default: return 'label-secondary';
    }
}

function getIncidentStatusClass($status) {
    switch ($status) {
        case 'open': return 'label-error';
        case 'acknowledged': return 'label-warning';
        case 'resolved': return 'label-success';
        default: return 'label-secondary';
    }
}
?>

<style>
.alert-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}
.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}
.incident-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    background: rgba(220, 53, 69, 0.05);
}
.incident-item:last-child {
    border-bottom: none;
}
.rule-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
}
.rule-item:last-child {
    border-bottom: none;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-flag text-primary mr-2"></i>
                Alerting Dashboard
            </h2>
            <div>
                <a href="/alerts?action=create-rule" class="btn btn-primary mr-1">
                    <i class="icon icon-plus"></i> New Rule
                </a>
                <form method="POST" action="/alerts" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="action" value="test_alerts">
                    <button type="submit" class="btn btn-success">
                        <i class="icon icon-refresh"></i> Test Alerts
                    </button>
                </form>
            </div>
        </div>
        <p class="text-gray">Monitor DMARC authentication in real-time with custom threshold rules and instant notifications.</p>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['total_rules'] ?></div>
        <div class="stat-label">Total Rules</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['enabled_rules'] ?></div>
        <div class="stat-label">Enabled Rules</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['open_incidents'] ?></div>
        <div class="stat-label">Open Incidents</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['critical_incidents'] ?></div>
        <div class="stat-label">Critical Incidents</div>
    </div>
</div>

<div class="columns">
    <!-- Active Incidents -->
    <div class="column col-12 col-lg-8">
        <div class="alert-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4>
                    <i class="icon icon-flag mr-1"></i>
                    Active Incidents
                </h4>
                <a href="/alerts?action=incidents" class="btn btn-link btn-sm">View All</a>
            </div>
            
            <?php if (empty($this->data['incidents'])): ?>
                <div class="empty">
                    <div class="empty-icon">
                        <i class="icon icon-4x icon-check text-success"></i>
                    </div>
                    <p class="empty-title h5">All Clear</p>
                    <p class="empty-subtitle">No active incidents at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($this->data['incidents'], 0, 5) as $incident): ?>
                    <div class="incident-item">
                        <div>
                            <strong><?= htmlspecialchars($incident['rule_name']) ?></strong>
                            <br><small class="text-gray"><?= htmlspecialchars($incident['message']) ?></small>
                            <br><small class="text-gray">Triggered: <?= date('M j, Y H:i', strtotime($incident['triggered_at'])) ?></small>
                        </div>
                        <div class="text-right">
                            <span class="label <?= getSeverityClass($incident['severity']) ?>"><?= ucfirst($incident['severity']) ?></span>
                            <br><span class="label <?= getIncidentStatusClass($incident['status']) ?>"><?= ucfirst($incident['status']) ?></span>
                            <br><form method="POST" action="/alerts" style="display: inline; margin-top: 0.5rem;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <input type="hidden" name="action" value="acknowledge_incident">
                                <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                                <?php if ($incident['status'] === 'open'): ?>
                                    <button type="submit" class="btn btn-sm btn-primary">Acknowledge</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alert Rules Summary -->
    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
        <div class="alert-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4>
                    <i class="icon icon-bookmark mr-1"></i>
                    Alert Rules
                </h4>
                <a href="/alerts?action=rules" class="btn btn-link btn-sm">Manage</a>
            </div>
            
            <?php if (empty($this->data['rules'])): ?>
                <div class="empty">
                    <p class="empty-title">No Rules</p>
                    <p class="empty-subtitle">Create your first alert rule to start monitoring.</p>
                    <div class="empty-action">
                        <a href="/alerts?action=create-rule" class="btn btn-primary btn-sm">
                            <i class="icon icon-plus"></i> Create Rule
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($this->data['rules'], 0, 5) as $rule): ?>
                    <div class="rule-item">
                        <div>
                            <strong><?= htmlspecialchars($rule['name']) ?></strong>
                            <br><small class="text-gray"><?= htmlspecialchars($rule['metric']) ?> <?= $rule['threshold_operator'] ?> <?= $rule['threshold_value'] ?></small>
                            <?php if ($rule['incident_count'] > 0): ?>
                                <br><small class="text-error"><?= $rule['incident_count'] ?> open incidents</small>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="label <?= getSeverityClass($rule['severity']) ?>"><?= ucfirst($rule['severity']) ?></span>
                            <br><?php if ($rule['enabled']): ?>
                                <span class="label label-success">Enabled</span>
                            <?php else: ?>
                                <span class="label label-secondary">Disabled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="columns">
    <div class="column col-12">
        <div class="alert-card">
            <h4>Quick Actions</h4>
            <div class="btn-group">
                <a href="/alerts?action=create-rule" class="btn btn-primary">
                    <i class="icon icon-plus"></i> Create Alert Rule
                </a>
                <a href="/alerts?action=rules" class="btn btn-link">
                    <i class="icon icon-bookmark"></i> Manage Rules
                </a>
                <a href="/alerts?action=incidents" class="btn btn-link">
                    <i class="icon icon-flag"></i> View All Incidents
                </a>
                <a href="/analytics" class="btn btn-link">
                    <i class="icon icon-trending-up"></i> Analytics Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>