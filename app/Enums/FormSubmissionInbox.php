<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a form's submissions are reviewed. Each form picks one in the admin
 * (FormDefinitionServiceInterface::inboxFor); the inbox also decides what a
 * submission is checked against, such as an open job for JOBS.
 */
enum FormSubmissionInbox: string
{
    case EVENT_REGISTRATIONS = 'event_registrations';
    case JOBS = 'jobs';
    case ADMISSIONS = 'admissions';
    case SUGGESTIONS = 'suggestions';
}
