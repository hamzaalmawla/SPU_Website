<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DateNormalizer
{
    public function normalize(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $candidate = trim((string) $value);

        if ($candidate === '' || in_array($candidate, (array) config('old_database.fake_dates', []), true)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($candidate);
        } catch (\Throwable) {
            return null;
        }
    }
}
