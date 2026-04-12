<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for a single audit log entry.
 */
final readonly class AuditLogDTO
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int|string $id,
        public string $action,
        public int|string|null $actorId,
        public string $createdAt,
        public array $context,
    ) {}
}
