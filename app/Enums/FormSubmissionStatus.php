<?php

declare(strict_types=1);

namespace App\Enums;

enum FormSubmissionStatus: string
{
    case NEW = 'new';
    case IN_REVIEW = 'in_review';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    /** @return list<self> */
    public function legalTransitions(FormSubmissionInbox $inbox): array
    {
        if ($this === self::NEW) {
            return [self::IN_REVIEW];
        }

        if ($inbox === FormSubmissionInbox::SUGGESTIONS) {
            return match ($this) {
                self::IN_REVIEW => [self::RESOLVED],
                self::RESOLVED => [self::CLOSED],
                default => [],
            };
        }

        return match ($this) {
            self::IN_REVIEW => [self::ACCEPTED, self::REJECTED],
            self::ACCEPTED, self::REJECTED => [self::CLOSED],
            default => [],
        };
    }

    public function canTransitionTo(self $next, FormSubmissionInbox $inbox): bool
    {
        return in_array($next, $this->legalTransitions($inbox), true);
    }
}
