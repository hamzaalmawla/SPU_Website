<?php

declare(strict_types=1);

namespace App\DTOs\Council;

/**
 * A council's page (About › Councils › {council}): its introduction, the heads
 * and members, and its functions, in one language.
 */
final readonly class CouncilPageDTO
{
    /**
     * @param  list<CouncilPersonDTO>  $heads
     * @param  list<CouncilPersonDTO>  $members
     * @param  list<array{title: string, description: string}>  $functions
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $slug,
        public string $url,
        public string $title,
        public string $summary,
        public ?string $heroImage,
        public string $membersTitle,
        public string $membersSubtitle,
        public string $headLabel,
        public string $memberLabel,
        public string $profileLabel,
        public array $heads,
        public array $members,
        public string $functionsTitle,
        public string $functionsSubtitle,
        public array $functions,
    ) {}
}
