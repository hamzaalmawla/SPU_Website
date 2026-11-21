<?php

declare(strict_types=1);

namespace App\DTOs\Homepage;

/**
 * Public homepage payload composed of the fixed section set.
 */
final readonly class HomepageDTO
{
    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $sections,
    ) {}

    /**
     * Look up a section by its key.
     */
    public function findSection(string $key): ?HomepageSectionDTO
    {
        foreach ($this->sections as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }
}
