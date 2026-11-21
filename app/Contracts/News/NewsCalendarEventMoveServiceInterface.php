<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\NewsCalendarEventMoveResultDTO;

/**
 * Moves the events published in the Events page list (news.events) into
 * published Events articles. The page list itself is left as it is: a moved
 * entry is hidden from the site and its old addresses redirect, so running
 * it again only moves what is new.
 */
interface NewsCalendarEventMoveServiceInterface
{
    /**
     * @param  string  $actor  Id or email of the user the articles are published as.
     * @param  bool  $write  Without it, only report what would be moved.
     */
    public function move(string $actor, bool $write): NewsCalendarEventMoveResultDTO;
}
