<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactMessageStatus: string
{
    case NEW = 'new';
    case IN_REVIEW = 'in_review';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    /** @return list<self> */
    public function legalTransitions(): array
    {
        return match ($this) {
            self::NEW => [self::IN_REVIEW],
            self::IN_REVIEW => [self::RESOLVED, self::CLOSED],
            self::RESOLVED => [self::CLOSED],
            self::CLOSED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->legalTransitions(), true);
    }
}
