<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\CacheServiceInterface;
use App\Events\PagePublished;
use App\Events\PageUnpublished;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Flushes page-related cache tags when a page is published or unpublished.
 *
 * Listens to both PagePublished and PageUnpublished events.
 * Registered via EventServiceProvider since this listener handles multiple events.
 *
 * Note: Cache invalidation is still handled inline in PageService as well.
 * This listener provides the event-driven foundation for future decoupling.
 */
final class InvalidatePageCache implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    /**
     * Handle page publish/unpublish events.
     */
    public function handle(PagePublished|PageUnpublished $event): void
    {
        $this->cacheService->flushTags(['pages', 'seo', 'sitemap', 'navigation', 'settings']);
    }
}
