<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">DMARC Dashboard</h1>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Domains</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= htmlspecialchars($stats['total_domains']) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-globe fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Reports</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= htmlspecialchars($stats['total_reports']) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-area fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Active Brands</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= count($stats['brands']) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-building fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Recent Activity</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= count($stats['recent_reports']) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="row">
                <div class="col-xl-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent DMARC Reports</h6>
                            <a href="/reports" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['recent_reports'])) : ?>
                                <p class="text-muted">No recent reports found.</p>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Domain</th>
                                                <th>Reporter</th>
                                                <th>Period</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_reports'] as $report) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($report->domain) ?></td>
                                                    <td><?= htmlspecialchars($report->org_name) ?></td>
                                                    <td><?= date('M j', strtotime($report->report_begin)) ?> - <?= date('M j', strtotime($report->report_end)) ?></td>
                                                    <td>
                                                        <?php if ($report->processed) : ?>
                                                            <span class="badge badge-success">Processed</span>
                                                        <?php else : ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php endif; ?>
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

                <!-- Domain Status -->
                <div class="col-xl-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Domain Status</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($domains)) : ?>
                                <p class="text-muted">No domains configured.</p>
                                <a href="/domains" class="btn btn-primary btn-sm">Add Domain</a>
                            <?php else : ?>
                                <?php foreach (array_slice($domains, 0, 5) as $domain) : ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-grow-1">
                                            <div class="font-weight-bold"><?= htmlspecialchars($domain->domain) ?></div>
                                            <small class="text-muted">
                                                Policy: <?= htmlspecialchars(strtoupper($domain->dmarc_policy)) ?>
                                                <?php if ($domain->brand_name) : ?>
                                                    | <?= htmlspecialchars($domain->brand_name) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="ml-2">
                                            <?php
                                            $statusClass = 'secondary';
                                            $statusText = 'Unknown';

                                            if ($domain->recent_stats->total_reports > 0) {
                                                $passRate = $domain->recent_stats->total_reports > 0
                                                    ? ($domain->recent_stats->processed_reports / $domain->recent_stats->total_reports) * 100
                                                    : 0;

                                                if ($passRate >= 90) {
                                                    $statusClass = 'success';
                                                    $statusText = 'Good';
                                                } elseif ($passRate >= 70) {
                                                    $statusClass = 'warning';
                                                    $statusText = 'Fair';
                                                } else {
                                                    $statusClass = 'danger';
                                                    $statusText = 'Poor';
                                                }
                                            }
                                            ?>
                                            <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($domains) > 5) : ?>
                                    <div class="text-center">
                                        <a href="/domains" class="btn btn-sm btn-outline-primary">View All Domains</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="/domains" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-globe mr-2"></i>Manage Domains
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/reports" class="btn btn-outline-success btn-block">
                                        <i class="fas fa-chart-line mr-2"></i>View Reports
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/upload" class="btn btn-outline-info btn-block">
                                        <i class="fas fa-upload mr-2"></i>Upload Reports
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/settings" class="btn btn-outline-secondary btn-block">
                                        <i class="fas fa-cog mr-2"></i>Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>