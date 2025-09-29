<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: report_detail.php
 * Description: Detailed view of a single DMARC report
 */

require 'partials/header.php';

// Helper functions
function formatAuthResult($result, $total = null) {
    if ($total && $total > 0) {
        $percentage = round(($result / $total) * 100);
        $class = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'error');
        return "<span class=\"label label-$class\">$result ($percentage%)</span>";
    }
    
    $class = $result === 'pass' ? 'success' : ($result === 'fail' ? 'error' : 'warning');
    return "<span class=\"label label-$class\">$result</span>";
}

function formatDisposition($disposition) {
    $classes = [
        'none' => 'success',
        'quarantine' => 'warning', 
        'reject' => 'error'
    ];
    $class = $classes[$disposition] ?? 'secondary';
    $display = $disposition === 'none' ? 'pass' : $disposition;
    return "<span class=\"label label-$class\">$display</span>";
}

function formatIPAddress($ip) {
    // Basic validation for IPv4/IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return "<code class=\"text-primary\">$ip</code>";
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return "<code class=\"text-primary\">$ip</code>";
    }
    return "<code>$ip</code>";
}
?>

<style>
.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.stat-box {
    text-align: center;
    padding: 1rem;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
    margin-bottom: 1rem;
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
.ip-group {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 1rem;
    overflow: hidden;
}
.ip-header {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
}
.record-row {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid #f1f3f4;
}
.record-row:last-child {
    border-bottom: none;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-eye text-primary mr-2"></i>
                Report Details
            </h2>
            <a href="/reports" class="btn btn-link">
                <i class="icon icon-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<!-- Report Summary -->
<div class="summary-card">
    <div class="columns">
        <div class="column col-8">
            <h3 class="mb-1"><?= htmlspecialchars($this->data['report']['domain']) ?></h3>
            <p class="mb-1">
                <strong>From:</strong> <?= htmlspecialchars($this->data['report']['org_name']) ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($this->data['report']['email']) ?><br>
                <strong>Report ID:</strong> <code><?= htmlspecialchars($this->data['report']['report_id']) ?></code>
            </p>
            <p class="mb-0">
                <strong>Period:</strong> 
                <?= date('M j, Y', $this->data['report']['date_range_begin']) ?> - 
                <?= date('M j, Y', $this->data['report']['date_range_end']) ?>
                <br>
                <strong>Received:</strong> <?= date('M j, Y H:i', strtotime($this->data['report']['received_at'])) ?>
            </p>
        </div>
        <div class="column col-4">
            <div class="stat-box">
                <div class="stat-number"><?= number_format($this->data['summary']['total_volume']) ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="columns mb-2">
    <div class="column col-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-success"><?= number_format($this->data['summary']['pass_count']) ?></div>
                <small class="text-gray">Passed</small>
            </div>
        </div>
    </div>
    <div class="column col-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-warning"><?= number_format($this->data['summary']['quarantine_count']) ?></div>
                <small class="text-gray">Quarantined</small>
            </div>
        </div>
    </div>
    <div class="column col-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-error"><?= number_format($this->data['summary']['reject_count']) ?></div>
                <small class="text-gray">Rejected</small>
            </div>
        </div>
    </div>
    <div class="column col-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><?= formatAuthResult($this->data['summary']['dkim_pass_count'], $this->data['summary']['total_volume']) ?></div>
                <small class="text-gray">DKIM Pass Rate</small>
            </div>
        </div>
    </div>
    <div class="column col-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><?= formatAuthResult($this->data['summary']['spf_pass_count'], $this->data['summary']['total_volume']) ?></div>
                <small class="text-gray">SPF Pass Rate</small>
            </div>
        </div>
    </div>
</div>

<!-- IP Sources Section -->
<div class="columns">
    <div class="column col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-location mr-1"></i>
                    IP Sources (<?= $this->data['summary']['unique_ips'] ?> unique addresses)
                </div>
            </div>
            <div class="card-body p-0">
                <?php foreach ($this->data['ip_groups'] as $ipGroup): ?>
                    <div class="ip-group">
                        <div class="ip-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?= formatIPAddress($ipGroup['ip']) ?>
                                    <span class="ml-2 text-gray">(<?= count($ipGroup['records']) ?> records)</span>
                                </div>
                                <span class="chip"><?= number_format($ipGroup['total_count']) ?> messages</span>
                            </div>
                        </div>
                        
                        <?php foreach ($ipGroup['records'] as $record): ?>
                            <div class="record-row">
                                <div class="columns">
                                    <div class="column col-2">
                                        <span class="chip"><?= number_format($record['count']) ?></span>
                                    </div>
                                    <div class="column col-2">
                                        <?= formatDisposition($record['disposition']) ?>
                                    </div>
                                    <div class="column col-2">
                                        <small class="text-gray">DKIM:</small>
                                        <?= formatAuthResult($record['dkim_result']) ?>
                                    </div>
                                    <div class="column col-2">
                                        <small class="text-gray">SPF:</small>
                                        <?= formatAuthResult($record['spf_result']) ?>
                                    </div>
                                    <div class="column col-4">
                                        <?php if ($record['header_from']): ?>
                                            <small class="text-gray">From:</small>
                                            <code><?= htmlspecialchars($record['header_from']) ?></code>
                                        <?php endif; ?>
                                        <?php if ($record['envelope_from'] && $record['envelope_from'] !== $record['header_from']): ?>
                                            <br><small class="text-gray">Envelope:</small>
                                            <code><?= htmlspecialchars($record['envelope_from']) ?></code>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Records Table -->
<div class="columns">
    <div class="column col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-mail mr-1"></i>
                    All Authentication Records
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Source IP</th>
                                <th class="text-center">Count</th>
                                <th class="text-center">Disposition</th>
                                <th class="text-center">DKIM</th>
                                <th class="text-center">SPF</th>
                                <th>Header From</th>
                                <th>Envelope From</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->data['records'] as $record): ?>
                                <tr>
                                    <td><?= formatIPAddress($record['source_ip']) ?></td>
                                    <td class="text-center">
                                        <span class="chip"><?= number_format($record['count']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= formatDisposition($record['disposition']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= formatAuthResult($record['dkim_result']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= formatAuthResult($record['spf_result']) ?>
                                    </td>
                                    <td>
                                        <?php if ($record['header_from']): ?>
                                            <code><?= htmlspecialchars($record['header_from']) ?></code>
                                        <?php else: ?>
                                            <span class="text-gray">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['envelope_from']): ?>
                                            <code><?= htmlspecialchars($record['envelope_from']) ?></code>
                                        <?php else: ?>
                                            <span class="text-gray">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>