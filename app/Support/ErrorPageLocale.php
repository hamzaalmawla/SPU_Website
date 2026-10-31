<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dependency-free locale resolution for error pages.
 *
 * Error pages — especially 500 and 503 — may render precisely because the
 * database, cache, or a service binding is unavailable. This helper therefore
 * never touches the container, the database, the session, or the application
 * locale: it derives the locale from the URL path segment first, then the
 * Accept-Language header, and finally falls back to the site default (ar).
 *
 * It is deliberately outside the service layer. Anything behind a contract
 * would have to be resolved from the container while the container is the
 * very thing that may be failing.
 */
final class ErrorPageLocale
{
    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    public const FALLBACK = 'ar';

    /**
     * Resolve the display locale for an error page.
     *
     * @param  string  $path  Request path, with or without a leading slash.
     * @param  string|null  $acceptLanguage  Raw Accept-Language header value.
     */
    public static function resolve(string $path, ?string $acceptLanguage = null): string
    {
        return self::fromPath($path)
            ?? self::fromAcceptLanguage($acceptLanguage)
            ?? self::FALLBACK;
    }

    /**
     * Text direction for a locale.
     */
    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * Read the locale from the first path segment (/ar/..., /en/...).
     */
    private static function fromPath(string $path): ?string
    {
        $segment = strtolower(strtok(ltrim($path, '/'), '/') ?: '');

        return in_array($segment, self::SUPPORTED, true) ? $segment : null;
    }

    /**
     * Read the highest-weighted supported locale from Accept-Language.
     */
    private static function fromAcceptLanguage(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return null;
        }

        $ranked = [];

        foreach (explode(',', $acceptLanguage) as $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));

            if ($tag === '') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            $primary = strtok($tag, '-') ?: $tag;

            if (! in_array($primary, self::SUPPORTED, true)) {
                continue;
            }

            if (! isset($ranked[$primary]) || $ranked[$primary] < $quality) {
                $ranked[$primary] = $quality;
            }
        }

        if ($ranked === []) {
            return null;
        }

        arsort($ranked);

        return (string) array_key_first($ranked);
    }
}
