<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: index.php
 * Description: V PHP Framework
 */

require_once '../config.php';
require_once '../vendor/autoload.php';

use App\Core\Router;
use App\Core\ErrorManager;
use App\Core\SessionManager;

$secureFlag = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'path'     => '/',
    'httponly' => true,
    'secure'   => $secureFlag,
    'samesite' => 'Lax',
]);

$session = SessionManager::getInstance();
$session->start();
if (!$session->get('csrf_token')) {
    $session->set('csrf_token', bin2hex(random_bytes(32)));
}

ErrorManager::handle(function (): void {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    Router::getInstance()->dispatch($_SERVER['REQUEST_METHOD'], $uri);
});
