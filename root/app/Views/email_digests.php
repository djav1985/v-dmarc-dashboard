<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Email digest scheduling view.
 */

require 'partials/header.php';

function formatFrequency(string $frequency): string
{
    if (str_starts_with($frequency, 'custom:')) {
        $parts = explode(':', $frequency, 2);
        $days = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 7;
        return 'Custom (' . $days . ' day' . ($days === 1 ? '' : 's') . ')';
    }

    return match ($frequency) {
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        default => ucfirst($frequency),
    };
}

?>

<style>
.digest-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    background: #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.digest-form .form-group {
    margin-bottom: 1rem;
}
.digest-table td {
    vertical-align: top;
}
.badge-enabled {
    background-color: #32b643;
    color: #fff;
}
.badge-disabled {
    background-color: #e85600;
    color: #fff;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-mail icon-2x text-primary mr-2"></i>
                Email Digest Scheduling
            </h2>
            <span class="text-gray">Configure automated weekly, monthly, or custom summary emails.</span>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column col-12 col-lg-7">
        <div class="digest-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0">
                    <i class="icon icon-timer"></i> Scheduled Digests
                </h4>
                <span class="label label-rounded label-secondary">
                    <?= count($this->data['schedules']) ?> Active Schedule<?= count($this->data['schedules']) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($this->data['schedules'])): ?>
                <div class="empty">
                    <div class="empty-icon"><i class="icon icon-4x icon-calendar text-gray"></i></div>
                    <p class="empty-title h5">No digests yet</p>
                    <p class="empty-subtitle">Create a schedule to start delivering recurring insights to your stakeholders.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table digest-table">
                        <thead>
                            <tr>
                                <th>Name &amp; Recipients</th>
                                <th>Frequency</th>
                                <th>Filters</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->data['schedules'] as $schedule): ?>
                                <?php $recipientList = json_decode($schedule['recipients'] ?? '[]', true) ?? []; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($schedule['name']) ?></strong>
                                        <br>
                                        <small class="text-gray">Recipients: <?= htmlspecialchars(implode(', ', $recipientList)) ?: 'None' ?></small>
                                        <?php if (!empty($schedule['sent_count'])): ?>
                                            <br><small class="text-gray">Total sends: <?= (int) $schedule['sent_count'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="chip">
                                            <?= htmlspecialchars(formatFrequency($schedule['frequency'])) ?>
                                        </span>
                                        <br>
                                        <small class="text-gray">Next run:
                                            <?= $schedule['next_scheduled'] ? htmlspecialchars($schedule['next_scheduled']) : 'Pending' ?></small>
                                        <br>
                                        <small class="text-gray">Last sent:
                                            <?= $schedule['last_sent'] ? htmlspecialchars($schedule['last_sent']) : 'Never' ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            Domain: <?= $schedule['domain_filter'] !== '' ? htmlspecialchars($schedule['domain_filter']) : 'All' ?><br>
                                            Group: <?= !empty($schedule['group_name']) ? htmlspecialchars($schedule['group_name']) : 'All' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ((int) $schedule['enabled'] === 1): ?>
                                            <span class="label badge-enabled">Enabled</span>
                                        <?php else: ?>
                                            <span class="label badge-disabled">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="/email-digests" class="mb-1">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="toggle_schedule">
                                            <input type="hidden" name="schedule_id" value="<?= (int) $schedule['id'] ?>">
                                            <input type="hidden" name="desired_state" value="<?= (int) $schedule['enabled'] === 1 ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= (int) $schedule['enabled'] === 1 ? 'btn-warning' : 'btn-success' ?>">
                                                <?= (int) $schedule['enabled'] === 1 ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="column col-12 col-lg-5">
        <div class="digest-card digest-form">
            <h4 class="mb-2">
                <i class="icon icon-plus"></i> Create Digest Schedule
            </h4>
            <form method="POST" action="/email-digests">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create_schedule">

                <div class="form-group">
                    <label class="form-label" for="digest_name">Schedule Name</label>
                    <input type="text" class="form-input" id="digest_name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="frequency">Frequency</label>
                    <select class="form-select" id="frequency" name="frequency">
                        <option value="daily">Daily Summary</option>
                        <option value="weekly" selected>Weekly Digest</option>
                        <option value="monthly">Monthly Digest</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <div class="form-group" id="custom-range-group" style="display: none;">
                    <label class="form-label" for="custom_range_days">Custom Range (days)</label>
                    <input type="number" min="1" max="90" value="7" class="form-input" id="custom_range_days" name="custom_range_days">
                </div>

                <div class="form-group">
                    <label class="form-label" for="recipients">Recipients</label>
                    <input type="text" class="form-input" id="recipients" name="recipients" placeholder="Comma-separated email addresses" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="domain_filter">Domain Filter</label>
                    <input type="text" class="form-input" id="domain_filter" name="domain_filter" placeholder="example.com (optional)">
                </div>

                <div class="form-group">
                    <label class="form-label" for="group_filter">Domain Group</label>
                    <select class="form-select" id="group_filter" name="group_filter">
                        <option value="">All Groups</option>
                        <?php foreach ($this->data['groups'] as $group): ?>
                            <option value="<?= (int) $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="first_send_at">First Send Time</label>
                    <input type="datetime-local" class="form-input" id="first_send_at" name="first_send_at">
                    <small class="form-input-hint">Leave blank to schedule automatically.</small>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="start_immediately">
                        <i class="form-icon"></i> Send on next cron run
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="enabled" checked>
                        <i class="form-icon"></i> Enable schedule upon creation
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="icon icon-send"></i> Save Schedule
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const frequencySelect = document.getElementById('frequency');
    const customGroup = document.getElementById('custom-range-group');

    function toggleCustomRange() {
        if (!frequencySelect || !customGroup) {
            return;
        }

        customGroup.style.display = frequencySelect.value === 'custom' ? 'block' : 'none';
    }

    if (frequencySelect) {
        frequencySelect.addEventListener('change', toggleCustomRange);
        toggleCustomRange();
    }
})();
</script>

<?php require 'partials/footer.php'; ?>
