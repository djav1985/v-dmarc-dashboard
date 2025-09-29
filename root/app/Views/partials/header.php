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
 * Description: V PHP Framework
 */
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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="/assets/js/header-scripts.js"></script>
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <title><?= defined('APP_NAME') ? APP_NAME : 'DMARC Dashboard' ?></title>
</head>
<body>
    <header class="navbar">
        <section class="navbar-section">
            <a class="navbar-brand mr-2" href="/home">
                <i class="icon icon-mail mr-1 text-primary"></i>
                DMARC Dashboard
            </a>
            <a class="btn btn-link mx-1" href="/home">
                <i class="icon icon-home"></i> Dashboard
            </a>
            <a class="btn btn-link mx-1" href="/reports">
                <i class="icon icon-list"></i> Reports
            </a>
            <a class="btn btn-link mx-1" href="/analytics">
                <i class="icon icon-bookmark"></i> Analytics
            </a>
            <a class="btn btn-link mx-1" href="/domain-groups">
                <i class="icon icon-people"></i> Groups
            </a>
            <a class="btn btn-link mx-1" href="/upload">
                <i class="icon icon-upload"></i> Upload
            </a>
            <a class="btn btn-link mx-1" href="/imap">
                <i class="icon icon-mail"></i> IMAP
            </a>
        </section>
        <section class="navbar-section">
            <div class="dropdown dropdown-right">
                <a href="#" class="btn btn-link dropdown-toggle" tabindex="0">
                    <i class="icon icon-people"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                    <i class="icon icon-caret"></i>
                </a>
                <ul class="menu">
                    <li class="menu-item">
                        <form method="POST" action="/login" class="m-1">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token'] ?? ''?>">
                            <button class="btn btn-link btn-sm text-left" name="logout" type="submit">
                                <i class="icon icon-shutdown"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </section>
    </header>
    <main class="container grid-lg">
