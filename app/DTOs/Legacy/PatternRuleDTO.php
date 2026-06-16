<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * DTO for a pattern-based legacy redirect rule.
 */
final readonly class PatternRuleDTO
{
    public function __construct(
        public int $id,
        public string $pattern,
        public string $replacement,
        public int $statusCode,
        public int $priority,
        public bool $isActive,
    ) {}
}
