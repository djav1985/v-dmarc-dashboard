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
                <div class="card-title h5"><i class="icon icon-shield mr-2"></i>IP blacklist</div>
                <div class="card-subtitle text-gray">Manually ban abusive sources or reinstate trusted addresses.</div>
            </div>
            <div class="card-body">
                <form method="post" class="form-inline mb-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group mr-2">
                        <label class="form-label" for="ip">IP address</label>
                        <input type="text" id="ip" name="ip" class="form-input" placeholder="203.0.113.10" required>
                    </div>
                    <div class="form-group mr-2">
                        <button type="submit" name="action" value="ban" class="btn btn-error">
                            <i class="icon icon-stop mr-1"></i> Ban IP
                        </button>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="action" value="unban" class="btn btn-success">
                            <i class="icon icon-check mr-1"></i> Unban IP
                        </button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th scope="col">IP address</th>
                                <th scope="col">Login attempts</th>
                                <th scope="col">Status</th>
                                <th scope="col">Last updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($entries)) : ?>
                                <?php foreach ($entries as $entry) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entry['ip_address']); ?></td>
                                        <td><?= (int) $entry['login_attempts']; ?></td>
                                        <td>
                                            <?php if (!empty($entry['blacklisted'])) : ?>
                                                <span class="label label-rounded label-error">Blacklisted</span>
                                            <?php else : ?>
                                                <span class="label label-rounded label-success">Allowed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i:s', (int) $entry['timestamp'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="text-center text-gray">No blacklist entries found.</td>
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
