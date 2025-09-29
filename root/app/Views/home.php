<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: home.php
 * Description: V PHP Framework
 */

require 'partials/header.php';

$rawUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
$displayUsername = trim($rawUsername) !== '' ? $rawUsername : 'User';
$displayUsername = htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8');
?>
    <div class="container grid-lg">
        <h2>Dashboard</h2>
        <p>Welcome, <?= $displayUsername ?>.</p>
    </div>
<?php
 require 'partials/footer.php';
?>
