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
            // Root route shows landing page
            $r->addRoute('GET', '/', function (): void {
                require __DIR__ . '/../Views/index.php';
            });
            
            // Authentication routes
            $r->addRoute('GET', '/login', [\App\Controllers\LoginController::class, 'handleRequest']);
            $r->addRoute('POST', '/login', [\App\Controllers\LoginController::class, 'handleSubmission']);

            // Legacy home route (redirect to dashboard)
            $r->addRoute('GET', '/home', function (): void {
                header('Location: /dashboard');
                exit();
            });
            $r->addRoute('POST', '/home', [\App\Controllers\HomeController::class, 'handleSubmission']);

            // DMARC Dashboard routes
            $r->addRoute('GET', '/dashboard', [\App\Controllers\DashboardController::class, 'handleRequest']);
            $r->addRoute('POST', '/dashboard', [\App\Controllers\DashboardController::class, 'handleSubmission']);

            // Domain management routes
            $r->addRoute('GET', '/domains', [\App\Controllers\DomainController::class, 'handleRequest']);
            $r->addRoute('POST', '/domains', [\App\Controllers\DomainController::class, 'handleSubmission']);
            $r->addRoute('GET', '/domains/{id:\d+}', [\App\Controllers\DomainController::class, 'handleRequest']);

            // Upload routes
            $r->addRoute('GET', '/upload', [\App\Controllers\UploadController::class, 'handleRequest']);
            $r->addRoute('POST', '/upload', [\App\Controllers\UploadController::class, 'handleSubmission']);

            // Demo routes (no database required)
            $r->addRoute('GET', '/demo', [\App\Controllers\DemoController::class, 'handleRequest']);
            $r->addRoute('POST', '/demo', [\App\Controllers\DemoController::class, 'handleSubmission']);
            $r->addRoute('GET', '/demo/domains', [\App\Controllers\DemoController::class, 'domainsList']);
            $r->addRoute('POST', '/demo/domains', [\App\Controllers\DemoController::class, 'handleSubmission']);
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
                // Only enforce auth for protected routes (skip for /login and /demo)
                if ($uri !== '/login' && !str_starts_with($uri, '/demo') && !str_starts_with($uri, '/api/public')) {
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
