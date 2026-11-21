<?php

declare(strict_types=1);

namespace App\DTOs\Career;

final readonly class StudentListImportResultDTO
{
    public function __construct(
        public int $importId,
        public int $created,
        public int $updated,
        public int $skipped,
    ) {}
}
