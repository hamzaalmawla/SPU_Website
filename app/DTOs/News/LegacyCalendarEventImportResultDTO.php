<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class LegacyCalendarEventImportResultDTO
{
    /**
     * @param  list<string>  $added  Ids of the events added
     * @param  list<string>  $present  Ids already on the Events page
     */
    public function __construct(
        public array $added,
        public array $present,
        public bool $written,
        public bool $published,
        public bool $hadPendingDraft,
    ) {}
}
