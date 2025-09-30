<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: header.php
 * Description: Enhanced header with branding and RBAC support
 */

use App\Core\BrandingManager;
use App\Core\RBACManager;

$branding = BrandingManager::getInstance();
$rbac = RBACManager::getInstance();
$brandingVars = $branding->getBrandingVars();
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-exp.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-icons.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
    <link rel="stylesheet" href="/assets/css/enhanced-styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="/assets/js/header-scripts.js"></script>
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <title><?= htmlspecialchars($brandingVars['app_name']) ?></title>
    
    <!-- Custom branding CSS -->
    <style>
        <?= $branding->getCustomCSS() ?>
    </style>
</head>
<body>
    <header class="navbar">
        <section class="navbar-section">
            <a class="navbar-brand" href="/home">
                <?php if (!empty($brandingVars['app_logo_url'])): ?>
                    <img src="<?= htmlspecialchars($brandingVars['app_logo_url']) ?>" 
                         alt="<?= htmlspecialchars($brandingVars['app_name']) ?>" 
                         style="height: 32px; margin-right: 0.5rem;">
                <?php else: ?>
                    <i class="icon icon-mail mr-1 text-primary"></i>
                <?php endif; ?>
                <?= htmlspecialchars($brandingVars['app_name']) ?>
            </a>
        </section>
        
        <section class="navbar-section navbar-center">
            <div class="nav">
                <a class="nav-item" href="/home">
                    <i class="icon icon-home"></i> Dashboard
                </a>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_VIEW_REPORTS)): ?>
                <a class="nav-item" href="/reports">
                    <i class="icon icon-list"></i> Reports
                </a>
                <?php endif; ?>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_VIEW_ANALYTICS)): ?>
                <a class="nav-item" href="/analytics">
                    <i class="icon icon-bookmark"></i> Analytics
                </a>
                <?php endif; ?>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_MANAGE_GROUPS)): ?>
                <a class="nav-item" href="/domain-groups">
                    <i class="icon icon-people"></i> Groups
                </a>
                <?php endif; ?>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_MANAGE_ALERTS)): ?>
                <a class="nav-item" href="/alerts">
                    <i class="icon icon-flag"></i> Alerts
                </a>
                <?php endif; ?>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_VIEW_REPORTS)): ?>
                <a class="nav-item" href="/reports-management">
                    <i class="icon icon-docs"></i> PDF Reports
                </a>
                <?php endif; ?>
                
                <?php if ($rbac->hasPermission(RBACManager::PERM_UPLOAD_REPORTS)): ?>
                <a class="nav-item" href="/upload">
                    <i class="icon icon-upload"></i> Upload
                </a>
                <a class="nav-item" href="/imap">
                    <i class="icon icon-mail"></i> IMAP
                </a>
                <?php endif; ?>
            </div>
        </section>
        
        <section class="navbar-section">
            <div class="dropdown dropdown-right">
                <a href="#" class="btn btn-link dropdown-toggle" tabindex="0">
                    <i class="icon icon-people"></i>
                    <?php 
                    $displayName = $_SESSION['username'] ?? 'User';
                    if (!empty($_SESSION['user_first_name']) || !empty($_SESSION['user_last_name'])) {
                        $displayName = trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));
                    }
                    echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
                    ?>
                    <i class="icon icon-caret"></i>
                </a>
                <ul class="menu">
                    <?php if ($rbac->hasPermission(RBACManager::PERM_MANAGE_USERS)): ?>
                    <li class="menu-item">
                        <a href="/user-management" class="btn btn-link btn-sm">
                            <i class="icon icon-people"></i> User Management
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($rbac->hasPermission(RBACManager::PERM_MANAGE_SETTINGS)): ?>
                    <li class="menu-item">
                        <a href="/branding" class="btn btn-link btn-sm">
                            <i class="icon icon-photo"></i> Branding
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="divider"></li>
                    <li class="menu-item">
                        <form method="POST" action="/login" class="m-1">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button class="btn btn-link btn-sm text-left" name="logout" type="submit">
                                <i class="icon icon-shutdown"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </section>
    </header>
    
    <!-- Message Display Area -->
    <div class="container grid-lg">
        <?php
        $messages = App\Helpers\MessageHelper::getMessages();
        foreach ($messages as $message): ?>
            <div class="alert alert-<?= $message['type'] === 'error' ? 'error' : $message['type'] ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <main class="container grid-lg"><?php 
