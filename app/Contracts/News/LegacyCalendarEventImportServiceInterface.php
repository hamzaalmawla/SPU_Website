<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\LegacyCalendarEventImportResultDTO;

interface LegacyCalendarEventImportServiceInterface
{
    /**
     * Add the old site's calendar events to the Events page (news.events) as
     * past events. Events already there (same id) are left as they are.
     *
     * @param  array<string, mixed>  $source  The decoded legacy-calendar-events.json
     */
    public function import(array $source, string $actor, bool $write): LegacyCalendarEventImportResultDTO;
}
