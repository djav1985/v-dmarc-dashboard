<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: Router.php
 * Description: V PHP Framework
 */

namespace App\Core;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;


class Router
{
    private Dispatcher $dispatcher;
    private static ?Router $instance = null;

    /**
     * Builds the route dispatcher and registers application routes.
     */
    private function __construct()
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            // Redirect the root URL to the home page for convenience
            $r->addRoute('GET', '/', function (): void {
                header('Location: /home');
                exit();
            });
            // Basic example routes
            $r->addRoute('GET', '/home', [\App\Controllers\HomeController::class, 'handleRequest']);
            $r->addRoute('POST', '/home', [\App\Controllers\HomeController::class, 'handleSubmission']);

            $r->addRoute('GET', '/login', [\App\Controllers\LoginController::class, 'handleRequest']);
            $r->addRoute('POST', '/login', [\App\Controllers\LoginController::class, 'handleSubmission']);
            
            // DMARC Dashboard specific routes
            $r->addRoute('GET', '/upload', [\App\Controllers\UploadController::class, 'handleRequest']);
            $r->addRoute('POST', '/upload', [\App\Controllers\UploadController::class, 'handleSubmission']);
            
            // IMAP Email Ingestion routes
            $r->addRoute('GET', '/imap', [\App\Controllers\ImapController::class, 'handleRequest']);
            $r->addRoute('POST', '/imap', [\App\Controllers\ImapController::class, 'handleSubmission']);
        });
    }

    /**
     * Returns the shared Router instance.
     */
    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Dispatches the request to the appropriate controller action.
     *
     * @param string $method HTTP method of the incoming request.
     * @param string $uri The requested URI path.
     */
    public function dispatch(string $method, string $uri): void
{
    $routeInfo = $this->dispatcher->dispatch($method, $uri);

    switch ($routeInfo[0]) {
        case Dispatcher::NOT_FOUND:
            header('HTTP/1.0 404 Not Found');
            require __DIR__ . '/../Views/404.php';
            break;

        case Dispatcher::METHOD_NOT_ALLOWED:
            header('HTTP/1.0 405 Method Not Allowed');
            break;

        case Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars    = $routeInfo[2] ?? [];

            if (is_array($handler) && count($handler) === 2) {
                // Only enforce auth for controller routes (skip for /login)
                if ($uri !== '/login') {
                    SessionManager::getInstance()->requireAuth();
                }
                [$class, $action] = $handler;
                call_user_func_array([new $class(), $action], $vars);

            } elseif (is_callable($handler)) {
                call_user_func_array($handler, array_values($vars));

            } else {
                throw new \RuntimeException('Invalid route handler');
            }
            break;
    }
}

}
