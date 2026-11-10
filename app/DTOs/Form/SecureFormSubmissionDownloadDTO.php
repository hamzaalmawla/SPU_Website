<?php

declare(strict_types=1);

namespace App\DTOs\Form;

final readonly class SecureFormSubmissionDownloadDTO
{
    public function __construct(
        public int $submissionId,
        public string $field,
        public string $disk,
        public string $path,
        public string $downloadName,
        public string $mimeType,
        public int $size,
    ) {}
}
