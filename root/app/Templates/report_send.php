<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
?>
<h2 style="margin-top: 0; font-size: 18px;">DMARC Aggregate Report</h2>
<p style="font-size: 14px; line-height: 1.6;">Domain <?= htmlspecialchars($report['domain'] ?? '') ?> &middot; Report ID <?= htmlspecialchars($report['report_id'] ?? '') ?></p>
<p style="font-size: 14px; line-height: 1.6;">Reporting window: <?= date('Y-m-d H:i', (int) ($report['date_range_begin'] ?? time())) ?> to <?= date('Y-m-d H:i', (int) ($report['date_range_end'] ?? time())) ?>.</p>

<table>
    <tr>
        <th>Organization</th>
        <td><?= htmlspecialchars($report['org_name'] ?? '') ?></td>
    </tr>
    <tr>
        <th>Total Volume</th>
        <td><?= number_format((int) ($summary['total_volume'] ?? 0)) ?></td>
    </tr>
    <tr>
        <th>Delivered</th>
        <td><span class="badge badge-success"><?= number_format((int) ($summary['passed_count'] ?? 0)) ?></span></td>
    </tr>
    <tr>
        <th>Quarantined</th>
        <td><span class="badge badge-warning"><?= number_format((int) ($summary['quarantined_count'] ?? 0)) ?></span></td>
    </tr>
    <tr>
        <th>Rejected</th>
        <td><span class="badge badge-danger"><?= number_format((int) ($summary['rejected_count'] ?? 0)) ?></span></td>
    </tr>
</table>

<?php if (!empty($records)): ?>
    <h3 style="margin-top: 24px; font-size: 16px;">Top sending sources</h3>
    <table>
        <thead>
            <tr>
                <th>Source IP</th>
                <th>Messages</th>
                <th>Disposition</th>
                <th>DKIM</th>
                <th>SPF</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><?= htmlspecialchars($record['source_ip'] ?? '') ?></td>
                    <td><?= number_format((int) ($record['count'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars($record['disposition'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['dkim_result'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['spf_result'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top: 24px; font-size: 14px;">Log into the dashboard to review the full report and related forensic details.</p>
<p><a href="<?= getenv('APP_URL') ?: 'https://example.com' ?>/reports" class="btn">View in dashboard</a></p>
