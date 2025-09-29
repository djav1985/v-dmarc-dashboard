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
 * Description: DMARC Dashboard Header using Spectre.css
 */

$pageTitle = $title ?? 'DMARC Dashboard';
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - DMARC Dashboard</title>
    
    <!-- Spectre.css Framework -->
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-exp.min.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    
    <!-- Scripts -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="/assets/js/header-scripts.js"></script>
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
</head>
<body>
    <!-- Navigation Header -->
    <header class="navbar">
        <section class="navbar-section">
            <a class="navbar-brand mr-2" href="/dashboard">
                <i class="icon icon-shield mr-1"></i>
                DMARC Dashboard
            </a>
            <a class="btn btn-link" href="/dashboard">Dashboard</a>
            <a class="btn btn-link" href="/domains">Domains</a>
            <a class="btn btn-link" href="/reports">Reports</a>
            <a class="btn btn-link" href="/upload">Upload</a>
        </section>
        <section class="navbar-section">
            <div class="dropdown">
                <a href="#" class="btn btn-link dropdown-toggle" tabindex="0">
                    <i class="icon icon-people mr-1"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    <i class="icon icon-caret"></i>
                </a>
                <ul class="menu">
                    <li class="menu-item">
                        <a href="/profile" class="menu-link">
                            <i class="icon icon-edit mr-1"></i>Profile
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/settings" class="menu-link">
                            <i class="icon icon-apps mr-1"></i>Settings
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li class="menu-item">
                        <form method="POST" action="/login" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button class="btn btn-link menu-link" name="logout" type="submit" style="border: none; background: none; text-align: left; width: 100%;">
                                <i class="icon icon-shutdown mr-1"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </section>
    </header>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="toast toast-success">
            <button class="btn btn-clear float-right" onclick="this.parentElement.style.display='none'"></button>
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="toast toast-error">
            <button class="btn btn-clear float-right" onclick="this.parentElement.style.display='none'"></button>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="toast toast-warning">
            <button class="btn btn-clear float-right" onclick="this.parentElement.style.display='none'"></button>
            <?= htmlspecialchars($_SESSION['warning']) ?>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <!-- Main Content -->
