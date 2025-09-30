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
     * @param string $type Message type (success, error, warning, info).
     * @return void
     */
    public static function addMessage(string $message, string $type = 'info'): void
    {
        $session = SessionManager::getInstance();
        $messages = $session->get('messages', []);
        $messages[] = [
            'text' => $message,
            'type' => $type
        ];
        $session->set('messages', $messages);
    }

    /**
     * Get all messages from the session.
     *
     * @return array Array of message objects with 'text' and 'type' keys.
     */
    public static function getMessages(): array
    {
        $session = SessionManager::getInstance();
        return $session->get('messages', []);
    }

    /**
     * Clear all messages from the session.
     *
     * @return void
     */
    public static function clearMessages(): void
    {
        $session = SessionManager::getInstance();
        $session->set('messages', []);
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
                // Handle both old string format and new array format
                if (is_string($message)) {
                    echo '<script>showToast(' . json_encode($message) . ');</script>';
                } else {
                    $text = $message['text'] ?? $message;
                    echo '<script>showToast(' . json_encode($text) . ');</script>';
                }
            }
            $session->set('messages', []);
        }
    }
}
