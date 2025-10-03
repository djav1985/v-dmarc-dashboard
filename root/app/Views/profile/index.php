<?php
require __DIR__ . '/../partials/header.php';

use App\Core\SessionManager;

$session = SessionManager::getInstance();
$csrf = $session->get('csrf_token');
$userData = $user ?? null;
?>
<div class="columns mt-2">
    <div class="column col-12 col-xl-10 col-mx-auto">
        <div class="hero hero-sm bg-secondary text-center text-light mb-2">
            <div class="hero-body">
                <h2 class="mb-0"><i class="icon icon-people mr-2"></i>Account Profile</h2>
                <p class="mb-0">Manage your contact details and authentication settings</p>
            </div>
        </div>

        <div class="columns">
            <div class="column col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title h5">Profile details</div>
                        <div class="card-subtitle text-gray">These details are used in notifications.</div>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update_details">
                            <div class="form-group">
                                <label class="form-label" for="first_name">First name</label>
                                <input type="text" class="form-input" id="first_name" name="first_name" value="<?= htmlspecialchars(is_object($userData) ? $userData->first_name : '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last_name">Last name</label>
                                <input type="text" class="form-input" id="last_name" name="last_name" value="<?= htmlspecialchars(is_object($userData) ? $userData->last_name : '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-input" id="email" name="email" required value="<?= htmlspecialchars(is_object($userData) ? $userData->email : '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email">
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="icon icon-check mr-1"></i> Save changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="column col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title h5">Update password</div>
                        <div class="card-subtitle text-gray">Passwords must be at least 12 characters long.</div>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update_password">
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current password</label>
                                <input type="password" class="form-input" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_password">New password</label>
                                <input type="password" class="form-input" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm new password</label>
                                <input type="password" class="form-input" id="confirm_password" name="confirm_password" required minlength="12" autocomplete="new-password">
                            </div>
                            <button class="btn btn-secondary" type="submit">
                                <i class="icon icon-lock mr-1"></i> Update password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php';
?>
