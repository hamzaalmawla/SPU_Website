<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyResearchMetadataDTO
{
    /** @param list<string> $keywords @param list<string> $evidence */
    public function __construct(
        public ?string $authors,
        public ?string $citation,
        public ?string $abstract,
        public ?string $publisher,
        public ?string $doi,
        public ?int $publicationYear,
        public ?string $journalRank,
        public array $keywords,
        public array $evidence,
    ) {}
}
