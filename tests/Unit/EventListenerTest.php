<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Events\DraftConflictDetected;
use App\Events\HomepagePublished;
use App\Events\PagePublished;
use App\Events\PageUnpublished;
use App\Listeners\InvalidateHomepageCache;
use App\Listeners\InvalidatePageCache;
use App\Listeners\LogDraftConflict;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class EventListenerTest extends TestCase
{
    public function test_page_published_listener_is_registered(): void
    {
        Event::fake([PagePublished::class]);
        Event::assertListening(PagePublished::class, InvalidatePageCache::class);
    }

    public function test_page_unpublished_listener_is_registered(): void
    {
        Event::fake([PageUnpublished::class]);
        Event::assertListening(PageUnpublished::class, InvalidatePageCache::class);
    }

    public function test_homepage_published_listener_is_registered(): void
    {
        Event::fake([HomepagePublished::class]);
        Event::assertListening(HomepagePublished::class, InvalidateHomepageCache::class);
    }

    public function test_draft_conflict_listener_is_registered(): void
    {
        Event::fake([DraftConflictDetected::class]);
        Event::assertListening(DraftConflictDetected::class, LogDraftConflict::class);
    }

    public function test_invalidate_page_cache_flushes_tags_on_publish(): void
    {
        $cacheService = $this->createMock(CacheServiceInterface::class);
        $cacheService->expects($this->once())
            ->method('flushTags')
            ->with(['pages', 'seo', 'sitemap', 'navigation', 'settings'])
            ->willReturn(true);

        $listener = new InvalidatePageCache($cacheService);
        $listener->handle(new PagePublished(pageId: 1, actorId: 1));
    }

    public function test_invalidate_page_cache_flushes_tags_on_unpublish(): void
    {
        $cacheService = $this->createMock(CacheServiceInterface::class);
        $cacheService->expects($this->once())
            ->method('flushTags')
            ->with(['pages', 'seo', 'sitemap', 'navigation', 'settings'])
            ->willReturn(true);

        $listener = new InvalidatePageCache($cacheService);
        $listener->handle(new PageUnpublished(pageId: 5, actorId: 2));
    }

    public function test_invalidate_homepage_cache_flushes_tags(): void
    {
        $cacheService = $this->createMock(CacheServiceInterface::class);

        // Expects forget and flushTags called for both locales (ar, en)
        $cacheService->expects($this->exactly(2))
            ->method('forget');

        $cacheService->expects($this->exactly(2))
            ->method('flushTags')
            ->willReturn(true);

        $listener = new InvalidateHomepageCache($cacheService);
        $listener->handle(new HomepagePublished(draftId: 1, actorId: 1));
    }

    public function test_log_draft_conflict_writes_audit_entry(): void
    {
        $auditService = $this->createMock(AuditServiceInterface::class);
        $auditService->expects($this->once())
            ->method('log')
            ->with(
                'draft.conflict',
                42,
                'App\\Models\\PageDraft',
                10,
                [
                    'expected_version' => 1,
                    'actual_version' => 2,
                ],
            )
            ->willReturn(true);

        $listener = new LogDraftConflict($auditService);
        $listener->handle(new DraftConflictDetected(
            entityType: 'App\\Models\\PageDraft',
            entityId: 10,
            expectedVersion: 1,
            actualVersion: 2,
            actorId: 42,
        ));
    }
}
