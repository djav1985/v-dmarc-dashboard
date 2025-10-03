
Warning: preg_match(): Compilation failed: unknown property after \P or \p at offset 46 in D:\Git Repos\appsbyv\v-dmarc-dashboard\vendor\squizlabs\php_codesniffer\src\Filters\Filter.php on line 271

Warning: preg_match(): Compilation failed: unknown property after \P or \p at offset 46 in D:\Git Repos\appsbyv\v-dmarc-dashboard\vendor\squizlabs\php_codesniffer\src\Filters\Filter.php on line 271

Warning: preg_match(): Compilation failed: unknown property after \P or \p at offset 46 in D:\Git Repos\appsbyv\v-dmarc-dashboard\vendor\squizlabs\php_codesniffer\src\Filters\Filter.php on line 271
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
use App\Core\Installer;

Installer::ensureInstalled();

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
