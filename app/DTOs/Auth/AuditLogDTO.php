<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

/**
 * Data transfer object for a single audit log entry.
 */
final readonly class AuditLogDTO
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $id,
        public string $action,
        public ?int $actorId,
        public ?string $entityType,
        public ?int $entityId,
        public string $createdAt,
        public array $context,
    ) {}
}
