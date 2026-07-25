<?php

declare(strict_types=1);

namespace App\Enums;

enum FormSubmissionInbox: string
{
    case EVENT_REGISTRATIONS = 'event_registrations';
    case JOBS = 'jobs';
    case ADMISSIONS = 'admissions';
    case SUGGESTIONS = 'suggestions';

    public static function fromFormId(string $formId): self
    {
        return self::tryFromFormId($formId)
            ?? throw new \InvalidArgumentException('Unsupported dynamic form inbox.');
    }

    public static function tryFromFormId(string $formId): ?self
    {
        return match ($formId) {
            'conference-registration',
            'symposium-registration',
            'activity-registration' => self::EVENT_REGISTRATIONS,
            'job-application' => self::JOBS,
            'admissions-application' => self::ADMISSIONS,
            'suggestions-complaints' => self::SUGGESTIONS,
            default => null,
        };
    }

    /** @return list<string> */
    public function formIds(): array
    {
        return match ($this) {
            self::EVENT_REGISTRATIONS => [
                'conference-registration',
                'symposium-registration',
                'activity-registration',
            ],
            self::JOBS => ['job-application'],
            self::ADMISSIONS => ['admissions-application'],
            self::SUGGESTIONS => ['suggestions-complaints'],
        };
    }
}
