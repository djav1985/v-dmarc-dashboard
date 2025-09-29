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
    <link rel="stylesheet" href="/assets/css/login.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>
<body class="bg-gray">
    <div class="hero hero-sm">
        <div class="hero-body">
            <div class="container grid-lg">
                <div class="columns">
                    <div class="column col-6 col-mx-auto">
                        <div class="card">
                            <div class="card-header text-center">
                                <div class="card-title h4">
                                    <i class="icon icon-2x icon-mail text-primary"></i>
                                    <div class="mt-2">DMARC Dashboard</div>
                                </div>
                                <div class="card-subtitle text-gray">
                                    Secure email authentication monitoring
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Login form -->
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="username">Username</label>
                                        <input class="form-input" id="username" type="text" name="username" 
                                               autocomplete="username" placeholder="Enter your username" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="password">Password</label>
                                        <input class="form-input" id="password" type="password" name="password" 
                                               autocomplete="current-password" placeholder="Enter your password" required>
                                    </div>

                                    <div class="form-group">
                                        <button class="btn btn-primary btn-block btn-lg" type="submit">
                                            <i class="icon icon-arrow-right"></i> Sign In
                                        </button>
                                    </div>
                                </form>

                                <div class="divider text-center" data-content="DEFAULT CREDENTIALS"></div>
                                
                                <div class="toast toast-primary">
                                    <div class="toast-body">
                                        <small>
                                            <strong>Default login:</strong> admin / admin<br>
                                            Please change after first login
                                        </small>
                                    </div>
                                </div>
                            </div>
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
