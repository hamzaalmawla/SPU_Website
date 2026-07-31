<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Media\PublicMediaAssetDTO;
use App\DTOs\News\NewsArticleDTO;
use App\DTOs\News\NewsAttachmentDTO;
use App\DTOs\News\NewsCategoryDTO;
use App\DTOs\News\NewsEventDTO;
use App\DTOs\News\NewsGalleryItemDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class NewsService implements NewsServiceInterface
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly MediaServiceInterface $mediaService,
    ) {}

    public function getIndexPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.index', $locale) ?? $this->indexPageFallback($locale);
    }

    public function buildPreviewIndexPage(string $locale, array $content): array
    {
        return $this->normalizeIndexPageContent($content, $locale);
    }

    public function getArticlesPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.articles', $locale) ?? $this->articlesPageFallback($locale);
    }

    public function buildPreviewArticlesPage(string $locale, array $content): array
    {
        return $this->normalizeArticlesPageContent($content, $locale);
    }

    public function getAnnouncementsPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.announcements', $locale) ?? $this->announcementsPageFallback($locale);
    }

    public function buildPreviewAnnouncementsPage(string $locale, array $content): array
    {
        return $this->normalizeAnnouncementsPageContent($content, $locale);
    }

    public function getEventsPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.events', $locale) ?? $this->eventsPageFallback($locale);
    }

    public function buildPreviewEventsPage(string $locale, array $content): array
    {
        return $this->normalizeEventsPageContent($content, $locale);
    }

    public function listNewsEvents(string $locale, bool $past = false, ?string $category = null): Collection
    {
        return $this->mapNewsEvents($this->getEventsPageContent($locale), $locale, $past, $category);
    }

    public function listPreviewNewsEvents(string $locale, array $content, bool $past = false, ?string $category = null): Collection
    {
        return $this->mapNewsEvents($this->normalizeEventsPageContent($content, $locale), $locale, $past, $category);
    }

    /** @param array<string, mixed> $content @return Collection<int, NewsEventDTO> */
    private function mapNewsEvents(array $content, string $locale, bool $past, ?string $category): Collection
    {
        $key = $past ? 'past' : 'upcoming';
        $events = $content[$key] ?? [];

        $events = collect(is_array($events) ? $events : [])
            ->filter(fn (mixed $event): bool => is_array($event) && ($category === null || ($event['categoryId'] ?? null) === $category))
            ->map(fn (array $event): NewsEventDTO => $this->mapNewsEvent($event, $locale, $past));

        return ($past
            ? $events->sortByDesc(fn (NewsEventDTO $event): string => $event->startsAt)
            : $events->sortBy(fn (NewsEventDTO $event): string => $event->startsAt))
            ->values();
    }

    public function findNewsEvent(string $eventId, string $locale, ?bool $past = null): ?NewsEventDTO
    {
        $sets = $past === null ? [false, true] : [$past];

        foreach ($sets as $isPast) {
            $event = $this->listNewsEvents($locale, $isPast)
                ->first(fn (NewsEventDTO $event): bool => hash_equals($event->id, $eventId));

            if ($event instanceof NewsEventDTO) {
                return $event;
            }
        }

        return null;
    }

    public function getNewsEventCalendar(string $locale, ?string $month = null): array
    {
        $events = $this->listNewsEvents($locale);
        $selectedMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? $month
            : substr($events->first()?->startsAt ?? now()->format('Y-m'), 0, 7);

        $monthDate = CarbonImmutable::createFromFormat('Y-m-d', $selectedMonth.'-01')->startOfMonth();
        $gridStart = $monthDate->startOfWeek(CarbonImmutable::SUNDAY);
        $days = [];

        for ($offset = 0; $offset < 42; $offset++) {
            $date = $gridStart->addDays($offset);
            $dateString = $date->toDateString();
            $days[] = [
                'date' => $dateString,
                'day' => $date->day,
                'inMonth' => $date->month === $monthDate->month,
                'events' => $events->filter(fn (NewsEventDTO $event): bool => str_starts_with($event->startsAt, $dateString))->values(),
            ];
        }

        return [
            'month' => $selectedMonth,
            'monthLabel' => $monthDate->locale($locale)->translatedFormat('F Y'),
            'previousMonth' => $monthDate->subMonth()->format('Y-m'),
            'nextMonth' => $monthDate->addMonth()->format('Y-m'),
            'events' => $events->filter(fn (NewsEventDTO $event): bool => str_starts_with($event->startsAt, $selectedMonth))->values(),
            'days' => $days,
        ];
    }

    public function getGalleryPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.gallery', $locale) ?? $this->galleryPageFallback($locale);
    }

    public function buildPreviewGalleryPage(string $locale, array $content): array
    {
        return $this->normalizeGalleryPageContent($content, $locale);
    }

    public function getGalleryListing(string $locale, ?string $category = null, int $page = 1, int $perPage = 8): array
    {
        return $this->galleryListingFromContent($this->getGalleryPageContent($locale), $locale, $category, $page, $perPage);
    }

    public function buildPreviewGalleryListing(string $locale, array $content, ?string $category = null, int $page = 1, int $perPage = 8): array
    {
        return $this->galleryListingFromContent($this->normalizeGalleryPageContent($content, $locale), $locale, $category, $page, $perPage);
    }

    public function getEditablePayload(string $targetKey): array
    {
        if (! in_array($targetKey, ['news.index', 'news.articles', 'news.announcements', 'news.events', 'news.gallery'], true)) {
            throw new \InvalidArgumentException('Unsupported news target.');
        }

        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);

        if (is_array($published['translations']['ar'] ?? null) && is_array($published['translations']['en'] ?? null)) {
            return [
                'translations' => [
                    'ar' => $published['translations']['ar'],
                    'en' => $published['translations']['en'],
                ],
            ];
        }

        $fallback = match ($targetKey) {
            'news.index' => fn (string $locale): array => $this->indexPageFallback($locale),
            'news.articles' => fn (string $locale): array => $this->articlesPageFallback($locale),
            'news.announcements' => fn (string $locale): array => $this->announcementsPageFallback($locale),
            'news.events' => fn (string $locale): array => $this->eventsPageFallback($locale),
            'news.gallery' => fn (string $locale): array => $this->galleryPageFallback($locale),
        };

        return [
            'translations' => [
                'ar' => $fallback('ar'),
                'en' => $fallback('en'),
            ],
        ];
    }

    public function listPublicArticles(string $locale, array $filters = [], int $page = 1, int $perPage = 12): PaginatedResultDTO
    {
        $cacheKey = 'news:list:'.$locale.':'.md5(json_encode([
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
        ], JSON_THROW_ON_ERROR));

        return $this->newsCache()->remember($cacheKey, function () use ($locale, $filters, $page, $perPage): PaginatedResultDTO {
            $query = NewsArticle::query()
                ->public()
                ->with(['translations', 'seoMeta', 'coverMedia', 'category.translations'])
                ->when(is_string($filters['category'] ?? null) && $filters['category'] !== '', function (Builder $query) use ($filters): void {
                    $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('slug', $filters['category']));
                })
                ->when(is_string($filters['categoryType'] ?? null) && $filters['categoryType'] !== '', function (Builder $query) use ($filters): void {
                    $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $filters['categoryType']));
                })
                ->when(is_int($filters['excludeId'] ?? null), fn (Builder $query): Builder => $query->whereKeyNot($filters['excludeId']))
                ->when(is_string($filters['search'] ?? null) && trim((string) $filters['search']) !== '', function (Builder $query) use ($filters): void {
                    $search = trim((string) $filters['search']);
                    $query->whereHas('translations', function (Builder $translationQuery) use ($search): void {
                        $translationQuery
                            ->where('title', 'like', '%'.$search.'%')
                            ->orWhere('excerpt', 'like', '%'.$search.'%')
                            ->orWhere('body', 'like', '%'.$search.'%');
                    });
                });

            $this->applyNewestArticleOrder($query);

            $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

            return new PaginatedResultDTO(
                items: $paginator->getCollection()->map(fn (NewsArticle $article): NewsArticleDTO => $this->mapArticle($article, $locale)),
                total: $paginator->total(),
                currentPage: $paginator->currentPage(),
                perPage: $paginator->perPage(),
                lastPage: $paginator->lastPage(),
            );
        }, 300);
    }

    public function getPublicArticle(string $slug, string $locale): ?NewsArticleDTO
    {
        return $this->newsCache()->remember('news:article:'.$locale.':'.$slug, function () use ($slug, $locale): ?NewsArticleDTO {
            $article = NewsArticle::query()
                ->public()
                ->when(ctype_digit($slug),
                    fn (Builder $query): Builder => $query->whereKey((int) $slug),
                    fn (Builder $query): Builder => $query->where('slug', $slug),
                )
                ->with(['translations', 'seoMeta.ogImageMedia', 'coverMedia', 'category.translations', 'attachments.mediaAsset'])
                ->first();

            return $article instanceof NewsArticle ? $this->mapArticle($article, $locale, true) : null;
        }, 300);
    }

    public function getFeaturedArticles(string $locale, int $limit = 3, ?string $categoryType = null): Collection
    {
        $query = NewsArticle::query()
            ->public()
            ->where('is_featured', true)
            ->when($categoryType !== null, function (Builder $query) use ($categoryType): void {
                $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $categoryType));
            })
            ->with(['translations', 'coverMedia', 'category.translations']);
        $this->applyNewestArticleOrder($query);

        return $query
            ->limit($limit)
            ->get()
            ->map(function (NewsArticle $article) use ($locale): ArticleCardDTO {
                $translation = $this->articleTranslation($article, $locale);
                $category = $article->category instanceof NewsCategory ? $this->mapCategory($article->category, $locale) : null;

                return new ArticleCardDTO(
                    id: (int) $article->getKey(),
                    locale: $locale,
                    title: $this->plainText((string) $translation->title),
                    slug: (string) $article->slug,
                    excerpt: $this->articleExcerpt($translation),
                    imageUrl: $this->mediaUrl($article->coverMedia),
                    publishedAt: $this->articlePublishedAt($article),
                    url: $this->articleUrl($locale, (int) $article->getKey()),
                    categoryLabel: $category?->name,
                );
            })
            ->values();
    }

    public function getLatestArticleCards(string $locale, int $limit = 5, ?string $categoryType = null): Collection
    {
        return $this->newsCache()->remember('news:latest:'.$locale.':'.$limit.':'.($categoryType ?? 'all'), function () use ($locale, $limit, $categoryType): Collection {
            $query = NewsArticle::query()
                ->public()
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->when($categoryType !== null, function (Builder $query) use ($categoryType): void {
                    $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $categoryType));
                });
            $this->applyNewestArticleOrder($query);

            return $query
                ->limit($limit)
                ->get()
                ->map(fn (NewsArticle $article): ArticleCardDTO => $this->mapArticleCard($article, $locale))
                ->values();
        }, 300);
    }

    public function getRelatedArticleCards(string $slug, string $locale, int $limit = 3): Collection
    {
        return $this->newsCache()->remember('news:related:'.$locale.':'.$slug.':'.$limit, function () use ($slug, $locale, $limit): Collection {
            $article = $this->publicArticleByIdentifier($slug);

            if (! $article instanceof NewsArticle) {
                return collect();
            }

            $query = NewsArticle::query()
                ->public()
                ->whereKeyNot($article->getKey())
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->when($article->news_category_id !== null, function (Builder $query) use ($article): void {
                    $query->where('news_category_id', $article->news_category_id);
                });
            $this->applyNewestArticleOrder($query);

            return $query
                ->limit($limit)
                ->get()
                ->map(fn (NewsArticle $related): ArticleCardDTO => $this->mapArticleCard($related, $locale))
                ->values();
        }, 300);
    }

    public function getAdjacentArticleCards(string $slug, string $locale): array
    {
        return $this->newsCache()->remember('news:adjacent:'.$locale.':'.$slug, function () use ($slug, $locale): array {
            $article = $this->publicArticleByIdentifier($slug);

            if (! $article instanceof NewsArticle) {
                return ['previous' => null, 'next' => null];
            }

            $previous = NewsArticle::query()
                ->public()
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->whereKeyNot($article->getKey())
                ->where('id', '<', $article->getKey())
                ->orderByDesc('id')
                ->first();
            $next = NewsArticle::query()
                ->public()
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->whereKeyNot($article->getKey())
                ->where('id', '>', $article->getKey())
                ->orderBy('id')
                ->first();

            return [
                'previous' => $previous instanceof NewsArticle ? $this->mapArticleCard($previous, $locale) : null,
                'next' => $next instanceof NewsArticle ? $this->mapArticleCard($next, $locale) : null,
            ];
        }, 300);
    }

    public function getPublicCategories(string $locale, ?string $type = null): Collection
    {
        return $this->newsCache()->remember('news:categories:'.$locale.':'.($type ?? 'all'), function () use ($locale, $type): Collection {
            return NewsCategory::query()
                ->enabled()
                ->with('translations')
                ->when($type !== null, fn (Builder $query): Builder => $query->where('type', $type))
                ->whereHas('articles', fn (Builder $query): Builder => $query
                    ->where('status', 'published')
                    ->where('is_enabled', true)
                    ->where(function (Builder $articleQuery): void {
                        $articleQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
                    }))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (NewsCategory $category): NewsCategoryDTO => $this->mapCategory($category, $locale))
                ->values();
        }, 300);
    }

    private function mapArticle(NewsArticle $article, string $locale, bool $includeAttachments = false): NewsArticleDTO
    {
        $translation = $this->articleTranslation($article, $locale);
        $seo = $this->articleSeo($article, $locale);
        $excerpt = $this->articleExcerpt($translation);
        $imageUrl = $seo?->og_image_url
            ?? $this->mediaUrl($seo?->ogImageMedia)
            ?? $this->mediaUrl($article->coverMedia);

        return new NewsArticleDTO(
            id: (int) $article->getKey(),
            locale: $locale,
            slug: (string) $article->slug,
            title: $this->plainText((string) $translation->title),
            excerpt: $excerpt,
            body: $includeAttachments ? $translation->body : null,
            imageUrl: $imageUrl,
            publishedAt: $this->articlePublishedAt($article),
            url: $this->articleUrl($locale, (int) $article->getKey()),
            category: $article->category instanceof NewsCategory ? $this->mapCategory($article->category, $locale) : null,
            attachments: $includeAttachments ? $this->mapAttachments($article, $locale) : [],
            metaTitle: $this->nullablePlainText($seo?->meta_title),
            metaDescription: $seo?->meta_description ?: $excerpt,
            ogTitle: $this->nullablePlainText($seo?->og_title),
            ogDescription: $seo?->og_description,
            ogImage: $imageUrl,
            robots: $seo?->robots ?? 'index,follow',
        );
    }

    private function mapArticleCard(NewsArticle $article, string $locale): ArticleCardDTO
    {
        $translation = $this->articleTranslation($article, $locale);
        $category = $article->category instanceof NewsCategory ? $this->mapCategory($article->category, $locale) : null;

        return new ArticleCardDTO(
            id: (int) $article->getKey(),
            locale: $locale,
            title: $this->plainText((string) $translation->title),
            slug: (string) $article->slug,
            excerpt: $this->articleExcerpt($translation),
            imageUrl: $this->mediaUrl($article->coverMedia),
            publishedAt: $this->articlePublishedAt($article),
            url: $this->articleUrl($locale, (int) $article->getKey()),
            categoryLabel: $category?->name,
        );
    }

    private function mapCategory(NewsCategory $category, string $locale): NewsCategoryDTO
    {
        $translation = $this->categoryTranslation($category, $locale);

        return new NewsCategoryDTO(
            id: (int) $category->getKey(),
            slug: (string) $category->slug,
            type: (string) $category->type,
            name: (string) $translation->name,
            description: $translation->description,
        );
    }

    /** @return array<int, NewsAttachmentDTO> */
    private function mapAttachments(NewsArticle $article, string $locale): array
    {
        return $article->attachments
            ->map(fn (NewsArticleAttachment $attachment): NewsAttachmentDTO => new NewsAttachmentDTO(
                id: (int) $attachment->getKey(),
                kind: (string) $attachment->kind,
                label: $locale === 'ar' ? $attachment->label_ar : ($attachment->label_en ?? $attachment->label_ar),
                url: $this->mediaUrl($attachment->mediaAsset),
            ))
            ->filter(fn (NewsAttachmentDTO $attachment): bool => $attachment->url !== null)
            ->values()
            ->all();
    }

    private function articleTranslation(NewsArticle $article, string $locale): NewsArticleTranslation
    {
        $requested = $article->translations->firstWhere('locale', $locale);

        return ($requested instanceof NewsArticleTranslation
            && trim((string) $requested->title) !== ''
            && trim(strip_tags((string) $requested->body)) !== '' ? $requested : null)
            ?? $article->translations->firstWhere('locale', 'ar')
            ?? $article->translations->first();
    }

    private function articleExcerpt(NewsArticleTranslation $translation): ?string
    {
        $excerpt = trim((string) $translation->excerpt);
        if ($excerpt !== '') {
            return $excerpt;
        }

        $body = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $translation->body), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $body === '' ? null : Str::limit($body, 220, '...');
    }

    private function articlePublishedAt(NewsArticle $article): ?string
    {
        return $article->legacy_source_table === 'jx_categories'
            ? null
            : $article->published_at?->toDateString();
    }

    private function applyNewestArticleOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN legacy_source_table = 'jx_categories' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE WHEN legacy_source_table = 'jx_categories' THEN NULL ELSE published_at END DESC")
            ->orderByRaw("CASE WHEN legacy_source_table = 'jx_categories' THEN legacy_source_id ELSE NULL END DESC")
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    private function plainText(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function nullablePlainText(?string $value): ?string
    {
        return $value === null ? null : $this->plainText($value);
    }

    private function categoryTranslation(NewsCategory $category, string $locale): NewsCategoryTranslation
    {
        return $category->translations->firstWhere('locale', $locale)
            ?? $category->translations->firstWhere('locale', 'ar')
            ?? $category->translations->first();
    }

    private function articleSeo(NewsArticle $article, string $locale): ?NewsArticleSeoMeta
    {
        return $article->seoMeta->firstWhere('locale', $locale)
            ?? $article->seoMeta->firstWhere('locale', 'ar')
            ?? $article->seoMeta->first();
    }

    private function mediaUrl(?MediaAsset $media): ?string
    {
        if (! $media instanceof MediaAsset) {
            return null;
        }

        $path = $media->webp_path ?: $media->path;

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        if ($media->disk === 'legacy') {
            $publicLegacyPath = 'legacy/'.ltrim($path, '/');

            return is_file(public_path($publicLegacyPath)) ? '/'.$publicLegacyPath : null;
        }

        return '/storage/'.$path;
    }

    private function articleUrl(string $locale, int $articleId): string
    {
        return '/'.$locale.'/news/'.$articleId;
    }

    private function publicArticleByIdentifier(string $identifier): ?NewsArticle
    {
        return NewsArticle::query()
            ->public()
            ->when(ctype_digit($identifier),
                fn (Builder $query): Builder => $query->whereKey((int) $identifier),
                fn (Builder $query): Builder => $query->where('slug', $identifier),
            )
            ->first();
    }

    private function newsCache(): CacheServiceInterface
    {
        return $this->cacheService->tags(['news', 'public-pages', 'seo', 'sitemap']);
    }

    /** @return array<string, mixed>|null */
    private function publishedLocalizedPayload(string $targetKey, string $locale): ?array
    {
        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);
        $localized = is_array($published['translations'][$locale] ?? null)
            ? $published['translations'][$locale]
            : null;

        if (! is_array($localized)) {
            return null;
        }

        return match ($targetKey) {
            'news.articles' => $this->normalizeArticlesPageContent($localized, $locale),
            'news.announcements' => $this->normalizeAnnouncementsPageContent($localized, $locale),
            'news.events' => $this->normalizeEventsPageContent($localized, $locale),
            'news.gallery' => $this->normalizeGalleryPageContent($localized, $locale),
            default => $this->normalizeIndexPageContent($localized, $locale),
        };
    }

    /** @return array<string, mixed> */
    private function indexPageFallback(string $locale): array
    {
        $isAr = $locale === 'ar';

        return $this->normalizeIndexPageContent([
            'title' => $isAr ? 'الأخبار' : 'News',
            'headline' => $isAr ? 'الأخبار' : 'NEWS',
            'summary' => $isAr ? 'تابع آخر أخبار الجامعة السورية الخاصة وإعلاناتها.' : 'Follow the latest Syrian Private University news and announcements.',
            'pageTitle' => $isAr ? 'الأخبار' : 'News',
            'pageDescription' => $isAr ? 'تابع آخر أخبار الجامعة السورية الخاصة وإعلاناتها.' : 'Follow the latest Syrian Private University news and announcements.',
            'heroImage' => '/images/slider-1.webp',
            'heroTitle' => $isAr ? 'الأخبار' : 'NEWS',
            'heroLinks' => [
                ['id' => 'last-news', 'label' => $isAr ? 'آخر الأخبار' : 'Last News'],
                ['id' => 'announcements', 'label' => $isAr ? 'الإعلانات' : 'Announcements'],
                ['id' => 'events', 'label' => $isAr ? 'الفعاليات' : 'Events'],
                ['id' => 'media-gallery', 'label' => $isAr ? 'معرض الوسائط' : 'Media Gallery'],
            ],
            'lastNewsTitle' => $isAr ? 'آخر الأخبار' : 'Last News',
            'lastNewsViewAllLabel' => $isAr ? 'عرض الكل' : 'View All News',
            'announcementsTitle' => $isAr ? 'الإعلانات' : 'Announcements',
            'announcementsViewAllLabel' => $isAr ? 'عرض كافة الإعلانات' : 'View All Announcements',
            'eventsTitle' => $isAr ? 'الفعاليات القادمة' : 'Upcoming Events',
            'eventsViewAllLabel' => $isAr ? 'عرض تفاصيل كافة الفعاليات' : 'View All Events Details',
            'exploreMoreTitle' => $isAr ? 'استكشف المزيد' : 'Explore More',
            'archiveTitle' => $isAr ? 'أرشيف الأخبار' : 'News Archive',
            'archiveCta' => $isAr ? 'انقر للزيارة' : 'Visit Room',
            'announcementsCardTitle' => $isAr ? 'الإعلانات' : 'Announcements',
            'announcementsCardCta' => $isAr ? 'انقر للزيارة' : 'Visit Room',
            'readMoreLabel' => $isAr ? 'اقرأ المزيد' : 'Read More',
            'viewDetailsLabel' => $isAr ? 'عرض التفاصيل' : 'View Details',
            'newLabel' => $isAr ? 'جديد' : 'New',
            'emptyAnnouncements' => $isAr ? 'لا توجد إعلانات منشورة حالياً.' : 'No announcements are currently published.',
            'newsFallbackCategory' => $isAr ? 'أخبار' : 'News',
            'universityNewsFallbackCategory' => $isAr ? 'أخبار الجامعة' : 'University News',
        ], $locale);
    }

    /** @return array<string, mixed> */
    private function announcementsPageFallback(string $locale): array
    {
        $isAr = $locale === 'ar';

        return $this->normalizeAnnouncementsPageContent([
            'pageTitle' => $isAr ? 'الإعلانات' : 'Announcements',
            'pageDescription' => $isAr ? 'تابع الإعلانات الأكاديمية والإدارية الرسمية للجامعة.' : 'Follow official academic and administrative university announcements.',
            'heroImage' => '/images/slider-1.webp',
            'featuredLabel' => $isAr ? 'إعلان مميز' : 'Priority Announcement',
            'allCategoriesLabel' => $isAr ? 'كل التصنيفات' : 'All Categories',
            'readMoreLabel' => $isAr ? 'قراءة الإعلان' : 'Read Full Announcement',
            'downloadLabel' => $isAr ? 'تحميل المرفق' : 'Download Attachment',
            'emptyState' => $isAr ? 'لا توجد إعلانات منشورة حالياً.' : 'No announcements are currently published.',
        ], $locale);
    }

    /** @return array<string, mixed> */
    private function articlesPageFallback(string $locale): array
    {
        $isAr = $locale === 'ar';

        return $this->normalizeArticlesPageContent([
            'title' => $isAr ? 'قائمة الأخبار' : 'News Listing',
            'summary' => $isAr ? 'تصفح أخبار الجامعة السورية الخاصة حسب التصنيف.' : 'Browse Syrian Private University news by category.',
            'heroImage' => '/images/slider-1.webp',
            'allLabel' => $isAr ? 'كل الأخبار' : 'All News',
            'searchLabel' => $isAr ? 'البحث في الأخبار' : 'Search news',
            'searchPlaceholder' => $isAr ? 'ابحث بالعنوان أو المحتوى' : 'Search by title or content',
            'searchAction' => $isAr ? 'بحث' : 'Search',
            'readMoreLabel' => $isAr ? 'اقرأ المزيد' : 'Read More',
            'emptyLabel' => $isAr ? 'لا توجد أخبار مطابقة.' : 'No matching news articles were found.',
            'previousLabel' => $isAr ? 'الصفحة السابقة' : 'Previous page',
            'nextLabel' => $isAr ? 'الصفحة التالية' : 'Next page',
            'seoTitle' => ($isAr ? 'قائمة الأخبار' : 'News Listing').' | SPU',
            'seoDescription' => $isAr ? 'أخبار الجامعة السورية الخاصة.' : 'Syrian Private University news listing.',
            'seoImage' => '/images/slider-1.webp',
        ], $locale);
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeArticlesPageContent(array $content, string $locale): array
    {
        $fallback = $locale === 'ar' ? [
            'title' => 'قائمة الأخبار', 'summary' => '', 'heroImage' => '/images/slider-1.webp', 'allLabel' => 'كل الأخبار',
            'searchLabel' => 'البحث في الأخبار', 'searchPlaceholder' => 'ابحث بالعنوان أو المحتوى', 'searchAction' => 'بحث',
            'readMoreLabel' => 'اقرأ المزيد', 'emptyLabel' => 'لا توجد أخبار مطابقة.', 'previousLabel' => 'الصفحة السابقة',
            'nextLabel' => 'الصفحة التالية', 'seoTitle' => 'قائمة الأخبار | SPU', 'seoDescription' => '', 'seoImage' => '/images/slider-1.webp',
        ] : [
            'title' => 'News Listing', 'summary' => '', 'heroImage' => '/images/slider-1.webp', 'allLabel' => 'All News',
            'searchLabel' => 'Search news', 'searchPlaceholder' => 'Search by title or content', 'searchAction' => 'Search',
            'readMoreLabel' => 'Read More', 'emptyLabel' => 'No matching news articles were found.', 'previousLabel' => 'Previous page',
            'nextLabel' => 'Next page', 'seoTitle' => 'News Listing | SPU', 'seoDescription' => '', 'seoImage' => '/images/slider-1.webp',
        ];

        foreach ($fallback as $key => $value) {
            $candidate = $content[$key] ?? $value;
            $fallback[$key] = is_string($candidate) || is_numeric($candidate) ? (string) $candidate : $value;
        }

        return $fallback;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeAnnouncementsPageContent(array $content, string $locale): array
    {
        $fallback = [
            'pageTitle' => $locale === 'ar' ? 'الإعلانات' : 'Announcements',
            'pageDescription' => '',
            'heroImage' => '/images/slider-1.webp',
            'featuredLabel' => $locale === 'ar' ? 'إعلان مميز' : 'Priority Announcement',
            'allCategoriesLabel' => $locale === 'ar' ? 'كل التصنيفات' : 'All Categories',
            'readMoreLabel' => $locale === 'ar' ? 'قراءة الإعلان' : 'Read Full Announcement',
            'downloadLabel' => $locale === 'ar' ? 'تحميل المرفق' : 'Download Attachment',
            'emptyState' => $locale === 'ar' ? 'لا توجد إعلانات منشورة حالياً.' : 'No announcements are currently published.',
        ];

        foreach ($fallback as $key => $value) {
            $candidate = $content[$key] ?? $value;
            $fallback[$key] = is_string($candidate) || is_numeric($candidate) ? (string) $candidate : $value;
        }

        $fallback['title'] = $fallback['pageTitle'];
        $fallback['headline'] = $fallback['pageTitle'];
        $fallback['summary'] = $fallback['pageDescription'];

        return $fallback;
    }

    /** @return array<string, mixed> */
    private function eventsPageFallback(string $locale): array
    {
        $path = resource_path('data/news-events-content.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $content = is_array($decoded['translations'][$locale] ?? null) ? $decoded['translations'][$locale] : [];

        return $this->normalizeEventsPageContent($content, $locale);
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeEventsPageContent(array $content, string $locale): array
    {
        $isAr = $locale === 'ar';
        $defaults = [
            'title' => $isAr ? 'فعاليات الجامعة' : 'University Events',
            'headline' => $isAr ? 'فعاليات الجامعة' : 'University Events',
            'summary' => '',
            'heroImage' => '/images/uni-main-place.JPG',
            'calendarTitle' => $isAr ? 'تقويم الفعاليات' : 'Events Calendar',
            'upcomingTitle' => $isAr ? 'الفعاليات القادمة' : 'Upcoming Events',
            'pastTitle' => $isAr ? 'الفعاليات السابقة' : 'Past Events',
            'allCategoriesLabel' => $isAr ? 'جميع الفعاليات' : 'All Events',
            'registerLabel' => $isAr ? 'سجل الآن' : 'Register Now',
            'detailsLabel' => $isAr ? 'عرض التفاصيل' : 'View Details',
            'freeLabel' => $isAr ? 'مجاني' : 'Free',
            'spotsLeftLabel' => $isAr ? 'أماكن متبقية' : 'spots left',
            'emptyLabel' => $isAr ? 'لا توجد فعاليات ضمن هذا التصنيف.' : 'No events match this category.',
            'registrationTitle' => $isAr ? 'التسجيل في الفعالية' : 'Event Registration',
            'registrationInfo' => '',
            'notFoundTitle' => $isAr ? 'الفعالية غير موجودة' : 'Event Not Found',
            'notFoundText' => '',
            'backLabel' => $isAr ? 'العودة إلى الفعاليات' : 'Back to Events',
            'highlightsLabel' => $isAr ? 'أبرز محاور الفعالية' : 'Event Highlights',
            'speakersLabel' => $isAr ? 'المتحدثون' : 'Speakers',
            'resultsLabel' => $isAr ? 'النتائج والإنجازات' : 'Results and Achievements',
            'galleryLabel' => $isAr ? 'معرض الصور' : 'Photo Gallery',
        ];

        foreach ($defaults as $key => $value) {
            $candidate = $content[$key] ?? $value;
            $defaults[$key] = is_string($candidate) || is_numeric($candidate) ? (string) $candidate : $value;
        }

        $defaults['categories'] = array_values(array_filter(
            is_array($content['categories'] ?? null) ? $content['categories'] : [],
            static fn (mixed $category): bool => is_array($category) && is_string($category['id'] ?? null) && is_string($category['label'] ?? null),
        ));
        $defaults['upcoming'] = $this->normalizeEventRecords($content['upcoming'] ?? [], false);
        $defaults['past'] = $this->normalizeEventRecords($content['past'] ?? [], true);

        return $defaults;
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeEventRecords(mixed $records, bool $past): array
    {
        if (! is_array($records)) {
            return [];
        }

        $allowedForms = ['conference-registration', 'activity-registration'];
        $normalized = [];
        $seen = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $id = trim((string) ($record['id'] ?? ''));
            $title = trim((string) ($record['title'] ?? ''));
            $startsAt = trim((string) ($record['startsAt'] ?? ''));

            if ($id === '' || $title === '' || $startsAt === '' || isset($seen[$id]) || strtotime($startsAt) === false) {
                continue;
            }

            $seen[$id] = true;
            $formId = is_string($record['formId'] ?? null) && in_array($record['formId'], $allowedForms, true) ? $record['formId'] : null;
            $capacity = is_numeric($record['capacity'] ?? null) ? max(0, (int) $record['capacity']) : null;
            $registered = is_numeric($record['registered'] ?? null) ? max(0, (int) $record['registered']) : 0;

            $normalized[] = array_merge($record, [
                'id' => $id,
                'title' => $title,
                'startsAt' => $startsAt,
                'formId' => $past ? null : $formId,
                'capacity' => $capacity,
                'registered' => $capacity === null ? $registered : min($capacity, $registered),
            ]);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $event */
    private function mapNewsEvent(array $event, string $locale, bool $past): NewsEventDTO
    {
        $speakers = collect(is_array($event['speakers'] ?? null) ? $event['speakers'] : [])
            ->filter(fn (mixed $speaker): bool => is_array($speaker))
            ->map(fn (array $speaker): array => ['name' => (string) ($speaker['name'] ?? ''), 'title' => (string) ($speaker['title'] ?? '')])
            ->values()
            ->all();
        $formId = is_string($event['formId'] ?? null) ? $event['formId'] : null;
        $capacity = is_int($event['capacity'] ?? null) ? $event['capacity'] : null;
        $registered = (int) ($event['registered'] ?? 0);
        $remainingCapacity = $capacity === null ? null : max(0, $capacity - $registered);
        $isRegisterable = ! $past && $formId !== null && ($remainingCapacity === null || $remainingCapacity > 0);
        $id = (string) $event['id'];

        return new NewsEventDTO(
            id: $id,
            locale: $locale,
            title: (string) $event['title'],
            summary: (string) ($event['summary'] ?? ''),
            startsAt: (string) $event['startsAt'],
            endsAt: is_string($event['endsAt'] ?? null) ? $event['endsAt'] : null,
            dateLabel: (string) ($event['dateLabel'] ?? ''),
            timeLabel: (string) ($event['timeLabel'] ?? ''),
            location: (string) ($event['location'] ?? ''),
            categoryId: (string) ($event['categoryId'] ?? ''),
            categoryLabel: (string) ($event['categoryLabel'] ?? ''),
            imageUrl: (string) ($event['image'] ?? '/images/uni-main-place.JPG'),
            isPast: $past,
            isFeatured: (bool) ($event['featured'] ?? false),
            formId: $formId,
            capacity: $capacity,
            registered: $registered,
            remainingCapacity: $remainingCapacity,
            isRegisterable: $isRegisterable,
            registrationUrl: $isRegisterable ? '/'.$locale.'/news/events-list/register?event='.rawurlencode($id) : null,
            detailUrl: $past ? '/'.$locale.'/news/events-list/past?event='.rawurlencode($id) : '/'.$locale.'/news/events-list#'.rawurlencode($id),
            participants: is_string($event['participants'] ?? null) ? $event['participants'] : null,
            highlights: array_values(array_filter(is_array($event['highlights'] ?? null) ? $event['highlights'] : [], 'is_string')),
            speakers: $speakers,
            results: is_string($event['results'] ?? null) ? $event['results'] : null,
            gallery: array_values(array_filter(is_array($event['gallery'] ?? null) ? $event['gallery'] : [], 'is_string')),
        );
    }

    /** @return array<string, mixed> */
    private function galleryPageFallback(string $locale): array
    {
        $path = resource_path('data/news-gallery-content.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $content = is_array($decoded['translations'][$locale] ?? null) ? $decoded['translations'][$locale] : [];

        return $this->normalizeGalleryPageContent($content, $locale);
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeGalleryPageContent(array $content, string $locale): array
    {
        $isAr = $locale === 'ar';
        $defaults = [
            'title' => $isAr ? 'معرض الوسائط' : 'Media Gallery',
            'headline' => $isAr ? 'معرض الوسائط' : 'Media Gallery',
            'summary' => '',
            'heroImage' => '/images/slider-1.webp',
            'allLabel' => $isAr ? 'كل الصور' : 'All Images',
            'latestLabel' => $isAr ? 'عرض الأحدث' : 'Showing Latest',
            'emptyLabel' => $isAr ? 'لا توجد صور ضمن هذا التصنيف.' : 'No gallery images match this category.',
            'openLabel' => $isAr ? 'فتح الصورة' : 'Open image',
            'closeLabel' => $isAr ? 'إغلاق عارض الصور' : 'Close image viewer',
            'previousLabel' => $isAr ? 'الصورة السابقة' : 'Previous image',
            'nextLabel' => $isAr ? 'الصورة التالية' : 'Next image',
        ];

        foreach ($defaults as $key => $value) {
            $candidate = $content[$key] ?? $value;
            $defaults[$key] = is_string($candidate) || is_numeric($candidate) ? (string) $candidate : $value;
        }

        $defaults['categories'] = array_values(array_filter(
            is_array($content['categories'] ?? null) ? $content['categories'] : [],
            static fn (mixed $category): bool => is_array($category)
                && is_string($category['id'] ?? null)
                && trim($category['id']) !== ''
                && is_string($category['label'] ?? null)
                && trim($category['label']) !== '',
        ));
        $defaults['items'] = $this->normalizeGalleryRecords($content['items'] ?? []);

        return $defaults;
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeGalleryRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $id = trim((string) ($record['id'] ?? ''));
            $mediaId = is_numeric($record['mediaId'] ?? null) ? (int) $record['mediaId'] : null;
            $imageUrl = is_string($record['imageUrl'] ?? null) ? trim($record['imageUrl']) : '';

            if ($id === '' || isset($seen[$id]) || ($mediaId === null && $imageUrl === '')) {
                continue;
            }

            $seen[$id] = true;
            $normalized[] = array_merge($record, [
                'id' => $id,
                'mediaId' => $mediaId,
                'imageUrl' => $imageUrl,
                'categoryId' => trim((string) ($record['categoryId'] ?? '')),
                'categoryLabel' => trim((string) ($record['categoryLabel'] ?? '')),
                'dateLabel' => trim((string) ($record['dateLabel'] ?? '')),
                'featured' => (bool) ($record['featured'] ?? false),
            ]);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $content @return array{page: array<string, mixed>, featured: NewsGalleryItemDTO|null, items: PaginatedResultDTO} */
    private function galleryListingFromContent(array $content, string $locale, ?string $category, int $page, int $perPage): array
    {
        $records = is_array($content['items'] ?? null) ? $content['items'] : [];
        $mediaIds = collect($records)
            ->map(fn (mixed $record): ?int => is_array($record) && is_int($record['mediaId'] ?? null) ? $record['mediaId'] : null)
            ->filter(fn (?int $id): bool => $id !== null)
            ->values()
            ->all();
        $media = $this->mediaService->resolvePublicImages($mediaIds, $locale)
            ->keyBy(fn (PublicMediaAssetDTO $asset): int => $asset->mediaId);

        $items = collect($records)
            ->map(function (array $record) use ($media): ?NewsGalleryItemDTO {
                $asset = is_int($record['mediaId'] ?? null) ? $media->get($record['mediaId']) : null;
                $title = $asset instanceof PublicMediaAssetDTO ? $asset->title : trim((string) ($record['title'] ?? ''));
                $altText = $asset instanceof PublicMediaAssetDTO ? $asset->altText : trim((string) ($record['altText'] ?? ''));
                $imageUrl = $asset instanceof PublicMediaAssetDTO ? $asset->url : trim((string) ($record['imageUrl'] ?? ''));

                if ($title === '' || $altText === '' || $imageUrl === '') {
                    return null;
                }

                return new NewsGalleryItemDTO(
                    id: (string) $record['id'],
                    title: $title,
                    altText: $altText,
                    caption: $asset instanceof PublicMediaAssetDTO ? $asset->caption : (is_string($record['caption'] ?? null) ? $record['caption'] : null),
                    imageUrl: $imageUrl,
                    categoryId: (string) ($record['categoryId'] ?? ''),
                    categoryLabel: (string) ($record['categoryLabel'] ?? ''),
                    dateLabel: (string) ($record['dateLabel'] ?? ''),
                    isFeatured: (bool) ($record['featured'] ?? false),
                    mediaId: $asset instanceof PublicMediaAssetDTO ? $asset->mediaId : null,
                    width: $asset instanceof PublicMediaAssetDTO ? $asset->width : null,
                    height: $asset instanceof PublicMediaAssetDTO ? $asset->height : null,
                );
            })
            ->filter(fn (mixed $item): bool => $item instanceof NewsGalleryItemDTO)
            ->when($category !== null, fn (Collection $items): Collection => $items->filter(fn (NewsGalleryItemDTO $item): bool => $item->categoryId === $category))
            ->values();

        $featured = $items->first(fn (NewsGalleryItemDTO $item): bool => $item->isFeatured) ?? $items->first();
        $regular = $featured instanceof NewsGalleryItemDTO
            ? $items->reject(fn (NewsGalleryItemDTO $item): bool => $item->id === $featured->id)->values()
            : $items;
        $perPage = max(1, min(24, $perPage));
        $total = $regular->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);

        return [
            'page' => $content,
            'featured' => $featured instanceof NewsGalleryItemDTO ? $featured : null,
            'items' => new PaginatedResultDTO(
                items: $regular->slice(($currentPage - 1) * $perPage, $perPage)->values(),
                total: $total,
                currentPage: $currentPage,
                perPage: $perPage,
                lastPage: $lastPage,
            ),
        ];
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeIndexPageContent(array $content, string $locale): array
    {
        $fallback = [
            'title' => $locale === 'ar' ? 'الأخبار' : 'News',
            'headline' => $locale === 'ar' ? 'الأخبار' : 'NEWS',
            'summary' => '',
            'pageTitle' => $locale === 'ar' ? 'الأخبار' : 'News',
            'pageDescription' => '',
            'heroImage' => '/images/slider-1.webp',
            'heroTitle' => $locale === 'ar' ? 'الأخبار' : 'NEWS',
            'heroLinks' => [],
            'lastNewsTitle' => $locale === 'ar' ? 'آخر الأخبار' : 'Last News',
            'lastNewsViewAllLabel' => $locale === 'ar' ? 'عرض الكل' : 'View All News',
            'announcementsTitle' => $locale === 'ar' ? 'الإعلانات' : 'Announcements',
            'announcementsViewAllLabel' => $locale === 'ar' ? 'عرض كافة الإعلانات' : 'View All Announcements',
            'eventsTitle' => $locale === 'ar' ? 'الفعاليات القادمة' : 'Upcoming Events',
            'eventsViewAllLabel' => $locale === 'ar' ? 'عرض تفاصيل كافة الفعاليات' : 'View All Events Details',
            'exploreMoreTitle' => $locale === 'ar' ? 'استكشف المزيد' : 'Explore More',
            'archiveTitle' => $locale === 'ar' ? 'أرشيف الأخبار' : 'News Archive',
            'archiveCta' => $locale === 'ar' ? 'انقر للزيارة' : 'Visit Room',
            'announcementsCardTitle' => $locale === 'ar' ? 'الإعلانات' : 'Announcements',
            'announcementsCardCta' => $locale === 'ar' ? 'انقر للزيارة' : 'Visit Room',
            'readMoreLabel' => $locale === 'ar' ? 'اقرأ المزيد' : 'Read More',
            'viewDetailsLabel' => $locale === 'ar' ? 'عرض التفاصيل' : 'View Details',
            'newLabel' => $locale === 'ar' ? 'جديد' : 'New',
            'emptyAnnouncements' => $locale === 'ar' ? 'لا توجد إعلانات منشورة حالياً.' : 'No announcements are currently published.',
            'newsFallbackCategory' => $locale === 'ar' ? 'أخبار' : 'News',
            'universityNewsFallbackCategory' => $locale === 'ar' ? 'أخبار الجامعة' : 'University News',
        ];

        $normalized = [];

        foreach ($fallback as $key => $value) {
            if ($key === 'heroLinks') {
                $normalized[$key] = array_values(array_filter(is_array($content[$key] ?? null) ? $content[$key] : [], static fn (mixed $item): bool => is_array($item)));

                continue;
            }

            $candidate = $content[$key] ?? $value;
            $normalized[$key] = is_string($candidate) || is_numeric($candidate) ? (string) $candidate : $value;
        }

        $normalized['title'] = $normalized['title'] !== '' ? $normalized['title'] : $normalized['pageTitle'];
        $normalized['headline'] = $normalized['headline'] !== '' ? $normalized['headline'] : $normalized['heroTitle'];
        $normalized['summary'] = $normalized['summary'] !== '' ? $normalized['summary'] : $normalized['pageDescription'];

        return $normalized;
    }
}
