<?php

declare(strict_types=1);

namespace App\DTOs\Contact;

final readonly class ContactSubmissionDataDTO
{
    public function __construct(
        public string $locale,
        public string $name,
        public string $email,
        public string $subject,
        public string $message,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
