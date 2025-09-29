<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container">
    <div class="columns">
        <div class="column col-12">
            <h1>DMARC Dashboard</h1>
            
            <!-- Statistics Cards -->
            <div class="columns">
                <div class="column col-3">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">Total Domains</div>
                            <div class="card-subtitle text-gray">Active monitoring</div>
                        </div>
                        <div class="card-body">
                            <div class="h2 text-primary"><?= htmlspecialchars($stats['total_domains']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="column col-3">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">Total Reports</div>
                            <div class="card-subtitle text-gray">Processed</div>
                        </div>
                        <div class="card-body">
                            <div class="h2 text-success"><?= htmlspecialchars($stats['total_reports']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="column col-3">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">Active Brands</div>
                            <div class="card-subtitle text-gray">Organizations</div>
                        </div>
                        <div class="card-body">
                            <div class="h2 text-secondary"><?= count($stats['brands']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="column col-3">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">Recent Activity</div>
                            <div class="card-subtitle text-gray">This week</div>
                        </div>
                        <div class="card-body">
                            <div class="h2 text-warning"><?= count($stats['recent_reports']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports and Domain Status -->
            <div class="columns">
                <div class="column col-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">
                                <i class="icon icon-bookmark mr-1"></i>Recent DMARC Reports
                            </div>
                            <div class="card-subtitle">
                                <a href="/reports" class="btn btn-primary btn-sm">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['recent_reports'])): ?>
                                <p class="text-gray">No recent reports found.</p>
                            <?php else: ?>
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>Reporter</th>
                                            <th>Period</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_reports'] as $report): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($report->domain) ?></td>
                                                <td><?= htmlspecialchars($report->org_name) ?></td>
                                                <td><?= date('M j', strtotime($report->report_begin)) ?> - <?= date('M j', strtotime($report->report_end)) ?></td>
                                                <td>
                                                    <?php if ($report->processed): ?>
                                                        <span class="label label-success">Processed</span>
                                                    <?php else: ?>
                                                        <span class="label label-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Domain Status -->
                <div class="column col-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">
                                <i class="icon icon-location mr-1"></i>Domain Status
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($domains)): ?>
                                <p class="text-gray">No domains configured.</p>
                                <a href="/domains" class="btn btn-primary btn-sm">Add Domain</a>
                            <?php else: ?>
                                <?php foreach (array_slice($domains, 0, 5) as $domain): ?>
                                    <div class="tile tile-centered">
                                        <div class="tile-content">
                                            <div class="tile-title"><?= htmlspecialchars($domain->domain) ?></div>
                                            <small class="tile-subtitle text-gray">
                                                Policy: <?= htmlspecialchars(strtoupper($domain->dmarc_policy)) ?>
                                                <?php if ($domain->brand_name): ?>
                                                    | <?= htmlspecialchars($domain->brand_name) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="tile-action">
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
                                                    $statusClass = 'error';
                                                    $statusText = 'Poor';
                                                }
                                            }
                                            ?>
                                            <span class="label label-<?= $statusClass ?>"><?= $statusText ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($domains) > 5): ?>
                                    <div class="text-center mt-2">
                                        <a href="/domains" class="btn btn-sm">View All Domains</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="columns">
                <div class="column col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title h5">
                                <i class="icon icon-apps mr-1"></i>Quick Actions
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="columns">
                                <div class="column col-3">
                                    <a href="/domains" class="btn btn-lg btn-block">
                                        <i class="icon icon-location mr-1"></i>Manage Domains
                                    </a>
                                </div>
                                <div class="column col-3">
                                    <a href="/reports" class="btn btn-lg btn-block">
                                        <i class="icon icon-bookmark mr-1"></i>View Reports
                                    </a>
                                </div>
                                <div class="column col-3">
                                    <a href="/upload" class="btn btn-lg btn-block">
                                        <i class="icon icon-upload mr-1"></i>Upload Reports
                                    </a>
                                </div>
                                <div class="column col-3">
                                    <a href="/settings" class="btn btn-lg btn-block">
                                        <i class="icon icon-apps mr-1"></i>Settings
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