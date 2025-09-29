<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: analytics.php
 * Description: DMARC Dashboard analytics and visualizations
 */

require 'partials/header.php';

// Helper functions
function formatHealthScore($score, $category) {
    $colors = [
        'excellent' => 'text-success',
        'good' => 'text-primary', 
        'warning' => 'text-warning',
        'critical' => 'text-error'
    ];
    $color = $colors[$category] ?? 'text-gray';
    return "<span class=\"$color font-weight-bold\">{$score}%</span>";
}

function getHealthBadge($category, $label) {
    $classes = [
        'excellent' => 'label-success',
        'good' => 'label-primary',
        'warning' => 'label-warning', 
        'critical' => 'label-error'
    ];
    $class = $classes[$category] ?? 'label-secondary';
    return "<span class=\"label $class\">$label</span>";
}
?>

<style>
.analytics-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    padding: 1.5rem;
}
.metric-card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-bottom: 1rem;
}
.metric-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    line-height: 1;
}
.metric-label {
    font-size: 0.9rem;
    opacity: 0.9;
}
.chart-container {
    position: relative;
    height: 300px;
    margin: 1rem 0;
}
.health-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
}
.health-item:last-child {
    border-bottom: none;
}
.threat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    background: rgba(255,0,0,0.02);
}
.navbar-center {
    flex: 1;
    justify-content: center;
}
@media (max-width: 840px) {
    .navbar-center {
        display: none;
    }
    .navbar-brand {
        font-size: 0.9rem;
    }
}
</style>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-bookmark text-primary mr-2"></i>
                Analytics Dashboard
            </h2>
            <div class="text-gray">
                <?= $this->data['filters']['start_date'] ?> to <?= $this->data['filters']['end_date'] ?>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="columns">
    <div class="column col-12">
        <form method="POST" action="/analytics" class="analytics-card mb-2">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <div class="columns">
                <div class="column col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="domain">Domain</label>
                        <select class="form-select" id="domain" name="domain">
                            <option value="">All Domains</option>
                            <?php foreach ($this->data['domains'] as $domain): ?>
                                <option value="<?= htmlspecialchars($domain['domain']) ?>" 
                                    <?= $this->data['filters']['domain'] === $domain['domain'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($domain['domain']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="column col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input type="date" class="form-input" id="start_date" name="start_date" 
                               value="<?= htmlspecialchars($this->data['filters']['start_date']) ?>">
                    </div>
                </div>
                
                <div class="column col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" class="form-input" id="end_date" name="end_date" 
                               value="<?= htmlspecialchars($this->data['filters']['end_date']) ?>">
                    </div>
                </div>
                
                <div class="column col-12 col-sm-6 col-md-12 col-lg-3">
                    <div class="form-group">
                        <label class="form-label d-invisible d-sm-block">&nbsp;</label>
                        <div class="d-flex flex-wrap">
                            <button type="submit" class="btn btn-primary mr-2 mb-1">
                                <i class="icon icon-search"></i> Update
                            </button>
                            <a href="/analytics" class="btn btn-link mb-1">Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics -->
<div class="columns mb-2">
    <div class="column col-3">
        <div class="metric-card">
            <div class="metric-number"><?= number_format($this->data['summary_stats']['total_volume'] ?? 0) ?></div>
            <div class="metric-label">Total Messages</div>
        </div>
    </div>
    <div class="column col-3">
        <div class="metric-card">
            <div class="metric-number"><?= number_format($this->data['summary_stats']['domain_count'] ?? 0) ?></div>
            <div class="metric-label">Monitored Domains</div>
        </div>
    </div>
    <div class="column col-3">
        <div class="metric-card">
            <div class="metric-number"><?= number_format($this->data['summary_stats']['report_count'] ?? 0) ?></div>
            <div class="metric-label">Reports Processed</div>
        </div>
    </div>
    <div class="column col-3">
        <div class="metric-card">
            <div class="metric-number"><?= number_format($this->data['summary_stats']['pass_rate'] ?? 0, 1) ?>%</div>
            <div class="metric-label">Overall Pass Rate</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="columns">
    <!-- Volume Trends -->
    <div class="column col-8">
        <div class="analytics-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-trending-up mr-1"></i>
                    Message Volume Trends
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="volumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Domain Health -->
    <div class="column col-4">
        <div class="analytics-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-shield mr-1"></i>
                    Domain Health Scores
                </div>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($this->data['health_scores'])): ?>
                    <div class="empty">
                        <p class="empty-title">No Data</p>
                        <p class="empty-subtitle">No health data available for the selected period.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($this->data['health_scores'] as $domain): ?>
                        <div class="health-item">
                            <div>
                                <strong><?= htmlspecialchars($domain['domain']) ?></strong>
                                <br><small class="text-gray"><?= number_format($domain['total_volume']) ?> messages</small>
                            </div>
                            <div class="text-right">
                                <?= formatHealthScore($domain['health_score'], $domain['health_category']) ?>
                                <br><?= getHealthBadge($domain['health_category'], $domain['health_label']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Second Charts Row -->
<div class="columns">
    <!-- Compliance Trends -->
    <div class="column col-8">
        <div class="analytics-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-check mr-1"></i>
                    Authentication Compliance Trends
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="complianceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Threats -->
    <div class="column col-4">
        <div class="analytics-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-flag mr-1"></i>
                    Top Threats
                </div>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($this->data['top_threats'])): ?>
                    <div class="empty">
                        <p class="empty-title">No Threats</p>
                        <p class="empty-subtitle">No threatening IPs found in the selected period.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($this->data['top_threats'] as $threat): ?>
                        <div class="threat-item">
                            <div>
                                <code class="text-error"><?= htmlspecialchars($threat['source_ip']) ?></code>
                                <br><small class="text-gray"><?= number_format($threat['threat_volume']) ?> failed messages</small>
                            </div>
                            <div class="text-right">
                                <span class="label label-error"><?= number_format($threat['threat_rate'], 1) ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Prepare data for charts
const trendData = <?= json_encode($this->data['trend_data']) ?>;
const complianceData = <?= json_encode($this->data['compliance_data']) ?>;

// Volume Trends Chart
const volumeCtx = document.getElementById('volumeChart').getContext('2d');
const volumeChart = new Chart(volumeCtx, {
    type: 'line',
    data: {
        labels: trendData.map(d => d.date),
        datasets: [
            {
                label: 'Total Volume',
                data: trendData.map(d => d.total_volume || 0),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Passed',
                data: trendData.map(d => d.passed_count || 0),
                borderColor: '#27d267',
                backgroundColor: 'rgba(39, 210, 103, 0.1)',
                fill: false,
                tension: 0.4
            },
            {
                label: 'Quarantined',
                data: trendData.map(d => d.quarantined_count || 0),
                borderColor: '#ffb82c',
                backgroundColor: 'rgba(255, 184, 44, 0.1)',
                fill: false,
                tension: 0.4
            },
            {
                label: 'Rejected',
                data: trendData.map(d => d.rejected_count || 0),
                borderColor: '#e85656',
                backgroundColor: 'rgba(232, 86, 86, 0.1)',
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Compliance Trends Chart
const complianceCtx = document.getElementById('complianceChart').getContext('2d');
const complianceChart = new Chart(complianceCtx, {
    type: 'line',
    data: {
        labels: complianceData.map(d => d.date),
        datasets: [
            {
                label: 'DMARC Compliance',
                data: complianceData.map(d => d.dmarc_compliance || 0),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: false,
                tension: 0.4
            },
            {
                label: 'DKIM Compliance',
                data: complianceData.map(d => d.dkim_compliance || 0),
                borderColor: '#27d267',
                backgroundColor: 'rgba(39, 210, 103, 0.1)',
                fill: false,
                tension: 0.4
            },
            {
                label: 'SPF Compliance',
                data: complianceData.map(d => d.spf_compliance || 0),
                borderColor: '#ffb82c',
                backgroundColor: 'rgba(255, 184, 44, 0.1)',
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});
</script>

<?php require 'partials/footer.php'; ?>