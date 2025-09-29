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
<title>V PHP Framework</title>
</head>
<body>
    <nav class="navbar">
        <section class="navbar-section">
            <a class="navbar-brand mr-2" href="/home">Home</a>
        </section>
        <section class="navbar-section">
            <form method="POST" action="/login" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token'] ?? ''?>">
                <button class="btn btn-link" name="logout">Logout</button>
            </form>
        </section>
    </nav>
