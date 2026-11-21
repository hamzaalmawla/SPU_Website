<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use Illuminate\Http\UploadedFile;

final readonly class StaffIntakeSubmissionDTO
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  list<array<string, mixed>>  $educations
     * @param  ?string  $heldPhoto  handle of a photo kept from an earlier try (StaffIntakeServiceInterface::holdUploads)
     * @param  ?string  $heldCv  handle of a CV kept from an earlier try
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
        public ?string $heldPhoto = null,
        public ?string $heldCv = null,
    ) {}
}
