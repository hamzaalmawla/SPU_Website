<?php

declare(strict_types=1);

namespace App\Support;

final class UrlSanitizer
{
    /**
     * @param  array<int, string>  $allowedSchemes
     */
    public static function sanitize(?string $url, array $allowedSchemes = ['http', 'https', 'mailto', 'tel'], bool $allowRelative = true): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $collapsed = strtolower(preg_replace('/\s+/', '', $url) ?? $url);

        foreach (['javascript:', 'vbscript:', 'data:text/html'] as $unsafePrefix) {
            if (str_starts_with($collapsed, $unsafePrefix)) {
                return null;
            }
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        if (str_starts_with($url, '#')) {
            return $allowRelative ? $url : null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null) {
            return $allowRelative ? $url : null;
        }

        $scheme = strtolower($scheme);

        if (! in_array($scheme, $allowedSchemes, true)) {
            return null;
        }

        if (in_array($scheme, ['http', 'https'], true) && ! is_string(parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        return $url;
    }
}
