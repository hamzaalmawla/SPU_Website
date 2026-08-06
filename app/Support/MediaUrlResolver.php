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

        if ($disk === 'legacy') {
            return self::resolveLegacy($value);
        }

        if (preg_match('#^(https?:)?//#', $value) === 1 || str_starts_with($value, '/')) {
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

            return UrlSanitizer::sanitize(Storage::disk($diskName)->url($value), ['http', 'https'], true);
        } catch (Throwable) {
            return UrlSanitizer::sanitize('/'.ltrim($value, '/'), ['http', 'https'], true);
        }
    }
}
