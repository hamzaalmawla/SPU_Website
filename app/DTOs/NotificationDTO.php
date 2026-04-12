<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for a single notification entry.
 */
final readonly class NotificationDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int|string $id,
        public string $channel,
        public int|string $recipientId,
        public bool $isRead,
        public string $createdAt,
        public array $payload,
    ) {}
}
