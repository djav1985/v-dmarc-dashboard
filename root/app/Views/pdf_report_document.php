<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

$period = $report['period'] ?? ['start' => '', 'end' => ''];
$filters = $report['filters'] ?? [];
$sections = $report['sections'] ?? [];
$template = $report['template'] ?? [];

if (!function_exists('renderKeyValueTable')) {
    function renderKeyValueTable(array $rows): string
    {
        $html = '<table class="kv-table">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . htmlspecialchars((string) $label) . '</th><td>' . htmlspecialchars((string) $value) . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }
}

if (!function_exists('renderSummarySection')) {
    function renderSummarySection(array $summary): string
    {
        if (empty($summary)) {
            return '<p class="empty">No summary data was available for this period.</p>';
        }

        $metrics = [
            'Domains' => number_format((float) ($summary['domain_count'] ?? 0)),
            'Reports' => number_format((float) ($summary['report_count'] ?? 0)),
            'Total Volume' => number_format((float) ($summary['total_volume'] ?? 0)),
            'Pass Rate' => isset($summary['pass_rate']) ? number_format((float) $summary['pass_rate'], 2) . '%' : 'n/a',
            'Delivered' => number_format((float) ($summary['passed_count'] ?? 0)),
            'Quarantined' => number_format((float) ($summary['quarantined_count'] ?? 0)),
            'Rejected' => number_format((float) ($summary['rejected_count'] ?? 0)),
            'Unique IPs' => number_format((float) ($summary['unique_ips'] ?? 0)),
        ];

        return renderKeyValueTable($metrics);
    }
}

if (!function_exists('renderDomainHealth')) {
    function renderDomainHealth(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">No domain health data available.</p>';
        }

        $html = '<table class="data-table">';
        $html .= '<thead><tr><th>Domain</th><th>Health</th><th>Pass</th><th>Quarantine</th><th>Reject</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($row['domain'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($row['health_label'] ?? '')) . '</td>';
            $html .= '<td>' . number_format((float) ($row['passed_count'] ?? 0)) . '</td>';
            $html .= '<td>' . number_format((float) ($row['quarantined_count'] ?? 0)) . '</td>';
            $html .= '<td>' . number_format((float) ($row['rejected_count'] ?? 0)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

if (!function_exists('renderThreats')) {
    function renderThreats(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">No threat activity detected in this range.</p>';
        }
        $html = '<table class="data-table">';
        $html .= '<thead><tr><th>Source IP</th><th>Threat Volume</th><th>Affected Domains</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($row['source_ip'] ?? '')) . '</td>';
            $html .= '<td>' . number_format((float) ($row['threat_volume'] ?? ($row['total_volume'] ?? 0))) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($row['affected_domains'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

if (!function_exists('renderCompliance')) {
    function renderCompliance(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">No compliance timeline available.</p>';
        }
        $html = '<table class="data-table">';
        $html .= '<thead><tr><th>Period</th><th>DMARC Compliance</th><th>SPF Pass</th><th>DKIM Pass</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($row['period'] ?? ($row['date'] ?? ''))) . '</td>';
            $html .= '<td>' . number_format((float) ($row['dmarc_compliance'] ?? 0), 2) . '%</td>';
            $html .= '<td>' . number_format((float) ($row['spf_pass_rate'] ?? 0), 2) . '%</td>';
            $html .= '<td>' . number_format((float) ($row['dkim_pass_rate'] ?? 0), 2) . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

if (!function_exists('renderTrend')) {
    function renderTrend(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">Trend analytics are not available for this period.</p>';
        }
        $html = '<table class="data-table">';
        $html .= '<thead><tr><th>Date</th><th>Total Volume</th><th>Delivered</th><th>Quarantined</th><th>Rejected</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($row['date'] ?? '')) . '</td>';
            $html .= '<td>' . number_format((float) ($row['total_volume'] ?? 0)) . '</td>';
            $html .= '<td>' . number_format((float) ($row['passed_count'] ?? 0)) . '</td>';
            $html .= '<td>' . number_format((float) ($row['quarantined_count'] ?? 0)) . '</td>';
            $html .= '<td>' . number_format((float) ($row['rejected_count'] ?? 0)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

if (!function_exists('renderAuthBreakdown')) {
    function renderAuthBreakdown(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">No authentication breakdown data was produced.</p>';
        }
        $html = '<table class="data-table">';
        $html .= '<thead><tr><th>Domain</th><th>Disposition</th><th>SPF</th><th>DKIM</th><th>Messages</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($row['domain'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($row['disposition'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($row['spf_result'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($row['dkim_result'] ?? '')) . '</td>';
            $html .= '<td>' . number_format((float) ($row['total'] ?? ($row['count'] ?? 0))) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

if (!function_exists('renderRecommendations')) {
    function renderRecommendations(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="empty">No recommendations generated for this reporting period.</p>';
        }
        $html = '<ul class="recommendations">';
        foreach ($rows as $row) {
            $title = $row['title'] ?? 'Recommendation';
            $description = $row['description'] ?? '';
            $priority = strtoupper((string) ($row['priority'] ?? 'normal'));
            $html .= '<li><strong>' . htmlspecialchars((string) $title) . '</strong><br />';
            $html .= '<span class="badge">' . htmlspecialchars($priority) . '</span>';
            if ($description !== '') {
                $html .= '<div class="description">' . htmlspecialchars((string) $description) . '</div>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

if (!function_exists('renderSection')) {
    function renderSection(string $key, array $data): string
    {
        return match ($key) {
            'summary' => renderSummarySection($data),
            'domain_health' => renderDomainHealth($data),
            'top_threats' => renderThreats($data),
            'compliance_status' => renderCompliance($data),
            'detailed_analytics' => renderTrend($data['trends'] ?? []),
            'volume_trends' => renderTrend($data),
            'authentication_breakdown' => renderAuthBreakdown($data),
            'recommendations' => renderRecommendations($data),
            default => '<pre class="unknown">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT) ?: '') . '</pre>',
        };
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($reportTitle) ?></title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2933;
            margin: 0;
            padding: 32px;
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
            color: #1b2a4b;
        }

        h2 {
            border-bottom: 2px solid #dfe3ea;
            padding-bottom: 6px;
            margin-top: 32px;
            color: #33415c;
        }

        h3 {
            margin-top: 24px;
            color: #3d5a80;
        }

        .meta {
            margin-top: 8px;
            color: #52606d;
            font-size: 14px;
        }

        .kv-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .kv-table th {
            text-align: left;
            width: 35%;
            padding: 6px;
            background: #f0f4f8;
        }

        .kv-table td {
            padding: 6px;
            border-bottom: 1px solid #e4e7eb;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13px;
        }

        .data-table th {
            background: #d9e2ec;
            text-align: left;
            padding: 6px;
        }

        .data-table td {
            border-bottom: 1px solid #e4e7eb;
            padding: 6px;
        }

        .empty {
            margin: 12px 0;
            color: #9aa5b1;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            background: #243b53;
            color: #fff;
            border-radius: 4px;
            font-size: 10px;
            margin-top: 4px;
        }

        .recommendations {
            list-style: none;
            padding-left: 0;
        }

        .recommendations li {
            margin-bottom: 12px;
        }

        .description {
            margin-top: 4px;
        }

        .unknown {
            background: #f7fafc;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <h1><?= htmlspecialchars($reportTitle) ?></h1>
    <div class="meta">
        <strong>Template:</strong> <?= htmlspecialchars($template['name'] ?? 'Custom Template') ?><br />
        <strong>Reporting Period:</strong> <?= htmlspecialchars((string) ($period['start'] ?? '')) ?> - <?= htmlspecialchars((string) ($period['end'] ?? '')) ?><br />
        <?php if (!empty($filters['domain']) || !empty($filters['group'])): ?>
            <strong>Filters:</strong>
            <?php if (!empty($filters['domain'])): ?>Domain = <?= htmlspecialchars((string) $filters['domain']) ?><?php endif; ?>
            <?php if (!empty($filters['group'])): ?> Group ID = <?= htmlspecialchars((string) $filters['group']) ?><?php endif; ?><br />
            <?php endif; ?>
            <strong>Generated At:</strong> <?= date('Y-m-d H:i:s') ?>
    </div>

    <?php foreach ($sections as $key => $data): ?>
        <h2><?= ucwords(str_replace('_', ' ', (string) $key)) ?></h2>
        <?= renderSection((string) $key, is_array($data) ? $data : []) ?>
    <?php endforeach; ?>
</body>

</html>
