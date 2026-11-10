<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/** A photo or CV a teacher sent, kept on the private disk until approval. */
final readonly class StaffIntakeUploadDTO
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $downloadName,
        public string $mimeType,
        public int $size,
    ) {}
}
