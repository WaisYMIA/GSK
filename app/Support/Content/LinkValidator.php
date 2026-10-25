<?php

namespace App\Support\Content;

// Only http, https, mailto and same-site relative links are allowed. javascript:, data: and the like are refused.
final class LinkValidator
{
    public static function isAllowed(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'http' || $scheme === 'https') {
            return ! empty($parts['host']);
        }
        if ($scheme === 'mailto') {
            return isset($parts['path']) && filter_var($parts['path'], FILTER_VALIDATE_EMAIL) !== false;
        }

        return false;
    }
}
