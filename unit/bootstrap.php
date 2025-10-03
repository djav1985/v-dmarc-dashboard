<?php

if (!defined('DB_NAME')) {
    require __DIR__ . '/../root/config.php';
}
require __DIR__ . '/../root/vendor/autoload.php';

use App\Core\Installer;

Installer::ensureInstalled();
