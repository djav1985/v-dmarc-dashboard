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

            // Password reset flows
            $r->addRoute('GET', '/password-reset', [\App\Controllers\PasswordResetController::class, 'handleRequest']);
            $r->addRoute('POST', '/password-reset', [\App\Controllers\PasswordResetController::class, 'handleSubmission']);
            $r->addRoute('GET', '/password-reset/{token}', [\App\Controllers\PasswordResetController::class, 'showResetForm']);
            $r->addRoute('POST', '/password-reset/{token}', [\App\Controllers\PasswordResetController::class, 'processReset']);

            // DMARC Dashboard specific routes
            $r->addRoute('GET', '/upload', [\App\Controllers\UploadController::class, 'handleRequest']);
            $r->addRoute('POST', '/upload', [\App\Controllers\UploadController::class, 'handleSubmission']);

            // IMAP Email Ingestion routes
            $r->addRoute('GET', '/imap', [\App\Controllers\ImapController::class, 'handleRequest']);
            $r->addRoute('POST', '/imap', [\App\Controllers\ImapController::class, 'handleSubmission']);

            // Profile management
            $r->addRoute('GET', '/profile', [\App\Controllers\ProfileController::class, 'handleRequest']);
            $r->addRoute('POST', '/profile', [\App\Controllers\ProfileController::class, 'handleSubmission']);

            // Reports listing and filtering
            $r->addRoute('GET', '/reports', [\App\Controllers\ReportsController::class, 'handleRequest']);
            $r->addRoute('POST', '/reports', [\App\Controllers\ReportsController::class, 'handleSubmission']);
            $r->addRoute('GET', '/reports/export/csv', [\App\Controllers\ReportsController::class, 'exportCsv']);
            $r->addRoute('GET', '/reports/export/xlsx', [\App\Controllers\ReportsController::class, 'exportXlsx']);
            $r->addRoute('POST', '/reports/saved-filters', [\App\Controllers\SavedReportFiltersController::class, 'store']);
            $r->addRoute('POST', '/reports/saved-filters/{id:\d+}/update', [\App\Controllers\SavedReportFiltersController::class, 'update']);
            $r->addRoute('POST', '/reports/saved-filters/{id:\d+}/delete', [\App\Controllers\SavedReportFiltersController::class, 'delete']);

            // Individual report details
            $r->addRoute('GET', '/report/{id:\d+}', [\App\Controllers\ReportDetailController::class, 'handleRequest']);

            // Analytics dashboard
            $r->addRoute('GET', '/analytics', [\App\Controllers\AnalyticsController::class, 'handleRequest']);
            $r->addRoute('POST', '/analytics', [\App\Controllers\AnalyticsController::class, 'handleSubmission']);

            // Domain Groups management
            $r->addRoute('GET', '/domain-groups', [\App\Controllers\DomainGroupsController::class, 'handleRequest']);
            $r->addRoute('POST', '/domain-groups', [\App\Controllers\DomainGroupsController::class, 'handleSubmission']);

            // Alerting system
            $r->addRoute('GET', '/alerts', [\App\Controllers\AlertController::class, 'handleRequest']);
            $r->addRoute('POST', '/alerts', [\App\Controllers\AlertController::class, 'handleSubmission']);

            // Email digest scheduling
            $r->addRoute('GET', '/email-digests', [\App\Controllers\EmailDigestController::class, 'handleRequest']);
            $r->addRoute('POST', '/email-digests', [\App\Controllers\EmailDigestController::class, 'handleSubmission']);

            // Reports management and PDF generation
            $r->addRoute('GET', '/reports-management', [\App\Controllers\ReportsManagementController::class, 'handleRequest']);
            $r->addRoute('POST', '/reports-management', [\App\Controllers\ReportsManagementController::class, 'handleSubmission']);

            // User management (RBAC)
            $r->addRoute('GET', '/user-management', [\App\Controllers\UserManagementController::class, 'handleRequest']);
            $r->addRoute('POST', '/user-management', [\App\Controllers\UserManagementController::class, 'handleSubmission']);

            // Security operations
            $r->addRoute('GET', '/audit-logs', [\App\Controllers\AuditLogController::class, 'handleRequest']);
            $r->addRoute('POST', '/audit-logs', [\App\Controllers\AuditLogController::class, 'handleSubmission']);
            $r->addRoute('GET', '/blacklist', [\App\Controllers\BlacklistController::class, 'handleRequest']);
            $r->addRoute('POST', '/blacklist', [\App\Controllers\BlacklistController::class, 'handleSubmission']);

            // Forensic reports
            $r->addRoute('GET', '/forensic-reports', [\App\Controllers\ForensicReportsController::class, 'handleRequest']);
            $r->addRoute('GET', '/forensic-reports/{id:\d+}', [\App\Controllers\ForensicReportsController::class, 'show']);

            // TLS reports
            $r->addRoute('GET', '/tls-reports', [\App\Controllers\TlsReportsController::class, 'handleRequest']);
            $r->addRoute('GET', '/tls-reports/{id:\d+}', [\App\Controllers\TlsReportsController::class, 'show']);

            // Branding settings
            $r->addRoute('GET', '/branding', [\App\Controllers\BrandingController::class, 'handleRequest']);
            $r->addRoute('POST', '/branding', [\App\Controllers\BrandingController::class, 'handleSubmission']);

            // Data retention settings
            $r->addRoute('GET', '/retention-settings', [\App\Controllers\RetentionSettingsController::class, 'handleRequest']);
            $r->addRoute('POST', '/retention-settings', [\App\Controllers\RetentionSettingsController::class, 'handleSubmission']);
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
                    if (!$this->isPublicRoute($uri)) {
                        SessionManager::getInstance()->requireAuth();
                    }
                    [$class, $action] = $handler;
                    call_user_func_array([new $class(), $action], array_values($vars));
                } elseif (is_callable($handler)) {
                    call_user_func_array($handler, array_values($vars));
                } else {
                    throw new \RuntimeException('Invalid route handler');
                }
                break;
        }
    }

    private function isPublicRoute(string $uri): bool
    {
        if ($uri === '/login') {
            return true;
        }

        if (str_starts_with($uri, '/password-reset')) {
            return true;
        }

        return false;
    }
}
