<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use App\DTOs\Legacy\LegacyNewsSlugCleanupItemDTO;
use App\DTOs\Legacy\LegacyNewsSlugCleanupPlanDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyNewsSlugCleanupPlannerService implements LegacyNewsSlugCleanupPlannerServiceInterface
{
    public function plan(?int $limit = 50, int $maxSlugLength = 80): LegacyNewsSlugCleanupPlanDTO
    {
        $maxSlugLength = max(20, min($maxSlugLength, 80));
        $limit = $limit !== null ? max(1, $limit) : null;
        $total = DB::table('news_articles')->whereRaw('LENGTH(slug) > ?', [$maxSlugLength])->count();
        $rowsQuery = DB::table('news_articles')
            ->select('id', 'slug', 'legacy_source_id', 'legacy_service_type')
            ->whereRaw('LENGTH(slug) > ?', [$maxSlugLength])
            ->orderBy('id');

        if ($limit !== null) {
            $rowsQuery->limit($limit);
        }

        $reserved = $this->reservedShortSlugs($maxSlugLength);
        $items = collect();
        $collisionAdjustedRows = 0;

        foreach ($rowsQuery->get() as $row) {
            $oldSlug = (string) $row->slug;
            $baseSlug = $this->baseSlug($oldSlug, $maxSlugLength);
            $proposedSlug = $this->uniquePlannedSlug($baseSlug, $reserved, $maxSlugLength);
            $reserved[$proposedSlug] = (int) $row->id;
            $collisionAdjusted = $proposedSlug !== $baseSlug;

            if ($collisionAdjusted) {
                $collisionAdjustedRows++;
            }

            $items->push(new LegacyNewsSlugCleanupItemDTO(
                articleId: (int) $row->id,
                legacySourceId: $row->legacy_source_id !== null ? (int) $row->legacy_source_id : null,
                legacyServiceType: $row->legacy_service_type !== null ? (int) $row->legacy_service_type : null,
                oldSlug: $oldSlug,
                proposedSlug: $proposedSlug,
                oldSlugLength: strlen($oldSlug),
                proposedSlugLength: strlen($proposedSlug),
                collisionAdjusted: $collisionAdjusted,
                redirectRequired: $oldSlug !== $proposedSlug,
                redirectFromAr: '/ar/news/'.$oldSlug,
                redirectToAr: '/ar/news/'.$proposedSlug,
                redirectFromEn: '/en/news/'.$oldSlug,
                redirectToEn: '/en/news/'.$proposedSlug,
            ));
        }

        $plannedRows = $items->count();

        return new LegacyNewsSlugCleanupPlanDTO(
            maxSlugLength: $maxSlugLength,
            limit: $limit,
            totalLongSlugRows: $total,
            plannedRows: $plannedRows,
            omittedRows: max(0, $total - $plannedRows),
            collisionAdjustedRows: $collisionAdjustedRows,
            status: $total === 0 ? 'no_changes' : 'dry_run_only',
            items: $items,
        );
    }

    /** @return array<string, int> */
    private function reservedShortSlugs(int $maxSlugLength): array
    {
        return DB::table('news_articles')
            ->select('id', 'slug')
            ->whereRaw('LENGTH(slug) <= ?', [$maxSlugLength])
            ->pluck('id', 'slug')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function baseSlug(string $oldSlug, int $maxSlugLength): string
    {
        $slug = Str::slug($oldSlug);

        if ($slug === '') {
            $slug = 'legacy-news';
        }

        return $this->limitSlug($slug, $maxSlugLength);
    }

    /** @param array<string, int> $reserved */
    private function uniquePlannedSlug(string $baseSlug, array $reserved, int $maxSlugLength): string
    {
        if (! array_key_exists($baseSlug, $reserved)) {
            return $baseSlug;
        }

        $counter = 1;

        do {
            $suffix = '-'.$counter;
            $candidate = $this->limitSlug($baseSlug, $maxSlugLength - strlen($suffix)).$suffix;
            $counter++;
        } while (array_key_exists($candidate, $reserved));

        return $candidate;
    }

    private function limitSlug(string $slug, int $maxLength): string
    {
        if (strlen($slug) <= $maxLength) {
            return $slug;
        }

        return rtrim(substr($slug, 0, $maxLength), '-');
    }
}
