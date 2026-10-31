<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, array<string, int|string|bool>>  $lanes
 * @param  list<string>  $warnings
 */
final readonly class LegacyPhaseSixRestoreResultDTO
{
    public function __construct(
        public bool $written,
        public string $batch,
        public array $lanes,
        public array $warnings,
    ) {}
}
