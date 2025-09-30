<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
?>
<h2 style="margin-top: 0; font-size: 18px;">Your scheduled PDF report is ready</h2>
<p style="font-size: 14px; line-height: 1.6;">
    The <strong><?= htmlspecialchars($schedule['name'] ?? 'DMARC Schedule') ?></strong> schedule generated a new report
    covering <strong><?= htmlspecialchars($period_start ?? '') ?></strong> through
    <strong><?= htmlspecialchars($period_end ?? '') ?></strong>.
</p>
<?php
$frequency = $schedule['frequency'] ?? '';
if (str_starts_with((string) $frequency, 'custom:')) {
    $days = (int) substr($frequency, 7);
    $frequencyLabel = 'Every ' . max(1, $days) . ' days';
} else {
    $frequencyLabel = match ($frequency) {
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        default => ucfirst((string) $frequency),
    };
}
?>
<table style="font-size: 14px; line-height: 1.6; border-collapse: collapse;">
    <tr>
        <th align="left" style="padding-right: 12px;">Template</th>
        <td><?= htmlspecialchars($schedule['template_name'] ?? 'Selected template') ?></td>
    </tr>
    <tr>
        <th align="left" style="padding-right: 12px;">Title</th>
        <td><?= htmlspecialchars($schedule['title'] ?? $schedule['name'] ?? '') ?></td>
    </tr>
    <tr>
        <th align="left" style="padding-right: 12px;">Cadence</th>
        <td><?= htmlspecialchars($frequencyLabel) ?></td>
    </tr>
    <tr>
        <th align="left" style="padding-right: 12px;">File</th>
        <td><?= htmlspecialchars($generation['filename'] ?? '') ?> (<?= number_format((int) ($generation['size'] ?? 0)) ?> bytes)</td>
    </tr>
</table>
<p style="font-size: 14px; line-height: 1.6; margin-top: 16px;">
    The PDF is saved on the dashboard server. Sign in to download the latest report and share it with your team.
</p>
<p style="font-size: 14px;">
    <a href="<?= getenv('APP_URL') ?: 'https://example.com' ?>/reports-management" style="color: #3366cc;">Open reports management</a>
</p>
