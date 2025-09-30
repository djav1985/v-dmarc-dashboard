<?php require_once __DIR__ . '/partials/header.php'; ?>

<div class="columns">
    <div class="column col-12">
        <h2><i class="icon icon-people"></i> User Management</h2>
        <p class="text-gray">Manage users, roles, and permissions for the DMARC Dashboard.</p>
    </div>
</div>

<!-- Add New User -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title">Add New User</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="/user-management">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <div class="columns">
                <div class="column col-12 col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="username">Username *</label>
                        <input class="form-input" type="text" id="username" name="username" required>
                    </div>
                </div>
                <div class="column col-12 col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="password">Password *</label>
                        <input class="form-input" type="password" id="password" name="password" required>
                        <div class="form-input-hint">Minimum 8 characters</div>
                    </div>
                </div>
            </div>
            
            <div class="columns">
                <div class="column col-12 col-md-4">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name</label>
                        <input class="form-input" type="text" id="first_name" name="first_name">
                    </div>
                </div>
                <div class="column col-12 col-md-4">
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input class="form-input" type="text" id="last_name" name="last_name">
                    </div>
                </div>
                <div class="column col-12 col-md-4">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-input" type="email" id="email" name="email">
                    </div>
                </div>
            </div>

            <div class="columns">
                <div class="column col-12 col-lg-6">
                    <div class="form-group">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                                <option value="<?= htmlspecialchars($roleKey) ?>">
                                    <?= htmlspecialchars($roleLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="column col-12 col-lg-6 mt-2 mt-lg-0">
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="icon icon-plus"></i> Create User
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Existing Users</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td>
                            <?php 
                            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            echo htmlspecialchars($name ?: '-');
                            ?>
                        </td>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        <td>
                            <span class="badge badge-primary">
                                <?= htmlspecialchars($roles[$user['role']] ?? $user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <small><?= date('M j, Y H:i', strtotime($user['last_login'])) ?></small>
                            <?php else: ?>
                                <small class="text-gray">Never</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary" onclick="editUser('<?= htmlspecialchars($user['username']) ?>')">
                                    <i class="icon icon-edit"></i>
                                </button>
                                <?php if ($user['username'] !== ($_SESSION['username'] ?? '')): ?>
                                <button class="btn btn-sm btn-error" onclick="deleteUser('<?= htmlspecialchars($user['username']) ?>')">
                                    <i class="icon icon-delete"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal (simplified for demo) -->
<div class="modal" id="edit-user-modal">
    <a href="#close" class="modal-overlay" onclick="closeEditModal()"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" onclick="closeEditModal()"></a>
            <div class="modal-title h5">Edit User</div>
        </div>
        <div class="modal-body">
            <form method="POST" action="/user-management" id="edit-user-form">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="username" id="edit-username">
                
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" id="edit-role">
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <option value="<?= htmlspecialchars($roleKey) ?>">
                                <?= htmlspecialchars($roleLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" id="edit-active" value="1"> Active
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(username) {
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-user-modal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('edit-user-modal').classList.remove('active');
}

function deleteUser(username) {
    if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/user-management';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="username" value="${username}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>