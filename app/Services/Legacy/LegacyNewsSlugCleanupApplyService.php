<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyNewsSlugCleanupApplyServiceInterface;
use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyNewsSlugCleanupApplyResultDTO;
use App\DTOs\Legacy\LegacyNewsSlugCleanupItemDTO;
use Illuminate\Support\Facades\DB;

final class LegacyNewsSlugCleanupApplyService implements LegacyNewsSlugCleanupApplyServiceInterface
{
    public function __construct(
        private readonly LegacyNewsSlugCleanupPlannerServiceInterface $plannerService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function apply(?int $limit = null): LegacyNewsSlugCleanupApplyResultDTO
    {
        $plan = $this->plannerService->plan($limit);

        if ($plan->plannedRows === 0) {
            return new LegacyNewsSlugCleanupApplyResultDTO(
                status: 'no_changes',
                plannedRows: 0,
                updatedArticles: 0,
                createdRedirects: 0,
                updatedRedirects: 0,
                skippedRows: 0,
            );
        }

        $result = DB::transaction(function () use ($plan): LegacyNewsSlugCleanupApplyResultDTO {
            $updatedArticles = 0;
            $createdRedirects = 0;
            $updatedRedirects = 0;
            $skippedRows = 0;

            foreach ($plan->items as $item) {
                if (! $item instanceof LegacyNewsSlugCleanupItemDTO || $item->oldSlug === $item->proposedSlug) {
                    $skippedRows++;

                    continue;
                }

                $updated = DB::table('news_articles')
                    ->where('id', $item->articleId)
                    ->where('slug', $item->oldSlug)
                    ->update([
                        'slug' => $item->proposedSlug,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    $skippedRows++;

                    continue;
                }

                $updatedArticles++;
                $arRedirect = $this->upsertRedirect($item->redirectFromAr, $item->redirectToAr, 'ar', $item);
                $enRedirect = $this->upsertRedirect($item->redirectFromEn, $item->redirectToEn, 'en', $item);
                $createdRedirects += $arRedirect === 'created' ? 1 : 0;
                $createdRedirects += $enRedirect === 'created' ? 1 : 0;
                $updatedRedirects += $arRedirect === 'updated' ? 1 : 0;
                $updatedRedirects += $enRedirect === 'updated' ? 1 : 0;
            }

            return new LegacyNewsSlugCleanupApplyResultDTO(
                status: 'applied',
                plannedRows: $plan->plannedRows,
                updatedArticles: $updatedArticles,
                createdRedirects: $createdRedirects,
                updatedRedirects: $updatedRedirects,
                skippedRows: $skippedRows,
            );
        });

        if (! $this->cacheService->flushTags(['news', 'public-pages', 'public-shell', 'seo', 'sitemap', 'continuity'])) {
            $this->cacheService->flushAll();
        }

        return $result;
    }

    private function upsertRedirect(string $legacyPath, string $destinationUrl, string $locale, LegacyNewsSlugCleanupItemDTO $item): string
    {
        $existing = DB::table('legacy_exact_redirects')
            ->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($legacyPath)])
            ->where('locale', $locale)
            ->orderBy('id')
            ->first();

        $payload = [
            'legacy_path' => $legacyPath,
            'destination_url' => $destinationUrl,
            'status_code' => 301,
            'locale' => $locale,
            'is_active' => true,
            'notes' => 'Created by legacy news slug cleanup for article '.$item->articleId.'.',
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('legacy_exact_redirects')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return 'updated';
        }

        DB::table('legacy_exact_redirects')->insert(array_merge($payload, [
            'hit_count' => 0,
            'last_hit_at' => null,
            'created_at' => now(),
        ]));

        return 'created';
    }
}
