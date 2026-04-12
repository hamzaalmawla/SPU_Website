<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionWriteDTO;
use Illuminate\Support\Collection;

/**
 * Defines homepage section retrieval and editing operations.
 */
interface HomepageSectionServiceInterface
{
    /**
     * Retrieve editable homepage sections.
     *
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection;

    /**
     * Retrieve the public homepage composite view-model.
     *
     * @return array<string, mixed>
     */
    public function getPublicHomepage(string $locale): array;

    /**
     * Update one homepage section.
     */
    public function updateSection(string $sectionKey, HomepageSectionWriteDTO $data): bool;

    /**
     * Reorder homepage sections by key.
     *
     * @param  array<int, string>  $orderedKeys
     */
    public function reorder(array $orderedKeys): bool;
}
