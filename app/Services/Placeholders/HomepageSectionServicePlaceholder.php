<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\HomepageSectionServiceInterface;
use App\DTOs\HomepageSectionWriteDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for homepage section service contract.
 */
final class HomepageSectionServicePlaceholder implements HomepageSectionServiceInterface
{
    public function getSections(): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getPublicHomepage(string $locale): array
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateSection(string $sectionKey, HomepageSectionWriteDTO $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function reorder(array $orderedKeys): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
