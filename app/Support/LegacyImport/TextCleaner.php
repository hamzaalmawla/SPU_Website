<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

class TextCleaner
{
    public function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = $this->stripInvisibleCharacters($value);
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);
        $cleaned = preg_replace('/[ \t]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\n{3,}/u', "\n\n", $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : null;
    }

    public function stripInvisibleCharacters(string $value): string
    {
        return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    }
}
