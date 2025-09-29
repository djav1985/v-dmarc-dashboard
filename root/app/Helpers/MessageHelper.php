<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: MessageHelper.php
 * Description: V PHP Framework
 */

namespace App\Helpers;

use App\Core\SessionManager;

class MessageHelper
{
    /**
     * Add a message to the session queue.
     *
     * @param string $message Message to add.
     * @return void
     */
    public static function addMessage(string $message): void
    {
        $session = SessionManager::getInstance();
        $messages = $session->get('messages', []);
        $messages[] = $message;
        $session->set('messages', $messages);
    }

    /**
     * Display all session messages and clear them.
     *
     * @return void
     */
    public static function displayAndClearMessages(): void
    {
        $session = SessionManager::getInstance();
        $messages = $session->get('messages', []);
        if (!empty($messages)) {
            foreach ($messages as $message) {
                echo '<script>showToast(' . json_encode($message) . ');</script>';
            }
            $session->set('messages', []);
        }
    }
}
