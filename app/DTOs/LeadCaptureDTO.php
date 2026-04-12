<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Represents a validated lead capture payload.
 */
final readonly class LeadCaptureDTO
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $program,
        public string $locale,
        public string $source,
        public array $meta = [],
    ) {}
}
