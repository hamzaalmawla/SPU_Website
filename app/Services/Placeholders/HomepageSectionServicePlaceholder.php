<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\HomepageSectionServiceInterface;
use App\DTOs\HomepageSectionDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for the homepage section service contract.
 */
final class HomepageSectionServicePlaceholder implements HomepageSectionServiceInterface
{
    /**
     * @return Collection<int, HomepageSectionDTO>
     */
    public function getSections(): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getSectionByKey(string $key): ?HomepageSectionDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getPublicHomepage(string $locale): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateSection(string $key, array $payload, string $locale): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function toggleSection(string $key, bool $enabled): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function reorderSections(array $orderedKeys): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function validateSectionPayload(string $key, array $payload, string $locale): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
