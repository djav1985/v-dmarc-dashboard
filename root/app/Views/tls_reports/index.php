<?php
require __DIR__ . '/../partials/header.php';
?>
    <div class="columns mt-2">
        <div class="column col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5"><i class="icon icon-signal mr-2"></i>TLS reports</div>
                    <div class="card-subtitle text-gray">Monitor SMTP TLS policy adoption and delivery results</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Received</th>
                                    <th scope="col">Domain</th>
                                    <th scope="col">Organization</th>
                                    <th scope="col">Report ID</th>
                                    <th scope="col">Sessions</th>
                                    <th scope="col">Window</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reports)) : ?>
                                    <?php foreach ($reports as $report) : ?>
                                        <?php $success = (int) ($report['success_sessions'] ?? 0); ?>
                                        <?php $failure = (int) ($report['failure_sessions'] ?? 0); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['received_at']); ?></td>
                                            <td><?= htmlspecialchars($report['domain']); ?></td>
                                            <td><?= htmlspecialchars($report['org_name']); ?></td>
                                            <td><code><?= htmlspecialchars($report['report_id']); ?></code></td>
                                            <td>
                                                <span class="label label-rounded label-success">OK: <?= $success; ?></span>
                                                <span class="label label-rounded label-error">Fail: <?= $failure; ?></span>
                                            </td>
                                            <td>
                                                <?= date('Y-m-d', (int) $report['date_range_begin']); ?>
                                                &rarr;
                                                <?= date('Y-m-d', (int) $report['date_range_end']); ?>
                                            </td>
                                            <td>
                                                <a class="btn btn-sm" href="/tls-reports/<?= (int) $report['id']; ?>">
                                                    <i class="icon icon-search"></i> Inspect
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-gray">No TLS reports ingested yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../partials/footer.php';
?>
