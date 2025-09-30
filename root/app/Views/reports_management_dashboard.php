<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: reports_management_dashboard.php
 * Description: PDF reports and policy simulation management
 */

require 'partials/header.php';

function getTemplateTypeClass($type) {
    switch ($type) {
        case 'executive': return 'label-primary';
        case 'technical': return 'label-success';
        case 'compliance': return 'label-warning';
        case 'custom': return 'label-secondary';
        default: return 'label-secondary';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function formatScheduleFrequency(string $frequency): string {
    if (str_starts_with($frequency, 'custom:')) {
        $days = (int) substr($frequency, 7);
        return 'Every ' . max(1, $days) . ' days';
    }

    return match ($frequency) {
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        default => ucfirst($frequency),
    };
}

function formatScheduleStatus(array $schedule): string {
    if ((int) ($schedule['enabled'] ?? 0) !== 1) {
        return 'Paused';
    }

    return match ($schedule['last_status'] ?? '') {
        'failed' => 'Last run failed',
        'success' => 'Active',
        default => 'Active',
    };
}

function formatScheduleDate(?string $value): string {
    if (empty($value)) {
        return '—';
    }

    try {
        return date('M j, Y H:i', strtotime($value));
    } catch (Throwable $exception) {
        return $value;
    }
}

function getScheduleRecipients(?string $recipientsJson): string {
    $decoded = json_decode($recipientsJson ?? '[]', true);
    if (!is_array($decoded)) {
        return '';
    }

    return implode(', ', array_filter(array_map('trim', $decoded)));
}
?>

<style>
.management-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}
.stat-label {
    font-size: 0.85rem;
    opacity: 0.9;
}
.feature-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-top: 2rem;
}
.template-item, .generation-item, .simulation-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
}
.template-item:last-child, .generation-item:last-child, .simulation-item:last-child {
    border-bottom: none;
}
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
}
.action-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    background: white;
    transition: box-shadow 0.2s;
}
.action-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
@media (max-width: 768px) {
    .feature-section {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}
.schedule-item {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #fdfdfd;
}
.schedule-item h5 {
    margin: 0 0 0.5rem 0;
}
.schedule-meta {
    font-size: 0.85rem;
    color: #6c757d;
}
.schedule-actions {
    margin-top: 0.75rem;
}
.schedule-actions form {
    display: inline-block;
    margin-right: 0.5rem;
}
.schedule-form .form-group {
    margin-bottom: 0.75rem;
}
.schedule-note {
    font-size: 0.75rem;
    color: #6c757d;
}
</style>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="toast toast-<?= $_SESSION['flash_type'] ?? 'success' ?>">
        <button class="btn btn-clear float-right"></button>
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-bookmark text-primary mr-2"></i>
                Reports Management
            </h2>
            <div>
                <a href="/reports-management?action=generate-pdf" class="btn btn-primary mr-1">
                    <i class="icon icon-download"></i> Generate PDF
                </a>
                <a href="/reports-management?action=create-simulation" class="btn btn-success">
                    <i class="icon icon-apps"></i> New Simulation
                </a>
            </div>
        </div>
        <p class="text-gray">Generate professional PDF reports and simulate DMARC policy changes before implementation.</p>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['total_templates'] ?></div>
        <div class="stat-label">PDF Templates</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['recent_generations'] ?></div>
        <div class="stat-label">Recent Reports</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['total_simulations'] ?></div>
        <div class="stat-label">Policy Simulations</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $this->data['stats']['active_schedules'] ?? 0 ?></div>
        <div class="stat-label">Active Schedules</div>
    </div>
</div>

<!-- Feature Sections -->
<div class="feature-section">
    <!-- PDF Templates & Recent Generations -->
    <div>
        <div class="management-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                <h4>
                    <i class="icon icon-download mr-1"></i>
                    PDF Report Templates
                </h4>
                <a href="/reports-management?action=pdf-templates" class="btn btn-link btn-sm">View All</a>
            </div>
            
            <?php if (empty($this->data['templates'])): ?>
                <div class="empty">
                    <p class="empty-title">No Templates</p>
                    <p class="empty-subtitle">PDF templates will be available for report generation.</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($this->data['templates'], 0, 4) as $template): ?>
                    <div class="template-item">
                        <div>
                            <strong><?= htmlspecialchars($template['name']) ?></strong>
                            <br><small class="text-gray"><?= htmlspecialchars($template['description']) ?></small>
                            <br><small class="text-gray">Used <?= $template['usage_count'] ?> times</small>
                        </div>
                        <div class="text-right">
                            <span class="label <?= getTemplateTypeClass($template['template_type']) ?>">
                                <?= ucfirst($template['template_type']) ?>
                            </span>
                            <br><a href="/reports-management?action=generate-pdf&template_id=<?= $template['id'] ?>" 
                                   class="btn btn-sm btn-primary mt-1">Generate</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent PDF Generations -->
        <div class="management-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                <h4>
                    <i class="icon icon-bookmark mr-1"></i>
                    Recent PDF Reports
                </h4>
            </div>
            
            <?php if (empty($this->data['recent_generations'])): ?>
                <div class="empty">
                    <p class="empty-title">No Reports Generated</p>
                    <p class="empty-subtitle">Generated PDF reports will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($this->data['recent_generations'] as $generation): ?>
                    <div class="generation-item">
                        <div>
                            <strong><?= htmlspecialchars($generation['title']) ?></strong>
                            <br><small class="text-gray"><?= htmlspecialchars($generation['filename']) ?></small>
                            <br><small class="text-gray">
                                <?= date('M j, Y H:i', strtotime($generation['generated_at'])) ?> • 
                                <?= formatFileSize($generation['file_size']) ?>
                            </small>
                        </div>
                        <div class="text-right">
                            <span class="label label-secondary"><?= htmlspecialchars($generation['template_name']) ?></span>
                            <br><small class="text-gray">by <?= htmlspecialchars($generation['generated_by']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Policy Simulations -->
    <div>
        <div class="management-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4>
                    <i class="icon icon-apps mr-1"></i>
                    Policy Simulations
                </h4>
                <a href="/reports-management?action=policy-simulations" class="btn btn-link btn-sm">View All</a>
            </div>
            
            <?php if (empty($this->data['simulations'])): ?>
                <div class="empty">
                    <p class="empty-title">No Simulations</p>
                    <p class="empty-subtitle">Create policy simulations to test DMARC changes safely.</p>
                    <div class="empty-action">
                        <a href="/reports-management?action=create-simulation" class="btn btn-primary btn-sm">
                            <i class="icon icon-plus"></i> Create Simulation
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($this->data['simulations'] as $simulation): ?>
                    <div class="simulation-item">
                        <div>
                            <strong><?= htmlspecialchars($simulation['name']) ?></strong>
                            <br><small class="text-gray"><?= htmlspecialchars($simulation['domain']) ?></small>
                            <br><small class="text-gray">
                                <?= date('M j', strtotime($simulation['simulation_period_start'])) ?> - 
                                <?= date('M j, Y', strtotime($simulation['simulation_period_end'])) ?>
                            </small>
                        </div>
                        <div class="text-right">
                            <?php if (!empty($simulation['results'])): ?>
                                <span class="label label-success">Completed</span>
                            <?php else: ?>
                                <span class="label label-warning">Pending</span>
                            <?php endif; ?>
                            <br><a href="/reports-management?action=view-simulation&id=<?= $simulation['id'] ?>" 
                                   class="btn btn-sm btn-link mt-1">View</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Schedule Management -->
<div class="management-card mt-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <h4>
            <i class="icon icon-calendar mr-1"></i>
            Scheduled PDF Reports
        </h4>
        <span class="label label-secondary">Automation</span>
    </div>
    <div class="columns">
        <div class="column col-7 col-md-12">
            <?php if (empty($this->data['schedules'])): ?>
                <div class="empty">
                    <p class="empty-title">No schedules configured</p>
                    <p class="empty-subtitle">Create a schedule to automatically deliver PDF reports to stakeholders.</p>
                </div>
            <?php else: ?>
                <?php foreach ($this->data['schedules'] as $schedule): ?>
                    <?php
                    $scheduleRecipients = getScheduleRecipients($schedule['recipients'] ?? '[]');
                    $isEnabled = (int) ($schedule['enabled'] ?? 0) === 1;
                    ?>
                    <div class="schedule-item">
                        <h5>
                            <?= htmlspecialchars($schedule['name'] ?? 'Schedule #' . ($schedule['id'] ?? '')) ?>
                            <?php if (!$isEnabled): ?>
                                <span class="label label-warning ml-1">Paused</span>
                            <?php endif; ?>
                        </h5>
                        <div class="schedule-meta">
                            Template: <strong><?= htmlspecialchars($schedule['template_name'] ?? ('Template #' . ($schedule['template_id'] ?? ''))) ?></strong>
                            &middot; Cadence: <?= htmlspecialchars(formatScheduleFrequency($schedule['frequency'] ?? '')) ?>
                            &middot; Recipients: <?= $scheduleRecipients !== '' ? htmlspecialchars($scheduleRecipients) : '—' ?>
                        </div>
                        <div class="schedule-meta">
                            Last Run: <?= htmlspecialchars(formatScheduleDate($schedule['last_run_at'] ?? null)) ?>
                            &middot; Next Run: <?= htmlspecialchars(formatScheduleDate($schedule['next_run_at'] ?? null)) ?>
                            &middot; Status: <?= htmlspecialchars(formatScheduleStatus($schedule)) ?>
                        </div>
                        <?php if (!empty($schedule['last_error'])): ?>
                            <div class="schedule-note text-error mt-1">
                                <?= htmlspecialchars($schedule['last_error']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="schedule-actions mt-2">
                            <form method="post" class="mr-1">
                                <input type="hidden" name="action" value="run_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int) ($schedule['id'] ?? 0) ?>">
                                <button class="btn btn-sm btn-primary" type="submit">
                                    <i class="icon icon-activity"></i> Run Now
                                </button>
                            </form>
                            <form method="post" class="mr-1">
                                <input type="hidden" name="action" value="toggle_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int) ($schedule['id'] ?? 0) ?>">
                                <input type="hidden" name="schedule_enabled" value="<?= $isEnabled ? 0 : 1 ?>">
                                <button class="btn btn-sm btn-<?= $isEnabled ? 'warning' : 'success' ?>" type="submit">
                                    <?= $isEnabled ? 'Pause' : 'Resume' ?>
                                </button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this schedule?');">
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int) ($schedule['id'] ?? 0) ?>">
                                <button class="btn btn-sm btn-error" type="submit">Delete</button>
                            </form>
                        </div>

                        <form method="post" class="schedule-form mt-2">
                            <input type="hidden" name="action" value="update_schedule">
                            <input type="hidden" name="schedule_id" value="<?= (int) ($schedule['id'] ?? 0) ?>">
                            <div class="form-group">
                                <label class="form-label">Display Name</label>
                                <input type="text" class="form-input" name="schedule_name" value="<?= htmlspecialchars($schedule['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Title</label>
                                <input type="text" class="form-input" name="schedule_title" value="<?= htmlspecialchars($schedule['title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Template</label>
                                <select class="form-select" name="schedule_template_id" required>
                                    <?php foreach ($this->data['templates'] as $template): ?>
                                        <option value="<?= (int) $template['id'] ?>" <?= (int) ($schedule['template_id'] ?? 0) === (int) $template['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($template['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cadence</label>
                                <input type="text" class="form-input" name="schedule_frequency" value="<?= htmlspecialchars($schedule['frequency'] ?? 'weekly') ?>" required>
                                <div class="schedule-note">Use daily, weekly, monthly or custom:&lt;days&gt; (e.g. custom:14).</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Recipients</label>
                                <textarea class="form-input" name="schedule_recipients" rows="2" required><?= htmlspecialchars($scheduleRecipients) ?></textarea>
                                <div class="schedule-note">Separate email addresses with commas or line breaks.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Domain Filter (optional)</label>
                                <input type="text" class="form-input" name="schedule_domain_filter" value="<?= htmlspecialchars($schedule['domain_filter'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Group Filter</label>
                                <select class="form-select" name="schedule_group_filter">
                                    <option value="">All Groups</option>
                                    <?php foreach ($this->data['groups'] as $group): ?>
                                        <option value="<?= (int) $group['id'] ?>" <?= (int) ($schedule['group_filter'] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Next Run (optional)</label>
                                <input type="datetime-local" class="form-input" name="schedule_start_at" value="<?= !empty($schedule['next_run_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($schedule['next_run_at']))) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-switch">
                                    <input type="checkbox" name="schedule_enabled" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                                    <i class="form-icon"></i> Enabled
                                </label>
                            </div>
                            <button class="btn btn-sm btn-secondary" type="submit">Save Changes</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="column col-5 col-md-12">
            <h5>Create New Schedule</h5>
            <form method="post" class="schedule-form">
                <input type="hidden" name="action" value="create_schedule">
                <div class="form-group">
                    <label class="form-label">Schedule Name</label>
                    <input type="text" class="form-input" name="schedule_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Title</label>
                    <input type="text" class="form-input" name="schedule_title" placeholder="Defaults to schedule name">
                </div>
                <div class="form-group">
                    <label class="form-label">Template</label>
                    <select class="form-select" name="schedule_template_id" required>
                        <option value="">Select Template</option>
                        <?php foreach ($this->data['templates'] as $template): ?>
                            <option value="<?= (int) $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cadence</label>
                    <input type="text" class="form-input" name="schedule_frequency" value="weekly" required>
                    <div class="schedule-note">Use daily, weekly, monthly or custom:&lt;days&gt; (e.g. custom:10).</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Recipients</label>
                    <textarea class="form-input" name="schedule_recipients" rows="3" placeholder="security@example.com" required></textarea>
                    <div class="schedule-note">Separate multiple recipients with commas or new lines.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Domain Filter (optional)</label>
                    <input type="text" class="form-input" name="schedule_domain_filter" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Group Filter</label>
                    <select class="form-select" name="schedule_group_filter">
                        <option value="">All Groups</option>
                        <?php foreach ($this->data['groups'] as $group): ?>
                            <option value="<?= (int) $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">First Run (optional)</label>
                    <input type="datetime-local" class="form-input" name="schedule_start_at">
                </div>
                <button class="btn btn-primary" type="submit">
                    <i class="icon icon-plus"></i> Create Schedule
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <div class="action-card">
        <i class="icon icon-3x icon-download text-primary mb-2"></i>
        <h5>Executive Report</h5>
        <p class="text-gray">Generate a high-level summary report for executive leadership with key metrics and recommendations.</p>
        <a href="/reports-management?action=generate-pdf&template_id=1" class="btn btn-primary">Generate Now</a>
    </div>

    <div class="action-card">
        <i class="icon icon-3x icon-bookmark text-success mb-2"></i>
        <h5>Technical Analysis</h5>
        <p class="text-gray">Create detailed technical reports with comprehensive analytics, IP analysis, and authentication breakdowns.</p>
        <a href="/reports-management?action=generate-pdf&template_id=2" class="btn btn-success">Generate Now</a>
    </div>

    <div class="action-card">
        <i class="icon icon-3x icon-flag text-warning mb-2"></i>
        <h5>Compliance Report</h5>
        <p class="text-gray">Generate regulatory compliance focused reports with audit trails and policy adherence metrics.</p>
        <a href="/reports-management?action=generate-pdf&template_id=3" class="btn btn-warning">Generate Now</a>
    </div>

    <div class="action-card">
        <i class="icon icon-3x icon-apps text-secondary mb-2"></i>
        <h5>Policy Simulation</h5>
        <p class="text-gray">Test DMARC policy changes safely by simulating their impact on historical data before implementation.</p>
        <a href="/reports-management?action=create-simulation" class="btn btn-secondary">Create Simulation</a>
    </div>
</div>

<!-- Management Links -->
<div class="columns mt-2">
    <div class="column col-12">
        <div class="management-card">
            <h4>Management Tools</h4>
            <div class="btn-group">
                <a href="/reports-management?action=pdf-templates" class="btn btn-link">
                    <i class="icon icon-bookmark"></i> Manage Templates
                </a>
                <a href="/reports-management?action=policy-simulations" class="btn btn-link">
                    <i class="icon icon-apps"></i> View All Simulations
                </a>
                <a href="/analytics" class="btn btn-link">
                    <i class="icon icon-trending-up"></i> Analytics Dashboard
                </a>
                <a href="/domain-groups" class="btn btn-link">
                    <i class="icon icon-people"></i> Domain Groups
                </a>
            </div>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>