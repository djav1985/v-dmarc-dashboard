<?php

use App\Core\SessionManager;
use App\Helpers\MessageHelper;

$csrf = SessionManager::getInstance()->get('csrf_token');
?>
<!DOCTYPE html>
<html lang="en-US">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create a new password • <?= defined('APP_NAME') ? APP_NAME : 'DMARC Dashboard'; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-exp.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-icons.min.css">
    <link rel="stylesheet" href="/assets/css/enhanced-styles.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>

<body class="bg-gray">
    <div class="container grid-lg">
        <div class="columns">
            <div class="column col-12 col-sm-10 col-md-6 col-lg-5 col-mx-auto mt-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title h5">Choose a new password</div>
                        <div class="card-subtitle text-gray">Passwords must be at least 12 characters long.</div>
                    </div>
                    <div class="card-body">
                        <form method="post" class="form-horizontal">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <label class="form-label" for="password">New password</label>
                                <input type="password" class="form-input" id="password" name="password" required autocomplete="new-password" minlength="12">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm password</label>
                                <input type="password" class="form-input" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="12">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                Update password
                            </button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small class="text-gray">Resetting for <?= htmlspecialchars($email ?? 'your account', ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                </div>
                <?php foreach (MessageHelper::getMessages() as $message) : ?>
                    <div class="toast mt-2 <?= $message['type'] === 'error' ? 'toast-error' : 'toast-' . htmlspecialchars($message['type']); ?>">
                        <?= htmlspecialchars($message['text']); ?>
                    </div>
                <?php endforeach; ?>
                <?php MessageHelper::clearMessages(); ?>
            </div>
        </div>
    </div>
</body>

</html>
