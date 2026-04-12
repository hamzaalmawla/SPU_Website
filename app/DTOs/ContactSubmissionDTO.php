<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Represents a validated contact submission payload.
 */
final readonly class ContactSubmissionDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $subject,
        public string $message,
        public string $locale,
        public ?string $facultySlug,
        public ?string $honeypot,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
