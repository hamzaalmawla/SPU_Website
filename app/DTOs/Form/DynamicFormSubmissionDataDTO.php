<?php

declare(strict_types=1);

namespace App\DTOs\Form;

use Illuminate\Http\UploadedFile;

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, UploadedFile>  $files
 */
final readonly class DynamicFormSubmissionDataDTO
{
    public function __construct(
        public string $formId,
        public string $locale,
        public array $payload,
        public array $files,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $eventSource = null,
        public ?string $eventId = null,
        public ?string $jobId = null,
        public ?string $jobSlug = null,
    ) {}
}
