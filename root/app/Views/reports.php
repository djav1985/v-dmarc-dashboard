<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: reports.php
 * Description: DMARC Reports listing with advanced filtering, saved filters, and exports
 */

require 'partials/header.php';

$currentFilters = $this->data['filters'] ?? [];
$sortBy = $currentFilters['sort_by'] ?? 'received_at';
$sortDir = strtoupper($currentFilters['sort_dir'] ?? 'DESC');
$queryBase = $this->data['query_params_no_page'] ?? [];
$activeSavedFilterId = $this->data['active_saved_filter_id'] ?? null;
$currentFilterJson = $this->data['current_filter_json'] ?? '[]';
$enforcementLevels = $this->data['enforcement_levels'] ?? [];
$perPageOptions = [25, 50, 100];

function build_sort_url(string $column, string $currentSort, string $currentDir, array $baseParams): string
{
    $params = $baseParams;
    $params['sort'] = $column;
    $params['dir'] = ($currentSort === $column && strtoupper($currentDir) === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1;
    return '/reports?' . http_build_query($params);
}

function sort_indicator(string $column, string $currentSort, string $currentDir): string
{
    if ($currentSort !== $column) {
        return '<i class="icon icon-resize-vert text-gray"></i>';
    }

    return strtoupper($currentDir) === 'ASC'
        ? '<i class="icon icon-arrow-up text-primary"></i>'
        : '<i class="icon icon-arrow-down text-primary"></i>';
}

function render_hidden_fields(array $params): string
{
    $output = '';
    foreach ($params as $key => $value) {
        $keyEscaped = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            foreach ($value as $item) {
                $itemEscaped = htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8');
                $output .= '<input type="hidden" name="' . $keyEscaped . '[]" value="' . $itemEscaped . '">';
            }
        } else {
            $valueEscaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $output .= '<input type="hidden" name="' . $keyEscaped . '" value="' . $valueEscaped . '">';
        }
    }

    return $output;
}

function getDispositionBadge(string $disposition, string $count): string
{
    $classes = [
        'none' => 'label-success',
        'quarantine' => 'label-warning',
        'reject' => 'label-error',
    ];
    $class = $classes[$disposition] ?? 'label-secondary';
    return "<span class=\"label $class\">$count</span>";
}

function getAuthResultBadge(int $result, int $total): string
{
    if ($total <= 0) {
        return '<span class="label label-secondary">0</span>';
    }

    $percentage = (int) round(($result / $total) * 100);
    $class = $percentage >= 80 ? 'label-success' : ($percentage >= 50 ? 'label-warning' : 'label-error');
    return "<span class=\"label $class\">$result ({$percentage}%)</span>";
}

function getFailureBadge(int $failures, int $total): string
{
    if ($failures <= 0) {
        return '<span class="label label-secondary">0</span>';
    }

    $percentage = $total > 0 ? (int) round(($failures / $total) * 100) : 0;
    $class = $percentage >= 25 ? 'label-error' : ($percentage >= 10 ? 'label-warning' : 'label-secondary');
    return "<span class=\"label $class\">$failures ({$percentage}%)</span>";
}

function describe_filter(array $filter): array
{
    $items = [];
    if (!empty($filter['domain'])) {
        $domains = is_array($filter['domain']) ? $filter['domain'] : [$filter['domain']];
        $items[] = 'Domain: ' . implode(', ', array_map('htmlspecialchars', $domains));
    }
    if (!empty($filter['org_name'])) {
        $items[] = 'Organization: ' . htmlspecialchars((string) $filter['org_name']);
    }
    if (!empty($filter['reporter_email'])) {
        $items[] = 'Reporter: ' . htmlspecialchars((string) $filter['reporter_email']);
    }
    if (!empty($filter['source_ip'])) {
        $items[] = 'Source IP: ' . htmlspecialchars((string) $filter['source_ip']);
    }
    if (!empty($filter['disposition'])) {
        $dispositions = is_array($filter['disposition']) ? $filter['disposition'] : [$filter['disposition']];
        $items[] = 'Disposition: ' . implode(', ', array_map('htmlspecialchars', $dispositions));
    }
    if (!empty($filter['dkim_result'])) {
        $items[] = 'DKIM: ' . htmlspecialchars((string) $filter['dkim_result']);
    }
    if (!empty($filter['spf_result'])) {
        $items[] = 'SPF: ' . htmlspecialchars((string) $filter['spf_result']);
    }
    if (!empty($filter['header_from'])) {
        $items[] = 'Header-From: ' . htmlspecialchars((string) $filter['header_from']);
    }
    if (!empty($filter['envelope_from'])) {
        $items[] = 'Envelope-From: ' . htmlspecialchars((string) $filter['envelope_from']);
    }
    if (!empty($filter['envelope_to'])) {
        $items[] = 'Envelope-To: ' . htmlspecialchars((string) $filter['envelope_to']);
    }
    if (!empty($filter['ownership_contact'])) {
        $items[] = 'Ownership: ' . htmlspecialchars((string) $filter['ownership_contact']);
    }
    if (!empty($filter['enforcement_level'])) {
        $levels = is_array($filter['enforcement_level']) ? $filter['enforcement_level'] : [$filter['enforcement_level']];
        $items[] = 'Enforcement: ' . implode(', ', array_map('htmlspecialchars', $levels));
    }
    if (!empty($filter['date_from']) || !empty($filter['date_to'])) {
        $range = trim(($filter['date_from'] ?? '') . ' → ' . ($filter['date_to'] ?? ''));
        $items[] = 'Dates: ' . htmlspecialchars($range);
    }
    if (!empty($filter['min_volume'])) {
        $items[] = 'Min Volume: ' . (int) $filter['min_volume'];
    }
    if (!empty($filter['max_volume'])) {
        $items[] = 'Max Volume: ' . (int) $filter['max_volume'];
    }
    if (!empty($filter['has_failures'])) {
        $items[] = 'Only failing traffic';
    }

    return $items;
}

function enforcement_badge(?string $level): string
{
    if ($level === null || $level === '') {
        return '';
    }

    $normalized = strtolower($level);
    $class = match ($normalized) {
        'reject' => 'label-error',
        'quarantine' => 'label-warning',
        'monitor' => 'label-secondary',
        'none' => 'label-gray',
        default => 'label-primary',
    };

    return '<span class="label ' . $class . '">' . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<style>
    .filter-card {
        border-radius: 8px;
    }
    .filter-card .card-body {
        background: #f8f9fa;
    }
    .filter-card .form-group {
        margin-bottom: 0.75rem;
    }
    .saved-filter-card .tile {
        border: 1px solid #e6e6e6;
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .saved-filter-card .tile.active-filter {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6 inset;
    }
    .saved-filter-card .tile-actions form {
        display: inline-block;
        margin-right: 0.5rem;
    }
    .saved-filter-card .tile-actions form:last-child {
        margin-right: 0;
    }
    .table-metrics .chip {
        margin: 0.1rem;
    }
    .export-actions form {
        display: inline-block;
        margin-left: 0.5rem;
    }
    .export-actions button {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
</style>

<?php
$selectedDomain = $currentFilters['domain'] ?? '';
if (is_array($selectedDomain)) {
    $selectedDomain = $selectedDomain[0] ?? '';
}
$selectedDisposition = $currentFilters['disposition'] ?? '';
if (is_array($selectedDisposition)) {
    $selectedDisposition = $selectedDisposition[0] ?? '';
}
$selectedEnforcement = $currentFilters['enforcement_level'] ?? '';
if (is_array($selectedEnforcement)) {
    $selectedEnforcement = $selectedEnforcement[0] ?? '';
}
$perPage = $currentFilters['per_page'] ?? ($currentFilters['limit'] ?? 25);
$hasFailures = !empty($currentFilters['has_failures']);
$activeFilterSummary = describe_filter($currentFilters);
?>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <div>
                <h2 class="mb-0">
                    <i class="icon icon-2x icon-list text-primary mr-2"></i>
                    DMARC Reports
                </h2>
                <?php if (!empty($this->data['saved_filter_name'])): ?>
                    <div class="text-gray">Active saved filter: <strong><?= htmlspecialchars($this->data['saved_filter_name']) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="text-gray text-right">
                <div>Total reports: <?= number_format($this->data['pagination']['total_reports'] ?? 0) ?></div>
                <div>Showing page <?= (int) ($this->data['pagination']['current_page'] ?? 1) ?> of <?= (int) ($this->data['pagination']['total_pages'] ?? 1) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column col-12">
        <div class="card filter-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-filter mr-1"></i> Filter Reports
                </div>
                <div class="card-subtitle text-gray">
                    Apply ownership, enforcement, reputation, and policy facets to refine the dataset. Leave a field blank to ignore it.
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="/reports">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <?php if ($activeSavedFilterId): ?>
                        <input type="hidden" name="saved_filter_id" value="<?= (int) $activeSavedFilterId ?>">
                    <?php endif; ?>

                    <div class="columns">
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="domain">Domain</label>
                                <select class="form-select" id="domain" name="domain">
                                    <option value="">All domains</option>
                                    <?php foreach ($this->data['domains'] as $domain): ?>
                                        <option value="<?= htmlspecialchars($domain['domain']) ?>" <?= $selectedDomain === $domain['domain'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($domain['domain']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="org_name">Organization</label>
                                <input type="text" class="form-input" id="org_name" name="org_name" value="<?= htmlspecialchars($currentFilters['org_name'] ?? '') ?>" placeholder="Reporter name">
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="reporter_email">Reporter Email</label>
                                <input type="email" class="form-input" id="reporter_email" name="reporter_email" value="<?= htmlspecialchars($currentFilters['reporter_email'] ?? '') ?>" placeholder="example@provider.com">
                            </div>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="source_ip">Source IP</label>
                                <input type="text" class="form-input" id="source_ip" name="source_ip" value="<?= htmlspecialchars($currentFilters['source_ip'] ?? '') ?>" placeholder="198.51.100.10">
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="header_from">Header From</label>
                                <input type="text" class="form-input" id="header_from" name="header_from" value="<?= htmlspecialchars($currentFilters['header_from'] ?? '') ?>" placeholder="mail.example.com">
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="envelope_from">Envelope From</label>
                                <input type="text" class="form-input" id="envelope_from" name="envelope_from" value="<?= htmlspecialchars($currentFilters['envelope_from'] ?? '') ?>" placeholder="bounce@example.com">
                            </div>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="disposition">Disposition</label>
                                <select class="form-select" id="disposition" name="disposition">
                                    <option value="">All dispositions</option>
                                    <option value="none" <?= $selectedDisposition === 'none' ? 'selected' : '' ?>>None (pass)</option>
                                    <option value="quarantine" <?= $selectedDisposition === 'quarantine' ? 'selected' : '' ?>>Quarantine</option>
                                    <option value="reject" <?= $selectedDisposition === 'reject' ? 'selected' : '' ?>>Reject</option>
                                </select>
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="dkim_result">DKIM Result</label>
                                <select class="form-select" id="dkim_result" name="dkim_result">
                                    <option value="">Any</option>
                                    <?php foreach (['pass', 'fail', 'softfail', 'neutral', 'temperror', 'permerror', 'none'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($currentFilters['dkim_result'] ?? '') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="spf_result">SPF Result</label>
                                <select class="form-select" id="spf_result" name="spf_result">
                                    <option value="">Any</option>
                                    <?php foreach (['pass', 'fail', 'softfail', 'neutral', 'temperror', 'permerror', 'none'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($currentFilters['spf_result'] ?? '') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="enforcement_level">Enforcement Level</label>
                                <select class="form-select" id="enforcement_level" name="enforcement_level">
                                    <option value="">Any</option>
                                    <?php foreach ($enforcementLevels as $level): ?>
                                        <option value="<?= htmlspecialchars($level) ?>" <?= strtolower($selectedEnforcement) === strtolower($level) ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="ownership_contact">Ownership Contact</label>
                                <input type="text" class="form-input" id="ownership_contact" name="ownership_contact" value="<?= htmlspecialchars($currentFilters['ownership_contact'] ?? '') ?>" placeholder="Security team">
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="date_from">From Date</label>
                                <input type="date" class="form-input" id="date_from" name="date_from" value="<?= htmlspecialchars($currentFilters['date_from'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="date_to">To Date</label>
                                <input type="date" class="form-input" id="date_to" name="date_to" value="<?= htmlspecialchars($currentFilters['date_to'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="column col-12 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="report_id">Report ID</label>
                                <input type="text" class="form-input" id="report_id" name="report_id" value="<?= htmlspecialchars($currentFilters['report_id'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column col-6 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="min_volume">Min Volume</label>
                                <input type="number" min="0" class="form-input" id="min_volume" name="min_volume" value="<?= htmlspecialchars($currentFilters['min_volume'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="column col-6 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="max_volume">Max Volume</label>
                                <input type="number" min="0" class="form-input" id="max_volume" name="max_volume" value="<?= htmlspecialchars($currentFilters['max_volume'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="column col-6 col-md-3">
                            <div class="form-group">
                                <label class="form-label" for="per_page">Per Page</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <?php foreach ($perPageOptions as $option): ?>
                                        <option value="<?= $option ?>" <?= (int) $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column col-6 col-md-3">
                            <div class="form-group">
                                <label class="form-label d-block">&nbsp;</label>
                                <label class="form-switch">
                                    <input type="checkbox" name="has_failures" value="1" <?= $hasFailures ? 'checked' : '' ?>>
                                    <i class="form-icon"></i> Only show failing traffic
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column col-12">
                            <div class="d-flex flex-wrap">
                                <button type="submit" class="btn btn-primary mr-2 mb-2">
                                    <i class="icon icon-search"></i> Apply Filters
                                </button>
                                <a href="/reports" class="btn btn-link mb-2">Clear Filters</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column col-12 col-lg-4">
        <div class="card saved-filter-card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-bookmark mr-1"></i> Saved Filters
                </div>
                <div class="card-subtitle text-gray">
                    Store frequently used filter combinations for quick access.
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="/reports/saved-filters" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="filters_json" value='<?= htmlspecialchars($currentFilterJson, ENT_QUOTES, 'UTF-8') ?>'>
                    <div class="form-group">
                        <label class="form-label" for="new_filter_name">Name current filter set</label>
                        <input type="text" class="form-input" id="new_filter_name" name="name" placeholder="Quarterly compliance" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="icon icon-plus"></i> Save current filters
                    </button>
                </form>

                <?php if (empty($this->data['saved_filters'])): ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="icon icon-flag text-gray"></i></div>
                        <p class="empty-title h5">No saved filters yet</p>
                        <p class="empty-subtitle">Use the form above to capture your current configuration.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($this->data['saved_filters'] as $savedFilter): ?>
                        <?php $isActive = (int) ($savedFilter['id'] ?? 0) === (int) $activeSavedFilterId; ?>
                        <div class="tile<?= $isActive ? ' active-filter' : '' ?>">
                            <div class="tile-content">
                                <div class="tile-title h6 mb-1">
                                    <?= htmlspecialchars($savedFilter['name'] ?? 'Saved Filter') ?>
                                    <?php if ($isActive): ?>
                                        <span class="label label-primary ml-1">Active</span>
                                    <?php endif; ?>
                                </div>
                                <?php $summary = describe_filter($savedFilter['filters'] ?? []); ?>
                                <div class="tile-subtitle text-gray">
                                    <?= empty($summary) ? 'No specific filter facets stored.' : implode(' • ', $summary) ?>
                                </div>
                            </div>
                            <div class="tile-action tile-actions mt-2">
                                <form method="GET" action="/reports">
                                    <input type="hidden" name="saved_filter_id" value="<?= (int) $savedFilter['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="icon icon-forward"></i> Apply
                                    </button>
                                </form>
                                <form method="POST" action="/reports/saved-filters/<?= (int) $savedFilter['id'] ?>/update">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <input type="hidden" name="update_action" value="refresh">
                                    <input type="hidden" name="filters_json" value='<?= htmlspecialchars($currentFilterJson, ENT_QUOTES, 'UTF-8') ?>'>
                                    <button type="submit" class="btn btn-sm btn-secondary">
                                        <i class="icon icon-refresh"></i> Overwrite with current
                                    </button>
                                </form>
                                <form method="POST" action="/reports/saved-filters/<?= (int) $savedFilter['id'] ?>/update" class="mt-1">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <input type="hidden" name="update_action" value="rename">
                                    <div class="input-group">
                                        <input type="text" name="name" class="form-input input-sm" value="<?= htmlspecialchars($savedFilter['name'] ?? '') ?>" aria-label="Rename saved filter">
                                        <button type="submit" class="btn btn-sm">Rename</button>
                                    </div>
                                </form>
                                <form method="POST" action="/reports/saved-filters/<?= (int) $savedFilter['id'] ?>/delete" class="mt-1">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <button type="submit" class="btn btn-sm btn-error">
                                        <i class="icon icon-cross"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="column col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <div class="card-title h5 mb-1">
                        <i class="icon icon-mail mr-1"></i> Aggregate Reports
                    </div>
                    <div class="card-subtitle text-gray">
                        <?= empty($activeFilterSummary) ? 'Displaying all accessible aggregate reports.' : implode(' • ', $activeFilterSummary) ?>
                    </div>
                </div>
                <div class="export-actions">
                    <form method="GET" action="/reports/export/csv" class="mb-1">
                        <?= render_hidden_fields($queryBase) ?>
                        <button type="submit" class="btn btn-sm btn-secondary">
                            <i class="icon icon-download"></i> CSV Export
                        </button>
                    </form>
                    <form method="GET" action="/reports/export/xlsx">
                        <?= render_hidden_fields($queryBase) ?>
                        <button type="submit" class="btn btn-sm btn-secondary">
                            <i class="icon icon-download"></i> XLSX Export
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($this->data['reports'])): ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="icon icon-mail text-gray"></i></div>
                        <p class="empty-title h5">No reports match the selected filters</p>
                        <p class="empty-subtitle">Try expanding the date range, removing ownership filters, or clearing policy requirements.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-metrics">
                            <thead>
                                <tr>
                                    <th class="sort-header" onclick="location.href='<?= build_sort_url('domain', $sortBy, $sortDir, $queryBase) ?>'">
                                        Domain <?= sort_indicator('domain', $sortBy, $sortDir) ?>
                                    </th>
                                    <th>Reporter</th>
                                    <th class="sort-header text-center" onclick="location.href='<?= build_sort_url('date_range_begin', $sortBy, $sortDir, $queryBase) ?>'">
                                        Period <?= sort_indicator('date_range_begin', $sortBy, $sortDir) ?>
                                    </th>
                                    <th class="text-center">Totals</th>
                                    <th class="text-center">Pass</th>
                                    <th class="text-center">Quarantine</th>
                                    <th class="text-center">Reject</th>
                                    <th class="text-center">DKIM Pass</th>
                                    <th class="text-center">SPF Pass</th>
                                    <th class="sort-header text-center" onclick="location.href='<?= build_sort_url('received_at', $sortBy, $sortDir, $queryBase) ?>'">
                                        Received <?= sort_indicator('received_at', $sortBy, $sortDir) ?>
                                    </th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->data['reports'] as $report): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($report['domain'] ?? '') ?></strong>
                                            <?php if (!empty($report['ownership_contact'])): ?>
                                                <br><small class="text-gray"><?= htmlspecialchars($report['ownership_contact']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($report['enforcement_level'])): ?>
                                                <div class="mt-1"><?= enforcement_badge($report['enforcement_level']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($report['org_name'] ?? '') ?>
                                            <?php if (!empty($report['email'])): ?>
                                                <br><small class="text-gray"><?= htmlspecialchars($report['email']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($report['report_id'])): ?>
                                                <br><small class="text-tiny">ID: <?= htmlspecialchars($report['report_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?= !empty($report['date_range_begin']) ? date('M j', (int) $report['date_range_begin']) : '—' ?> –
                                                <?= !empty($report['date_range_end']) ? date('M j, Y', (int) $report['date_range_end']) : '—' ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="chip">Total: <?= number_format((int) ($report['total_volume'] ?? 0)) ?></div>
                                            <div class="chip chip-error">Failures: <?= getFailureBadge((int) ($report['failure_volume'] ?? 0), (int) ($report['total_volume'] ?? 0)) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('none', number_format((int) ($report['passed_count'] ?? 0))) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('quarantine', number_format((int) ($report['quarantined_count'] ?? 0))) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('reject', number_format((int) ($report['rejected_count'] ?? 0))) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getAuthResultBadge((int) ($report['dkim_pass_count'] ?? 0), (int) ($report['total_volume'] ?? 0)) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getAuthResultBadge((int) ($report['spf_pass_count'] ?? 0), (int) ($report['total_volume'] ?? 0)) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $receivedRaw = $report['received_at'] ?? null;
                                            $receivedTs = $receivedRaw ? strtotime((string) $receivedRaw) : false;
                                            ?>
                                            <small><?= $receivedTs ? date('M j, Y H:i', $receivedTs) : '—' ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="mb-1">
                                                <a href="/report/<?= (int) ($report['id'] ?? 0) ?>" class="btn btn-sm btn-primary" title="View details">
                                                    <i class="icon icon-eye"></i>
                                                </a>
                                            </div>
                                            <form method="POST" action="/reports" class="send-report-form">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                                <input type="hidden" name="action" value="send_report_email">
                                                <input type="hidden" name="report_id" value="<?= (int) ($report['id'] ?? 0) ?>">
                                                <input type="text" name="recipients" class="form-input" placeholder="Emails" required>
                                                <button type="submit" class="btn btn-sm btn-secondary mt-1">
                                                    <i class="icon icon-send"></i> Email report
                                                </button>
                                            </form>
                                            <small class="text-gray">Separate multiple addresses with commas.</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (($this->data['pagination']['total_pages'] ?? 1) > 1): ?>
    <?php
    $currentPage = (int) ($this->data['pagination']['current_page'] ?? 1);
    $totalPages = (int) ($this->data['pagination']['total_pages'] ?? 1);
    $paginationBase = $queryBase;
    ?>
    <div class="columns">
        <div class="column col-12">
            <div class="d-flex justify-content-center mt-2">
                <ul class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <?php $prevParams = $paginationBase; $prevParams['page'] = $currentPage - 1; ?>
                        <li class="page-item">
                            <a class="page-link" href="/reports?<?= http_build_query($prevParams) ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $currentPage - 2);
                    $end = min($totalPages, $currentPage + 2);
                    for ($i = $start; $i <= $end; $i++):
                        $pageParams = $paginationBase;
                        $pageParams['page'] = $i;
                    ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="/reports?<?= http_build_query($pageParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <?php $nextParams = $paginationBase; $nextParams['page'] = $currentPage + 1; ?>
                        <li class="page-item">
                            <a class="page-link" href="/reports?<?= http_build_query($nextParams) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
