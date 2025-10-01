<?php

namespace App\Utilities;

/**
 * URL validation utility to prevent SSRF attacks.
 */
class UrlValidator
{
    /**
     * Validate a webhook URL to ensure it uses safe schemes and doesn't target internal resources.
     *
     * @param string $url The URL to validate
     * @return array{valid:bool,error:?string}
     */
    public static function validateWebhookUrl(string $url): array
    {
        $url = trim($url);

        // Empty URLs are allowed (webhooks are optional)
        if ($url === '') {
            return ['valid' => true, 'error' => null];
        }

        // Parse the URL
        $parsed = parse_url($url);
        if ($parsed === false) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Check scheme first (even if host is missing)
        if (!isset($parsed['scheme'])) {
            return ['valid' => false, 'error' => 'URL must include a scheme (http:// or https://)'];
        }

        // Only allow HTTP and HTTPS schemes
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['valid' => false, 'error' => 'Only HTTP and HTTPS schemes are allowed'];
        }

        // Check host is present
        if (!isset($parsed['host'])) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Block localhost and loopback addresses
        $host = strtolower($parsed['host']);
        if (self::isInternalHost($host)) {
            return ['valid' => false, 'error' => 'Internal or private IP addresses are not allowed'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Check if a host is an internal or private address.
     *
     * @param string $host The hostname or IP address
     * @return bool True if the host is internal/private
     */
    private static function isInternalHost(string $host): bool
    {
        // Remove IPv6 brackets if present
        if (preg_match('/^\[(.+)\]$/', $host, $matches)) {
            $host = $matches[1];
        }

        // Check for localhost variations
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        // Check for localhost variations with different TLDs
        if (preg_match('/^localhost\./i', $host)) {
            return true;
        }

        // Check for .local domains
        if (preg_match('/\.local$/i', $host)) {
            return true;
        }

        // Resolve hostname to IP if it's a valid hostname
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // If gethostbyname fails, it returns the original hostname
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            // Could not resolve to IP, but hostname doesn't match internal patterns
            return false;
        }

        // Validate it's not a private or reserved IP
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isPrivateIPv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isPrivateIPv6($ip);
        }

        return false;
    }

    /**
     * Check if an IPv4 address is private or reserved.
     *
     * @param string $ip IPv4 address
     * @return bool True if private or reserved
     */
    private static function isPrivateIPv4(string $ip): bool
    {
        // Use filter_var with NO_PRIV_RANGE and NO_RES_RANGE flags
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    /**
     * Check if an IPv6 address is private or reserved.
     *
     * @param string $ip IPv6 address
     * @return bool True if private or reserved
     */
    private static function isPrivateIPv6(string $ip): bool
    {
        // Use filter_var with NO_PRIV_RANGE and NO_RES_RANGE flags
        $flags = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }
}
