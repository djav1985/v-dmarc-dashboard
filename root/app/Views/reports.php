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
 * Description: DMARC Reports listing with filtering and sorting
 */

require 'partials/header.php';

// Helper function for building sort URLs
function getSortUrl($column, $currentSort, $currentDir) {
    $newDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $newDir;
    unset($params['page']); // Reset to page 1 when sorting
    return '/reports?' . http_build_query($params);
}

// Helper function for sort indicators
function getSortIndicator($column, $currentSort, $currentDir) {
    if ($currentSort !== $column) {
        return '<i class="icon icon-resize-vert text-gray"></i>';
    }
    return $currentDir === 'ASC' 
        ? '<i class="icon icon-arrow-up text-primary"></i>' 
        : '<i class="icon icon-arrow-down text-primary"></i>';
}

// Helper function for disposition badge styling
function getDispositionBadge($disposition, $count) {
    $classes = [
        'none' => 'label-success',
        'quarantine' => 'label-warning', 
        'reject' => 'label-error'
    ];
    $class = $classes[$disposition] ?? 'label-secondary';
    return "<span class=\"label $class\">$count</span>";
}

// Helper function for authentication result styling
function getAuthResultBadge($result, $total) {
    if ($total == 0) return '<span class="label label-secondary">0</span>';
    $percentage = round(($result / $total) * 100);
    $class = $percentage >= 80 ? 'label-success' : ($percentage >= 50 ? 'label-warning' : 'label-error');
    return "<span class=\"label $class\">$result ($percentage%)</span>";
}
?>

<style>
.filter-row {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.filter-row .columns {
    margin-bottom: 0;
}
.filter-row .form-group {
    margin-bottom: 0.5rem;
}
@media (min-width: 960px) {
    .filter-row .form-group {
        margin-bottom: 0;
    }
}
.table-responsive {
    overflow-x: auto;
}
.sort-header {
    cursor: pointer;
    user-select: none;
}
.sort-header:hover {
    background-color: #f1f3f4;
}
.send-report-form {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.3rem;
    margin-top: 0.5rem;
}
.send-report-form .form-input {
    max-width: 220px;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-list text-primary mr-2"></i>
                DMARC Reports
            </h2>
            <div class="text-gray">
                Total: <?= number_format($this->data['pagination']['total_reports']) ?> reports
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="columns">
    <div class="column col-12">
        <form method="POST" action="/reports" class="filter-row">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <div class="columns">
                <div class="column col-12 col-sm-6 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="domain">Domain</label>
                        <select class="form-select" id="domain" name="domain">
                            <option value="">All Domains</option>
                            <?php foreach ($this->data['domains'] as $domain): ?>
                                <option value="<?= htmlspecialchars($domain['domain']) ?>"
                                    <?= $this->data['filters']['domain'] === $domain['domain'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($domain['domain']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-2">
                    <div class="form-group">
                        <label class="form-label" for="disposition">Disposition</label>
                        <select class="form-select" id="disposition" name="disposition">
                            <option value="">All</option>
                            <option value="none" <?= $this->data['filters']['disposition'] === 'none' ? 'selected' : '' ?>>None (Pass)</option>
                            <option value="quarantine" <?= $this->data['filters']['disposition'] === 'quarantine' ? 'selected' : '' ?>>Quarantine</option>
                            <option value="reject" <?= $this->data['filters']['disposition'] === 'reject' ? 'selected' : '' ?>>Reject</option>
                        </select>
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-2">
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input type="date" class="form-input" id="start_date" name="start_date"
                               value="<?= htmlspecialchars($this->data['filters']['start_date']) ?>">
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-2">
                    <div class="form-group">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" class="form-input" id="end_date" name="end_date"
                               value="<?= htmlspecialchars($this->data['filters']['end_date']) ?>">
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-2">
                    <div class="form-group">
                        <label class="form-label" for="per_page">Per Page</label>
                        <select class="form-select" id="per_page" name="per_page">
                            <option value="25" <?= $this->data['pagination']['per_page'] == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $this->data['pagination']['per_page'] == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $this->data['pagination']['per_page'] == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-1">
                    <div class="form-group">
                        <label class="form-label d-invisible d-lg-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="icon icon-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="columns mt-1">
                <div class="column col-12 col-sm-6 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="date_from">From Date</label>
                        <input type="date" class="form-input" id="date_from" name="date_from"
                               value="<?= htmlspecialchars($this->data['filters']['date_from']) ?>">
                    </div>
                </div>

                <div class="column col-12 col-sm-6 col-lg-3">
                    <div class="form-group">
                        <label class="form-label" for="date_to">To Date</label>
                        <input type="date" class="form-input" id="date_to" name="date_to"
                               value="<?= htmlspecialchars($this->data['filters']['date_to']) ?>">
                    </div>
                </div>

                <div class="column col-12 col-lg-4 col-xl-3">
                    <div class="form-group">
                        <label class="form-label d-invisible d-lg-block">&nbsp;</label>
                        <div class="d-flex flex-wrap">
                            <button type="submit" class="btn btn-primary mr-2 mb-1">
                                <i class="icon icon-search"></i> Filter
                            </button>
                            <a href="/reports" class="btn btn-link mb-1">Clear</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reports Table -->
<div class="columns">
    <div class="column col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-mail mr-1"></i>
                    Aggregate Reports
                    <?php if ($this->data['pagination']['current_page'] > 1): ?>
                        - Page <?= $this->data['pagination']['current_page'] ?> of <?= $this->data['pagination']['total_pages'] ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($this->data['reports'])): ?>
                    <div class="empty">
                        <div class="empty-icon">
                            <i class="icon icon-4x icon-mail text-gray"></i>
                        </div>
                        <p class="empty-title h5">No Reports Found</p>
                        <p class="empty-subtitle">
                            No DMARC reports match the current filters. Try adjusting your search criteria.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="sort-header" onclick="location.href='<?= getSortUrl('domain', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>'">
                                        Domain <?= getSortIndicator('domain', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>
                                    </th>
                                    <th class="sort-header" onclick="location.href='<?= getSortUrl('org_name', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>'">
                                        Organization <?= getSortIndicator('org_name', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>
                                    </th>
                                    <th class="sort-header text-center" onclick="location.href='<?= getSortUrl('date_range_begin', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>'">
                                        Report Period <?= getSortIndicator('date_range_begin', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>
                                    </th>
                                    <th class="text-center">Volume</th>
                                    <th class="text-center">Pass</th>
                                    <th class="text-center">Quarantine</th>
                                    <th class="text-center">Reject</th>
                                    <th class="text-center">DKIM Pass</th>
                                    <th class="text-center">SPF Pass</th>
                                    <th class="sort-header text-center" onclick="location.href='<?= getSortUrl('received_at', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>'">
                                        Received <?= getSortIndicator('received_at', $this->data['filters']['sort_by'], $this->data['filters']['sort_dir']) ?>
                                    </th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->data['reports'] as $report): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($report['domain']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($report['org_name']) ?>
                                            <br><small class="text-gray"><?= htmlspecialchars($report['email']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?= date('M j', $report['date_range_begin']) ?> - 
                                                <?= date('M j, Y', $report['date_range_end']) ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="chip"><?= number_format($report['total_volume']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('none', number_format($report['passed_count'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('quarantine', number_format($report['quarantined_count'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getDispositionBadge('reject', number_format($report['rejected_count'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getAuthResultBadge($report['dkim_pass_count'], $report['total_volume']) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= getAuthResultBadge($report['spf_pass_count'], $report['total_volume']) ?>
                                        </td>
                                        <td class="text-center">
                                            <small><?= date('M j, Y H:i', strtotime($report['received_at'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <a href="/report/<?= $report['id'] ?>" class="btn btn-sm btn-primary" title="View Details">
                                                <i class="icon icon-eye"></i>
                                            </a>
                                            <form method="POST" action="/reports" class="send-report-form">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                                <input type="hidden" name="action" value="send_report_email">
                                                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                                <input type="text" name="recipients" class="form-input" placeholder="Emails" required>
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="icon icon-send"></i> Send by Email
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

<!-- Pagination -->
<?php if ($this->data['pagination']['total_pages'] > 1): ?>
<div class="columns">
    <div class="column col-12">
        <div class="d-flex justify-content-center mt-2">
            <ul class="pagination">
                <!-- Previous Page -->
                <?php if ($this->data['pagination']['current_page'] > 1): ?>
                    <?php
                    $prevParams = $_GET;
                    $prevParams['page'] = $this->data['pagination']['current_page'] - 1;
                    ?>
                    <li class="page-item">
                        <a class="page-link" href="/reports?<?= http_build_query($prevParams) ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start = max(1, $this->data['pagination']['current_page'] - 2);
                $end = min($this->data['pagination']['total_pages'], $this->data['pagination']['current_page'] + 2);
                
                for ($i = $start; $i <= $end; $i++):
                    $pageParams = $_GET;
                    $pageParams['page'] = $i;
                ?>
                    <li class="page-item <?= $i === $this->data['pagination']['current_page'] ? 'active' : '' ?>">
                        <a class="page-link" href="/reports?<?= http_build_query($pageParams) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($this->data['pagination']['current_page'] < $this->data['pagination']['total_pages']): ?>
                    <?php
                    $nextParams = $_GET;
                    $nextParams['page'] = $this->data['pagination']['current_page'] + 1;
                    ?>
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