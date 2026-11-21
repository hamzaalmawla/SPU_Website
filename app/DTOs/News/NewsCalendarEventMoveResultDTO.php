<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsCalendarEventMoveResultDTO
{
    /**
     * @param  list<array{id: string, title: string, articleId: ?int, sessions: int, registrations: int}>  $moved  Moved now, or to be moved in a dry run (no article yet).
     * @param  list<array{id: string, title: string, articleId: int}>  $alreadyMoved
     * @param  list<string>  $samples  Untouched sample events, never moved.
     * @param  list<array{id: string, title: string, errors: list<string>}>  $failed
     */
    public function __construct(
        public array $moved,
        public array $alreadyMoved,
        public array $samples,
        public array $failed,
        public bool $written,
    ) {}
}
