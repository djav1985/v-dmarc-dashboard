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
                    <div class="card-title h5"><i class="icon icon-activity mr-2"></i>Security audit logs</div>
                    <div class="card-subtitle text-gray">Review authentication, configuration, and access changes</div>
                </div>
                <div class="card-body">
                    <form method="post" class="form-horizontal mb-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="columns">
                            <div class="column col-12 col-md-3">
                                <label class="form-label" for="filter-user">User</label>
                                <input type="text" id="filter-user" name="user" class="form-input" placeholder="username" value="<?= htmlspecialchars($filters['user'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="column col-12 col-md-3">
                                <label class="form-label" for="filter-action">Action</label>
                                <select id="filter-action" name="action" class="form-select">
                                    <option value="">All actions</option>
                                    <?php foreach ($actions as $actionName) : ?>
                                        <option value="<?= htmlspecialchars($actionName, ENT_QUOTES, 'UTF-8'); ?>" <?= ($filters['action'] ?? '') === $actionName ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars(str_replace('_', ' ', $actionName)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="column col-6 col-md-2">
                                <label class="form-label" for="filter-limit">Limit</label>
                                <input type="number" id="filter-limit" name="limit" class="form-input" min="10" max="200" value="<?= (int) ($filters['limit'] ?? 50); ?>">
                            </div>
                            <div class="column col-6 col-md-2 d-flex flex-column justify-end">
                                <button type="submit" class="btn btn-primary mt-2 mt-md-4">
                                    <i class="icon icon-refresh mr-1"></i> Apply filters
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Timestamp</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Resource</th>
                                    <th scope="col">Details</th>
                                    <th scope="col">IP</th>
                                    <th scope="col">User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)) : ?>
                                    <?php foreach ($logs as $log) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($log['timestamp']); ?></td>
                                            <td>
                                                <?= htmlspecialchars($log['user_id'] ?? 'system'); ?>
                                                <?php if (!empty($log['first_name']) || !empty($log['last_name'])) : ?>
                                                    <br><small class="text-gray"><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="label label-rounded label-primary text-uppercase"><?= htmlspecialchars($log['action']); ?></span></td>
                                            <td>
                                                <strong><?= htmlspecialchars($log['resource_type'] ?? 'n/a'); ?></strong><br>
                                                <small class="text-gray">ID: <?= htmlspecialchars($log['resource_id'] ?? '—'); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($log['details'])) : ?>
                                                    <code><?= htmlspecialchars($log['details']); ?></code>
                                                <?php else : ?>
                                                    <span class="text-gray">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                            <td class="text-break"><?= htmlspecialchars($log['user_agent'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-gray">No audit events found for the selected filters.</td>
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
