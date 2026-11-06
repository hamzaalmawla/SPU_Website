<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use Illuminate\Http\UploadedFile;

final readonly class StaffIntakeSubmissionDTO
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  list<array<string, mixed>>  $educations
     */
    public function __construct(
        public string $token,
        public string $ref,
        public string $locale,
        public array $fields,
        public array $educations,
        public ?UploadedFile $photo,
        public ?UploadedFile $cv,
        public bool $confirmedNotDuplicate,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
