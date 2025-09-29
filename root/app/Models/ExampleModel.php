<?php

namespace App\Models;

/**
 * Generic sample model for demonstration.
 */
class ExampleModel
{
    /**
     * Return placeholder list of items.
     */
    public static function getAllItems(string $owner): array
    {
        return [(object) ['item' => 'example']];
    }

    /**
     * Return placeholder details about an item.
     */
    public static function getItemInfo(string $owner, string $name): object
    {
        return (object) ['info' => 'placeholder'];
    }

    /**
     * Placeholder blacklist check.
     */
    public static function isIpBlocked(string $ip): bool
    {
        return false;
    }
}
