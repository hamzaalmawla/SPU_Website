<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\News\NewsArticleDTO;
use App\DTOs\News\NewsAttachmentDTO;
use App\DTOs\News\NewsCategoryDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class NewsService implements NewsServiceInterface
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
    ) {}

    public function getIndexPageContent(string $locale): array
    {
        return $this->publishedLocalizedPayload('news.index', $locale) ?? $this->indexPageFallback($locale);
    }

    public function buildPreviewIndexPage(string $locale, array $content): array
    {
        return $this->normalizeIndexPageContent($content, $locale);
    }

    public function getEditablePayload(string $targetKey): array
    {
        if ($targetKey !== 'news.index') {
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

        return [
            'translations' => [
                'ar' => $this->indexPageFallback('ar'),
                'en' => $this->indexPageFallback('en'),
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
                ->when(is_string($filters['search'] ?? null) && trim((string) $filters['search']) !== '', function (Builder $query) use ($filters): void {
                    $search = trim((string) $filters['search']);
                    $query->whereHas('translations', function (Builder $translationQuery) use ($search): void {
                        $translationQuery
                            ->where('title', 'like', '%'.$search.'%')
                            ->orWhere('excerpt', 'like', '%'.$search.'%')
                            ->orWhere('body', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('published_at')
                ->orderBy('sort_order')
                ->orderByDesc('id');

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

    public function getFeaturedArticles(string $locale, int $limit = 3): Collection
    {
        return NewsArticle::query()
            ->public()
            ->where('is_featured', true)
            ->with(['translations', 'coverMedia', 'category.translations'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(function (NewsArticle $article) use ($locale): ArticleCardDTO {
                $translation = $this->articleTranslation($article, $locale);
                $category = $article->category instanceof NewsCategory ? $this->mapCategory($article->category, $locale) : null;

                return new ArticleCardDTO(
                    id: (int) $article->getKey(),
                    locale: $locale,
                    title: (string) $translation->title,
                    slug: (string) $article->slug,
                    excerpt: $translation->excerpt,
                    imageUrl: $this->mediaUrl($article->coverMedia),
                    publishedAt: $article->published_at?->toDateString(),
                    url: $this->articleUrl($locale, (int) $article->getKey()),
                    categoryLabel: $category?->name,
                );
            })
            ->values();
    }

    public function getLatestArticleCards(string $locale, int $limit = 5, ?string $categoryType = null): Collection
    {
        return $this->newsCache()->remember('news:latest:'.$locale.':'.$limit.':'.($categoryType ?? 'all'), function () use ($locale, $limit, $categoryType): Collection {
            return NewsArticle::query()
                ->public()
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->when($categoryType !== null, function (Builder $query) use ($categoryType): void {
                    $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $categoryType));
                })
                ->orderByDesc('published_at')
                ->orderBy('sort_order')
                ->orderByDesc('id')
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

            return NewsArticle::query()
                ->public()
                ->whereKeyNot($article->getKey())
                ->with(['translations', 'coverMedia', 'category.translations'])
                ->when($article->news_category_id !== null, function (Builder $query) use ($article): void {
                    $query->where('news_category_id', $article->news_category_id);
                })
                ->orderByDesc('published_at')
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

    public function getPublicCategories(string $locale): Collection
    {
        return $this->newsCache()->remember('news:categories:'.$locale, function () use ($locale): Collection {
            return NewsCategory::query()
                ->enabled()
                ->with('translations')
                ->whereHas('articles', fn (Builder $query): Builder => $query->public())
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
        $imageUrl = $seo?->og_image_url
            ?? $this->mediaUrl($seo?->ogImageMedia)
            ?? $this->mediaUrl($article->coverMedia);

        return new NewsArticleDTO(
            id: (int) $article->getKey(),
            locale: $locale,
            slug: (string) $article->slug,
            title: (string) $translation->title,
            excerpt: $translation->excerpt,
            body: $includeAttachments ? $translation->body : null,
            imageUrl: $imageUrl,
            publishedAt: $article->published_at?->toDateString(),
            url: $this->articleUrl($locale, (int) $article->getKey()),
            category: $article->category instanceof NewsCategory ? $this->mapCategory($article->category, $locale) : null,
            attachments: $includeAttachments ? $this->mapAttachments($article, $locale) : [],
            metaTitle: $seo?->meta_title,
            metaDescription: $seo?->meta_description,
            ogTitle: $seo?->og_title,
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
            title: (string) $translation->title,
            slug: (string) $article->slug,
            excerpt: $translation->excerpt,
            imageUrl: $this->mediaUrl($article->coverMedia),
            publishedAt: $article->published_at?->toDateString(),
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
            ->values()
            ->all();
    }

    private function articleTranslation(NewsArticle $article, string $locale): NewsArticleTranslation
    {
        return $article->translations->firstWhere('locale', $locale)
            ?? $article->translations->firstWhere('locale', 'ar')
            ?? $article->translations->first();
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

        return is_array($localized) ? $this->normalizeIndexPageContent($localized, $locale) : null;
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
