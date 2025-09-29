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
    <div class="container grid-lg">
        <div class="columns">
            <div class="column col-8">
                <h2>DMARC Dashboard</h2>
                <p>Welcome, <?= $displayUsername ?>. Monitor your domain's email authentication status.</p>
            </div>
            <div class="column col-4">
                <div class="text-right">
                    <a href="/upload" class="btn btn-primary">Upload Reports</a>
                </div>
            </div>
        </div>
        
        <?php if (empty($dashboardSummary)): ?>
            <div class="empty">
                <div class="empty-icon">
                    <i class="icon icon-3x icon-mail"></i>
                </div>
                <p class="empty-title h5">No DMARC Reports Yet</p>
                <p class="empty-subtitle">Upload your first DMARC report to get started with monitoring your domain's email authentication.</p>
                <div class="empty-action">
                    <a href="/upload" class="btn btn-primary">Upload First Report</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5">Recent Activity (Last 7 Days)</div>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Reports</th>
                                <th>Passed</th>
                                <th>Quarantined</th>
                                <th>Rejected</th>
                                <th>Last Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboardSummary as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['domain']) ?></td>
                                    <td><?= (int) $row['report_count'] ?></td>
                                    <td><span class="text-success"><?= (int) $row['passed_count'] ?></span></td>
                                    <td><span class="text-warning"><?= (int) $row['quarantined_count'] ?></span></td>
                                    <td><span class="text-error"><?= (int) $row['rejected_count'] ?></span></td>
                                    <td><?= $row['last_report_date'] ? date('M j, Y', $row['last_report_date']) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php
 require 'partials/footer.php';
?>
