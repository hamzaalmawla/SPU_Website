<?php

declare(strict_types=1);

namespace App\Services\Achievement;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\DTOs\Achievement\AchievementArchiveDTO;
use App\DTOs\Achievement\AchievementCardDTO;
use App\DTOs\Achievement\AchievementCategoryDTO;
use App\DTOs\Achievement\AchievementDetailDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\Achievement\Achievement;
use App\Models\Achievement\AchievementCategory;
use App\Models\Achievement\AchievementTranslation;
use App\Support\HtmlSanitizer;
use App\Support\MediaUrlResolver;
use App\Support\UrlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AchievementService implements AchievementServiceInterface
{
    public function __construct(
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

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
                    ->where('is_active', true)
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

    public function find(int $id, string $locale): ?AchievementDetailDTO
    {
        $achievement = $this->publicQuery()->whereKey($id)->first();

        if (! $achievement instanceof Achievement) {
            return null;
        }

        $card = $this->mapCard($achievement, $locale);
        $translation = $this->translation($achievement, $locale);
        $body = is_string($translation?->body) ? $this->htmlSanitizer->sanitize($translation->body) : '';

        return new AchievementDetailDTO(
            id: $card->id,
            title: $card->title,
            typeTag: $card->typeTag,
            summary: $card->summary,
            body: trim(strip_tags($body)) !== '' ? $body : null,
            image: $card->image,
            meta: $card->meta,
            link: $this->editorLink($translation),
            categories: $card->categories,
            publishedAt: $achievement->published_at?->locale($locale)->translatedFormat('j F Y'),
            url: $card->url,
        );
    }

    public function preparePublishingData(array $data): array
    {
        // The public archive needs status=published, is_public and a past
        // published_at. Editors chose "Published", saved, and saw nothing: the
        // date was optional and empty, so the row could never match. Publishing
        // without a date means "now", as it does for news.
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    private function publicQuery(): Builder
    {
        return Achievement::query()
            ->public()
            ->whereHas('translations', fn (Builder $query): Builder => $query->whereIn('locale', ['ar', 'en']))
            ->with(['translations', 'categories' => fn ($query) => $query->active()->orderBy('sort_order'), 'categories.translations', 'imageMedia']);
    }

    private function translation(Achievement $achievement, string $locale): ?AchievementTranslation
    {
        $translation = $achievement->translations->firstWhere('locale', $locale)
            ?? $achievement->translations->firstWhere('locale', 'ar');

        return $translation instanceof AchievementTranslation ? $translation : null;
    }

    /** @return array{label: string, url: string}|null */
    private function editorLink(?AchievementTranslation $translation): ?array
    {
        $label = is_string($translation?->action_label) ? trim($translation->action_label) : '';
        $url = UrlSanitizer::sanitize(is_string($translation?->action_url) ? trim($translation->action_url) : null) ?? '';

        return $label !== '' && $url !== '' ? ['label' => $label, 'url' => $url] : null;
    }

    private function mapCard(Achievement $achievement, string $locale): AchievementCardDTO
    {
        $translation = $this->translation($achievement, $locale);
        $categories = $achievement->categories
            ->map(fn (AchievementCategory $category): AchievementCategoryDTO => $this->mapCategory($category, $locale))
            ->values()
            ->all();
        $media = $achievement->imageMedia;
        $image = $media === null
            ? (is_string($achievement->legacy_image_path) && $achievement->legacy_image_path !== '' ? $achievement->legacy_image_path : null)
            : MediaUrlResolver::resolveImage($media->webp_path, $media->path, $media->disk);
        // Every card opens the achievement's own page. The editor's label still
        // names the button; their link moves onto that page.
        $url = '/'.$locale.'/achievements/'.$achievement->getKey();
        $actionLabel = $this->editorLink($translation)['label'] ?? __('public.achievements.view_details', [], $locale);

        return new AchievementCardDTO(
            id: (int) $achievement->getKey(),
            title: (string) ($translation?->title ?? ''),
            typeTag: ($categories[0] ?? null)?->name,
            summary: $translation?->summary,
            image: $image,
            meta: $translation?->meta,
            action: ['label' => $actionLabel, 'url' => $url],
            categories: $categories,
            publishedAt: $achievement->published_at?->toAtomString(),
            url: $url,
        );
    }

    private function mapCategory(AchievementCategory $category, string $locale): AchievementCategoryDTO
    {
        $translation = $category->translations->firstWhere('locale', $locale)
            ?? $category->translations->firstWhere('locale', 'ar');

        return new AchievementCategoryDTO((int) $category->getKey(), $category->slug, (string) ($translation?->name ?? $category->slug));
    }
}
