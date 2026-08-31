<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaUrlResolver
{
    /**
     * Directory on the public disk holding offline-generated legacy derivatives.
     *
     * The legacy media tree is mounted read only, so derivatives are never
     * written back beside their source.
     */
    public const DERIVATIVE_DIRECTORY = 'legacy-derivatives';

    /**
     * Decoded manifest, memoised per process, keyed by the manifest mtime so a
     * regeneration is picked up without a restart.
     *
     * @var array<string, array{default?: string, variants?: array<string, string>}>|null
     */
    private static ?array $derivativeManifest = null;

    private static ?int $derivativeManifestMtime = null;

    /**
     * When the manifest file was last stat'd. The resolver runs once per image,
     * so re-stat'ing on every call would cost eighty syscalls on the homepage.
     */
    private static ?float $derivativeManifestCheckedAt = null;

    private const MANIFEST_RECHECK_SECONDS = 1.0;

    public static function resolveLegacy(?string $value): ?string
    {
        if (! (bool) config('legacy_media.enabled', true) || $value === null || trim($value) === '') {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        if (self::hasUnsafeLegacyExtension($value)) {
            return null;
        }

        // A generated derivative always lives on our own host, so it wins over
        // both the local path and any configured legacy base URL.
        $derivative = self::legacyDerivativeUrl($value);

        if ($derivative !== null) {
            return $derivative;
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }

        $path = '/'.ltrim($value, '/');
        $baseUrl = trim((string) config('legacy_media.base_url', ''));

        if ($baseUrl !== '') {
            return UrlSanitizer::sanitize(rtrim($baseUrl, '/').$path, ['http', 'https'], true);
        }

        return UrlSanitizer::sanitize($path, ['http', 'https'], true);
    }

    /**
     * Manifest key for a legacy image value: the host-less, slash-less path.
     */
    public static function legacyDerivativeKey(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $value;
        $path = ltrim(rawurldecode($path), '/');

        return $path === '' ? null : $path;
    }

    /**
     * The default (largest generated) derivative URL, or null when none exists.
     */
    public static function legacyDerivativeUrl(?string $value): ?string
    {
        $entry = self::legacyDerivativeEntry($value);
        $default = $entry['default'] ?? null;

        return is_string($default) && $default !== ''
            ? UrlSanitizer::sanitize('/storage/'.ltrim($default, '/'), ['http', 'https'], true)
            : null;
    }

    /**
     * A width-descriptor srcset across every generated variant, or null when
     * fewer than two variants exist and a srcset would buy nothing.
     */
    public static function legacySrcset(?string $value): ?string
    {
        $entry = self::legacyDerivativeEntry($value);
        $variants = $entry['variants'] ?? [];

        if (! is_array($variants) || count($variants) < 2) {
            return null;
        }

        $candidates = [];

        foreach ($variants as $width => $path) {
            $width = (int) $width;

            if ($width <= 0 || ! is_string($path) || $path === '') {
                continue;
            }

            $url = UrlSanitizer::sanitize('/storage/'.ltrim($path, '/'), ['http', 'https'], true);

            if ($url !== null) {
                $candidates[$width] = $url.' '.$width.'w';
            }
        }

        if (count($candidates) < 2) {
            return null;
        }

        ksort($candidates);

        return implode(', ', $candidates);
    }

    /**
     * Drop the memoised manifest. Used by tests that write one mid-process.
     */
    public static function flushLegacyDerivativeManifest(): void
    {
        self::$derivativeManifest = null;
        self::$derivativeManifestMtime = null;
        self::$derivativeManifestCheckedAt = null;
    }

    /**
     * @return array{default?: string, variants?: array<string, string>}|null
     */
    private static function legacyDerivativeEntry(?string $value): ?array
    {
        $key = self::legacyDerivativeKey($value);

        if ($key === null) {
            return null;
        }

        $entry = self::legacyDerivativeManifest()[$key] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @return array<string, array{default?: string, variants?: array<string, string>}>
     */
    private static function legacyDerivativeManifest(): array
    {
        if (! app()->bound('config') || ! (bool) config('media.derivatives.enabled', true)) {
            return [];
        }

        $now = microtime(true);

        // Within a single page render the manifest cannot change, so stat it at
        // most once a second and reuse the decoded copy for every other image.
        if (self::$derivativeManifest !== null
            && self::$derivativeManifestCheckedAt !== null
            && ($now - self::$derivativeManifestCheckedAt) < self::MANIFEST_RECHECK_SECONDS) {
            return self::$derivativeManifest;
        }

        try {
            $path = storage_path('app/public/'.self::DERIVATIVE_DIRECTORY.'/manifest.json');
            $mtime = @filemtime($path);
        } catch (Throwable) {
            return [];
        }

        self::$derivativeManifestCheckedAt = $now;

        if ($mtime === false) {
            self::$derivativeManifest = [];
            self::$derivativeManifestMtime = null;

            return [];
        }

        if (self::$derivativeManifest !== null && self::$derivativeManifestMtime === $mtime) {
            return self::$derivativeManifest;
        }

        $contents = @file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;
        $images = is_array($decoded) && is_array($decoded['images'] ?? null) ? $decoded['images'] : [];

        self::$derivativeManifest = $images;
        self::$derivativeManifestMtime = $mtime;

        return $images;
    }

    public static function resolve(?string $value, ?string $disk = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! app()->bound('config')) {
            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }

        if ($disk === 'legacy') {
            return self::resolveLegacy($value);
        }

        if (preg_match('#^(https?:)?//#', $value) === 1) {
            $diskName = $disk ?? (string) config('filesystems.media_disk', 'public');
            $configuredUrl = (string) config('filesystems.disks.'.$diskName.'.url', '');
            $valueHost = parse_url($value, PHP_URL_HOST);
            $configuredHost = parse_url($configuredUrl, PHP_URL_HOST);

            if ($diskName === 'public'
                && (string) config('filesystems.disks.public.driver') === 'local'
                && is_string($valueHost)
                && ($valueHost === $configuredHost || in_array($valueHost, ['localhost', '127.0.0.1', '::1'], true))) {
                $path = parse_url($value, PHP_URL_PATH);

                if (is_string($path) && $path !== '') {
                    return UrlSanitizer::sanitize($path, ['http', 'https'], true);
                }
            }

            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }

        if (str_starts_with($value, '/')) {
            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }

        try {
            if (! app()->bound('config')) {
                return UrlSanitizer::sanitize($value, ['http', 'https'], true);
            }

            $diskName = $disk ?? (string) config('filesystems.media_disk', 'public');

            if (! array_key_exists($diskName, config('filesystems.disks', []))) {
                return UrlSanitizer::sanitize('/'.ltrim($value, '/'), ['http', 'https'], true);
            }

            $url = Storage::disk($diskName)->url($value);

            // Keep local filesystem URLs host-relative so uploads work behind any
            // configured domain, proxy, or XAMPP virtual host.
            if ($diskName === 'public' && (string) config('filesystems.disks.public.driver') === 'local') {
                $publicUrl = (string) config('filesystems.disks.public.url', '/storage');
                $path = parse_url($url, PHP_URL_PATH);

                if (is_string($path) && $path !== '') {
                    return UrlSanitizer::sanitize($path, ['http', 'https'], true);
                }

                return UrlSanitizer::sanitize('/'.ltrim(str_replace($publicUrl, '', $url), '/'), ['http', 'https'], true);
            }

            return UrlSanitizer::sanitize($url, ['http', 'https'], true);
        } catch (Throwable) {
            return UrlSanitizer::sanitize('/'.ltrim($value, '/'), ['http', 'https'], true);
        }
    }

    public static function resolveImage(?string $webpPath, ?string $originalPath, ?string $disk = null): ?string
    {
        return self::resolve(
            is_string($webpPath) && $webpPath !== '' ? $webpPath : $originalPath,
            $disk,
        );
    }

    private static function hasUnsafeLegacyExtension(string $value): bool
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = rawurldecode(is_string($path) ? $path : $value);

        return preg_match(
            '/\.(?:html?|xhtml|xml|svgz?|php[0-9]?|phtml|phar|cgi|pl|py|sh|asp|aspx|jsp|exe|dll|com|bat|cmd|ps1)(?:\.|$)/i',
            $path,
        ) === 1;
    }
}
