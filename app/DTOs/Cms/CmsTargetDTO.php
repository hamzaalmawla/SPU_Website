<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

/**
 * Describes one public page/subpage that should be managed by the unified CMS workflow.
 */
final readonly class CmsTargetDTO
{
    /**
     * @param  array<int, string>  $locales
     */
    public function __construct(
        public string $key,
        public string $area,
        public string $labelKey,
        public ?string $publicPath,
        public ?string $routeName,
        public ?string $parentKey,
        public bool $supportsDraftWorkflow,
        public array $locales = ['ar', 'en'],
    ) {}
}
