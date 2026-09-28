<?php

declare(strict_types=1);

namespace App\Services\Achievement;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\DTOs\Achievement\AchievementArchiveDTO;
use App\DTOs\Achievement\AchievementCardDTO;
use App\DTOs\Achievement\AchievementCategoryDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\Achievement\Achievement;
use App\Models\Achievement\AchievementCategory;
use App\Support\MediaUrlResolver;
use App\Support\UrlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AchievementService implements AchievementServiceInterface
{
    public function homepage(string $locale, int $max = 3): Collection
    {
        if ($max <= 0) {
            return collect();
        }

        $limit = min(3, $max);

        return $this->publicQuery()
            ->orderByDesc('pin_to_homepage')
            ->orderByRaw('CASE WHEN pin_to_homepage = 1 THEN sort_order END ASC')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Achievement $achievement): AchievementCardDTO => $this->mapCard($achievement, $locale));
    }

    public function archive(string $locale, array $categorySlugs = [], int $page = 1, int $perPage = 9): AchievementArchiveDTO
    {
        $categories = AchievementCategory::query()
            ->active()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AchievementCategory $category): AchievementCategoryDTO => $this->mapCategory($category, $locale));
        $activeSlugs = $categories->pluck('slug')->all();
        $selected = array_values(array_unique(array_intersect(
            $activeSlugs,
            array_filter(array_map(static fn (mixed $slug): string => is_string($slug) ? trim($slug) : '', $categorySlugs)),
        )));
        $query = $this->publicQuery()
            ->when($selected !== [], function (Builder $query) use ($selected): void {
                $query->whereHas('categories', fn (Builder $categoryQuery): Builder => $categoryQuery
                    ->active()
                    ->whereIn('slug', $selected));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');
        $paginator = $query->paginate(max(1, min(48, $perPage)), ['*'], 'page', max(1, $page));

        return new AchievementArchiveDTO(
            results: new PaginatedResultDTO(
                items: $paginator->getCollection()->map(fn (Achievement $achievement): AchievementCardDTO => $this->mapCard($achievement, $locale)),
                total: $paginator->total(),
                currentPage: $paginator->currentPage(),
                perPage: $paginator->perPage(),
                lastPage: $paginator->lastPage(),
            ),
            categories: $categories->all(),
            selectedCategories: $selected,
        );
    }

    private function publicQuery(): Builder
    {
        return Achievement::query()
            ->public()
            ->whereHas('translations', fn (Builder $query): Builder => $query->whereIn('locale', ['ar', 'en']))
            ->with(['translations', 'categories' => fn ($query) => $query->active()->orderBy('sort_order'), 'categories.translations', 'imageMedia']);
    }

    private function mapCard(Achievement $achievement, string $locale): AchievementCardDTO
    {
        $translation = $achievement->translations->firstWhere('locale', $locale)
            ?? $achievement->translations->firstWhere('locale', 'ar');
        $categories = $achievement->categories
            ->map(fn (AchievementCategory $category): AchievementCategoryDTO => $this->mapCategory($category, $locale))
            ->values()
            ->all();
        $media = $achievement->imageMedia;
        $image = $media === null
            ? (is_string($achievement->legacy_image_path) && $achievement->legacy_image_path !== '' ? $achievement->legacy_image_path : null)
            : MediaUrlResolver::resolveImage($media->webp_path, $media->path, $media->disk);
        $actionLabel = is_string($translation?->action_label) ? trim($translation->action_label) : '';
        $actionUrl = UrlSanitizer::sanitize(is_string($translation?->action_url) ? trim($translation->action_url) : null) ?? '';

        return new AchievementCardDTO(
            id: (int) $achievement->getKey(),
            title: (string) ($translation?->title ?? ''),
            typeTag: $categories[0]->name ?? null,
            summary: $translation?->summary,
            image: $image,
            meta: $translation?->meta,
            action: $actionLabel !== '' && $actionUrl !== '' ? ['label' => $actionLabel, 'url' => $actionUrl] : null,
            categories: $categories,
            publishedAt: $achievement->published_at?->toAtomString(),
        );
    }

    private function mapCategory(AchievementCategory $category, string $locale): AchievementCategoryDTO
    {
        $translation = $category->translations->firstWhere('locale', $locale)
            ?? $category->translations->firstWhere('locale', 'ar');

        return new AchievementCategoryDTO((int) $category->getKey(), $category->slug, (string) ($translation?->name ?? $category->slug));
    }
}
