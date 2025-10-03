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
function formatAuthResult($result, $total = null)
{
    if ($total && $total > 0) {
        $percentage = round(($result / $total) * 100);
        $class = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'error');
        return "<span class=\"label label-$class\">$result ($percentage%)</span>";
    }

    $class = $result === 'pass' ? 'success' : ($result === 'fail' ? 'error' : 'warning');
    return "<span class=\"label label-$class\">$result</span>";
}

function formatDisposition($disposition)
{
    $classes = [
        'none' => 'success',
        'quarantine' => 'warning',
        'reject' => 'error'
    ];
    $class = $classes[$disposition] ?? 'secondary';
    $display = $disposition === 'none' ? 'pass' : $disposition;
    return "<span class=\"label label-$class\">$display</span>";
}

function formatIPAddress($ip)
{
    // Basic validation for IPv4/IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return "<code class=\"text-primary\">$ip</code>";
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return "<code class=\"text-primary\">$ip</code>";
    }
    return "<code>$ip</code>";
}

function formatRegistry(?string $registry): string
{
    if (!$registry) {
        return '<span class="text-gray">Unknown registry</span>';
    }
    return '<span class="chip chip-sm">' . htmlspecialchars(strtoupper($registry)) . '</span>';
}

function formatDnsblStatus(array $intel): string
{
    $listed = !empty($intel['dnsbl_listed']);
    $class = $listed ? 'label label-error' : 'label label-success';
    $text = $listed ? 'Spamhaus listing' : 'Spamhaus clear';
    return '<span class="' . $class . '">' . $text . '</span>';
}

function formatReputationScore($score): string
{
    if ($score === null || $score === '') {
        return '<span class="chip">Score: n/a</span>';
    }
    $score = (int) $score;
    $class = $score >= 80 ? 'label label-error' : ($score >= 40 ? 'label label-warning' : 'label label-success');
    return '<span class="' . $class . '">Score ' . $score . '</span>';
}

function renderPolicyChips(array $report): string
{
    $policies = [
        'p' => $report['policy_p'] ?? null,
        'sp' => $report['policy_sp'] ?? null,
        'adkim' => $report['policy_adkim'] ?? null,
        'aspf' => $report['policy_aspf'] ?? null,
        'pct' => $report['policy_pct'] ?? null,
        'fo' => $report['policy_fo'] ?? null,
    ];

    $chips = [];
    foreach ($policies as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $displayValue = $value;
        if ($label === 'pct') {
            $displayValue = (int) $value . '%';
        }

        $chips[] = '<span class="chip chip-sm mr-1"><strong class="text-uppercase">'
            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
            . '</strong>: '
            . htmlspecialchars((string) $displayValue, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    return !empty($chips) ? implode('', $chips) : '<span class="text-gray">No published policy details</span>';
}

function renderReasonList(array $reasons): string
{
    if (empty($reasons)) {
        return '<span class="text-gray">No details provided</span>';
    }

    $items = [];
    foreach ($reasons as $reason) {
        if (!is_array($reason)) {
            continue;
        }

        $type = isset($reason['type']) && $reason['type'] !== ''
            ? '<span class="label label-secondary label-rounded text-uppercase mr-1">'
            . htmlspecialchars((string) $reason['type'], ENT_QUOTES, 'UTF-8')
            . '</span>'
            : '';

        $comment = isset($reason['comment']) && $reason['comment'] !== ''
            ? '<span class="text-gray">' . htmlspecialchars((string) $reason['comment'], ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        if ($type === '' && $comment === '') {
            continue;
        }

        $items[] = '<li>' . $type . $comment . '</li>';
    }

    if (empty($items)) {
        return '<span class="text-gray">No details provided</span>';
    }

    return '<ul class="reason-list mb-0">' . implode('', $items) . '</ul>';
}

function renderAuthResults(array $authResults): string
{
    if (empty($authResults)) {
        return '<span class="text-gray">No authentication details</span>';
    }

    $segments = [];
    foreach ($authResults as $method => $entries) {
        if (!is_array($entries) || empty($entries)) {
            continue;
        }

        $methodLabel = '<span class="label label-primary label-rounded text-uppercase mr-1">'
            . htmlspecialchars((string) $method, ENT_QUOTES, 'UTF-8')
            . '</span>';

        $details = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $chips = [];
            foreach ($entry as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $chips[] = '<span class="chip chip-sm mr-1">'
                    . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                    . ': '
                    . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                    . '</span>';
            }

            if (!empty($chips)) {
                $details[] = '<div class="mt-1">' . implode('', $chips) . '</div>';
            }
        }

        if (!empty($details)) {
            $segments[] = '<div class="auth-block">' . $methodLabel . implode('', $details) . '</div>';
        }
    }

    return !empty($segments) ? implode('', $segments) : '<span class="text-gray">No authentication details</span>';
}

function renderRdapContacts(array $contacts): string
{
    if (empty($contacts)) {
        return '<span class="text-gray">No published contacts</span>';
    }

    $items = [];
    foreach ($contacts as $contact) {
        $name = $contact['name'] ?? $contact['handle'] ?? 'Contact';
        $segments = [htmlspecialchars((string) $name)];

        if (!empty($contact['roles'])) {
            $segments[] = '<span class="label label-secondary label-rounded text-uppercase">' . htmlspecialchars(implode(', ', (array) $contact['roles'])) . '</span>';
        }

        if (!empty($contact['email'])) {
            $segments[] = '<span class="text-gray">' . htmlspecialchars((string) $contact['email']) . '</span>';
        }

        if (!empty($contact['phone'])) {
            $segments[] = '<span class="text-gray">' . htmlspecialchars((string) $contact['phone']) . '</span>';
        }

        $items[] = '<li class="menu-item">' . implode(' ', $segments) . '</li>';
    }

    return '<ul class="menu menu-nav contact-list mb-0">' . implode('', $items) . '</ul>';
}

$intelMap = $this->data['ip_intelligence'] ?? [];
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
        background: rgba(255, 255, 255, 0.1);
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

    .policy-summary {
        margin-top: 0.75rem;
    }

    .policy-summary .chip {
        margin-bottom: 0.25rem;
    }

    .reason-list {
        list-style: none;
        padding-left: 0;
    }

    .reason-list li {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.35rem;
    }

    .reason-list li:last-child {
        margin-bottom: 0;
    }

    .auth-block {
        margin-bottom: 0.5rem;
    }

    .auth-block:last-child {
        margin-bottom: 0;
    }

    .auth-block .chip {
        display: inline-block;
        margin-bottom: 0.25rem;
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

    .ip-insights {
        background: #f5f7fb;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e9ecef;
    }

    .ip-insights .tile {
        margin-bottom: 0.5rem;
    }

    .contact-list {
        list-style: none;
        padding-left: 0;
    }

    .contact-list .menu-item {
        padding: 0.15rem 0;
    }

    .dnsbl-notes div {
        line-height: 1.2;
    }
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
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
        <div class="column col-12 col-lg-8">
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
            <div class="policy-summary">
                <small class="text-uppercase text-light">Published Policy</small>
                <div class="mt-1"><?= renderPolicyChips($this->data['report']); ?></div>
            </div>
        </div>
        <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
            <div class="stat-box">
                <div class="stat-number"><?= number_format($this->data['summary']['total_volume']) ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="columns mb-2">
    <div class="column col-12 col-sm-6 col-lg-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-success"><?= number_format($this->data['summary']['pass_count']) ?></div>
                <small class="text-gray">Passed</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-warning"><?= number_format($this->data['summary']['quarantine_count']) ?></div>
                <small class="text-gray">Quarantined</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-2">
        <div class="card text-center">
            <div class="card-body">
                <div class="h4 text-error"><?= number_format($this->data['summary']['reject_count']) ?></div>
                <small class="text-gray">Rejected</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><?= formatAuthResult($this->data['summary']['dkim_pass_count'], $this->data['summary']['total_volume']) ?></div>
                <small class="text-gray">DKIM Pass Rate</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><?= formatAuthResult($this->data['summary']['spf_pass_count'], $this->data['summary']['total_volume']) ?></div>
                <small class="text-gray">SPF Pass Rate</small>
            </div>
        </div>
    </div>
</div>

<?php
$summary = $this->data['summary'];
$totalVolume = max(0, (int) ($summary['total_volume'] ?? 0));
$reasonVolume = (int) ($summary['policy_evaluated_reason_volume'] ?? 0);
$overrideVolume = (int) ($summary['policy_override_volume'] ?? 0);
$authVolume = (int) ($summary['auth_results_volume'] ?? 0);
$reasonPercent = $totalVolume > 0 ? (int) round(($reasonVolume / $totalVolume) * 100) : 0;
$overridePercent = $totalVolume > 0 ? (int) round(($overrideVolume / $totalVolume) * 100) : 0;
$authPercent = $totalVolume > 0 ? (int) round(($authVolume / $totalVolume) * 100) : 0;
?>

<div class="columns mb-2">
    <div class="column col-12 col-sm-6 col-lg-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><span class="chip chip-sm"><?= number_format($reasonVolume) ?> (<?= $reasonPercent ?>%)</span></div>
                <small class="text-gray">Policy Evaluation Flags</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><span class="chip chip-sm"><?= number_format($overrideVolume) ?> (<?= $overridePercent ?>%)</span></div>
                <small class="text-gray">Overrides Applied</small>
            </div>
        </div>
    </div>
    <div class="column col-12 col-sm-6 col-lg-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="h5"><span class="chip chip-sm"><?= number_format($authVolume) ?> (<?= $authPercent ?>%)</span></div>
                <small class="text-gray">Auth Evidence Provided</small>
            </div>
        </div>
    </div>
</div>

<div class="columns mb-2">
    <div class="column col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-flag mr-1"></i>
                    Policy Evaluation Reasons
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($summary['policy_evaluated_reason_breakdown'])): ?>
                    <ul class="menu menu-nav mb-0">
                        <?php foreach ($summary['policy_evaluated_reason_breakdown'] as $label => $count): ?>
                            <li class="menu-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($label === 'unspecified' ? 'Unspecified' : (string) $label, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="chip chip-sm"><?= number_format($count) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <span class="text-gray">No policy evaluation reasons provided.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-refresh mr-1"></i>
                    Overrides &amp; Auth Context
                </div>
            </div>
            <div class="card-body">
                <h6 class="text-uppercase text-gray mb-1">Overrides</h6>
                <?php if (!empty($summary['policy_override_breakdown'])): ?>
                    <ul class="menu menu-nav mb-2">
                        <?php foreach ($summary['policy_override_breakdown'] as $label => $count): ?>
                            <li class="menu-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($label === 'unspecified' ? 'Unspecified' : (string) $label, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="chip chip-sm"><?= number_format($count) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray mb-2">No policy overrides recorded.</p>
                <?php endif; ?>

                <h6 class="text-uppercase text-gray mb-1">Authentication Methods</h6>
                <?php if (!empty($summary['auth_result_breakdown'])): ?>
                    <ul class="menu menu-nav mb-0">
                        <?php foreach ($summary['auth_result_breakdown'] as $method => $count): ?>
                            <li class="menu-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars((string) $method, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="chip chip-sm"><?= number_format($count) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <span class="text-gray">No authentication breakdown was supplied.</span>
                <?php endif; ?>
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
                            <div class="d-flex flex-wrap justify-content-between align-items-center">
                                <div>
                                    <?= formatIPAddress($ipGroup['ip']) ?>
                                    <span class="ml-2 text-gray">(<?= count($ipGroup['records']) ?> records)</span>
                                </div>
                                <span class="chip"><?= number_format($ipGroup['total_count']) ?> messages</span>
                            </div>
                        </div>

                        <?php $intel = $intelMap[$ipGroup['ip']] ?? null; ?>
                        <?php if ($intel): ?>
                            <div class="ip-insights">
                                <div class="columns">
                                    <div class="column col-12 col-lg-4">
                                        <div class="tile tile-centered">
                                            <div class="tile-icon"><i class="icon icon-location text-primary"></i></div>
                                            <div class="tile-content">
                                                <div class="tile-title">
                                                    <?= htmlspecialchars($intel['country_name'] ?? 'Unknown location') ?>
                                                </div>
                                                <?php if (!empty($intel['city']) || !empty($intel['region'])): ?>
                                                    <div class="tile-subtitle text-gray">
                                                        <?= htmlspecialchars(trim(($intel['city'] ?? '') . (!empty($intel['region']) ? ', ' . $intel['region'] : ''))) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($intel['organization'])): ?>
                                                    <div class="text-tiny text-secondary">Org: <?= htmlspecialchars($intel['organization']) ?></div>
                                                <?php elseif (!empty($intel['isp'])): ?>
                                                    <div class="text-tiny text-secondary">ISP: <?= htmlspecialchars($intel['isp']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($intel['asn'])): ?>
                                                    <div class="text-tiny text-gray">ASN <?= htmlspecialchars($intel['asn']) ?><?= !empty($intel['asn_org']) ? ' (' . htmlspecialchars($intel['asn_org']) . ')' : '' ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
                                        <div class="tile tile-centered">
                                            <div class="tile-icon"><i class="icon icon-people text-primary"></i></div>
                                            <div class="tile-content">
                                                <div class="tile-title">Ownership</div>
                                                <div class="tile-subtitle">
                                                    <?= formatRegistry($intel['rdap_registry'] ?? null) ?>
                                                    <?php if (!empty($intel['rdap_network_range'])): ?>
                                                        <span class="chip chip-sm ml-1"><?= htmlspecialchars($intel['rdap_network_range']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($intel['rdap_network_start']) && !empty($intel['rdap_network_end'])): ?>
                                                    <div class="text-tiny text-gray">
                                                        <?= htmlspecialchars($intel['rdap_network_start']) ?> – <?= htmlspecialchars($intel['rdap_network_end']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mt-1">
                                                    <?= renderRdapContacts($intel['rdap_contacts'] ?? []) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
                                        <div class="tile tile-centered">
                                            <div class="tile-icon"><i class="icon icon-shield text-primary"></i></div>
                                            <div class="tile-content">
                                                <div class="tile-title">Reputation</div>
                                                <div class="tile-subtitle">
                                                    <?= formatDnsblStatus($intel) ?>
                                                </div>
                                                <div class="mt-1">
                                                    <?= formatReputationScore($intel['reputation_score'] ?? null) ?>
                                                </div>
                                                <?php if (!empty($intel['reputation_context']['threatlevel'])): ?>
                                                    <div class="text-tiny text-gray">Level: <?= htmlspecialchars($intel['reputation_context']['threatlevel']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($intel['reputation_context']['attacks'])): ?>
                                                    <div class="text-tiny text-gray">Recent attacks: <?= htmlspecialchars((string) $intel['reputation_context']['attacks']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($intel['dnsbl_sources'])): ?>
                                                    <div class="dnsbl-notes mt-1">
                                                        <?php foreach ($intel['dnsbl_sources'] as $source): ?>
                                                            <?php $response = isset($source['response']) ? htmlspecialchars((string) $source['response']) : ''; ?>
                                                            <?php if ($response !== ''): ?>
                                                                <div><?= $response ?></div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($intel['dnsbl_last_checked'])): ?>
                                                    <div class="text-tiny text-gray">Checked: <?= htmlspecialchars($intel['dnsbl_last_checked']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($ipGroup['records'] as $record): ?>
                            <div class="record-row">
                                <div class="columns">
                                    <div class="column col-12 col-sm-6 col-lg-2">
                                        <span class="chip"><?= number_format($record['count']) ?></span>
                                    </div>
                                    <div class="column col-12 col-sm-6 col-lg-2">
                                        <?= formatDisposition($record['disposition']) ?>
                                    </div>
                                    <div class="column col-12 col-sm-6 col-lg-2 mt-1 mt-lg-0">
                                        <small class="text-gray">DKIM:</small>
                                        <?= formatAuthResult($record['dkim_result']) ?>
                                    </div>
                                    <div class="column col-12 col-sm-6 col-lg-2 mt-1 mt-lg-0">
                                        <small class="text-gray">SPF:</small>
                                        <?= formatAuthResult($record['spf_result']) ?>
                                    </div>
                                    <div class="column col-12 col-lg-4 mt-1 mt-lg-0">
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
                                <?php if (!empty($record['policy_evaluated_reasons']) || !empty($record['policy_override_reasons']) || !empty($record['auth_results'])): ?>
                                    <div class="columns mt-1">
                                        <?php if (!empty($record['policy_evaluated_reasons'])): ?>
                                            <div class="column col-12 col-lg-4">
                                                <small class="text-gray text-uppercase text-tiny">Evaluation Reasons</small>
                                                <?= renderReasonList($record['policy_evaluated_reasons']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['policy_override_reasons'])): ?>
                                            <div class="column col-12 col-lg-4">
                                                <small class="text-gray text-uppercase text-tiny">Overrides</small>
                                                <?= renderReasonList($record['policy_override_reasons']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['auth_results'])): ?>
                                            <div class="column col-12 col-lg-4">
                                                <small class="text-gray text-uppercase text-tiny">Auth Results</small>
                                                <?= renderAuthResults($record['auth_results']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
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
