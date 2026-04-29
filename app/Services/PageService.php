<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\BreadcrumbItemDTO;
use App\DTOs\BreadcrumbTrailDTO;
use App\DTOs\DraftPayloadDTO;
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
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use App\Support\HtmlSanitizer;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class PageService implements PageServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO
    {
        $page = Page::query()->create([
            'parent_id' => $payload->parentPageId,
            'type' => $payload->isHomepageShell ? 'homepage' : 'landing',
            'template' => $payload->template,
            'slug' => $payload->slug,
            'status' => $payload->status,
            'sort_order' => 0,
            'is_enabled' => true,
            'show_in_breadcrumbs' => true,
            'show_in_nav' => true,
            'is_homepage_shell' => $payload->isHomepageShell,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->auditService->log('page.created', $userId, Page::class, (int) $page->getKey(), [
            'slug' => $page->slug,
            'template' => $page->template,
            'status' => $page->status,
        ]);

        return $this->mapPageToDto($page->fresh(['translations', 'seoMeta']));
    }

    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $updated = $page->update([
            'parent_id' => $payload->parentPageId,
            'template' => $payload->template,
            'slug' => $payload->slug,
            'status' => $payload->status,
            'is_enabled' => $payload->isEnabled,
            'show_in_breadcrumbs' => $payload->showInBreadcrumbs,
            'show_in_nav' => $payload->showInNav,
            'is_homepage_shell' => $payload->isHomepageShell,
            'publish_at' => $payload->publishAt,
            'content_json' => $payload->contentJson,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
                'field' => 'metadata',
            ]);
        }

        return $updated;
    }

    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        return $this->updateTranslation($pageId, 'ar', $payload);
    }

    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        return $this->updateTranslation($pageId, 'en', $payload);
    }

    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        return $this->updateSeo($pageId, 'ar', $payload);
    }

    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        return $this->updateSeo($pageId, 'en', $payload);
    }

    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId): PageDraftDTO
    {
        $page = Page::query()->findOrFail($pageId);

        $draft = PageDraft::query()->create([
            'page_id' => $pageId,
            'payload_json' => $this->pageDraftPayloadToArray($payload),
            'status' => $payload->metadata->status,
            'created_by' => $userId,
            'updated_by' => $userId,
            'scheduled_at' => $payload->metadata->publishAt,
        ]);

        $this->auditService->log('page.draft_saved', $userId, Page::class, (int) $page->getKey(), [
            'draft_id' => (int) $draft->getKey(),
            'status' => $draft->status,
        ]);

        return $this->mapDraftToDto($draft);
    }

    public function publish(int $pageId, int $userId): bool
    {
        $page = Page::query()->with(['translations', 'seoMeta'])->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $draft = PageDraft::query()
            ->where('page_id', $pageId)
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();

        DB::transaction(function () use ($page, $draft, $userId): void {
            if ($draft instanceof PageDraft && is_array($draft->payload_json)) {
                $this->applyDraftPayloadToPage($page, $draft->payload_json, $userId);
                $draft->forceFill([
                    'status' => 'published',
                    'approved_by' => $userId,
                    'published_at' => now(),
                ])->save();
            }

            $page->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'publish_at' => $page->publish_at?->isFuture() ? $page->publish_at : now(),
                'approved_by' => $userId,
                'updated_by' => $userId,
            ])->save();
        });

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.publish', $userId, Page::class, (int) $page->getKey());

        return true;
    }

    public function unpublish(int $pageId, int $userId): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $updated = $page->update([
            'status' => 'draft',
            'published_at' => null,
            'updated_by' => $userId,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.unpublish', $userId, Page::class, (int) $page->getKey());
        }

        return $updated;
    }

    public function schedulePublish(int $pageId, DateTimeInterface $publishAt, int $userId): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $draft = PageDraft::query()
            ->where('page_id', $pageId)
            ->latest('updated_at')
            ->first();

        if ($draft instanceof PageDraft) {
            $draft->forceFill([
                'status' => 'scheduled',
                'updated_by' => $userId,
                'scheduled_at' => $publishAt,
            ])->save();
        }

        $updated = $page->update([
            'status' => 'scheduled',
            'publish_at' => $publishAt,
            'updated_by' => $userId,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.schedule', $userId, Page::class, (int) $page->getKey(), [
                'publish_at' => $publishAt->format(DATE_ATOM),
            ]);
        }

        return $updated;
    }

    private function updateTranslation(int $pageId, string $locale, PageTranslationDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $sanitizedBody = $this->htmlSanitizer->sanitize($payload->body);
        $sanitizedBodyPayload = $this->sanitizeBodyPayload($payload->bodyPayload);

        $translation = PageTranslation::query()->updateOrCreate(
            ['page_id' => $pageId, 'locale' => $locale],
            [
                'title' => $payload->title,
                'navigation_label' => $payload->navigationLabel,
                'headline' => $payload->headline,
                'subheadline' => $payload->subheadline,
                'hero_payload' => $payload->heroPayload,
                'overview_cards_payload' => $payload->overviewCardsPayload,
                'stats_payload' => $payload->statsPayload,
                'body_payload' => $sanitizedBodyPayload,
                'cta_payload' => $payload->ctaPayload,
                'sidebar_payload' => $payload->sidebarPayload,
                'excerpt' => $payload->excerpt,
                'body' => $sanitizedBody,
                'raw_excerpt' => $payload->rawExcerpt,
                'meta_title_fallback' => $payload->metaTitleFallback,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
            'field' => 'translation',
            'locale' => $locale,
        ]);

        return $translation->exists;
    }

    private function updateSeo(int $pageId, string $locale, PageSeoInputDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $seo = PageSeoMeta::query()->updateOrCreate(
            ['page_id' => $pageId, 'locale' => $locale],
            [
                'meta_title' => $payload->title,
                'meta_description' => $payload->metaDescription,
                'og_title' => $payload->ogTitle,
                'og_description' => $payload->ogDescription,
                'og_image_url' => $payload->ogImage,
                'canonical_url' => $payload->canonicalUrl,
                'robots' => $payload->robots,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
            'field' => 'seo',
            'locale' => $locale,
        ]);

        return $seo->exists;
    }

    /**
     * Sanitize the bodyPayload array, cleaning legacy_html block content.
     *
     * @param  array<string, mixed>|null  $bodyPayload
     * @return array<string, mixed>|null
     */
    private function sanitizeBodyPayload(?array $bodyPayload): ?array
    {
        if ($bodyPayload === null) {
            return null;
        }

        if (! is_array($bodyPayload['blocks'] ?? null)) {
            return $bodyPayload;
        }

        $bodyPayload['blocks'] = array_map(function (mixed $block): mixed {
            if (! is_array($block)) {
                return $block;
            }

            if (($block['type'] ?? null) === 'legacy_html' && ! empty($block['content'])) {
                $block['content'] = $this->htmlSanitizer->sanitize($block['content']);
            }

            return $block;
        }, $bodyPayload['blocks']);

        return $bodyPayload;
    }

    private function applyDraftPayloadToPage(Page $page, array $payload, int $userId): void
    {
        $draftPage = is_array($payload['page'] ?? null) ? $payload['page'] : $payload;
        $metadata = $this->metadataFromDraftArray(is_array($draftPage['metadata'] ?? null) ? $draftPage['metadata'] : [], $page);

        $page->forceFill([
            'parent_id' => $metadata->parentPageId,
            'template' => $metadata->template,
            'slug' => $metadata->slug,
            'status' => $metadata->status,
            'is_enabled' => $metadata->isEnabled,
            'show_in_breadcrumbs' => $metadata->showInBreadcrumbs,
            'show_in_nav' => $metadata->showInNav,
            'is_homepage_shell' => $metadata->isHomepageShell,
            'publish_at' => $metadata->publishAt,
            'content_json' => $metadata->contentJson,
            'updated_by' => $userId,
        ])->save();

        $this->updateTranslation($page->id, 'ar', $this->translationFromDraftArray(is_array($draftPage['arabicTranslation'] ?? null) ? $draftPage['arabicTranslation'] : [], $page, 'ar'));
        $this->updateTranslation($page->id, 'en', $this->translationFromDraftArray(is_array($draftPage['englishTranslation'] ?? null) ? $draftPage['englishTranslation'] : [], $page, 'en'));
        $this->updateSeo($page->id, 'ar', $this->seoInputFromDraftArray(is_array($draftPage['arabicSeo'] ?? null) ? $draftPage['arabicSeo'] : [], 'ar'));
        $this->updateSeo($page->id, 'en', $this->seoInputFromDraftArray(is_array($draftPage['englishSeo'] ?? null) ? $draftPage['englishSeo'] : [], 'en'));
    }

    private function mapDraftToDto(PageDraft $draft): PageDraftDTO
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pageDraftPayloadToArray(PageDraftDataDTO $payload): array
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

    private function seoInputFromDraftArray(array $payload, string $locale): PageSeoInputDTO
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

    /**
     * @return array<string, mixed>
     */
    private function metadataPayloadToArray(PageMetadataDTO $payload): array
    {
        return [
            'slug' => $payload->slug,
            'template' => $payload->template,
            'isHomepageShell' => $payload->isHomepageShell,
            'status' => $payload->status,
            'parentPageId' => $payload->parentPageId,
            'publishAt' => $payload->publishAt,
            'contentJson' => $payload->contentJson,
            'isEnabled' => $payload->isEnabled,
            'showInBreadcrumbs' => $payload->showInBreadcrumbs,
            'showInNav' => $payload->showInNav,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translationPayloadToArray(PageTranslationDTO $payload): array
    {
        return [
            'title' => $payload->title,
            'navigationLabel' => $payload->navigationLabel,
            'headline' => $payload->headline,
            'subheadline' => $payload->subheadline,
            'heroPayload' => $payload->heroPayload,
            'overviewCardsPayload' => $payload->overviewCardsPayload,
            'statsPayload' => $payload->statsPayload,
            'bodyPayload' => $payload->bodyPayload,
            'ctaPayload' => $payload->ctaPayload,
            'sidebarPayload' => $payload->sidebarPayload,
            'excerpt' => $payload->excerpt,
            'body' => $payload->body,
            'rawExcerpt' => $payload->rawExcerpt,
            'metaTitleFallback' => $payload->metaTitleFallback,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seoPayloadToArray(PageSeoInputDTO $payload): array
    {
        return [
            'title' => $payload->title,
            'metaDescription' => $payload->metaDescription,
            'ogTitle' => $payload->ogTitle,
            'ogDescription' => $payload->ogDescription,
            'ogImage' => $payload->ogImage,
            'canonicalUrl' => $payload->canonicalUrl,
            'robots' => $payload->robots,
        ];
    }

    private function touchPageCaches(int $pageId): void
    {
        $this->cacheService->flushTags(['pages', 'seo', 'sitemap', 'navigation', 'settings']);
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
                isEnabled: (bool) $page->is_enabled,
                showInBreadcrumbs: (bool) $page->show_in_breadcrumbs,
                showInNav: (bool) $page->show_in_nav,
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
            isEnabled: $this->boolFromDraft($payload, 'isEnabled', (bool) $page->is_enabled),
            showInBreadcrumbs: $this->boolFromDraft($payload, 'showInBreadcrumbs', (bool) $page->show_in_breadcrumbs),
            showInNav: $this->boolFromDraft($payload, 'showInNav', (bool) $page->show_in_nav),
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
