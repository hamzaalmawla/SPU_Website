<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaUrlResolver
{
    public static function resolveLegacy(?string $value): ?string
    {
        if (! (bool) config('legacy_media.enabled', true) || $value === null || trim($value) === '') {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
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
}
