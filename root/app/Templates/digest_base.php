<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
?>
<h2 style="margin-top: 0; font-size: 18px;"><?= htmlspecialchars($heading ?? 'DMARC Digest') ?></h2>
<p style="font-size: 14px; line-height: 1.6;">Reporting period: <?= htmlspecialchars($digest['period']['start'] ?? '') ?> to <?= htmlspecialchars($digest['period']['end'] ?? '') ?>.</p>

<?php $summary = $digest['summary'] ?? []; ?>
<table>
    <tr>
        <th>Domains</th>
        <td><?= number_format((int) ($summary['domain_count'] ?? 0)) ?></td>
    </tr>
    <tr>
        <th>Reports Received</th>
        <td><?= number_format((int) ($summary['report_count'] ?? 0)) ?></td>
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

<?php if (!empty($digest['domains'])): ?>
    <h3 style="margin-top: 24px; font-size: 16px;">Top domains by volume</h3>
    <table>
        <thead>
            <tr>
                <th>Domain</th>
                <th>Volume</th>
                <th>Pass Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($digest['domains'], 0, 10) as $domain): ?>
                <tr>
                    <td><?= htmlspecialchars($domain['domain'] ?? '') ?></td>
                    <td><?= number_format((int) ($domain['total_volume'] ?? 0)) ?></td>
                    <td><?= isset($domain['pass_rate']) ? htmlspecialchars((string) $domain['pass_rate']) . '%' : 'n/a' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($digest['threats'])): ?>
    <h3 style="margin-top: 24px; font-size: 16px;">Top threat sources</h3>
    <table>
        <thead>
            <tr>
                <th>Source IP</th>
                <th>Threat Volume</th>
                <th>Affected Domains</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($digest['threats'] as $threat): ?>
                <tr>
                    <td><?= htmlspecialchars($threat['source_ip'] ?? '') ?></td>
                    <td><?= number_format((int) ($threat['threat_volume'] ?? 0)) ?></td>
                    <td><?= number_format((int) ($threat['affected_domains'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top: 24px; font-size: 14px;">Access the dashboard for full drill-down and remediation guidance.</p>
<p><a href="<?= getenv('APP_URL') ?: 'https://example.com' ?>/analytics" class="btn">View analytics</a></p>
