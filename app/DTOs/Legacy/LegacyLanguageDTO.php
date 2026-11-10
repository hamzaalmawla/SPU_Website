<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyLanguageDTO
{
    public function __construct(
        public int $oldLanguageId,
        public string $oldSymbol,
        public string $locale,
        public bool $isSupportedLocale,
        public ?string $fallbackLocale,
    ) {}
}
