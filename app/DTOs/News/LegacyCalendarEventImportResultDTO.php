<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class LegacyCalendarEventImportResultDTO
{
    /**
     * @param  list<string>  $added  Ids of the events added
     * @param  list<string>  $present  Ids already on the Events page
     * @param  list<string>  $completed  Ids already there whose missing fields were filled in
     * @param  list<string>  $removedSamples  Ids of untouched sample events taken off the page
     * @param  list<string>  $incomplete  Events that still lack a field the events editor requires
     */
    public function __construct(
        public array $added,
        public array $present,
        public array $completed,
        public array $removedSamples,
        public array $incomplete,
        public bool $written,
        public bool $published,
        public bool $hadPendingDraft,
    ) {}
}
