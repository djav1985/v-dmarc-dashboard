<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Alert rule creation view.
 */

require 'partials/header.php';

?>

<style>
.rule-form-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    background: #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.rule-form-card .form-group {
    margin-bottom: 1rem;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-plus icon-2x text-primary mr-2"></i>
                Create Alert Rule
            </h2>
            <a href="/alerts?action=rules" class="btn btn-link btn-sm"><i class="icon icon-arrow-left"></i> Back to rules</a>
        </div>
        <p class="text-gray">Define the conditions that should trigger an incident and how notifications are delivered.</p>
    </div>
</div>

<div class="columns">
    <div class="column col-12 col-lg-8">
        <div class="rule-form-card">
            <form method="POST" action="/alerts">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create_rule">

                <div class="form-group">
                    <label class="form-label" for="rule_name">Rule Name</label>
                    <input type="text" class="form-input" id="rule_name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rule_description">Description</label>
                    <textarea class="form-input" id="rule_description" name="description" rows="2" placeholder="Explain the purpose of this alert"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="metric">Metric</label>
                    <select class="form-select" id="metric" name="metric" required>
                        <option value="dmarc_failure_rate">DMARC failure rate (%)</option>
                        <option value="volume_increase">Volume increase (%)</option>
                        <option value="new_failure_ips">New failing IPs</option>
                        <option value="spf_failures">SPF failures</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="threshold_operator">Threshold</label>
                    <div class="input-group">
                        <select class="form-select" id="threshold_operator" name="threshold_operator">
                            <option value=">">&gt;</option>
                            <option value=">=">&gt;=</option>
                            <option value="<">&lt;</option>
                            <option value="<=">&lt;=</option>
                            <option value="==">Equals</option>
                        </select>
                        <input type="number" step="0.01" class="form-input" name="threshold_value" value="10" required>
                    </div>
                    <small class="form-input-hint">Compare the selected metric against this value.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="time_window">Time Window (minutes)</label>
                    <input type="number" min="5" step="5" class="form-input" id="time_window" name="time_window" value="60" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="domain_filter">Domain Filter</label>
                    <input type="text" class="form-input" id="domain_filter" name="domain_filter" placeholder="example.com (optional)">
                </div>

                <div class="form-group">
                    <label class="form-label" for="group_filter">Domain Group</label>
                    <select class="form-select" id="group_filter" name="group_filter">
                        <option value="">All groups</option>
                        <?php foreach ($this->data['groups'] as $group): ?>
                            <option value="<?= (int) $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="severity">Severity</label>
                    <select class="form-select" id="severity" name="severity">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notification Channels</label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="notification_channels[]" value="email" checked>
                        <i class="form-icon"></i> Email
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="notification_channels[]" value="webhook" id="channel_webhook">
                        <i class="form-icon"></i> Webhook
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notification_recipients">Notification Recipients</label>
                    <input type="text" class="form-input" id="notification_recipients" name="notification_recipients" placeholder="Comma-separated email addresses">
                </div>

                <div class="form-group" id="webhook-url-group" style="display: none;">
                    <label class="form-label" for="webhook_url">Webhook URL</label>
                    <input type="url" class="form-input" id="webhook_url" name="webhook_url" placeholder="https://example.com/webhook">
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="enabled" checked>
                        <i class="form-icon"></i> Enable this rule immediately
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-check"></i> Save Rule
                </button>
            </form>
        </div>
    </div>

    <div class="column col-12 col-lg-4">
        <div class="rule-form-card">
            <h4 class="mb-2"><i class="icon icon-info"></i> Tips</h4>
            <ul>
                <li><small>Combine domain or group filters to focus alerts on specific business units.</small></li>
                <li><small>Use higher thresholds for informational alerts and lower thresholds for critical incidents.</small></li>
                <li><small>Multiple recipients can be provided using commas.</small></li>
            </ul>
        </div>
    </div>
</div>

<script>
(function () {
    const webhookCheckbox = document.getElementById('channel_webhook');
    const webhookGroup = document.getElementById('webhook-url-group');

    function toggleWebhookField() {
        if (!webhookCheckbox || !webhookGroup) {
            return;
        }

        webhookGroup.style.display = webhookCheckbox.checked ? 'block' : 'none';
    }

    if (webhookCheckbox) {
        webhookCheckbox.addEventListener('change', toggleWebhookField);
        toggleWebhookField();
    }
})();
</script>

<?php require 'partials/footer.php'; ?>
