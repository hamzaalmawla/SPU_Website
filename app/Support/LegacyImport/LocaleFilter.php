<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

class LocaleFilter
{
    public function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = strtolower(trim($locale));

        return match (true) {
            in_array($normalized, ['ar', 'arabic', 'ar-sa', 'ar_sy'], true) => 'ar',
            in_array($normalized, ['en', 'english', 'en-us', 'en-gb'], true) => 'en',
            default => null,
        };
    }

    public function isSupported(?string $locale): bool
    {
        return in_array($this->normalize($locale), (array) config('old_database.allowed_locales', []), true);
    }
}
