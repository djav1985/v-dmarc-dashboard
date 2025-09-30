<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: login.php
 * Description: V PHP Framework
 */
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <!-- Meta tags for responsive design and SEO -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= defined('APP_NAME') ? APP_NAME : 'DMARC Dashboard' ?></title>
    <!-- External CSS for styling -->
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-exp.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-icons.min.css">
    <link rel="stylesheet" href="/assets/css/enhanced-styles.css">
    <link rel="stylesheet" href="/assets/css/login.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>
<body>
    <div class="container grid-lg">
        <div class="columns">
            <div class="column col-4 col-sm-8 col-xs-12 col-mx-auto">
                <div class="login-container">
                    <div class="text-center mb-3">
                        <i class="icon icon-3x icon-mail text-primary mb-2"></i>
                        <h2>DMARC Dashboard</h2>
                        <p class="text-gray">Secure email authentication monitoring</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Sign In</div>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                                
                                <div class="form-group">
                                    <label class="form-label" for="username">Username</label>
                                    <input class="form-input" id="username" type="text" name="username" 
                                           autocomplete="username" placeholder="Enter username" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="password">Password</label>
                                    <input class="form-input" id="password" type="password" name="password" 
                                           autocomplete="current-password" placeholder="Enter password" required>
                                </div>

                                <div class="form-group">
                                    <button class="btn btn-primary btn-block" type="submit">
                                        Sign In
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <div class="toast">
                            <small class="text-gray">
                                <strong>Default credentials:</strong> admin / admin
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Display error messages if any -->
    <?php App\Helpers\MessageHelper::displayAndClearMessages(); ?>
</body>
</html>
