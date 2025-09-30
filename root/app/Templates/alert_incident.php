<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
?>
<h2 style="margin-top: 0; font-size: 18px;">Incident Triggered: <?= htmlspecialchars($incident['rule_name'] ?? 'Alert') ?></h2>
<p style="font-size: 14px; line-height: 1.6;">An alert rule has detected unusual activity that requires your attention.</p>

<table>
    <tr>
        <th>Severity</th>
        <td>
            <?php $severity = $incident['severity'] ?? 'medium'; ?>
            <span class="badge <?= $severity === 'critical' || $severity === 'high' ? 'badge-danger' : ($severity === 'medium' ? 'badge-warning' : 'badge-success') ?>">
                <?= htmlspecialchars(ucfirst($severity)) ?>
            </span>
        </td>
    </tr>
    <tr>
        <th>Status</th>
        <td><?= htmlspecialchars(ucfirst($incident['status'] ?? 'open')) ?></td>
    </tr>
    <tr>
        <th>Triggered</th>
        <td><?= htmlspecialchars($incident['triggered_at'] ?? '') ?></td>
    </tr>
    <tr>
        <th>Metric Value</th>
        <td><?= htmlspecialchars((string) ($incident['metric_value'] ?? '')) ?> (threshold <?= htmlspecialchars((string) ($incident['threshold_value'] ?? '')) ?>)</td>
    </tr>
</table>

<p style="font-size: 14px; line-height: 1.6; margin-top: 16px;">Summary:</p>
<p style="background-color: #f5f7fb; padding: 12px 16px; border-radius: 6px; font-size: 14px;">
    <?= htmlspecialchars($incident['message'] ?? '') ?>
</p>

<?php if (!empty($details) && is_array($details)): ?>
    <p style="font-size: 14px; line-height: 1.6; margin-top: 16px;">Context:</p>
    <table>
        <?php foreach ($details as $key => $value): ?>
            <tr>
                <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key))) ?></th>
                <td><?= htmlspecialchars((string) $value) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p style="margin-top: 24px; font-size: 14px;">Please log into the DMARC dashboard to acknowledge and investigate this incident.</p>
<p><a href="<?= getenv('APP_URL') ?: 'https://example.com' ?>/alerts" class="btn">View incidents</a></p>
