<?php

declare(strict_types=1);

namespace App\DTOs\Admissions;

final readonly class AdmissionsTuitionFeeImportResultDTO
{
    /**
     * @param  list<array{faculty: string, tracks: string, creditHours: string, syrianFee: string, foreignFee: string}>  $rows  English summary of each imported row
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public array $rows,
        public bool $valid,
        public array $errors,
        public bool $published,
        public bool $unchanged = false,
        public ?string $skipReason = null,
    ) {}
}
