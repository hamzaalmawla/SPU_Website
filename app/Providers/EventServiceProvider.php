<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\DraftConflictDetected;
use App\Events\HomepagePublished;
use App\Events\PagePublished;
use App\Events\PageUnpublished;
use App\Listeners\InvalidateHomepageCache;
use App\Listeners\InvalidatePageCache;
use App\Listeners\LogDraftConflict;
use App\Listeners\TrackFormMailSent;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers domain event to listener mappings.
 *
 * Explicit registration is used instead of automatic discovery to provide
 * clear visibility into event-listener relationships, especially for listeners
 * that handle multiple event types (e.g., InvalidatePageCache).
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap event-listener mappings.
     */
    public function boot(): void
    {
        Event::listen(PagePublished::class, InvalidatePageCache::class);
        Event::listen(PageUnpublished::class, InvalidatePageCache::class);
        Event::listen(HomepagePublished::class, InvalidateHomepageCache::class);
        Event::listen(DraftConflictDetected::class, LogDraftConflict::class);
        Event::listen(MessageSent::class, TrackFormMailSent::class);
    }
}
