<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaUrlResolver
{
    public static function resolve(?string $value, ?string $disk = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#', $value) === 1 || str_starts_with($value, '/')) {
            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }

        try {
            if (! app()->bound('config')) {
                return UrlSanitizer::sanitize($value, ['http', 'https'], true);
            }

            $diskName = $disk ?? (string) config('filesystems.media_disk', 'public');

            return UrlSanitizer::sanitize(Storage::disk($diskName)->url($value), ['http', 'https'], true);
        } catch (Throwable) {
            return UrlSanitizer::sanitize($value, ['http', 'https'], true);
        }
    }
}
