<?php

namespace App\Services\Publishing;

use Illuminate\Support\Str;

final class PublicMediaUrl
{
    public static function isValid(?string $url): bool
    {
        if (blank($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        if (! str_contains($host, '.')) {
            return false;
        }

        foreach (['localhost', '.localhost', '.ddev.site', '.test', '.local', '.internal'] as $blockedSuffix) {
            if ($host === $blockedSuffix || Str::endsWith($host, $blockedSuffix)) {
                return false;
            }
        }

        return true;
    }
}
