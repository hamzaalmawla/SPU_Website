<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\DraftPayloadDTO;
use App\DTOs\PageDraftDataDTO;
use App\DTOs\PageDraftDTO;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageSeoDTO;
use App\DTOs\PageSeoInputDTO;
use App\DTOs\PageTranslationDTO;
use App\DTOs\PreviewDTO;
use App\DTOs\PreviewPayloadDTO;
use App\Models\Page;
use App\Models\PageDraft;

/**
 * Handles draft lifecycle management: saving drafts, mapping draft payloads,
 * applying draft content to pages, and building preview DTOs from drafts.
 *
 * Extracted from PageService to keep each class focused on a single responsibility.
 */
final class PageDraftService
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly PagePublicReadService $publicReadService,
        private readonly PageUrlResolver $urlResolver,
    ) {}

    public function mapDraftToDto(PageDraft $draft): PageDraftDTO
    {
        return new PageDraftDTO(
            id: (int) $draft->getKey(),
            pageId: (int) $draft->page_id,
            status: (string) $draft->status,
            payload: new DraftPayloadDTO(
                page: $this->draftPayloadFromArray($draft, is_array($draft->payload_json) ? $draft->payload_json : []),
            ),
            createdBy: (int) $draft->created_by,
            publishAt: $draft->scheduled_at?->toIso8601String(),
            createdAt: $draft->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updatedAt: $draft->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            version: (int) ($draft->version ?? 1),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pageDraftPayloadToArray(PageDraftDataDTO $payload): array
    {
        return [
            'page' => [
                'metadata' => $this->metadataPayloadToArray($payload->metadata),
                'arabicTranslation' => $this->translationPayloadToArray($payload->arabicTranslation),
                'englishTranslation' => $this->translationPayloadToArray($payload->englishTranslation),
                'arabicSeo' => $this->seoPayloadToArray($payload->arabicSeo),
                'englishSeo' => $this->seoPayloadToArray($payload->englishSeo),
            ],
        ];
    }

    /**
     * Apply a draft payload to a page model (used during publish).
     *
     * @param  callable(int, string, PageTranslationDTO): bool  $updateTranslation
     * @param  callable(int, string, PageSeoInputDTO): bool  $updateSeo
     */
    public function applyDraftPayloadToPage(
        Page $page,
        array $payload,
        int $userId,
        callable $updateTranslation,
        callable $updateSeo,
    ): void {
        $draftPage = is_array($payload['page'] ?? null) ? $payload['page'] : $payload;
        $metadata = $this->metadataFromDraftArray(is_array($draftPage['metadata'] ?? null) ? $draftPage['metadata'] : [], $page);

        $page->forceFill([
            'parent_id' => $metadata->parentPageId,
            'template' => $metadata->template,
            'slug' => $metadata->slug,
            'faculty_scope_slug' => $metadata->facultyScopeSlug,
            'status' => $metadata->status,
            'is_enabled' => $metadata->isEnabled,
            'show_in_breadcrumbs' => $metadata->showInBreadcrumbs,
            'show_in_nav' => $metadata->showInNav,
            'is_homepage_shell' => $metadata->isHomepageShell,
            'publish_at' => $metadata->publishAt,
            'content_json' => $metadata->contentJson,
            'updated_by' => $userId,
        ])->save();

        $updateTranslation($page->id, 'ar', $this->translationFromDraftArray(is_array($draftPage['arabicTranslation'] ?? null) ? $draftPage['arabicTranslation'] : [], $page, 'ar'));
        $updateTranslation($page->id, 'en', $this->translationFromDraftArray(is_array($draftPage['englishTranslation'] ?? null) ? $draftPage['englishTranslation'] : [], $page, 'en'));
        $updateSeo($page->id, 'ar', $this->seoInputFromDraftArray(is_array($draftPage['arabicSeo'] ?? null) ? $draftPage['arabicSeo'] : [], 'ar'));
        $updateSeo($page->id, 'en', $this->seoInputFromDraftArray(is_array($draftPage['englishSeo'] ?? null) ? $draftPage['englishSeo'] : [], 'en'));
    }

    public function pagePreviewDto(Page $page, string $locale): PageDTO
    {
        $draft = PageDraft::query()
            ->where('page_id', $page->getKey())
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();

        if ($draft instanceof PageDraft && is_array($draft->payload_json)) {
            return $this->mapDraftPayloadToPageDto($page, $draft->payload_json, $locale);
        }

        return $this->publicReadService->mapPageToDto($page);
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
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

    public function mapDraftPayloadToPageDto(Page $page, array $payload, string $locale): PageDTO
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

    // ── Draft array mapping helpers ──

    public function metadataFromDraftArray(array $payload, Page $page): PageMetadataDTO
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
            isEnabled: $this->boolFromDraft($payload, 'isEnabled', (bool) $page->is_enabled),
            showInBreadcrumbs: $this->boolFromDraft($payload, 'showInBreadcrumbs', (bool) $page->show_in_breadcrumbs),
            showInNav: $this->boolFromDraft($payload, 'showInNav', (bool) $page->show_in_nav),
            facultyScopeSlug: $this->stringFromDraft($payload, 'facultyScopeSlug') ?? (is_string($page->faculty_scope_slug) ? $page->faculty_scope_slug : null),
        );
    }

    public function translationFromDraftArray(array $payload, Page $page, string $locale): PageTranslationDTO
    {
        if ($payload === []) {
            return $this->publicReadService->mapTranslation($page, null, $locale);
        }

        return new PageTranslationDTO(
            title: $this->requiredStringFromDraft($payload, 'title', $this->publicReadService->defaultPageTitle($page)),
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

    public function seoInputFromDraftArray(array $payload, string $locale): PageSeoInputDTO
    {
        return new PageSeoInputDTO(
            locale: $locale,
            title: $this->stringFromDraft($payload, 'title') ?? '',
            metaDescription: $this->stringFromDraft($payload, 'metaDescription'),
            ogTitle: $this->stringFromDraft($payload, 'ogTitle'),
            ogDescription: $this->stringFromDraft($payload, 'ogDescription'),
            ogImage: $this->stringFromDraft($payload, 'ogImage'),
            canonicalUrl: $this->stringFromDraft($payload, 'canonicalUrl'),
            robots: $this->stringFromDraft($payload, 'robots'),
        );
    }

    // ── Internal helpers ──

    private function draftPayloadFromArray(PageDraft $draft, array $payload): PageDraftDataDTO
    {
        $page = Page::query()->with(['translations', 'seoMeta'])->findOrFail((int) $draft->page_id);
        $draftPage = is_array($payload['page'] ?? null) ? $payload['page'] : $payload;

        return new PageDraftDataDTO(
            metadata: $this->metadataFromDraftArray(is_array($draftPage['metadata'] ?? null) ? $draftPage['metadata'] : [], $page),
            arabicTranslation: $this->translationFromDraftArray(is_array($draftPage['arabicTranslation'] ?? null) ? $draftPage['arabicTranslation'] : [], $page, 'ar'),
            englishTranslation: $this->translationFromDraftArray(is_array($draftPage['englishTranslation'] ?? null) ? $draftPage['englishTranslation'] : [], $page, 'en'),
            arabicSeo: $this->seoInputFromDraftArray(is_array($draftPage['arabicSeo'] ?? null) ? $draftPage['arabicSeo'] : [], 'ar'),
            englishSeo: $this->seoInputFromDraftArray(is_array($draftPage['englishSeo'] ?? null) ? $draftPage['englishSeo'] : [], 'en'),
        );
    }

    private function seoFromDraftArray(array $payload, Page $page, string $locale): PageSeoDTO
    {
        if ($payload === []) {
            return $this->seoMetadataService->buildForPage((int) $page->getKey(), $locale);
        }

        $fallbackSeo = $this->seoMetadataService->buildForPage((int) $page->getKey(), $locale);
        $path = $this->urlResolver->buildRelativeUrl($page, $locale) ?? '/'.$locale;

        return new PageSeoDTO(
            locale: $locale,
            title: $this->stringFromDraft($payload, 'title') ?? $fallbackSeo->title,
            metaDescription: $this->stringFromDraft($payload, 'metaDescription') ?? $fallbackSeo->metaDescription,
            ogTitle: $this->stringFromDraft($payload, 'ogTitle') ?? $fallbackSeo->ogTitle,
            ogDescription: $this->stringFromDraft($payload, 'ogDescription') ?? $fallbackSeo->ogDescription,
            ogImage: $this->stringFromDraft($payload, 'ogImage') ?? $fallbackSeo->ogImage,
            canonicalUrl: $this->stringFromDraft($payload, 'canonicalUrl') ?? $this->seoMetadataService->resolveCanonical($path, $locale),
            hreflang: $this->seoMetadataService->resolveHreflang($this->urlResolver->buildLocalePathMap($page)),
            robots: $this->stringFromDraft($payload, 'robots') ?? $fallbackSeo->robots,
        );
    }

    /** @return array<string, mixed> */
    private function metadataPayloadToArray(PageMetadataDTO $p): array
    {
        return ['slug' => $p->slug, 'template' => $p->template, 'isHomepageShell' => $p->isHomepageShell, 'status' => $p->status, 'parentPageId' => $p->parentPageId, 'publishAt' => $p->publishAt, 'contentJson' => $p->contentJson, 'isEnabled' => $p->isEnabled, 'showInBreadcrumbs' => $p->showInBreadcrumbs, 'showInNav' => $p->showInNav, 'facultyScopeSlug' => $p->facultyScopeSlug];
    }

    /** @return array<string, mixed> */
    private function translationPayloadToArray(PageTranslationDTO $p): array
    {
        return ['title' => $p->title, 'navigationLabel' => $p->navigationLabel, 'headline' => $p->headline, 'subheadline' => $p->subheadline, 'heroPayload' => $p->heroPayload, 'overviewCardsPayload' => $p->overviewCardsPayload, 'statsPayload' => $p->statsPayload, 'bodyPayload' => $p->bodyPayload, 'ctaPayload' => $p->ctaPayload, 'sidebarPayload' => $p->sidebarPayload, 'excerpt' => $p->excerpt, 'body' => $p->body, 'rawExcerpt' => $p->rawExcerpt, 'metaTitleFallback' => $p->metaTitleFallback];
    }

    /** @return array<string, mixed> */
    private function seoPayloadToArray(PageSeoInputDTO $p): array
    {
        return ['title' => $p->title, 'metaDescription' => $p->metaDescription, 'ogTitle' => $p->ogTitle, 'ogDescription' => $p->ogDescription, 'ogImage' => $p->ogImage, 'canonicalUrl' => $p->canonicalUrl, 'robots' => $p->robots];
    }

    /** @param array<string, mixed> $payload */
    private function stringFromDraft(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function requiredStringFromDraft(array $payload, string $key, string $default): string
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_string($value) ? $value : $default;
    }

    /** @param array<string, mixed> $payload */
    private function boolFromDraft(array $payload, string $key, bool $default): bool
    {
        $value = $payload[$key] ?? $payload[$this->toSnakeCase($key)] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /** @param array<string, mixed> $payload */
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

        return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => is_array($item))) : null;
    }

    private function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
    }

    /** @return array<string, mixed>|null */
    private function normalizeHomepageShellUrl(?array $payload, string $locale): ?array
    {
        if (! is_array($payload) || ! isset($payload['url']) || ! is_string($payload['url'])) {
            return $payload;
        }
        if ('/'.trim($payload['url'], '/') === '/'.$locale.'/home') {
            $payload['url'] = '/'.$locale;
        }

        return $payload;
    }
}
