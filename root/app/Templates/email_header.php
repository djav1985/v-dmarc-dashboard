<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($subject ?? 'DMARC Notification') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f5f7fb;
            color: #2e3a59;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 640px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(46, 58, 89, 0.08);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, #4a67ff, #5ac8fa);
            color: #ffffff;
            padding: 24px;
        }

        .email-content {
            padding: 24px;
        }

        .email-footer {
            padding: 16px 24px 32px;
            text-align: center;
            color: #7a869a;
            font-size: 12px;
        }

        .btn {
            display: inline-block;
            background-color: #4a67ff;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 4px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th,
        td {
            padding: 8px;
            border-bottom: 1px solid #eef1f6;
            text-align: left;
        }

        th {
            background-color: #f5f7fb;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #7a869a;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background-color: #e3fcef;
            color: #1f845a;
        }

        .badge-warning {
            background-color: #fff7d6;
            color: #8a6116;
        }

        .badge-danger {
            background-color: #ffe8e7;
            color: #c9372c;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h1 style="margin: 0; font-size: 20px;">DMARC Dashboard</h1>
            <p style="margin: 4px 0 0; font-size: 14px; opacity: 0.85;">Automated notification</p>
        </div>
        <div class="email-content">
