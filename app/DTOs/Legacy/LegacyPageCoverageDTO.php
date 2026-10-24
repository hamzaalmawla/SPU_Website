<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyPageCoverageDTO
{
    /**
     * @param  array{ar: ?int, en: ?int}  $coverage  Percent of the old text found on the new page; null when the old page has no text in that language.
     * @param  array{ar: int, en: int}  $oldLength  Characters of old text per language.
     */
    public function __construct(
        public int $legacyId,
        public string $site,
        public string $breadcrumb,
        public ?string $newPath,
        public ?string $legacyLink,
        public int $files,
        public array $oldLength,
        public array $coverage,
        public ?string $status,
    ) {}
}
