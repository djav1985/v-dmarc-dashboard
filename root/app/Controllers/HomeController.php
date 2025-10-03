<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: HomeController.php
 * Description: Simplified example controller
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;

class HomeController extends Controller
{
    /**
     * Display a simple dashboard.
     */
    public function handleRequest(): void
    {
        $this->render('home');
    }

    /**
     * Example POST handler used by the router.
     */
    public function handleSubmission(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['messages'][] = 'Invalid CSRF token. Please try again.';
        } else {
            $_SESSION['messages'][] = 'Nothing to process.';
        }
        header('Location: /home');
        exit;
    }
}
