<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: home.php
 * Description: DMARC Dashboard main view
 */

require 'partials/header.php';

use App\Models\DmarcReport;

$rawUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
$displayUsername = trim($rawUsername) !== '' ? $rawUsername : 'User';
$displayUsername = htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8');

// Get dashboard summary data
$dashboardSummary = [];
try {
    $dashboardSummary = DmarcReport::getDashboardSummary(7);
} catch (Exception $e) {
    // Database may not be set up yet - handle gracefully
    $dashboardSummary = [];
}
?>
    <div class="columns">
        <div class="column col-12">
            <div class="text-center mb-2">
                <h2>
                    <i class="icon icon-2x icon-shield text-primary mr-2"></i>
                    DMARC Dashboard
                </h2>
                <p class="text-gray">Welcome, <?= $displayUsername ?>. Monitor your domain's email authentication status.</p>
            </div>
        </div>
    </div>

    <div class="columns">
        <div class="column col-12">
            <?php if (empty($dashboardSummary)): ?>
                <div class="empty">
                    <div class="empty-icon">
                        <i class="icon icon-4x icon-mail text-primary"></i>
                    </div>
                    <p class="empty-title h4">No DMARC Reports Yet</p>
                    <p class="empty-subtitle">
                        Upload your first DMARC report to get started with monitoring your domain's email authentication.
                    </p>
                    <div class="empty-action">
                        <a href="/upload" class="btn btn-primary btn-lg">
                            <i class="icon icon-upload"></i> Upload First Report
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="columns">
                    <div class="column col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title h5">
                                    <i class="icon icon-time mr-1"></i>
                                    Recent Activity (Last 7 Days)
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th class="text-center">Reports</th>
                                            <th class="text-center">Passed</th>
                                            <th class="text-center">Quarantined</th>
                                            <th class="text-center">Rejected</th>
                                            <th>Last Report</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dashboardSummary as $row): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($row['domain']) ?></strong></td>
                                                <td class="text-center">
                                                    <span class="chip"><?= (int) $row['report_count'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="label label-success"><?= (int) $row['passed_count'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="label label-warning"><?= (int) $row['quarantined_count'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="label label-error"><?= (int) $row['rejected_count'] ?></span>
                                                </td>
                                                <td><?= $row['last_report_date'] ? date('M j, Y', $row['last_report_date']) : 'N/A' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title h6">Quick Actions</div>
                            </div>
                            <div class="card-body">
                                <div class="menu">
                                    <div class="menu-item">
                                        <a href="/upload" class="btn btn-primary btn-block">
                                            <i class="icon icon-upload"></i> Upload Reports
                                        </a>
                                    </div>
                                    <div class="divider"></div>
                                    <div class="menu-item">
                                        <small class="text-gray">
                                            Future features:<br>
                                            • Email ingestion<br>
                                            • Advanced analytics<br>
                                            • Alert management
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
 require 'partials/footer.php';
?>
