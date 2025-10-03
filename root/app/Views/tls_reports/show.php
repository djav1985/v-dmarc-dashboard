<?php
require __DIR__ . '/../partials/header.php';
?>
<div class="columns mt-2">
    <div class="column col-12 col-lg-10 col-mx-auto">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">TLS report for <?= htmlspecialchars($report['domain']); ?></div>
                <div class="card-subtitle text-gray">Report ID <?= htmlspecialchars($report['report_id']); ?></div>
            </div>
            <div class="card-body">
                <div class="columns">
                    <div class="column col-12 col-md-6">
                        <dl class="data-list">
                            <dt>Organization</dt>
                            <dd><?= htmlspecialchars($report['org_name']); ?></dd>
                            <dt>Contact</dt>
                            <dd><?= htmlspecialchars($report['contact_info'] ?? 'n/a'); ?></dd>
                            <dt>Date range</dt>
                            <dd><?= date('Y-m-d', (int) $report['date_range_begin']); ?> → <?= date('Y-m-d', (int) $report['date_range_end']); ?></dd>
                        </dl>
                    </div>
                    <div class="column col-12 col-md-6">
                        <dl class="data-list">
                            <dt>Sessions</dt>
                            <dd>
                                <span class="label label-rounded label-success">Successful <?= (int) ($report['success_sessions'] ?? 0); ?></span>
                                <span class="label label-rounded label-error">Failed <?= (int) ($report['failure_sessions'] ?? 0); ?></span>
                            </dd>
                            <dt>Received at</dt>
                            <dd><?= htmlspecialchars($report['received_at']); ?></dd>
                        </dl>
                    </div>
                </div>
                <div class="divider" data-content="POLICIES"></div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Policy type</th>
                                <th scope="col">Policy</th>
                                <th scope="col">MX host</th>
                                <th scope="col">Successful</th>
                                <th scope="col">Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report['policies'])) : ?>
                                <?php foreach ($report['policies'] as $policy) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($policy['policy_type']); ?></td>
                                        <td><code><?= htmlspecialchars($policy['policy_string'] ?? ''); ?></code></td>
                                        <td><?= htmlspecialchars($policy['mx_host'] ?? ''); ?></td>
                                        <td><?= (int) ($policy['successful_session_count'] ?? 0); ?></td>
                                        <td><?= (int) ($policy['failure_session_count'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-gray">No policy breakdown available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($report['raw_json'])) : ?>
                    <div class="divider" data-content="RAW JSON"></div>
                    <pre class="code" style="max-height: 320px; overflow:auto;"><?= htmlspecialchars($report['raw_json']); ?></pre>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a class="btn btn-link" href="/tls-reports">
                    <i class="icon icon-arrow-left"></i> Back to TLS reports
                </a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php';
?>
