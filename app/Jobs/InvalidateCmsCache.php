<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Shared\CacheServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class InvalidateCmsCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $targetKey,
    ) {}

    public function handle(CacheServiceInterface $cacheService): void
    {
        try {
            self::invalidate($cacheService, $this->targetKey);
        } catch (Throwable $exception) {
            Log::error('CMS cache invalidation failed; the queue will retry it.', [
                'target_key' => $this->targetKey,
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('CMS cache invalidation exhausted its retries.', [
            'target_key' => $this->targetKey,
            'exception' => $exception?->getMessage(),
        ]);
    }

    public static function invalidate(CacheServiceInterface $cacheService, string $targetKey): void
    {
        $tags = ['public-pages', 'public-shell', 'seo', 'sitemap', 'cms', 'cms:'.$targetKey];

        if (str_starts_with($targetKey, 'facilities.') || str_starts_with($targetKey, 'entity.faculty-member.')) {
            $tags[] = 'facilities';
        }

        if (str_starts_with($targetKey, 'research.')) {
            $tags[] = 'research';
        }

        if (str_starts_with($targetKey, 'news.') || str_starts_with($targetKey, 'entity.news-article.')) {
            $tags[] = 'news';
        }

        if (! $cacheService->flushTags($tags) && ! $cacheService->flushAll()) {
            throw new RuntimeException('CMS cache tags and fallback cache flush both failed.');
        }

        foreach (['ar', 'en'] as $locale) {
            foreach (['header', 'footer', 'utility'] as $treeType) {
                $cacheService->forget('menu.tree.'.$treeType.'.'.$locale);
            }

            $cacheService->forget('navigation.payload.'.$locale);
        }
    }
}
