<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class NormalizedLegacyUrlDTO
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public string $path,
        public ?string $queryString,
        public array $params,
        public LegacyLanguageDTO $language,
        public LegacySubsiteDTO $subsite,
        public ?string $entrypoint,
        public ?string $dir,
        public ?string $page,
        public string $extension,
        public ?string $service,
        public ?string $handlerKey,
        public string $requestType,
    ) {}
}
