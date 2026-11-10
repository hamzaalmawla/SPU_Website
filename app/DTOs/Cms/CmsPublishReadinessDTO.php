<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

final readonly class CmsPublishReadinessDTO
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(
        public bool $isReady,
        public array $errors = [],
    ) {}
}
