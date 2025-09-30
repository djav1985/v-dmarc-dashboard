<?php
require __DIR__ . '/../partials/header.php';

use App\Core\SessionManager;

$session = SessionManager::getInstance();
$csrf = $session->get('csrf_token');
?>
    <div class="columns mt-2">
        <div class="column col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5"><i class="icon icon-flag mr-2"></i>Forensic reports</div>
                    <div class="card-subtitle text-gray">High fidelity DMARC failure samples for in-depth analysis</div>
                </div>
                <div class="card-body">
                    <form method="get" class="form-horizontal mb-2">
                        <div class="columns">
                            <div class="column col-12 col-md-4">
                                <label class="form-label" for="domain">Domain filter</label>
                                <select name="domain" id="domain" class="form-select">
                                    <option value="">All accessible domains</option>
                                    <?php foreach ($domains as $domain) : ?>
                                        <option value="<?= (int) $domain['id']; ?>" <?= ($selectedDomain ?? '') == $domain['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($domain['domain']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="column col-12 col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="icon icon-refresh mr-1"></i> Apply
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Arrival</th>
                                    <th scope="col">Domain</th>
                                    <th scope="col">Source IP</th>
                                    <th scope="col">Authentication</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reports)) : ?>
                                    <?php foreach ($reports as $report) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['arrival_date']); ?></td>
                                            <td><?= htmlspecialchars($report['domain']); ?></td>
                                            <td><?= htmlspecialchars($report['source_ip']); ?></td>
                                            <td>
                                                <span class="label label-rounded label-primary">DKIM: <?= htmlspecialchars($report['dkim_result'] ?? 'n/a'); ?></span>
                                                <span class="label label-rounded label-secondary">SPF: <?= htmlspecialchars($report['spf_result'] ?? 'n/a'); ?></span>
                                            </td>
                                            <td>
                                                <a class="btn btn-sm" href="/forensic-reports/<?= (int) $report['id']; ?>">
                                                    <i class="icon icon-search"></i> View sample
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-gray">No forensic reports available for the current filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../partials/footer.php';
?>
