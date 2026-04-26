<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PageServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\BreadcrumbItemDTO;
use App\DTOs\BreadcrumbTrailDTO;
use App\DTOs\PageDraftDataDTO;
use App\DTOs\PageDraftDTO;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageSeoDTO;
use App\DTOs\PageSeoInputDTO;
use App\DTOs\PageShellDataDTO;
use App\DTOs\PageTranslationDTO;
use App\DTOs\PreviewDTO;
use App\DTOs\PreviewPayloadDTO;
use App\Models\Page;
use App\Models\PageDraft;
use App\Models\PageTranslation;
use BadMethodCallException;
use DateTimeInterface;

final class PageService implements PageServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId): PageDraftDTO
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function publish(int $pageId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function unpublish(int $pageId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function schedulePublish(int $pageId, DateTimeInterface $publishAt, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO
    {
        $segments = $this->segmentsFromSlugPath($slug);

        if ($segments === []) {
            return null;
        }

        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->where('slug', end($segments))
            ->first();

        if (! $page instanceof Page || ! $this->isPubliclyRenderable($page, $locale) || ! $this->matchesPath($page, $segments)) {
            return null;
        }

        return $this->mapPageToDto($page);
    }

    public function getAdminEditorPayload(int $pageId): PageDTO
    {
        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->findOrFail($pageId);

        return $this->mapPageToDto($page);
    }

    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO
    {
        $page = Page::query()
            ->with('translations')
            ->findOrFail($pageId);

        $items = [
            new BreadcrumbItemDTO(
                label: $this->homepageLabel($locale),
                url: '/'.$locale,
                isCurrent: false,
            ),
        ];

        foreach ($this->ancestorChain($page) as $ancestor) {
            if ((bool) $ancestor->is_homepage_shell || ! (bool) $ancestor->show_in_breadcrumbs) {
                continue;
            }

            $items[] = new BreadcrumbItemDTO(
                label: $this->breadcrumbLabel($ancestor, $locale),
                url: $this->buildRelativeUrl($ancestor, $locale),
                isCurrent: false,
            );
        }

        $items[] = new BreadcrumbItemDTO(
            label: $this->breadcrumbLabel($page, $locale),
            url: null,
            isCurrent: true,
        );

        return new BreadcrumbTrailDTO($locale, $items);
    }

    public function buildPreviewPayload(int $pageId, string $locale): PreviewDTO
    {
        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->find($pageId);

        $pageDto = $page instanceof Page
            ? $this->pagePreviewDto($page, $locale)
            : null;

        return new PreviewDTO(
            token: '',
            targetType: 'page',
            targetId: $pageId,
            locale: $locale,
            previewUrl: '/'.$locale.'/preview',
            payload: new PreviewPayloadDTO(page: $pageDto),
        );
    }

    public function buildPreviewPayloadFromSnapshot(int $pageId, array $snapshot, string $locale): PreviewDTO
    {
        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->find($pageId);

        $pageDto = $page instanceof Page
            ? $this->mapDraftPayloadToPageDto($page, $snapshot, $locale)
            : null;

        return new PreviewDTO(
            token: '',
            targetType: 'page',
            targetId: $pageId,
            locale: $locale,
            previewUrl: '/'.$locale.'/preview',
            payload: new PreviewPayloadDTO(page: $pageDto),
        );
    }

    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string
    {
        $page = Page::query()
            ->with('translations')
            ->find($pageId);

        if (! $page instanceof Page || ! $this->isPubliclyRenderable($page, $targetLocale)) {
            return null;
        }

        return $this->buildRelativeUrl($page, $targetLocale);
    }

    private function pagePreviewDto(Page $page, string $locale): PageDTO
    {
        $draft = PageDraft::query()
            ->where('page_id', $page->getKey())
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();

        if ($draft instanceof PageDraft && is_array($draft->payload_json)) {
            return $this->mapDraftPayloadToPageDto($page, $draft->payload_json, $locale);
        }

        return $this->mapPageToDto($page);
    }

    private function mapPageToDto(Page $page): PageDTO
    {
        return new PageDTO(
            id: (int) $page->getKey(),
            metadata: new PageMetadataDTO(
                slug: (string) $page->slug,
                template: (string) $page->template,
                isHomepageShell: (bool) $page->is_homepage_shell,
                status: (string) $page->status,
                parentPageId: $page->parent_id !== null ? (int) $page->parent_id : null,
                publishAt: $page->publish_at?->toIso8601String(),
                contentJson: is_array($page->content_json) ? $page->content_json : null,
            ),
            publishedAt: $page->published_at?->toIso8601String(),
            arabicTranslation: $this->mapTranslation($page, $this->findTranslation($page, 'ar'), 'ar'),
            englishTranslation: $this->mapTranslation($page, $this->findTranslation($page, 'en'), 'en'),
            arabicSeo: $this->seoMetadataService->buildForPage((int) $page->getKey(), 'ar'),
            englishSeo: $this->seoMetadataService->buildForPage((int) $page->getKey(), 'en'),
        );
    }

    private function mapDraftPayloadToPageDto(Page $page, array $payload, string $locale): PageDTO
    {
        $draftPage = is_array($payload['page'] ?? null) ? $payload['page'] : $payload;
        $metadata = $this->metadataFromDraftArray(is_array($draftPage['metadata'] ?? null) ? $draftPage['metadata'] : [], $page);
        $arabicTranslation = $this->translationFromDraftArray(is_array($draftPage['arabicTranslation'] ?? null) ? $draftPage['arabicTranslation'] : [], $page, 'ar');
        $englishTranslation = $this->translationFromDraftArray(is_array($draftPage['englishTranslation'] ?? null) ? $draftPage['englishTranslation'] : [], $page, 'en');
        $arabicSeo = $this->seoFromDraftArray(is_array($draftPage['arabicSeo'] ?? null) ? $draftPage['arabicSeo'] : [], $page, 'ar');
        $englishSeo = $this->seoFromDraftArray(is_array($draftPage['englishSeo'] ?? null) ? $draftPage['englishSeo'] : [], $page, 'en');

        return new PageDTO(
            id: (int) $page->getKey(),
            metadata: $metadata,
            publishedAt: $page->published_at?->toIso8601String(),
            arabicTranslation: $arabicTranslation,
            englishTranslation: $englishTranslation,
            arabicSeo: $arabicSeo,
            englishSeo: $englishSeo,
        );
    }

    private function metadataFromDraftArray(array $payload, Page $page): PageMetadataDTO
    {
        return new PageMetadataDTO(
            slug: $this->stringFromDraft($payload, 'slug') ?? (string) $page->slug,
            template: $this->stringFromDraft($payload, 'template') ?? (string) $page->template,
            isHomepageShell: $this->boolFromDraft($payload, 'isHomepageShell', (bool) $page->is_homepage_shell),
            status: $this->stringFromDraft($payload, 'status') ?? (string) $page->status,
            parentPageId: $this->intFromDraft($payload, 'parentPageId') ?? ($page->parent_id !== null ? (int) $page->parent_id : null),
            publishAt: $this->stringFromDraft($payload, 'publishAt') ?? $page->publish_at?->toIso8601String(),
            contentJson: is_array($payload['contentJson'] ?? null)
                ? $payload['contentJson']
                : (is_array($page->content_json) ? $page->content_json : null),
        );
    }

    private function translationFromDraftArray(array $payload, Page $page, string $locale): PageTranslationDTO
    {
        if ($payload === []) {
            return $this->mapTranslation($page, null, $locale);
        }

        return new PageTranslationDTO(
            title: $this->stringFromDraft($payload, 'title') ?? $this->defaultPageTitle($page),
            navigationLabel: $this->stringFromDraft($payload, 'navigationLabel'),
            headline: $this->stringFromDraft($payload, 'headline'),
            subheadline: $this->stringFromDraft($payload, 'subheadline'),
            heroPayload: is_array($payload['heroPayload'] ?? null) ? $payload['heroPayload'] : null,
            overviewCardsPayload: $this->listFromDraft($payload, 'overviewCardsPayload'),
            statsPayload: $this->listFromDraft($payload, 'statsPayload'),
            bodyPayload: is_array($payload['bodyPayload'] ?? null) ? $payload['bodyPayload'] : null,
            ctaPayload: $this->normalizeHomepageShellUrl(is_array($payload['ctaPayload'] ?? null) ? $payload['ctaPayload'] : null, $locale),
            sidebarPayload: is_array($payload['sidebarPayload'] ?? null) ? $payload['sidebarPayload'] : null,
            excerpt: $this->stringFromDraft($payload, 'excerpt'),
            body: $this->stringFromDraft($payload, 'body'),
            rawExcerpt: $this->stringFromDraft($payload, 'rawExcerpt'),
            metaTitleFallback: $this->stringFromDraft($payload, 'metaTitleFallback'),
        );
    }

    private function seoFromDraftArray(array $payload, Page $page, string $locale): PageSeoDTO
    {
        if ($payload === []) {
            return $this->seoMetadataService->buildForPage((int) $page->getKey(), $locale);
        }

        $fallbackSeo = $this->seoMetadataService->buildForPage((int) $page->getKey(), $locale);
        $path = $this->buildRelativeUrl($page, $locale) ?? '/'.$locale;

        return new PageSeoDTO(
            locale: $locale,
            title: $this->stringFromDraft($payload, 'title') ?? $fallbackSeo->title,
            metaDescription: $this->stringFromDraft($payload, 'metaDescription') ?? $fallbackSeo->metaDescription,
            ogTitle: $this->stringFromDraft($payload, 'ogTitle') ?? $fallbackSeo->ogTitle,
            ogDescription: $this->stringFromDraft($payload, 'ogDescription') ?? $fallbackSeo->ogDescription,
            ogImage: $this->stringFromDraft($payload, 'ogImage') ?? $fallbackSeo->ogImage,
            canonicalUrl: $this->stringFromDraft($payload, 'canonicalUrl') ?? $this->seoMetadataService->resolveCanonical($path, $locale),
            hreflang: $this->seoMetadataService->resolveHreflang($this->buildLocalePathMap($page)),
            robots: $this->stringFromDraft($payload, 'robots') ?? $fallbackSeo->robots,
        );
    }

    /**
     * Runtime read precedence is translation-first by design: localized translation payloads and
     * body fields remain authoritative, while pages.content_json only backfills shell-level gaps.
     */
    private function mapTranslation(Page $page, ?PageTranslation $translation, string $locale): PageTranslationDTO
    {
        $contentJson = is_array($page->content_json) ? $page->content_json : null;

        return new PageTranslationDTO(
            title: $translation?->title
                ?? $this->stringFromContentJson($contentJson, 'title')
                ?? $this->defaultPageTitle($page),
            navigationLabel: $translation?->navigation_label
                ?? $this->stringFromContentJson($contentJson, 'navigation_label', 'navigationLabel'),
            headline: $translation?->headline
                ?? $this->stringFromContentJson($contentJson, 'headline'),
            subheadline: $translation?->subheadline
                ?? $this->stringFromContentJson($contentJson, 'subheadline'),
            heroPayload: $translation?->hero_payload
                ?? $this->arrayFromContentJson($contentJson, 'hero_payload', 'heroPayload'),
            overviewCardsPayload: $translation?->overview_cards_payload
                ?? $this->listFromContentJson($contentJson, 'overview_cards_payload', 'overviewCardsPayload'),
            statsPayload: $translation?->stats_payload
                ?? $this->listFromContentJson($contentJson, 'stats_payload', 'statsPayload'),
            bodyPayload: $translation?->body_payload
                ?? $this->arrayFromContentJson($contentJson, 'body_payload', 'bodyPayload'),
            ctaPayload: $this->normalizeHomepageShellUrl(
                $translation?->cta_payload
                    ?? $this->arrayFromContentJson($contentJson, 'cta_payload', 'ctaPayload'),
                $locale,
            ),
            sidebarPayload: $translation?->sidebar_payload
                ?? $this->arrayFromContentJson($contentJson, 'sidebar_payload', 'sidebarPayload'),
            excerpt: $translation?->excerpt
                ?? $this->stringFromContentJson($contentJson, 'excerpt'),
            body: $translation?->body
                ?? $this->stringFromContentJson($contentJson, 'body'),
            rawExcerpt: $translation?->raw_excerpt
                ?? $this->stringFromContentJson($contentJson, 'raw_excerpt', 'rawExcerpt'),
            metaTitleFallback: $translation?->meta_title_fallback
                ?? $this->stringFromContentJson($contentJson, 'meta_title_fallback', 'metaTitleFallback'),
        );
    }

    private function isPubliclyRenderable(Page $page, string $locale): bool
    {
        if (! (bool) $page->is_enabled || $page->status !== 'published' || $page->published_at === null) {
            return false;
        }

        if ($page->publish_at !== null && $page->publish_at->isFuture()) {
            return false;
        }

        if ($this->findTranslation($page, $locale) === null) {
            return false;
        }

        foreach ($this->ancestorChain($page) as $ancestor) {
            if (! (bool) $ancestor->is_enabled || $ancestor->status !== 'published' || $ancestor->published_at === null) {
                return false;
            }

            if ($ancestor->publish_at !== null && $ancestor->publish_at->isFuture()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function segmentsFromSlugPath(string $slugPath): array
    {
        return array_values(array_filter(explode('/', trim($slugPath, '/'))));
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function matchesPath(Page $page, array $segments): bool
    {
        if ((bool) $page->is_homepage_shell) {
            return $segments === [(string) $page->slug];
        }

        $pageSegments = [];

        foreach ($this->ancestorChain($page) as $ancestor) {
            $pageSegments[] = (string) $ancestor->slug;
        }

        $pageSegments[] = (string) $page->slug;

        return $pageSegments === $segments;
    }

    /**
     * @return array<string, string>
     */
    private function buildLocalePathMap(Page $page): array
    {
        $map = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $url = $this->buildRelativeUrl($page, $candidateLocale);

            if ($url !== null) {
                $map[$candidateLocale] = $url;
            }
        }

        return $map;
    }

    private function buildRelativeUrl(Page $page, string $locale): ?string
    {
        if (! $this->isPubliclyRenderable($page, $locale)) {
            return null;
        }

        if ((bool) $page->is_homepage_shell) {
            return '/'.$locale;
        }

        $segments = [];

        foreach ($this->ancestorChain($page) as $ancestor) {
            $segments[] = (string) $ancestor->slug;
        }

        $segments[] = (string) $page->slug;

        return '/'.$locale.'/'.implode('/', array_filter($segments));
    }

    /**
     * @return array<int, Page>
     */
    private function ancestorChain(Page $page): array
    {
        $ancestors = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing(['parent', 'parent.translations']);

            if (! $cursor->parent instanceof Page) {
                return [];
            }

            $cursor = $cursor->parent;
            $ancestors[] = $cursor;
        }

        return array_reverse($ancestors);
    }

    private function breadcrumbLabel(Page $page, string $locale): string
    {
        $translation = $this->findTranslation($page, $locale);

        return $translation?->navigation_label
            ?? $translation?->headline
            ?? $translation?->title
            ?? $this->defaultPageTitle($page);
    }

    private function homepageLabel(string $locale): string
    {
        $home = Page::query()
            ->with('translations')
            ->where('is_homepage_shell', true)
            ->first();

        if ($home instanceof Page) {
            $translation = $this->findTranslation($home, $locale);

            if ($translation?->title !== null) {
                return $translation->title;
            }
        }

        return $locale === 'ar' ? 'الرئيسية' : 'Home';
    }

    private function findTranslation(Page $page, string $locale): ?PageTranslation
    {
        return $page->translations->firstWhere('locale', $locale);
    }

    private function defaultPageTitle(Page $page): string
    {
        return ucwords(str_replace('-', ' ', (string) $page->slug));
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     */
    private function stringFromContentJson(?array $contentJson, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     * @return array<string, mixed>|null
     */
    private function arrayFromContentJson(?array $contentJson, string ...$keys): ?array
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     * @return array<int, array<string, mixed>>|null
     */
    private function listFromContentJson(?array $contentJson, string ...$keys): ?array
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_array($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringFromDraft(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function boolFromDraft(array $payload, string $key, bool $default): bool
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function intFromDraft(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>|null
     */
    private function listFromDraft(array $payload, string $key): ?array
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        if (! is_array($value)) {
            return null;
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    private function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizeHomepageShellUrl(?array $payload, string $locale): ?array
    {
        if (! is_array($payload) || ! isset($payload['url']) || ! is_string($payload['url'])) {
            return $payload;
        }

        $normalizedUrl = '/'.trim($payload['url'], '/');

        if ($normalizedUrl === '/'.$locale.'/home') {
            $payload['url'] = '/'.$locale;
        }

        return $payload;
    }
}
