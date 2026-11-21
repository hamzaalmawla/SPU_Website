<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/**
 * Either the reference of the saved request, or the existing profiles the
 * teacher must first confirm they are not.
 */
final readonly class StaffIntakeSubmitResultDTO
{
    /** @param list<StaffMatchSuggestionDTO> $duplicateCandidates */
    public function __construct(
        public ?string $reference,
        public array $duplicateCandidates = [],
    ) {}
}
