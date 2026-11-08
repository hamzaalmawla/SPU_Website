<?php

declare(strict_types=1);

namespace App\Contracts\Council;

use App\DTOs\Council\CouncilPageDTO;
use App\DTOs\Council\CouncilSummaryDTO;
use Illuminate\Support\Collection;

/**
 * The public councils pages: About › Councils lists every shown council with
 * published content, and each council has its own page built from the same
 * template.
 */
interface CouncilPageServiceInterface
{
    /** @return Collection<int, CouncilSummaryDTO> */
    public function councils(string $locale): Collection;

    public function council(string $slug, string $locale): ?CouncilPageDTO;

    /**
     * A draft of a council's content as its page would show it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function previewCouncil(string $targetKey, array $payload, string $locale): ?CouncilPageDTO;

    /**
     * Where an older address of a council's page now lives, for example
     * /research/office for the research council. Null when no shown, published
     * council had that address.
     */
    public function urlForFormerPath(string $path, string $locale): ?string;
}
